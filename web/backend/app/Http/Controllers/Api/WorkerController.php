<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class WorkerController extends Controller
{
    public function index(Request $request)
    {
        $query = Worker::with(['management', 'sector'])->latest();

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn ($sub) => $sub
                ->where('dni', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%"));
        }
        
        if ($request->filled('management_id')) {
            $query->where('management_id', $request->integer('management_id'));
        }
        
        if ($request->filled('sector_id')) {
            $query->where('sector_id', $request->integer('sector_id'));
        }
        
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canDo('workers.manage'), 403);

        $worker = Worker::create($this->validated($request));

        return response()->json($worker->load(['management', 'sector']), 201);
    }

    public function update(Request $request, Worker $worker)
    {
        abort_unless($request->user()->canDo('workers.manage'), 403);

        $worker->update($this->validated($request, $worker->id));

        return response()->json($worker->fresh(['management', 'sector']));
    }

    public function destroy(Request $request, Worker $worker)
    {
        abort_unless($request->user()->canDo('workers.manage'), 403);

        $worker->delete();

        return response()->json(['message' => 'Trabajador eliminado.']);
    }

    public function searchByDni(string $dni)
    {
        $query = trim($dni);
        $worker = Worker::with(['management', 'sector'])
            ->where('is_active', true)
            ->where(function ($sub) use ($query) {
                $sub->where('dni', $query)
                    ->orWhere('dni', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%");
            })
            ->orderByRaw('CASE WHEN dni = ? THEN 0 ELSE 1 END', [$query])
            ->firstOrFail();

        return response()->json($worker);
    }

    public function registeredWithDocuments(Request $request)
    {
        $user = $request->user();

        $documentFilter = function ($query) use ($request, $user) {
            if (! $user->hasRole('ADMIN') && ! $user->hasRole('SST')) {
                $query->where('medical_documents.created_by', $user->id);
            }

            if ($request->filled('created_by')) {
                $query->where('medical_documents.created_by', $request->integer('created_by'));
            }

            if ($request->filled('date_from') || $request->filled('from')) {
                $fromField = $request->filled('date_from') ? 'date_from' : 'from';
                $query->whereDate('medical_documents.created_at', '>=', $request->date($fromField));
            }

            if ($request->filled('date_to') || $request->filled('to')) {
                $toField = $request->filled('date_to') ? 'date_to' : 'to';
                $query->whereDate('medical_documents.created_at', '<=', $request->date($toField));
            }
        };
        $documentDetail = function ($query) use ($documentFilter) {
            $documentFilter($query);
            $query->with(['type', 'deliveryRelation', 'creator', 'files'])
                ->latest('medical_documents.created_at');
        };

        $query = Worker::query()
            ->with(['management', 'sector'])
            ->withCount(['medicalDocuments as documents_count' => $documentFilter])
            ->with(['medicalDocuments' => $documentDetail])
            ->whereHas('medicalDocuments', $documentFilter)
            ->latest('workers.updated_at');

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(fn ($sub) => $sub
                ->where('dni', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('position', 'like', "%{$q}%"));
        }

        if ($request->filled('management_id')) {
            $query->where('management_id', $request->integer('management_id'));
        }

        if ($request->filled('sector_id')) {
            $query->where('sector_id', $request->integer('sector_id'));
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($request->integer('per_page', 15));
        $paginator->getCollection()->transform(fn (Worker $worker) => $this->registeredWorkerPayload($worker));

        return response()->json($paginator);
    }

    public function importTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Trabajadores');

        $headers = ['DNI', 'Nombres', 'Apellidos', 'Cargo', 'Correo', 'Telefono', 'Gerencia', 'Sector'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
            $sheet->getColumnDimensionByColumn($index + 1)->setAutoSize(true);
        }

        $dataSheet = $spreadsheet->createSheet();
        $dataSheet->setTitle('Data');
        
        $managements = \App\Models\Management::pluck('name')->toArray();
        $sectors = \App\Models\Sector::pluck('name')->toArray();

        foreach ($managements as $i => $m) {
            $dataSheet->setCellValue("A" . ($i + 1), $m);
        }
        foreach ($sectors as $i => $s) {
            $dataSheet->setCellValue("B" . ($i + 1), $s);
        }

        $mCount = count($managements);
        $sCount = count($sectors);

        $dataSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        $spreadsheet->setActiveSheetIndex(0);

        for ($row = 2; $row <= 1000; $row++) {
            if ($mCount > 0) {
                $validation = $sheet->getCell("G{$row}")->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setShowDropDown(true);
                $validation->setFormula1("Data!\$A\$1:\$A\${$mCount}");
            }
            if ($sCount > 0) {
                $validation = $sheet->getCell("H{$row}")->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setShowDropDown(true);
                $validation->setFormula1("Data!\$B\$1:\$B\${$sCount}");
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        return response()->download($tempFile, 'plantilla_trabajadores.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getSheetByName('Trabajadores') ?? $spreadsheet->getActiveSheet();
        
        $rows = $worksheet->toArray();
        array_shift($rows);

        $managements = \App\Models\Management::pluck('id', 'name')->toArray();
        $sectors = \App\Models\Sector::pluck('id', 'name')->toArray();

        $upsertData = [];
        $now = now();
        foreach ($rows as $row) {
            if (empty($row[0])) continue;

            $mName = (string) ($row[6] ?? '');
            $sName = (string) ($row[7] ?? '');

            $upsertData[] = [
                'dni' => (string) $row[0],
                'first_name' => ((string) $row[1]) ?: 'Sin Nombre',
                'last_name' => ((string) $row[2]) ?: 'Sin Apellidos',
                'position' => ((string) ($row[3] ?? '')) ?: null,
                'email' => ((string) ($row[4] ?? '')) ?: null,
                'phone' => ((string) ($row[5] ?? '')) ?: null,
                'management_id' => $managements[$mName] ?? null,
                'sector_id' => $sectors[$sName] ?? null,
                'is_active' => true,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($upsertData, 500) as $chunk) {
            Worker::upsert(
                $chunk,
                ['dni'], // Columna única
                ['first_name', 'last_name', 'position', 'email', 'phone', 'management_id', 'sector_id', 'is_active', 'deleted_at', 'updated_at']
            );
        }
        $count = count($upsertData);

        return response()->json(['message' => 'Trabajadores importados.', 'count' => $count]);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'dni' => ['required', 'string', 'max:20', Rule::unique('workers')->ignore($id)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:120'],
            'management_id' => ['nullable', 'exists:managements,id'],
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'is_active' => ['boolean'],
        ]);
    }

    private function registeredWorkerPayload(Worker $worker): array
    {
        $payload = is_array($worker->external_payload) ? $worker->external_payload : [];
        $area = $payload['area_desc'] ?? $worker->management?->name;
        $fundo = $payload['fundo'] ?? $payload['sede'] ?? $worker->sector?->name;

        return [
            'id' => $worker->id,
            'dni' => $worker->dni,
            'first_name' => $worker->first_name,
            'last_name' => $worker->last_name,
            'email' => $worker->email,
            'phone' => $worker->phone,
            'position' => $worker->position,
            'area' => $area,
            'fundo' => $fundo,
            'management' => $worker->management,
            'sector' => $worker->sector,
            'is_active' => $worker->is_active,
            'hire_date' => optional($worker->hire_date)->toDateString(),
            'termination_date' => optional($worker->termination_date)->toDateString(),
            'source' => $worker->source,
            'documents_count' => (int) $worker->documents_count,
            'documents' => $worker->medicalDocuments->map(fn ($document) => [
                'id' => $document->id,
                'status' => $document->status,
                'created_at' => $document->created_at,
                'type' => $document->type,
                'delivery_relation' => $document->deliveryRelation,
                'delivery_relation_detail' => $document->delivery_relation_detail,
                'deliverer_name' => $document->deliverer_name,
                'deliverer_document' => $document->deliverer_document,
                'contact_number' => $document->contact_number,
                'observation' => $document->observation,
                'creator' => $document->creator,
                'files' => $document->files,
            ])->values(),
        ];
    }
}
