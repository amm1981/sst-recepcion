<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalDocument;
use Illuminate\Http\Request;

class BiDocumentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 500), 1), 1000);

        $documents = MedicalDocument::with([
            'type',
            'worker.management',
            'worker.sector',
            'deliveryRelation',
            'creator',
            'statusChangedBy',
            'files',
            'history.user',
        ])
            ->latest('medical_documents.created_at')
            ->paginate($perPage);

        $documents->getCollection()->transform(fn (MedicalDocument $document) => $this->documentPayload($document));

        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'per_page' => $documents->perPage(),
                'last_page' => $documents->lastPage(),
                'total' => $documents->total(),
                'from' => $documents->firstItem(),
                'to' => $documents->lastItem(),
            ],
            'links' => [
                'first' => $documents->url(1),
                'last' => $documents->url($documents->lastPage()),
                'prev' => $documents->previousPageUrl(),
                'next' => $documents->nextPageUrl(),
            ],
        ]);
    }

    private function documentPayload(MedicalDocument $document): array
    {
        $worker = $document->worker;
        $payload = is_array($worker?->external_payload) ? $worker->external_payload : [];
        $workerName = trim((string) ($worker?->first_name ?? '') . ' ' . (string) ($worker?->last_name ?? ''));
        $rejectionReason = $document->history
            ->firstWhere('to_status', MedicalDocument::STATUS_REJECTED)
            ?->observation;

        return [
            'document_id' => $document->id,
            'document_number' => 'DOC-' . str_pad((string) $document->id, 6, '0', STR_PAD_LEFT),
            'registered_at' => optional($document->created_at)->toISOString(),
            'registered_at_local' => optional($document->created_at)->timezone('America/Lima')->format('Y-m-d H:i:s'),
            'updated_at' => optional($document->updated_at)->toISOString(),
            'document_type' => [
                'id' => $document->type?->id,
                'name' => $document->type?->name,
                'code' => $document->type?->code,
            ],
            'status' => $document->status,
            'status_changed_at' => optional($document->status_changed_at)->toISOString(),
            'status_changed_by' => [
                'id' => $document->statusChangedBy?->id,
                'name' => $document->statusChangedBy?->name,
                'email' => $document->statusChangedBy?->email,
            ],
            'rejection_reason' => $rejectionReason,
            'registrar' => [
                'id' => $document->creator?->id,
                'user' => $document->creator?->user,
                'name' => $document->creator?->name,
                'email' => $document->creator?->email,
            ],
            'worker' => [
                'id' => $worker?->id,
                'dni' => $worker?->dni,
                'full_name' => $workerName !== '' ? $workerName : null,
                'first_name' => $worker?->first_name,
                'last_name' => $worker?->last_name,
                'email' => $worker?->email,
                'phone' => $worker?->phone,
                'position' => $worker?->position,
                'is_active' => $worker?->is_active,
                'hire_date' => optional($worker?->hire_date)->format('Y-m-d'),
                'termination_date' => optional($worker?->termination_date)->format('Y-m-d'),
            ],
            'organization' => [
                'management_id' => $worker?->management?->id,
                'management' => $payload['area_desc'] ?? $worker?->management?->name,
                'sector_id' => $worker?->sector?->id,
                'sector' => $worker?->sector?->name,
                'fundo' => $payload['fundo'] ?? $payload['sede'] ?? $worker?->sector?->name,
            ],
            'delivery' => [
                'relation_id' => $document->deliveryRelation?->id,
                'relation' => $document->deliveryRelation?->name,
                'relation_detail' => $document->delivery_relation_detail,
                'deliverer_name' => $document->deliverer_name,
                'deliverer_document' => $document->deliverer_document,
                'contact_number' => $document->contact_number,
            ],
            'observation' => $document->observation,
            'files' => [
                'count' => $document->files->count(),
                'items' => $document->files->map(fn ($file) => [
                    'id' => $file->id,
                    'file_type' => $file->file_type,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                ])->values(),
            ],
            'status_history' => $document->history->map(fn ($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'observation' => $history->observation,
                'changed_at' => optional($history->created_at)->toISOString(),
                'changed_by' => [
                    'id' => $history->user?->id,
                    'name' => $history->user?->name,
                    'email' => $history->user?->email,
                ],
            ])->values(),
        ];
    }
}
