<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Webhook;
use App\Models\TenantStripeConfig;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class StripeServiceFactory
{
    /**
     * Obtener cliente de Stripe configurado para un tenant específico
     */
    public static function getClientForTenant(int $tenantId): ?StripeClient
    {
        $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();
        
        if (!$config) {
            Log::warning("Stripe config not found for tenant {$tenantId}");
            return null;
        }

        try {
            // Desencriptar las claves
            $sk = Crypt::decryptString($config->sk_encrypted);
            
            return new StripeClient([
                'api_key' => $sk,
                'stripe_version' => '2024-06-20', // Versión más reciente estable
            ]);
        } catch (\Exception $e) {
            Log::error("Error creating Stripe client for tenant {$tenantId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener clave pública para el frontend
     */
    public static function getPublicKey(int $tenantId): ?string
    {
        $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();
        
        if (!$config) {
            return null;
        }

        try {
            return Crypt::decryptString($config->pk_encrypted);
        } catch (\Exception $e) {
            Log::error("Error decrypting public key for tenant {$tenantId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verificar firma de webhook
     */
    public static function verifyWebhookSignature(
        string $payload,
        string $sigHeader,
        int $tenantId
    ): \Stripe\Event|null {
        $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();
        
        if (!$config) {
            Log::warning("Stripe config not found for webhook verification, tenant {$tenantId}");
            return null;
        }

        try {
            $whsec = Crypt::decryptString($config->whsec_encrypted);
            
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $whsec
            );

            return $event;
        } catch (\Exception $e) {
            Log::error("Webhook signature verification failed for tenant {$tenantId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear Payment Intent para una reserva
     */
    public static function createPaymentIntent(
        int $tenantId,
        int $bookingId,
        int $amount,
        string $currency,
        array $metadata = []
    ): ?\Stripe\PaymentIntent {
        $client = self::getClientForTenant($tenantId);
        
        if (!$client) {
            return null;
        }

        // Añadir metadata esencial para el webhook
        $metadata['tenant_id'] = $tenantId;
        $metadata['booking_id'] = $bookingId;

        try {
            return $client->paymentIntents->create([
                'amount' => $amount, // En céntimos
                'currency' => strtolower($currency),
                'automatic_payment_methods' => [
                    'enabled' => true, // Habilita tarjeta y Bizum automático si está disponible
                ],
                'metadata' => $metadata,
                'description' => "Reserva #{$bookingId}",
            ]);
        } catch (\Exception $e) {
            Log::error("Error creating Payment Intent for booking {$bookingId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesar reembolso
     */
    public static function refundPayment(
        int $tenantId,
        string $paymentIntentId,
        ?int $amount = null // Null para reembolso completo
    ): bool {
        $client = self::getClientForTenant($tenantId);
        
        if (!$client) {
            return false;
        }

        try {
            $params = ['payment_intent' => $paymentIntentId];
            
            if ($amount !== null) {
                $params['amount'] = $amount;
            }

            $client->refunds->create($params);
            
            Log::info("Refund processed for payment {$paymentIntentId}, tenant {$tenantId}");
            return true;
        } catch (\Exception $e) {
            Log::error("Error processing refund for payment {$paymentIntentId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar credenciales de Stripe con la API
     */
    public static function validateCredentials(string $sk, bool $testMode): bool
    {
        try {
            $client = new StripeClient([
                'api_key' => $sk,
                'stripe_version' => '2024-06-20',
            ]);

            // Intentar obtener información de la cuenta
            $account = $client->accounts->retrieve();
            
            return $account !== null;
        } catch (\Exception $e) {
            Log::error("Stripe credentials validation failed: " . $e->getMessage());
            return false;
        }
    }
}
