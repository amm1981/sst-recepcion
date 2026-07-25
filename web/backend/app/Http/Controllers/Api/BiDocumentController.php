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

        $reportRows = $documents->getCollection()
            ->map(fn (MedicalDocument $document) => $this->reportRow($document))
            ->values();

        $documents->getCollection()->transform(fn (MedicalDocument $document) => $this->documentPayload($document));

        return response()->json([
            'columns' => $this->readableColumns(),
            'data' => $documents->items(),
            'report_rows' => $reportRows,
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
            'document_date' => optional($document->document_date)->format('Y-m-d'),
            'document_date_display' => optional($document->document_date)->format('d/m/Y'),
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
                'position' => $document->worker_position_snapshot ?? $worker?->position,
                'is_active' => $worker?->is_active,
                'hire_date' => optional($worker?->hire_date)->format('Y-m-d'),
                'termination_date' => optional($worker?->termination_date)->format('Y-m-d'),
            ],
            'organization' => [
                'management_id' => $document->worker_management_id_snapshot ?? $worker?->management?->id,
                'management' => $document->worker_management_name_snapshot ?? $payload['area_desc'] ?? $worker?->management?->name,
                'sector_id' => $document->worker_sector_id_snapshot ?? $worker?->sector?->id,
                'sector' => $document->worker_sector_name_snapshot ?? $worker?->sector?->name,
                'fundo' => $payload['fundo'] ?? $payload['sede'] ?? $document->worker_sector_name_snapshot ?? $worker?->sector?->name,
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

    private function readableColumns(): array
    {
        return [
            ['key' => 'ID Documento', 'label' => 'ID Documento'],
            ['key' => 'Numero Documento', 'label' => 'Numero Documento'],
            ['key' => 'Fecha Registro', 'label' => 'Fecha Registro'],
            ['key' => 'Fecha del Documento', 'label' => 'Fecha del Documento'],
            ['key' => 'Usuario Registrador', 'label' => 'Usuario Registrador'],
            ['key' => 'Correo Registrador', 'label' => 'Correo Registrador'],
            ['key' => 'Tipo de Documento', 'label' => 'Tipo de Documento'],
            ['key' => 'Estado', 'label' => 'Estado'],
            ['key' => 'Motivo de Rechazo', 'label' => 'Motivo de Rechazo'],
            ['key' => 'DNI Trabajador', 'label' => 'DNI Trabajador'],
            ['key' => 'Nombre Trabajador', 'label' => 'Nombre Trabajador'],
            ['key' => 'Correo Trabajador', 'label' => 'Correo Trabajador'],
            ['key' => 'Telefono Trabajador', 'label' => 'Telefono Trabajador'],
            ['key' => 'Cargo', 'label' => 'Cargo'],
            ['key' => 'Area/Gerencia', 'label' => 'Area/Gerencia'],
            ['key' => 'Sector', 'label' => 'Sector'],
            ['key' => 'Fundo', 'label' => 'Fundo'],
            ['key' => 'Relacion Entrega', 'label' => 'Relacion Entrega'],
            ['key' => 'Detalle Relacion', 'label' => 'Detalle Relacion'],
            ['key' => 'Nombre Entregante', 'label' => 'Nombre Entregante'],
            ['key' => 'Documento Entregante', 'label' => 'Documento Entregante'],
            ['key' => 'Contacto', 'label' => 'Contacto'],
            ['key' => 'Observacion', 'label' => 'Observacion'],
            ['key' => 'Cantidad Archivos', 'label' => 'Cantidad Archivos'],
        ];
    }

    private function reportRow(MedicalDocument $document): array
    {
        $worker = $document->worker;
        $payload = is_array($worker?->external_payload) ? $worker->external_payload : [];
        $workerName = trim((string) ($worker?->first_name ?? '') . ' ' . (string) ($worker?->last_name ?? ''));
        $rejectionReason = $document->history
            ->firstWhere('to_status', MedicalDocument::STATUS_REJECTED)
            ?->observation;

        return [
            'ID Documento' => $document->id,
            'Numero Documento' => 'DOC-' . str_pad((string) $document->id, 6, '0', STR_PAD_LEFT),
            'Fecha Registro' => optional($document->created_at)->timezone('America/Lima')->format('d/m/Y H:i'),
            'Fecha del Documento' => optional($document->document_date)->format('d/m/Y'),
            'Usuario Registrador' => $document->creator?->name,
            'Correo Registrador' => $document->creator?->email,
            'Tipo de Documento' => $document->type?->name,
            'Estado' => $document->status,
            'Motivo de Rechazo' => $rejectionReason,
            'DNI Trabajador' => $worker?->dni,
            'Nombre Trabajador' => $workerName !== '' ? $workerName : null,
            'Correo Trabajador' => $worker?->email,
            'Telefono Trabajador' => $worker?->phone,
            'Cargo' => $document->worker_position_snapshot ?? $worker?->position,
            'Area/Gerencia' => $document->worker_management_name_snapshot ?? $payload['area_desc'] ?? $worker?->management?->name,
            'Sector' => $document->worker_sector_name_snapshot ?? $worker?->sector?->name,
            'Fundo' => $payload['fundo'] ?? $payload['sede'] ?? $document->worker_sector_name_snapshot ?? $worker?->sector?->name,
            'Relacion Entrega' => $document->deliveryRelation?->name,
            'Detalle Relacion' => $document->delivery_relation_detail,
            'Nombre Entregante' => $document->deliverer_name,
            'Documento Entregante' => $document->deliverer_document,
            'Contacto' => $document->contact_number,
            'Observacion' => $document->observation,
            'Cantidad Archivos' => $document->files->count(),
        ];
    }
}
