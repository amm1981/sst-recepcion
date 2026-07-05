<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Models\WorkerSyncLog;

class WorkerSyncController extends Controller
{
    /**
     * Dispara manualmente el comando de sincronización.
     */
    public function trigger(Request $request)
    {
        // Ejecuta el comando Artisan de forma síncrona
        $exitCode = Artisan::call('workers:sync-employee-flow');
        
        $latestLog = WorkerSyncLog::latest('id')->first();

        return response()->json([
            'message' => 'Sincronización finalizada.',
            'exit_code' => $exitCode,
            'log' => $latestLog
        ]);
    }

    /**
     * Obtiene el último log de sincronización.
     */
    public function latest(Request $request)
    {
        $latestLog = WorkerSyncLog::latest('id')->first();

        if (!$latestLog) {
            return response()->json(['message' => 'No hay sincronizaciones previas.'], 404);
        }

        return response()->json($latestLog);
    }
}
