<?php

namespace App\Services\Stripe;

use App\Models\TenantStripeConfig;
use Stripe\StripeClient;
use Stripe\Webhook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Factory para crear instancias de Stripe con configuración dinámica por tenant
 * Implementa caché en Redis para evitar desencriptar claves en cada request
 */
class StripeServiceFactory
{
    /**
     * Obtener cliente Stripe configurado para un tenant
     */
    public function getClient(int $tenantId): ?StripeClient
    {
        return Cache::remember(
            "stripe.client.{$tenantId}",
            3600, // 1 hora de caché
            function () use ($tenantId) {
                $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();

                if (!$config || !$config->isValid()) {
                    return null;
                }

                return new StripeClient([
                    'api_key' => $config->secret_key,
                ]);
            }
        );
    }

    /**
     * Obtener clave pública para frontend
     */
    public function getPublicKey(int $tenantId): ?string
    {
        return Cache::remember(
            "stripe.pk.{$tenantId}",
            86400, // 24 horas
            function () use ($tenantId) {
                $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();
                return $config?->public_key;
            }
        );
    }

    /**
     * Crear Payment Intent para un tenant
     */
    public function createPaymentIntent(
        int $tenantId,
        float $amount,
        string $currency,
        array $metadata = []
    ): ?\Stripe\PaymentIntent {
        $client = $this->getClient($tenantId);

        if (!$client) {
            Log::error("Stripe client not found for tenant {$tenantId}");
            return null;
        }

        try {
            return $client->paymentIntents->create([
                'amount' => (int)($amount * 100), // Stripe usa céntimos
                'currency' => strtolower($currency),
                'automatic_payment_methods' => [
                    'enabled' => true, // Habilita tarjeta y Bizum automático si está disponible
                ],
                'metadata' => array_merge([
                    'tenant_id' => $tenantId, // CRUCIAL para webhooks
                ], $metadata),
            ]);
        } catch (\Exception $e) {
            Log::error("Stripe PaymentIntent creation failed: " . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'amount' => $amount,
            ]);
            throw $e;
        }
    }

    /**
     * Verificar firma de webhook
     */
    public function verifyWebhookSignature(
        int $tenantId,
        string $payload,
        string $sigHeader
    ): ?\Stripe\Event {
        $config = TenantStripeConfig::where('tenant_id', $tenantId)->first();

        if (!$config || !$config->webhook_secret) {
            Log::warning("Webhook secret not configured for tenant {$tenantId}");
            return null;
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $config->webhook_secret
            );

            // Verificar que el evento pertenece al tenant correcto
            if (isset($event->data->object->metadata->tenant_id)) {
                $eventTenantId = (int)$event->data->object->metadata->tenant_id;
                if ($eventTenantId !== $tenantId) {
                    Log::warning("Webhook tenant mismatch", [
                        'expected' => $tenantId,
                        'received' => $eventTenantId,
                    ]);
                    return null;
                }
            }

            return $event;
        } catch (\UnexpectedValueException $e) {
            Log::error("Invalid webhook payload", ['tenant_id' => $tenantId]);
            return null;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error("Invalid webhook signature", ['tenant_id' => $tenantId]);
            return null;
        }
    }

    /**
     * Procesar reembolso
     */
    public function refundPayment(int $tenantId, string $paymentIntentId, ?float $amount = null): bool
    {
        $client = $this->getClient($tenantId);

        if (!$client) {
            return false;
        }

        try {
            $params = ['payment_intent' => $paymentIntentId];
            
            if ($amount !== null) {
                $params['amount'] = (int)($amount * 100);
            }

            $client->refunds->create($params);
            return true;
        } catch (\Exception $e) {
            Log::error("Stripe refund failed: " . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'payment_intent' => $paymentIntentId,
            ]);
            return false;
        }
    }

    /**
     * Limpiar caché de configuración Stripe para un tenant
     */
    public function clearCache(int $tenantId): void
    {
        Cache::forget("stripe.client.{$tenantId}");
        Cache::forget("stripe.pk.{$tenantId}");
    }
}
