<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Registro de nuevo Tenant y Usuario Propietario
     */
    public function register(RegisterTenantRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Crear Tenant
            $tenant = Tenant::create([
                'name' => $request->name,
                'subdomain' => $request->subdomain,
                'status' => 'trial',
                'default_locale' => $request->default_locale ?? 'es',
                'subscribed_until' => now()->addDays(14), // 14 días trial
                'branding' => [
                    'primary_color' => '#3B82F6',
                    'secondary_color' => '#1E40AF',
                    'logo_url' => null,
                ]
            ]);

            // Crear Usuario Propietario
            $user = User::create([
                'tenant_id' => $tenant->id,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'owner',
                'is_super_admin' => false,
                'preferences' => [
                    'timezone' => 'Europe/Madrid',
                    'locale' => $request->default_locale ?? 'es'
                ]
            ]);

            DB::commit();

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Registro exitoso',
                'tenant' => $tenant,
                'user' => $user,
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error en el registro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Credenciales inválidas'], 401);
        }

        $user = Auth::user();
        
        // Verificar estado del tenant
        if ($user->tenant && $user->tenant->status !== 'active' && $user->tenant->status !== 'trial') {
            Auth::logout();
            return response()->json(['error' => 'Tu cuenta está suspendida o ha expirado.'], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $user,
            'tenant' => $user->tenant,
            'token' => $token
        ]);
    }

    /**
     * Logout
     */
    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    /**
     * Obtener usuario actual
     */
    public function me(): JsonResponse
    {
        return response()->json([
            'user' => Auth::user(),
            'tenant' => Auth::user()->tenant
        ]);
    }
}
