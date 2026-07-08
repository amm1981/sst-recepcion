<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'user' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::with('role.permissions')->where('user', $data['user'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Credenciales invalidas o usuario inactivo.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'web')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesion cerrada.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userPayload($request->user()->load('role.permissions'))]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'user' => $user->user,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'permissions' => $user->role?->permissions->pluck('code')->values() ?? [],
        ];
    }
}
