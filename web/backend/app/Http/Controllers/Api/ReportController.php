<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class ReportController extends Controller
{
    private function applyFilters($query, Request $request)
    {
        $this->applyDocumentVisibility($query, $request);

        if ($request->filled('date_from') || $request->filled('from')) {
            $fromField = $request->filled('date_from') ? 'date_from' : 'from';
            $query->whereDate('medical_documents.created_at', '>=', $request->date($fromField));
        }
        
        if ($request->filled('date_to') || $request->filled('to')) {
            $toField = $request->filled('date_to') ? 'date_to' : 'to';
            $query->whereDate('medical_documents.created_at', '<=', $request->date($toField));
        }

        $query->when($request->filled('created_by'), function ($q) use ($request) {
            $q->where('medical_documents.created_by', $request->integer('created_by'));
        });

        $query->when($request->filled('type_id'), function ($q) use ($request) {
            $q->where('medical_documents.medical_document_type_id', $request->type_id);
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('medical_documents.status', $request->status);
        });

        if ($request->filled('management_id') || $request->filled('sector_id')) {
            $query->whereHas('worker', function ($q) use ($request) {
                $q->when($request->filled('management_id'), function ($q2) use ($request) {
                    $q2->where('management_id', $request->management_id);
                });
                $q->when($request->filled('sector_id'), function ($q2) use ($request) {
                    $q2->where('sector_id', $request->sector_id);
                });
            });
        }

        $query->when($request->filled('q'), function ($q) use ($request) {
            $search = $request->string('q');
            $q->where(function ($sub) use ($search) {
                $sub->where('medical_documents.id', $search)
                    ->orWhereHas('worker', function ($worker) use ($search) {
                        $worker->where('dni', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('type', fn ($type) => $type->where('name', 'like', "%{$search}%"));
            });
        });
        
        return $query;
    }

    private function applyDocumentVisibility($query, Request $request): void
    {
        $user = $request->user();

        if (! $user->hasRole('ADMIN') && ! $user->hasRole('SST')) {
            $query->where('medical_documents.created_by', $user->id);
        }
    }

    public function summary(Request $request)
    {
        $base = MedicalDocument::query();
        $base = $this->applyFilters($base, $request);

        $monthExpression = DB::getDriverName() === 'mysql'
            ? "DATE_FORMAT(medical_documents.created_at, '%Y-%m')"
            : "strftime('%Y-%m', medical_documents.created_at)";

        return response()->json([
            'total' => (clone $base)->count(),
            'by_status' => (clone $base)->select('medical_documents.status', DB::raw('count(*) as total'))->groupBy('medical_documents.status')->get(),
            'by_type' => (clone $base)
                ->join('medical_document_types', 'medical_document_types.id', '=', 'medical_documents.medical_document_type_id')
                ->select(
                    'medical_document_types.name',
                    DB::raw('count(*) as total'),
                    DB::raw("sum(case when medical_documents.status = 'PENDIENTE' then 1 else 0 end) as pendientes"),
                    DB::raw("sum(case when medical_documents.status = 'RECEPCIONADO' then 1 else 0 end) as recepcionados"),
                    DB::raw("sum(case when medical_documents.status = 'REGISTRADO' then 1 else 0 end) as registrados"),
                    DB::raw("sum(case when medical_documents.status = 'RECHAZADO' then 1 else 0 end) as rechazados")
                )
                ->groupBy('medical_document_types.name')
                ->get(),
            'monthly' => (clone $base)
                ->select(DB::raw("{$monthExpression} as month"), DB::raw('count(*) as total'))
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            'by_creator' => (clone $base)
                ->join('users', 'users.id', '=', 'medical_documents.created_by')
                ->select(
                    'users.id',
                    'users.name',
                    'users.user',
                    'users.email',
                    DB::raw('count(*) as total')
                )
                ->groupBy('users.id', 'users.name', 'users.user', 'users.email')
                ->orderByDesc('total')
                ->get(),
        ]);
    }

    public function registrars(Request $request)
    {
        $query = MedicalDocument::query();
        $this->applyFilters($query, $request);

        $creatorIds = (clone $query)
            ->select('medical_documents.created_by as created_by', DB::raw('count(*) as total'))
            ->groupBy('medical_documents.created_by')
            ->pluck('total', 'created_by');

        $users = User::query()
            ->with('role')
            ->whereIn('id', $creatorIds->keys())
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'user' => $user->user,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'documents_count' => (int) ($creatorIds[$user->id] ?? 0),
            ]);

        return response()->json($users->values());
    }

    public function exportExcel(Request $request)
    {
        $base = MedicalDocument::query();
        $base = $this->applyFilters($base, $request);

        $data = (clone $base)
            ->join('medical_document_types', 'medical_document_types.id', '=', 'medical_documents.medical_document_type_id')
            ->select(
                'medical_document_types.name',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when medical_documents.status = 'PENDIENTE' then 1 else 0 end) as pendientes"),
                DB::raw("sum(case when medical_documents.status = 'RECEPCIONADO' then 1 else 0 end) as recepcionados"),
                DB::raw("sum(case when medical_documents.status = 'REGISTRADO' then 1 else 0 end) as registrados"),
                DB::raw("sum(case when medical_documents.status = 'RECHAZADO' then 1 else 0 end) as rechazados")
            )
            ->groupBy('medical_document_types.name')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');

        $headers = ['Tipo de Documento', 'Total', 'Pendientes', 'Recepcionados', 'Registrados', 'Rechazados'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
            $sheet->getStyle([$index + 1, 1])->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($index + 1)->setAutoSize(true);
        }

        $row = 2;
        $tTotal = 0; $tPen = 0; $tRec = 0; $tReg = 0; $tRech = 0;

        foreach ($data as $item) {
            $sheet->setCellValue("A{$row}", $item->name);
            $sheet->setCellValue("B{$row}", $item->total);
            $sheet->setCellValue("C{$row}", $item->pendientes);
            $sheet->setCellValue("D{$row}", $item->recepcionados);
            $sheet->setCellValue("E{$row}", $item->registrados);
            $sheet->setCellValue("F{$row}", $item->rechazados);
            
            $tTotal += $item->total;
            $tPen += $item->pendientes;
            $tRec += $item->recepcionados;
            $tReg += $item->registrados;
            $tRech += $item->rechazados;
            
            $row++;
        }

        if (count($data) > 0) {
            $sheet->setCellValue("A{$row}", 'Total');
            $sheet->setCellValue("B{$row}", $tTotal);
            $sheet->setCellValue("C{$row}", $tPen);
            $sheet->setCellValue("D{$row}", $tRec);
            $sheet->setCellValue("E{$row}", $tReg);
            $sheet->setCellValue("F{$row}", $tRech);
            $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        return response()->download($tempFile, 'reporte.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportDetailExcel(Request $request)
    {
        $base = MedicalDocument::with([
            'type',
            'worker.management',
            'worker.sector',
            'deliveryRelation',
            'creator',
            'statusChangedBy',
            'files',
            'history.user',
        ]);
        $base = $this->applyFilters($base, $request);

        $documents = $base->latest('medical_documents.created_at')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detalle');

        $headers = [
            'ID Documento',
            'Fecha registro',
            'Usuario registrador',
            'Usuario registrador correo',
            'Tipo de documento',
            'Estado',
            'Motivo de rechazo',
            'DNI trabajador',
            'Nombre trabajador',
            'Correo trabajador',
            'Telefono trabajador',
            'Cargo',
            'Area/Gerencia',
            'Sector',
            'Fundo',
            'Fecha ingreso',
            'Fecha cese',
            'Relacion entrega',
            'Detalle relacion',
            'Nombre entregante',
            'Documento entregante',
            'Contacto',
            'Observacion',
            'Archivos adjuntos',
            'Enlaces de descarga',
            'Enlaces de vista previa',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
            $sheet->getStyle([$index + 1, 1])->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($index + 1)->setAutoSize(true);
        }

        $row = 2;
        foreach ($documents as $document) {
            $worker = $document->worker;
            $payload = is_array($worker?->external_payload) ? $worker->external_payload : [];
            $workerName = trim((string) ($worker?->first_name ?? '') . ' ' . (string) ($worker?->last_name ?? ''));
            $rejectionReason = $document->history
                ->firstWhere('to_status', MedicalDocument::STATUS_REJECTED)
                ?->observation;
            $downloadLinks = $document->files
                ->map(fn ($file) => URL::to("/api/medical-documents/files/{$file->id}/download"))
                ->implode("\n");
            $previewLinks = $document->files
                ->map(fn ($file) => URL::to("/api/medical-documents/files/{$file->id}/preview"))
                ->implode("\n");

            $values = [
                $document->id,
                optional($document->created_at)->format('d/m/Y H:i'),
                $document->creator?->name,
                $document->creator?->email,
                $document->type?->name,
                $document->status,
                $rejectionReason,
                $worker?->dni,
                $workerName,
                $worker?->email,
                $worker?->phone,
                $worker?->position,
                $payload['area_desc'] ?? $worker?->management?->name,
                $worker?->sector?->name,
                $payload['fundo'] ?? $payload['sede'] ?? $worker?->sector?->name,
                optional($worker?->hire_date)->format('d/m/Y'),
                optional($worker?->termination_date)->format('d/m/Y'),
                $document->deliveryRelation?->name,
                $document->delivery_relation_detail,
                $document->deliverer_name,
                $document->deliverer_document,
                $document->contact_number,
                $document->observation,
                $document->files->map(fn ($file) => "{$file->file_type}: {$file->original_name}")->implode("\n"),
                $downloadLinks,
                $previewLinks,
            ];

            foreach ($values as $index => $value) {
                $sheet->setCellValue([$index + 1, $row], $value);
            }
            $sheet->getStyle("A{$row}:Z{$row}")->getAlignment()->setWrapText(true);
            $row++;
        }

        $sheet->freezePane('A2');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        return response()->download($tempFile, 'detalle_documentos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $base = MedicalDocument::query();
        $base = $this->applyFilters($base, $request);

        $summary = $this->summaryData($base);
        $lines = [
            'DocsSalud SST - Reporte de documentos',
            'Generado: ' . now()->format('d/m/Y H:i'),
            'Dominio: ' . config('app.url'),
            '',
            'Resumen',
            'Total documentos: ' . $summary['total'],
            'Pendientes: ' . $summary['pending'],
            'Recepcionados: ' . $summary['received'],
            'Registrados: ' . $summary['registered'],
            'Rechazados: ' . $summary['rejected'],
            '',
            'Resumen por tipo',
        ];

        foreach ($summary['by_type'] as $item) {
            $lines[] = sprintf(
                '%s | Total: %s | Pend: %s | Rec: %s | Reg: %s | Rech: %s',
                $item->name,
                $item->total,
                $item->pendientes,
                $item->recepcionados,
                $item->registrados,
                $item->rechazados
            );
        }

        if ($summary['by_type']->isEmpty()) {
            $lines[] = 'Sin resultados para los filtros seleccionados.';
        }

        $pdf = $this->buildSimplePdf($lines);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte_documentos.pdf"',
        ]);
    }

    private function summaryData($base): array
    {
        $byType = (clone $base)
            ->join('medical_document_types', 'medical_document_types.id', '=', 'medical_documents.medical_document_type_id')
            ->select(
                'medical_document_types.name',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when medical_documents.status = 'PENDIENTE' then 1 else 0 end) as pendientes"),
                DB::raw("sum(case when medical_documents.status = 'RECEPCIONADO' then 1 else 0 end) as recepcionados"),
                DB::raw("sum(case when medical_documents.status = 'REGISTRADO' then 1 else 0 end) as registrados"),
                DB::raw("sum(case when medical_documents.status = 'RECHAZADO' then 1 else 0 end) as rechazados")
            )
            ->groupBy('medical_document_types.name')
            ->get();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('medical_documents.status', 'PENDIENTE')->count(),
            'received' => (clone $base)->where('medical_documents.status', 'RECEPCIONADO')->count(),
            'registered' => (clone $base)->where('medical_documents.status', 'REGISTRADO')->count(),
            'rejected' => (clone $base)->where('medical_documents.status', 'RECHAZADO')->count(),
            'by_type' => $byType,
        ];
    }

    private function buildSimplePdf(array $lines): string
    {
        $content = [
            'BT',
            '/F1 16 Tf',
            '40 800 Td',
            '20 TL',
        ];

        foreach (array_slice($lines, 0, 46) as $index => $line) {
            if ($index === 3) {
                $content[] = '/F1 10 Tf';
                $content[] = '14 TL';
            }
            $content[] = '(' . $this->escapePdfText($line) . ') Tj';
            $content[] = 'T*';
        }

        $content[] = 'ET';
        $stream = implode("\n", $content);

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj',
            "4 0 obj << /Length " . strlen($stream) . " >> stream\n{$stream}\nendstream endobj",
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $normalized);
    }
}
