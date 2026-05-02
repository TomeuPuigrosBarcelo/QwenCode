<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantStripeConfig;
use App\Services\StripeServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class StripeConfigController extends Controller
{
    /**
     * Obtener configuración de Stripe del tenant
     */
    public function show()
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();

        if (!$config) {
            return response()->json([
                'success' => true,
                'configured' => false,
                'config' => null
            ]);
        }

        // No devolver las claves secretas, solo información pública
        return response()->json([
            'success' => true,
            'configured' => true,
            'config' => [
                'id' => $config->id,
                'account_type' => $config->account_type,
                'test_mode' => $config->test_mode,
                'last_verified_at' => $config->last_verified_at,
                'has_public_key' => !empty($config->pk_encrypted),
                'has_secret_key' => !empty($config->sk_encrypted),
                'has_webhook_secret' => !empty($config->whsec_encrypted)
            ]
        ]);
    }

    /**
     * Guardar/Actualizar configuración de Stripe
     */
    public function store(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'pk' => 'required|string|max:255', // Public Key
            'sk' => 'required|string|max:255', // Secret Key
            'whsec' => 'nullable|string|max:255', // Webhook Secret
            'test_mode' => 'boolean'
        ]);

        // Validar credenciales con Stripe antes de guardar
        $isValid = StripeServiceFactory::validateCredentials(
            $validated['sk'],
            $validated['test_mode'] ?? false
        );

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales de Stripe no son válidas. Por favor, verifícalas.'
            ], 400);
        }

        // Encriptar las claves antes de guardar
        $encryptedPk = Crypt::encryptString($validated['pk']);
        $encryptedSk = Crypt::encryptString($validated['sk']);
        $encryptedWhsec = $validated['whsec'] 
            ? Crypt::encryptString($validated['whsec']) 
            : null;

        $config = TenantStripeConfig::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'account_type' => 'direct', // direct o connect
                'pk_encrypted' => $encryptedPk,
                'sk_encrypted' => $encryptedSk,
                'whsec_encrypted' => $encryptedWhsec,
                'test_mode' => $validated['test_mode'] ?? false,
                'last_verified_at' => now()
            ]
        );

        Log::info('Stripe configuration saved', [
            'tenant_id' => $tenantId,
            'test_mode' => $validated['test_mode'] ?? false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configuración de Stripe guardada correctamente',
            'config' => [
                'id' => $config->id,
                'test_mode' => $config->test_mode,
                'last_verified_at' => $config->last_verified_at
            ]
        ]);
    }

    /**
     * Actualizar configuración existente
     */
    public function update(Request $request)
    {
        return $this->store($request);
    }

    /**
     * Validar credenciales sin guardarlas
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'sk' => 'required|string|max:255',
            'test_mode' => 'boolean'
        ]);

        $isValid = StripeServiceFactory::validateCredentials(
            $validated['sk'],
            $validated['test_mode'] ?? false
        );

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ], 400);
        }

        // Obtener información de la cuenta para mostrar
        try {
            $client = new \Stripe\StripeClient([
                'api_key' => $validated['sk'],
                'stripe_version' => '2024-06-20',
            ]);
            
            $account = $client->accounts->retrieve();
            
            return response()->json([
                'success' => true,
                'message' => 'Credenciales válidas',
                'account' => [
                    'id' => $account->id,
                    'business_type' => $account->business_type,
                    'country' => $account->country,
                    'charges_enabled' => $account->charges_enabled,
                    'payments_enabled' => $account->payments_enabled
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar configuración de Stripe
     */
    public function destroy()
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();

        if ($config) {
            $config->delete();
            
            Log::info('Stripe configuration deleted', ['tenant_id' => $tenantId]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuración de Stripe eliminada'
        ]);
    }
}
