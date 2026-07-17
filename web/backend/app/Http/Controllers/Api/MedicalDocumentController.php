<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DeliveryRelation;
use App\Models\MedicalDocument;
use App\Models\MedicalDocumentFile;
use App\Models\MedicalDocumentStatusHistory;
use App\Models\Worker;
use App\Services\RejectedDocumentsMailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class MedicalDocumentController extends Controller
{
    private const MAX_DOCUMENT_FILE_KB = 10240;
    private const ALLOWED_DOCUMENT_MIMES = 'pdf,docx,jpeg,jpg,png';

    public function index(Request $request)
    {
        $query = MedicalDocument::with(['type', 'worker.management', 'worker.sector', 'deliveryRelation', 'creator', 'files'])
            ->latest();

        // Only ADMIN and SST can see all documents; everyone else sees only their own
        $user = $request->user();
        if (! $user->hasRole('ADMIN') && ! $user->hasRole('SST')) {
            $query->where('created_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from')->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to')->endOfDay());
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->integer('created_by'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhereHas('type', fn ($type) => $type->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('worker', fn ($worker) => $worker
                        ->where('dni', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%"));
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function counts(Request $request)
    {
        $query = MedicalDocument::query();

        $user = $request->user();
        if (! $user->hasRole('ADMIN') && ! $user->hasRole('SST')) {
            $query->where('created_by', $user->id);
        }

        return response()->json([
            'pending' => (clone $query)->where('status', self::STATUS_PENDING_LABEL)->count(),
            'received' => (clone $query)->where('status', self::STATUS_RECEIVED_LABEL)->count(),
            'registered' => (clone $query)->where('status', self::STATUS_REGISTERED_LABEL)->count(),
            'rejected' => (clone $query)->where('status', self::STATUS_REJECTED_LABEL)->count(),
        ]);
    }

    private const STATUS_PENDING_LABEL = 'PENDIENTE';
    private const STATUS_RECEIVED_LABEL = 'RECEPCIONADO';
    private const STATUS_REGISTERED_LABEL = 'REGISTRADO';
    private const STATUS_REJECTED_LABEL = 'RECHAZADO';

    public function store(Request $request)
    {
        abort_unless($request->user()->canDo('documents.create'), 403, 'No tiene permisos para crear documentos medicos.');

        $data = $this->validatedDocument($request);
        $relation = DeliveryRelation::findOrFail($data['delivery_relation_id']);

        if ($relation->requires_detail && blank($data['delivery_relation_detail'] ?? null)) {
            return response()->json(['message' => 'Debe detallar la relacion de entrega.'], 422);
        }

        $document = DB::transaction(function () use ($request, $data) {
            $worker = Worker::where('dni', $data['worker_dni'])->firstOrFail();

            $document = MedicalDocument::create([
                'medical_document_type_id' => $data['medical_document_type_id'],
                'worker_id' => $worker->id,
                'delivery_relation_id' => $data['delivery_relation_id'],
                'delivery_relation_detail' => $data['delivery_relation_detail'] ?? null,
                'deliverer_name' => $data['deliverer_name'],
                'deliverer_document' => $data['deliverer_document'] ?? null,
                'contact_number' => $data['contact_number'],
                'observation' => $data['observation'] ?? null,
                'status' => MedicalDocument::STATUS_PENDING,
                'created_by' => $request->user()->id,
                'offline_uuid' => $data['offline_uuid'] ?? null,
            ]);

            $this->storeUploadedFile($request, $document, 'deliverer_photo', 'DELIVERER_PHOTO');
            $this->storeUploadedFile($request, $document, 'medical_document_file', 'MEDICAL_DOCUMENT');

            foreach ($request->file('annexes', []) as $annex) {
                $this->storeFile($annex, $document, 'ANNEX', $request->user()->id);
            }

            MedicalDocumentStatusHistory::create([
                'medical_document_id' => $document->id,
                'from_status' => null,
                'to_status' => MedicalDocument::STATUS_PENDING,
                'observation' => 'Documento creado.',
                'changed_by' => $request->user()->id,
            ]);

            $this->audit($request, 'created', 'medical_documents', $document->id);

            // Dispatch notification to ADMIN and SST
            $notifyUsers = \App\Models\User::whereHas('role', function ($query) {
                $query->whereIn('code', ['ADMIN', 'SST']);
            })->get();

            $notifications = [];
            foreach ($notifyUsers as $user) {
                $notifications[] = [
                    'user_id' => $user->id,
                    'title' => 'Nuevo Documento Médico',
                    'body' => "Se ha registrado un nuevo documento para el trabajador {$worker->first_name} {$worker->last_name}.",
                    'data' => json_encode(['document_id' => $document->id]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($notifications)) {
                \App\Models\Notification::insert($notifications);
            }

            return $document;
        });

        return response()->json($document->load(['type', 'worker', 'deliveryRelation', 'files', 'history.user']), 201);
    }

    public function show(Request $request, MedicalDocument $medicalDocument)
    {
        $this->authorizeDocumentView($request, $medicalDocument);

        return response()->json($medicalDocument->load([
            'type',
            'worker.management',
            'worker.sector',
            'deliveryRelation',
            'creator',
            'statusChangedBy',
            'files',
            'history.user',
        ]));
    }

    public function update(Request $request, MedicalDocument $medicalDocument)
    {
        abort_unless($request->user()->hasRole('ADMIN'), 403);

        $data = $request->validate([
            'observation' => ['nullable', 'string'],
            'contact_number' => ['required', 'string', 'max:50'],
            'deliverer_name' => ['required', 'string', 'max:255'],
            'deliverer_document' => ['nullable', 'string', 'max:50'],
        ]);

        $medicalDocument->update($data);
        $this->audit($request, 'updated', 'medical_documents', $medicalDocument->id);
        $this->resendRejectedReportIfNeeded($medicalDocument);

        return response()->json($medicalDocument->fresh(['type', 'worker', 'deliveryRelation', 'files']));
    }

    public function updateObservation(Request $request, MedicalDocument $medicalDocument)
    {
        abort_unless($request->user()->hasRole('ADMIN'), 403);

        $data = $request->validate([
            'observation' => ['nullable', 'string'],
        ]);

        $medicalDocument->update([
            'observation' => $data['observation'] ?? null,
        ]);

        $this->audit($request, 'observation_updated', 'medical_documents', $medicalDocument->id);
        $this->resendRejectedReportIfNeeded($medicalDocument);

        return response()->json($medicalDocument->fresh([
            'type',
            'worker.management',
            'worker.sector',
            'deliveryRelation',
            'creator',
            'statusChangedBy',
            'files',
            'history.user',
        ]));
    }

    public function destroy(Request $request, MedicalDocument $medicalDocument)
    {
        abort_unless($request->user()->hasRole('ADMIN'), 403);

        $medicalDocument->delete();
        $this->audit($request, 'deleted', 'medical_documents', $medicalDocument->id);
        $this->resendRejectedReportIfNeeded($medicalDocument);

        return response()->json(['message' => 'Documento eliminado.']);
    }

    public function changeStatus(Request $request, MedicalDocument $medicalDocument)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                MedicalDocument::STATUS_RECEIVED,
                MedicalDocument::STATUS_REGISTERED,
                MedicalDocument::STATUS_REJECTED,
            ])],
            'observation' => [Rule::requiredIf($request->status === MedicalDocument::STATUS_REJECTED), 'nullable', 'string'],
        ]);

        $allowed = [
            MedicalDocument::STATUS_PENDING => [MedicalDocument::STATUS_RECEIVED, MedicalDocument::STATUS_REJECTED],
            MedicalDocument::STATUS_RECEIVED => [MedicalDocument::STATUS_REGISTERED, MedicalDocument::STATUS_REJECTED],
            MedicalDocument::STATUS_REGISTERED => $request->user()->hasRole('ADMIN') ? [MedicalDocument::STATUS_REJECTED] : [],
            MedicalDocument::STATUS_REJECTED => [],
        ];

        if (! in_array($data['status'], $allowed[$medicalDocument->status] ?? [], true)) {
            return response()->json(['message' => 'Transicion de estado no permitida.'], 422);
        }

        DB::transaction(function () use ($request, $medicalDocument, $data) {
            $from = $medicalDocument->status;
            $documentUpdates = [
                'status' => $data['status'],
                'status_changed_by' => $request->user()->id,
                'status_changed_at' => now(),
            ];

            if ($data['status'] === MedicalDocument::STATUS_REJECTED) {
                $documentUpdates['observation'] = $data['observation'] ?? null;
            }

            $medicalDocument->update($documentUpdates);

            MedicalDocumentStatusHistory::create([
                'medical_document_id' => $medicalDocument->id,
                'from_status' => $from,
                'to_status' => $data['status'],
                'observation' => $data['observation'] ?? null,
                'changed_by' => $request->user()->id,
            ]);

            $this->audit($request, 'status_changed', 'medical_documents', $medicalDocument->id, [
                'from' => $from,
                'to' => $data['status'],
            ]);
        });

        $this->sendRejectedReportIfNeeded($medicalDocument->fresh());

        return response()->json($medicalDocument->fresh(['type', 'worker', 'deliveryRelation', 'history.user']));
    }

    public function history(Request $request, MedicalDocument $medicalDocument)
    {
        $this->authorizeDocumentView($request, $medicalDocument);

        return response()->json($medicalDocument->history()->with('user')->get());
    }

    public function downloadFile(Request $request, MedicalDocumentFile $file)
    {
        $this->authorizeDocumentView($request, $file->medicalDocument);

        return $this->downloadFileResponse($file);
    }

    public function signedDownloadFile(Request $request, MedicalDocumentFile $file)
    {
        return $this->downloadFileResponse($file);
    }

    private function downloadFileResponse(MedicalDocumentFile $file)
    {
        [$disk, $path] = $this->documentDiskAndPath($file->path);
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $file->original_name);
    }

    public function previewFile(Request $request, MedicalDocumentFile $file)
    {
        // Support token via query param for img/iframe elements that can't send headers.
        if (! $request->user()) {
            $plainToken = $request->input('token') ?: $request->bearerToken();
            $token = $plainToken ? \Laravel\Sanctum\PersonalAccessToken::findToken($plainToken) : null;
            if ($token) {
                $request->setUserResolver(fn () => $token->tokenable);
            }
        }

        abort_unless($request->user(), 401);
        $this->authorizeDocumentView($request, $file->medicalDocument);

        return $this->previewFileResponse($file);
    }

    public function signedPreviewFile(Request $request, MedicalDocumentFile $file)
    {
        return $this->previewFileResponse($file);
    }

    private function previewFileResponse(MedicalDocumentFile $file)
    {
        [$disk, $path] = $this->documentDiskAndPath($file->path);
        abort_unless($disk->exists($path), 404);

        $mimeType = $file->mime_type ?? 'application/octet-stream';

        return response($disk->get($path))
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'inline; filename="' . $file->original_name . '"')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function validatedDocument(Request $request): array
    {
        return $request->validate([
            'medical_document_type_id' => ['required', 'exists:medical_document_types,id'],
            'worker_dni' => ['required', 'string', 'exists:workers,dni'],
            'delivery_relation_id' => ['required', 'exists:delivery_relations,id'],
            'delivery_relation_detail' => ['nullable', 'string', 'max:255'],
            'deliverer_name' => ['required', 'string', 'max:255'],
            'deliverer_document' => ['nullable', 'string', 'max:50'],
            'deliverer_photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png', 'max:' . self::MAX_DOCUMENT_FILE_KB],
            'medical_document_file' => ['required', 'file', 'mimes:' . self::ALLOWED_DOCUMENT_MIMES, 'max:' . self::MAX_DOCUMENT_FILE_KB],
            'contact_number' => ['required', 'string', 'max:50'],
            'annexes' => ['nullable', 'array', 'max:4'],
            'annexes.*' => ['file', 'mimes:' . self::ALLOWED_DOCUMENT_MIMES, 'max:' . self::MAX_DOCUMENT_FILE_KB],
            'observation' => ['nullable', 'string'],
            'offline_uuid' => ['nullable', 'string', 'max:120', 'unique:medical_documents,offline_uuid'],
        ]);
    }

    private function storeUploadedFile(Request $request, MedicalDocument $document, string $field, string $type): void
    {
        if ($request->hasFile($field)) {
            $this->storeFile($request->file($field), $document, $type, $request->user()->id);
        }
    }

    private function storeFile(UploadedFile $file, MedicalDocument $document, string $type, int $userId): void
    {
        $prepared = $this->prepareFileForStorage($file);
        $disk = Storage::disk($this->documentStorageDisk());
        $path = $this->uniqueStoragePath($this->documentStorageDirectory($document->id), $prepared['name']);

        $stream = fopen($prepared['path'], 'rb');
        try {
            $disk->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($prepared['temporary']) {
                @unlink($prepared['path']);
            }
        }

        MedicalDocumentFile::create([
            'medical_document_id' => $document->id,
            'file_type' => $type,
            'original_name' => $prepared['name'],
            'path' => $path,
            'mime_type' => $prepared['mime_type'],
            'size' => $prepared['size'],
            'uploaded_by' => $userId,
        ]);
    }

    private function prepareFileForStorage(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $path = $file->getRealPath();
        $name = $this->sanitizeFileName($file->getClientOriginalName());

        if (! $path || ! str_starts_with($mimeType, 'image/') || ! function_exists('imagecreatefromstring')) {
            return [
                'path' => $path ?: $file->getPathname(),
                'name' => $name,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'temporary' => false,
            ];
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (! $image) {
            return [
                'path' => $path,
                'name' => $name,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'temporary' => false,
            ];
        }

        $target = tempnam(sys_get_temp_dir(), 'docssalud_img_');
        $stored = false;
        if ($mimeType === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $stored = imagepng($image, $target, 5);
        } else {
            $stored = imagejpeg($image, $target, 88);
            $mimeType = 'image/jpeg';
            $name = preg_replace('/\.[^.]+$/', '.jpg', $name) ?: ($name . '.jpg');
        }
        imagedestroy($image);

        if (! $stored) {
            @unlink($target);
            return [
                'path' => $path,
                'name' => $name,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'temporary' => false,
            ];
        }

        return [
            'path' => $target,
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => filesize($target) ?: $file->getSize(),
            'temporary' => true,
        ];
    }

    private function uniqueStoragePath(string $directory, string $fileName): string
    {
        $disk = Storage::disk($this->documentStorageDisk());
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME) ?: 'archivo';
        $candidate = trim($directory . '/' . $fileName, '/');
        $suffix = 1;

        while ($disk->exists($candidate)) {
            $nextName = $baseName . '_' . $suffix . ($extension ? ".{$extension}" : '');
            $candidate = trim($directory . '/' . $nextName, '/');
            $suffix++;
        }

        return $candidate;
    }

    private function sanitizeFileName(string $name): string
    {
        $name = Str::of($name)->ascii()->replaceMatches('/[^A-Za-z0-9._-]+/', '_')->trim('_')->value();
        return $name !== '' ? $name : 'archivo';
    }

    private function documentStorageDisk(): string
    {
        return config('filesystems.default', 'local');
    }

    private function documentStoragePrefix(): string
    {
        return trim((string) config('filesystems.document_prefix', ''), '/');
    }

    private function documentStorageDirectory(int $documentId): string
    {
        return trim($this->documentStoragePrefix() . "/medical-documents/{$documentId}", '/');
    }

    private function documentDiskAndPath(string $storedPath): array
    {
        $disk = Storage::disk($this->documentStorageDisk());
        $path = ltrim($storedPath, '/');

        if ($disk->exists($path)) {
            return [$disk, $path];
        }

        $prefix = $this->documentStoragePrefix();
        if ($prefix !== '' && ! str_starts_with($path, "{$prefix}/")) {
            $prefixedPath = "{$prefix}/{$path}";
            if ($disk->exists($prefixedPath)) {
                return [$disk, $prefixedPath];
            }
        }

        return [$disk, $path];
    }

    private function authorizeDocumentView(Request $request, MedicalDocument $document): void
    {
        $user = $request->user();
        if (! $user->hasRole('ADMIN') && ! $user->hasRole('SST') && $document->created_by !== $user->id) {
            abort(403, 'No puede ver documentos creados por otros usuarios.');
        }
    }

    private function sendRejectedReportIfNeeded(MedicalDocument $document): void
    {
        if ($document->status !== MedicalDocument::STATUS_REJECTED) {
            return;
        }

        try {
            app(RejectedDocumentsMailSettings::class)->sendRejectedReport();
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar el reporte de documentos rechazados.', [
                'document_id' => $document->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resendRejectedReportIfNeeded(MedicalDocument $document): void
    {
        if ($document->status !== MedicalDocument::STATUS_REJECTED) {
            return;
        }

        app(RejectedDocumentsMailSettings::class)->resendIfReportWasAlreadySentToday();
    }

    private function audit(Request $request, string $action, string $entity, ?int $entityId = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
