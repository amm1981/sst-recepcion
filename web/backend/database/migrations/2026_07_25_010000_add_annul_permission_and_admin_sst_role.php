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
            'documents.annul' => 'Anular documentos',
            'workers.manage' => 'Gestionar trabajadores',
            'reports.view' => 'Ver reportes',
            'admin.manage' => 'Administrar sistema',
        ];

        $permissionIds = [];
        foreach ($permissions as $code => $name) {
            $permissionIds[$code] = DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'updated_at' => $now, 'created_at' => $now],
            );

            $permissionIds[$code] = DB::table('permissions')->where('code', $code)->value('id');
        }

        DB::table('roles')->updateOrInsert(
            ['code' => 'ADMIN_SST'],
            [
                'name' => 'Administrador SST',
                'description' => 'Gestion operativa SST sin acceso al modulo de administracion.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $rolePermissions = [
            'ADMIN' => array_keys($permissions),
            'ADMIN_SST' => ['documents.view', 'documents.create', 'documents.updateStatus', 'documents.annul', 'workers.manage', 'reports.view'],
            'SST' => ['documents.view', 'documents.create', 'documents.updateStatus', 'workers.manage', 'reports.view'],
        ];

        foreach ($rolePermissions as $roleCode => $codes) {
            $roleId = DB::table('roles')->whereRaw('UPPER(code) = ?', [$roleCode])->value('id');
            if (! $roleId) {
                continue;
            }

            foreach ($codes as $code) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionIds[$code]],
                    ['created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        // Access records are kept to avoid removing production permissions unexpectedly.
    }
};
