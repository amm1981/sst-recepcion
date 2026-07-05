<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRelation;
use App\Models\Management;
use App\Models\MedicalDocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private array $resources = [
        'users' => User::class,
        'roles' => Role::class,
        'permissions' => Permission::class,
        'document-types' => MedicalDocumentType::class,
        'managements' => Management::class,
        'sectors' => Sector::class,
        'delivery-relations' => DeliveryRelation::class,
    ];

    public function index(Request $request, string $resource)
    {
        $model = $this->model($resource);
        $query = $model::query()->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $resource) {
                if ($resource === 'users') {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                } else {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                }
            });
        }

        if ($resource === 'users') {
            $query->with('role.permissions');
        }

        if ($resource === 'roles') {
            $query->with('permissions');
        }

        if ($resource === 'sectors') {
            // No relations to eager load
        }

        return response()->json($query->paginate($request->integer('per_page', 50)));
    }

    public function store(Request $request, string $resource)
    {
        $model = $this->model($resource);
        $data = $this->validated($request, $resource);

        if ($resource === 'users') {
            $data['password'] = Hash::make($data['password']);
        }

        $record = $model::create($data);
        $this->syncRolePermissions($request, $resource, $record);

        return response()->json($record->fresh($this->relations($resource)), 201);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $model = $this->model($resource);
        $record = $model::findOrFail($id);
        $data = $this->validated($request, $resource, $id);

        if ($resource === 'users') {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $record->update($data);
        $this->syncRolePermissions($request, $resource, $record);

        return response()->json($record->fresh($this->relations($resource)));
    }

    public function destroy(string $resource, int $id)
    {
        $model = $this->model($resource);
        $record = $model::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Registro eliminado.']);
    }

    private function model(string $resource): string
    {
        abort_unless(isset($this->resources[$resource]), 404);

        return $this->resources[$resource];
    }

    private function validated(Request $request, string $resource, ?int $id = null): array
    {
        return match ($resource) {
            'users' => $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users')->ignore($id)],
                'phone' => ['nullable', 'string', 'max:50'],
                'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
                'role_id' => ['required', 'exists:roles,id'],
                'is_active' => ['boolean'],
            ]),
            'roles' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'code' => ['required', 'string', 'max:50', Rule::unique('roles')->ignore($id)],
                'description' => ['nullable', 'string'],
                'is_active' => ['boolean'],
                'permission_ids' => ['array'],
                'permission_ids.*' => ['exists:permissions,id'],
            ]),
            'permissions' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'code' => ['required', 'string', 'max:80', Rule::unique('permissions')->ignore($id)],
                'description' => ['nullable', 'string'],
            ]),
            'sectors' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'code' => ['required', 'string', 'max:50', Rule::unique('sectors')->ignore($id)],
                'is_active' => ['boolean'],
            ]),
            'delivery-relations' => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'code' => ['required', 'string', 'max:50', Rule::unique('delivery_relations')->ignore($id)],
                'requires_detail' => ['boolean'],
                'is_active' => ['boolean'],
            ]),
            default => $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'code' => ['required', 'string', 'max:50', Rule::unique((new ($this->model($resource)))->getTable())->ignore($id)],
                'is_active' => ['boolean'],
            ]),
        };
    }

    private function syncRolePermissions(Request $request, string $resource, $record): void
    {
        if ($resource === 'roles') {
            $record->permissions()->sync($request->input('permission_ids', []));
        }
    }

    private function relations(string $resource): array
    {
        return match ($resource) {
            'users' => ['role.permissions'],
            'roles' => ['permissions'],
            'sectors' => [],
            default => [],
        };
    }
}
