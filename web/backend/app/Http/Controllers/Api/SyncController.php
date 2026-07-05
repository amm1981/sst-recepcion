<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRelation;
use App\Models\Management;
use App\Models\MedicalDocumentType;
use App\Models\MobileDevice;
use App\Models\Sector;
use App\Models\SyncLog;
use App\Models\Worker;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function workers(Request $request)
    {
        $query = Worker::with(['management:id,name', 'sector:id,name'])->where('is_active', true);

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>', $request->input('updated_since'));
        }

        // Select only needed columns to reduce payload size
        return response()->json(
            $query->select(['id', 'dni', 'first_name', 'last_name', 'email', 'phone', 'position', 'management_id', 'sector_id'])->get()
        );
    }

    public function catalogs()
    {
        return response()->json([
            'medical_document_types' => MedicalDocumentType::where('is_active', true)->get(),
            'delivery_relations' => DeliveryRelation::where('is_active', true)->get(),
            'managements' => Management::where('is_active', true)->get(),
            'sectors' => Sector::where('is_active', true)->get(),
        ]);
    }

    public function permissions(Request $request)
    {
        $user = $request->user()->load('role.permissions');

        return response()->json([
            'role' => $user->role?->code,
            'permissions' => $user->role?->permissions->pluck('code')->values() ?? [],
        ]);
    }

    public function uploadDocument(Request $request, MedicalDocumentController $documents)
    {
        return $documents->store($request);
    }

    public function log(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => ['nullable', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:80'],
            'direction' => ['required', 'string', 'max:20'],
            'entity' => ['required', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:30'],
            'payload' => ['nullable', 'array'],
            'message' => ['nullable', 'string'],
        ]);

        $device = null;
        if (! empty($data['device_uuid'])) {
            $device = MobileDevice::updateOrCreate(
                ['device_uuid' => $data['device_uuid']],
                [
                    'user_id' => $request->user()->id,
                    'name' => $data['device_name'] ?? null,
                    'platform' => $data['platform'] ?? null,
                    'last_sync_at' => now(),
                    'is_active' => true,
                ]
            );
        }

        SyncLog::create([
            'user_id' => $request->user()->id,
            'mobile_device_id' => $device?->id,
            'direction' => $data['direction'],
            'entity' => $data['entity'],
            'status' => $data['status'] ?? 'OK',
            'payload' => $data['payload'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        return response()->json(['message' => 'Log registrado.']);
    }
}
