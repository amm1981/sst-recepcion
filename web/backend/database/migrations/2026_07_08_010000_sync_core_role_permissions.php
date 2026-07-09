<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            'documents.view' => 'Ver documentos',
            'documents.create' => 'Crear documentos',
            'documents.updateStatus' => 'Cambiar estado de documentos',
            'workers.manage' => 'Gestionar trabajadores',
            'reports.view' => 'Ver reportes',
            'admin.manage' => 'Administrar sistema',
        ];

        $permissionIds = [];
        foreach ($permissions as $code => $name) {
            $existing = DB::table('permissions')->where('code', $code)->first();

            if ($existing) {
                DB::table('permissions')->where('id', $existing->id)->update([
                    'name' => $name,
                    'updated_at' => $now,
                ]);
                $permissionIds[$code] = $existing->id;
                continue;
            }

            $permissionIds[$code] = DB::table('permissions')->insertGetId([
                'name' => $name,
                'code' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rolePermissions = [
            'ADMIN' => array_keys($permissions),
            'RRHH' => ['documents.view', 'documents.create', 'reports.view'],
            'SST' => ['documents.view', 'documents.create', 'documents.updateStatus', 'workers.manage', 'reports.view'],
        ];

        foreach ($rolePermissions as $roleCode => $codes) {
            $role = DB::table('roles')->whereRaw('UPPER(code) = ?', [$roleCode])->first();

            if (! $role) {
                continue;
            }

            foreach ($codes as $code) {
                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'permission_id' => $permissionIds[$code],
                    ],
                    [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // Permissions are intentionally left in place to avoid removing access configured in production.
    }
};
