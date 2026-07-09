<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DeliveryRelation;
use App\Models\MedicalDocument;
use App\Models\MedicalDocumentFile;
use App\Models\MedicalDocumentStatusHistory;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MedicalDocumentController extends Controller
{
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

        return response()->json($medicalDocument->fresh(['type', 'worker', 'deliveryRelation', 'files']));
    }

    public function destroy(Request $request, MedicalDocument $medicalDocument)
    {
        abort_unless($request->user()->hasRole('ADMIN'), 403);

        $medicalDocument->delete();
        $this->audit($request, 'deleted', 'medical_documents', $medicalDocument->id);

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
            MedicalDocument::STATUS_REGISTERED => [],
            MedicalDocument::STATUS_REJECTED => [],
        ];

        if (! in_array($data['status'], $allowed[$medicalDocument->status] ?? [], true)) {
            return response()->json(['message' => 'Transicion de estado no permitida.'], 422);
        }

        DB::transaction(function () use ($request, $medicalDocument, $data) {
            $from = $medicalDocument->status;
            $medicalDocument->update([
                'status' => $data['status'],
                'status_changed_by' => $request->user()->id,
                'status_changed_at' => now(),
            ]);

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
            'deliverer_photo' => ['nullable', 'file', 'max:8192'],
            'medical_document_file' => ['required', 'file', 'max:15360'],
            'contact_number' => ['required', 'string', 'max:50'],
            'annexes' => ['nullable', 'array', 'max:4'],
            'annexes.*' => ['file', 'max:15360'],
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

    private function storeFile($file, MedicalDocument $document, string $type, int $userId): void
    {
        $path = $file->store($this->documentStorageDirectory($document->id), $this->documentStorageDisk());

        MedicalDocumentFile::create([
            'medical_document_id' => $document->id,
            'file_type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
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
