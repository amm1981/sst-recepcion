<?php

namespace Database\Seeders;

use App\Models\DeliveryRelation;
use App\Models\Management;
use App\Models\MedicalDocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            ['name' => 'Ver documentos', 'code' => 'documents.view'],
            ['name' => 'Crear documentos', 'code' => 'documents.create'],
            ['name' => 'Cambiar estado de documentos', 'code' => 'documents.updateStatus'],
            ['name' => 'Gestionar trabajadores', 'code' => 'workers.manage'],
            ['name' => 'Ver reportes', 'code' => 'reports.view'],
            ['name' => 'Administrar sistema', 'code' => 'admin.manage'],
        ])->map(fn ($item) => Permission::updateOrCreate(['code' => $item['code']], $item));

        $roles = [
            'ADMIN' => ['name' => 'Administrador', 'description' => 'Gestion total del sistema.', 'permissions' => $permissions->pluck('id')->all()],
            'RRHH' => ['name' => 'Recursos Humanos', 'description' => 'Registro y consulta de sus documentos.', 'permissions' => $permissions->whereIn('code', ['documents.view', 'documents.create'])->pluck('id')->all()],
            'SST' => ['name' => 'Seguridad y Salud en el Trabajo', 'description' => 'Registro, consulta y cambio de estado.', 'permissions' => $permissions->whereIn('code', ['documents.view', 'documents.create', 'documents.updateStatus', 'workers.manage'])->pluck('id')->all()],
        ];

        foreach ($roles as $code => $roleData) {
            $role = Role::updateOrCreate(
                ['code' => $code],
                ['name' => $roleData['name'], 'description' => $roleData['description'], 'is_active' => true]
            );
            $role->permissions()->sync($roleData['permissions']);
        }

        $adminRole = Role::where('code', 'ADMIN')->first();
        $rrhhRole = Role::where('code', 'RRHH')->first();
        $sstRole = Role::where('code', 'SST')->first();

        User::updateOrCreate(['email' => 'admin@docssalud.test'], [
            'name' => 'Administrador DocsSalud',
            'password' => Hash::make('Password123'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
        User::updateOrCreate(['email' => 'rrhh@docssalud.test'], [
            'name' => 'Usuario RRHH',
            'password' => Hash::make('Password123'),
            'role_id' => $rrhhRole->id,
            'is_active' => true,
        ]);
        User::updateOrCreate(['email' => 'sst@docssalud.test'], [
            'name' => 'Usuario SST',
            'password' => Hash::make('Password123'),
            'role_id' => $sstRole->id,
            'is_active' => true,
        ]);

        $management = Management::updateOrCreate(['code' => 'OPER'], ['name' => 'Operaciones', 'is_active' => true]);
        $sector = Sector::updateOrCreate(['code' => 'PLANTA'], ['name' => 'Planta', 'is_active' => true]);

        \App\Models\Worker::updateOrCreate(['dni' => '12345678'], [
            'first_name' => 'Carlos',
            'last_name' => 'Ramirez Torres',
            'email' => 'carlos.ramirez@example.com',
            'phone' => '999111222',
            'position' => 'Operario',
            'management_id' => $management->id,
            'sector_id' => $sector->id,
            'is_active' => true,
        ]);
        \App\Models\Worker::updateOrCreate(['dni' => '87654321'], [
            'first_name' => 'Lucia',
            'last_name' => 'Vargas Medina',
            'email' => 'lucia.vargas@example.com',
            'phone' => '999333444',
            'position' => 'Supervisora',
            'management_id' => $management->id,
            'sector_id' => $sector->id,
            'is_active' => true,
        ]);

        MedicalDocumentType::updateOrCreate(['code' => 'DESCANSO_MEDICO'], ['name' => 'Descanso Medico', 'is_active' => true]);
        MedicalDocumentType::updateOrCreate(['code' => 'ATENCION_MEDICA'], ['name' => 'Atencion Medica', 'is_active' => true]);

        foreach ([
            ['code' => 'TRABAJADOR', 'name' => 'Trabajador', 'requires_detail' => false],
            ['code' => 'FAMILIAR_DIRECTO', 'name' => 'Familiar directo', 'requires_detail' => false],
            ['code' => 'FAMILIAR_INDIRECTO', 'name' => 'Familiar indirecto', 'requires_detail' => false],
            ['code' => 'ENCARGADO', 'name' => 'Encargado', 'requires_detail' => false],
            ['code' => 'AMIGO', 'name' => 'Amigo', 'requires_detail' => false],
            ['code' => 'OTROS', 'name' => 'Otros', 'requires_detail' => true],
        ] as $relation) {
            DeliveryRelation::updateOrCreate(['code' => $relation['code']], $relation + ['is_active' => true]);
        }
    }
}
