<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function applyFilters($query, Request $request)
    {
        $this->applyDocumentVisibility($query, $request);

        if ($request->filled('date_from') || $request->filled('from')) {
            $fromField = $request->filled('date_from') ? 'date_from' : 'from';
            $query->whereDate('medical_documents.created_at', '>=', $request->date($fromField));
        }
        
        $query->when($request->filled('to'), function ($q) use ($request) {
            $q->whereDate('medical_documents.created_at', '<=', $request->date('to'));
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
        ]);
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
}
