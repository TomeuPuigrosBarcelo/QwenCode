<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Multitenant Vacation Rental SaaS
|--------------------------------------------------------------------------
|
| Todas las rutas están protegidas por middleware de tenant identification
| excepto las de registro y login público.
|
*/

// Rutas públicas (sin autenticación)
Route::prefix('v1')->group(function () {
    // Auth público
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Web pública para clientes finales (reservas)
    Route::get('/properties/available', [PropertyController::class, 'searchAvailable']);
    Route::get('/properties/{id}/public', [PropertyController::class, 'publicShow']);
    Route::post('/bookings/public', [BookingController::class, 'publicStore']);
});

// Rutas protegidas (requieren autenticación)
Route::prefix('v1')->middleware(['auth:sanctum', 'identify.tenant', 'enforce.tenant.scope'])->group(function () {
    
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Propiedades
    Route::apiResource('properties', PropertyController::class);
    Route::post('/properties/{id}/images', [PropertyController::class, 'uploadImages']);
    Route::delete('/properties/{id}/images/{imageId}', [PropertyController::class, 'deleteImage']);
    
    // Tarifas por temporada
    Route::apiResource('properties.seasonal-rates', \App\Http\Controllers\Api\SeasonalRateController::class)
        ->shallow()
        ->only(['index', 'store', 'update', 'destroy']);
    
    // Reglas de reserva (bloqueos)
    Route::apiResource('properties.booking-rules', \App\Http\Controllers\Api\BookingRuleController::class)
        ->shallow()
        ->only(['index', 'store', 'update', 'destroy']);

    // Reservas
    Route::apiResource('bookings', BookingController::class)->only(['index', 'show', 'store']);
    Route::patch('/bookings/{id}/status', [BookingController::class, 'updateStatus']);
    Route::post('/bookings/{id}/payments', [\App\Http\Controllers\Api\PaymentController::class, 'processPayment']);
    Route::post('/bookings/{id}/refund', [\App\Http\Controllers\Api\PaymentController::class, 'processRefund']);

    // Pagos
    Route::get('/payments', [\App\Http\Controllers\Api\PaymentController::class, 'index']);
    Route::get('/payments/{id}', [\App\Http\Controllers\Api\PaymentController::class, 'show']);
    Route::post('/stripe/webhook', [\App\Http\Controllers\Api\WebhookController::class, 'handleStripe']);

    // Traducciones
    Route::get('/translations', [TranslationController::class, 'index']);
    Route::post('/translations/request-ai', [TranslationController::class, 'requestAiTranslation']);
    Route::patch('/translations/{id}', [TranslationController::class, 'update']);
    Route::get('/translation-jobs/{id}', [TranslationController::class, 'jobStatus']);

    // Plantillas de Email
    Route::apiResource('email-templates', \App\Http\Controllers\Api\EmailTemplateController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('/email-templates/{id}/preview', [\App\Http\Controllers\Api\EmailTemplateController::class, 'preview']);

    // Configuración de Stripe del tenant
    Route::get('/stripe/config', [\App\Http\Controllers\Api\StripeConfigController::class, 'show']);
    Route::post('/stripe/config', [\App\Http\Controllers\Api\StripeConfigController::class, 'store']);
    Route::post('/stripe/config/verify', [\App\Http\Controllers\Api\StripeConfigController::class, 'verify']);

    // Sync iCal
    Route::apiResource('ical-syncs', \App\Http\Controllers\Api\ICalSyncController::class)
        ->shallow()
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('/ical-syncs/{id}/sync-now', [\App\Http\Controllers\Api\ICalSyncController::class, 'triggerSync']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/revenue', [DashboardController::class, 'revenue']);
    Route::get('/dashboard/occupancy', [DashboardController::class, 'occupancy']);

    // Super Admin (solo usuarios con is_super_admin = true)
    Route::middleware(['super.admin'])->prefix('admin')->group(function () {
        Route::get('/tenants', [SuperAdminController::class, 'index']);
        Route::get('/tenants/{id}', [SuperAdminController::class, 'show']);
        Route::post('/tenants/{id}/impersonate', [SuperAdminController::class, 'impersonate']);
        Route::post('/tenants/{id}/suspend', [SuperAdminController::class, 'suspend']);
        Route::post('/tenants/{id}/activate', [SuperAdminController::class, 'activate']);
        Route::get('/system-logs', [SuperAdminController::class, 'systemLogs']);
        Route::get('/audit-logs', [SuperAdminController::class, 'auditLogs']);
        Route::post('/translations/{id}/save-global', [TranslationController::class, 'saveAsGlobal']);
    });
});

// Rutas de webhook (sin CSRF, sin auth, identifican tenant por metadata)
Route::post('/webhooks/stripe', [\App\Http\Controllers\Api\WebhookController::class, 'handleStripe'])
    ->withoutMiddleware(['auth:sanctum', 'identify.tenant']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'vacation-rental-saas-api',
    ]);
});
