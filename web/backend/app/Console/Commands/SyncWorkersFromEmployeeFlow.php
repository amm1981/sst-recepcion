<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmployeeFlowService;
use App\Models\Worker;
use App\Models\WorkerSyncLog;
use App\Models\Management;
use App\Models\Sector;
use Carbon\Carbon;
use Throwable;

class SyncWorkersFromEmployeeFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workers:sync-employee-flow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los trabajadores desde la API externa Employee Flow';

    protected array $managementMap = [
        'GP' => 'Gerencia Palto',
        'GC' => 'Gerencia Cítrico',
        'GG' => 'Gerencia General',
        'GA' => 'Gerencia Administración',
        'GAF' => 'Gerencia Administración',
        'DA' => 'Dirección Agrícola',
        'GG VID' => 'Gerencia General VID',
        'GVID' => 'Gerencia General VID',
        'NA' => 'No Asignado',
    ];

    /**
     * Execute the console command.
     */
    public function handle(EmployeeFlowService $apiService)
    {
        $log = WorkerSyncLog::create([
            'started_at' => now(),
            'status' => 'IN_PROGRESS',
        ]);

        $this->info('Iniciando sincronización de trabajadores...');

        try {
            $data = $apiService->getPersonal();
            
            if (isset($data['data']) && is_array($data['data'])) {
                $data = $data['data'];
            } elseif (!is_array($data)) {
                throw new \Exception('La respuesta de la API no es un arreglo válido.');
            }

            $log->total_received = count($data);
            $log->save();
            $this->info("Recibidos: {$log->total_received} trabajadores.");

            // Caché de gerencias y sectores para evitar demasiadas consultas
            $managementsCache = Management::all()->keyBy(fn ($item) => strtolower($item->name));
            $sectorsCache = Sector::all()->keyBy(fn ($item) => strtolower($item->name));

            foreach ($data as $item) {
                try {
                    $dni = trim((string) ($item['cod_trabajador'] ?? ''));
                    if (empty($dni)) {
                        $log->warning_count++;
                        continue;
                    }

                    $firstName = mb_convert_case(trim((string) ($item['Nombres'] ?? '')), MB_CASE_TITLE, 'UTF-8');
                    $apellidoPat = trim((string) ($item['apellido_pat'] ?? ''));
                    $apellidoMat = trim((string) ($item['apellido_mat'] ?? ''));
                    $lastName = mb_convert_case(trim("{$apellidoPat} {$apellidoMat}"), MB_CASE_TITLE, 'UTF-8');
                    
                    if (empty($firstName)) $firstName = 'Sin Nombre';
                    if (empty($lastName)) $lastName = 'Sin Apellidos';

                    $email = trim((string) ($item['email'] ?? '')) ?: null;
                    $position = trim((string) ($item['cargo'] ?? '')) ?: null;

                    // Mapeo de Gerencia
                    $areaDesc = trim((string) ($item['area_desc'] ?? ''));
                    $managementId = null;
                    if ($areaDesc) {
                        $parts = explode('-', $areaDesc);
                        $code = trim(end($parts));
                        $managementName = $this->managementMap[$code] ?? null;
                        
                        if ($managementName) {
                            $key = strtolower($managementName);
                            if (isset($managementsCache[$key])) {
                                $managementId = $managementsCache[$key]->id;
                            } else {
                                $this->warn("Gerencia no encontrada en BD: {$managementName}");
                                $log->warning_count++;
                            }
                        } else {
                            $this->warn("Código de gerencia desconocido: {$code}");
                            $log->warning_count++;
                        }
                    }

                    // Mapeo de Sector
                    $sede = trim((string) ($item['sede'] ?? ''));
                    $sectorId = null;
                    if ($sede) {
                        $key = strtolower($sede);
                        if (isset($sectorsCache[$key])) {
                            $sectorId = $sectorsCache[$key]->id;
                        } else {
                            // Crear sector automáticamente
                            $newSector = Sector::create(['name' => $sede, 'is_active' => true]);
                            $sectorsCache[$key] = $newSector;
                            $sectorId = $newSector->id;
                            $this->info("Nuevo sector creado: {$sede}");
                        }
                    } else {
                        $log->warning_count++;
                    }

                    // Fechas
                    $hireDate = !empty($item['fingreso']) ? Carbon::parse($item['fingreso'])->toDateString() : null;
                    $terminationDate = !empty($item['fcese']) ? Carbon::parse($item['fcese'])->toDateString() : null;
                    $externalUpdatedAt = !empty($item['updpersonal']) ? Carbon::parse($item['updpersonal']) : null;

                    // Lógica de estado
                    $estadoStr = trim((string) ($item['estado'] ?? ''));
                    $isActive = false;
                    
                    if ($estadoStr === 'A') {
                        $isActive = true;
                    } elseif ($estadoStr === 'C') {
                        $isActive = false;
                    } elseif ($terminationDate && $estadoStr !== 'A') {
                        $isActive = false;
                    }

                    // Buscar trabajador existente
                    $worker = Worker::withTrashed()->where('dni', $dni)->first();
                    $isNew = !$worker;

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
                        $worker->external_payload = json_encode($item);
                        
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
                            'external_payload' => json_encode($item),
                        ]);

                        $log->created_count++;
                    }

                    if (!$isActive) {
                        $log->inactive_count++;
                    }

                } catch (Throwable $e) {
                    $this->error("Error procesando DNI {$dni}: " . $e->getMessage());
                    $log->error_count++;
                }
            }

            $log->status = 'COMPLETED';
            $log->finished_at = now();
            $log->save();

            $this->info("Sincronización finalizada exitosamente.");
            $this->table(
                ['Recibidos', 'Creados', 'Actualizados', 'Inactivos', 'Advertencias', 'Errores'],
                [[$log->total_received, $log->created_count, $log->updated_count, $log->inactive_count, $log->warning_count, $log->error_count]]
            );

        } catch (Throwable $e) {
            $this->error("Error crítico: " . $e->getMessage());
            
            $log->status = 'FAILED';
            $log->error_message = $e->getMessage();
            $log->finished_at = now();
            $log->save();
        }
    }
}
