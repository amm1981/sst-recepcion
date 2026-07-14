<?php

namespace App\Console\Commands;

use App\Models\Management;
use App\Models\Sector;
use App\Models\Worker;
use App\Models\WorkerSyncLog;
use App\Services\EmployeeFlowService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class SyncWorkersFromEmployeeFlow extends Command
{
    protected $signature = 'workers:sync-employee-flow';

    protected $description = 'Sincroniza los trabajadores desde la API externa Employee Flow';

    protected array $managementMap = [
        'GP' => 'Gerencia Palto',
        'GC' => 'Gerencia Citrico',
        'GG' => 'Gerencia General',
        'GA' => 'Gerencia Administracion',
        'GAF' => 'Gerencia Administracion',
        'DA' => 'Direccion Agricola',
        'GG VID' => 'Gerencia General VID',
        'GVID' => 'Gerencia General VID',
        'NA' => 'No Asignado',
    ];

    public function handle(EmployeeFlowService $apiService): int
    {
        $details = [];
        $log = WorkerSyncLog::create([
            'started_at' => now(),
            'status' => 'IN_PROGRESS',
            'details' => [],
        ]);

        $this->info('Iniciando sincronizacion de trabajadores...');

        try {
            $data = $apiService->getPersonal();

            if (isset($data['data']) && is_array($data['data'])) {
                $data = $data['data'];
            } elseif (! is_array($data)) {
                throw new \Exception('La respuesta de la API no es un arreglo valido.');
            }

            $log->total_received = count($data);
            $log->save();
            $this->info("Recibidos: {$log->total_received} trabajadores.");

            $managementsCache = Management::all()->keyBy(fn ($item) => strtolower($item->name));
            $sectorsCache = Sector::all()->keyBy(fn ($item) => strtolower($item->name));

            foreach ($data as $item) {
                $dni = null;

                try {
                    $dni = trim((string) ($item['cod_trabajador'] ?? ''));
                    if ($dni === '') {
                        $log->warning_count++;
                        $this->addDetail($details, 'warning', null, 'Trabajador omitido porque no tiene DNI.', [
                            'source' => $this->compactPayload($item),
                        ]);
                        continue;
                    }

                    $firstName = mb_convert_case(trim((string) ($item['Nombres'] ?? '')), MB_CASE_TITLE, 'UTF-8');
                    $apellidoPat = trim((string) ($item['apellido_pat'] ?? ''));
                    $apellidoMat = trim((string) ($item['apellido_mat'] ?? ''));
                    $lastName = mb_convert_case(trim("{$apellidoPat} {$apellidoMat}"), MB_CASE_TITLE, 'UTF-8');

                    if ($firstName === '') {
                        $firstName = 'Sin Nombre';
                    }
                    if ($lastName === '') {
                        $lastName = 'Sin Apellidos';
                    }

                    $email = trim((string) ($item['email'] ?? '')) ?: null;
                    $position = trim((string) ($item['cargo'] ?? '')) ?: null;

                    $areaDesc = trim((string) ($item['area_desc'] ?? ''));
                    $managementId = null;
                    if ($areaDesc !== '') {
                        $parts = explode('-', $areaDesc);
                        $code = trim((string) end($parts));
                        $managementName = $this->managementMap[$code] ?? null;

                        if ($managementName) {
                            $key = strtolower($managementName);
                            if (isset($managementsCache[$key])) {
                                $managementId = $managementsCache[$key]->id;
                            } else {
                                $message = "Gerencia no encontrada en BD: {$managementName}";
                                $this->warn($message);
                                $log->warning_count++;
                                $this->addDetail($details, 'warning', $dni, $message, [
                                    'area_desc' => $areaDesc,
                                    'management_code' => $code,
                                ]);
                            }
                        } else {
                            $message = "Codigo de gerencia desconocido: {$code}";
                            $this->warn($message);
                            $log->warning_count++;
                            $this->addDetail($details, 'warning', $dni, $message, [
                                'area_desc' => $areaDesc,
                                'management_code' => $code,
                            ]);
                        }
                    }

                    $sede = trim((string) ($item['sede'] ?? ''));
                    $sectorId = null;
                    if ($sede !== '') {
                        $key = strtolower($sede);
                        if (isset($sectorsCache[$key])) {
                            $sectorId = $sectorsCache[$key]->id;
                        } else {
                            $newSector = Sector::create([
                                'name' => $sede,
                                'code' => $this->uniqueSectorCode($sede),
                                'is_active' => true,
                            ]);
                            $sectorsCache[$key] = $newSector;
                            $sectorId = $newSector->id;
                            $this->info("Nuevo sector creado: {$sede}");
                        }
                    } else {
                        $log->warning_count++;
                        $this->addDetail($details, 'warning', $dni, 'Trabajador sin sede/sector en EmployeeFlow.');
                    }

                    $hireDate = ! empty($item['fingreso']) ? Carbon::parse($item['fingreso'])->toDateString() : null;
                    $terminationDate = ! empty($item['fcese']) ? Carbon::parse($item['fcese'])->toDateString() : null;
                    $externalUpdatedAt = ! empty($item['updpersonal']) ? Carbon::parse($item['updpersonal']) : null;

                    $estadoStr = trim((string) ($item['estado'] ?? ''));
                    $isActive = false;
                    if ($estadoStr === 'A') {
                        $isActive = true;
                    } elseif ($estadoStr === 'C') {
                        $isActive = false;
                    } elseif ($terminationDate && $estadoStr !== 'A') {
                        $isActive = false;
                    }

                    $worker = Worker::withTrashed()->where('dni', $dni)->first();
                    $isNew = ! $worker;

                    if ($worker) {
                        $worker->first_name = $firstName;
                        $worker->last_name = $lastName;
                        $worker->email = $email;
                        $worker->position = $position;
                        $worker->management_id = $managementId;
                        $worker->sector_id = $sectorId;
                        $worker->is_active = $isActive;
                        $worker->source = 'employee_flow';
                        $worker->hire_date = $hireDate;
                        $worker->termination_date = $terminationDate;
                        $worker->external_updated_at = $externalUpdatedAt;
                        $worker->external_payload = $item;

                        if ($worker->trashed() && $isActive) {
                            $worker->restore();
                        } else {
                            $worker->save();
                        }

                        $log->updated_count++;
                    } else {
                        Worker::create([
                            'dni' => $dni,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => $email,
                            'position' => $position,
                            'management_id' => $managementId,
                            'sector_id' => $sectorId,
                            'is_active' => $isActive,
                            'source' => 'employee_flow',
                            'hire_date' => $hireDate,
                            'termination_date' => $terminationDate,
                            'external_updated_at' => $externalUpdatedAt,
                            'external_payload' => $item,
                        ]);

                        $log->created_count++;
                    }

                    if (! $isActive) {
                        $log->inactive_count++;
                    }
                } catch (Throwable $e) {
                    $this->error("Error procesando DNI {$dni}: " . $e->getMessage());
                    $log->error_count++;
                    $this->addDetail($details, 'error', $dni, $e->getMessage(), [
                        'exception' => $e::class,
                    ]);
                }
            }

            $log->status = 'COMPLETED';
            $log->finished_at = now();
            $log->details = $details;
            $log->save();

            $this->info('Sincronizacion finalizada exitosamente.');
            $this->table(
                ['Recibidos', 'Creados', 'Actualizados', 'Inactivos', 'Advertencias', 'Errores'],
                [[$log->total_received, $log->created_count, $log->updated_count, $log->inactive_count, $log->warning_count, $log->error_count]]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error critico: ' . $e->getMessage());

            $log->status = 'FAILED';
            $log->error_message = $e->getMessage();
            $log->finished_at = now();
            $this->addDetail($details, 'error', null, $e->getMessage(), [
                'exception' => $e::class,
                'scope' => 'critical',
            ]);
            $log->details = $details;
            $log->save();

            return self::FAILURE;
        }
    }

    private function addDetail(array &$details, string $type, ?string $dni, string $message, array $context = []): void
    {
        $details[] = [
            'type' => $type,
            'dni' => $dni,
            'message' => $message,
            'context' => $context,
        ];
    }

    private function uniqueSectorCode(string $name): string
    {
        $base = Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->substr(0, 45)
            ->value();

        $base = $base !== '' ? $base : 'SECTOR';
        $candidate = $base;
        $suffix = 1;

        while (Sector::where('code', $candidate)->exists()) {
            $candidate = Str::limit($base, 42, '') . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function compactPayload(array $item): array
    {
        return collect($item)
            ->only(['cod_trabajador', 'Nombres', 'apellido_pat', 'apellido_mat', 'area_desc', 'sede', 'estado'])
            ->all();
    }
}
