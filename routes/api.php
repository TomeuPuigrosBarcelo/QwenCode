<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\SeasonalRateController;
use App\Http\Controllers\Api\BookingRuleController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\SuperAdminController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StripeConfigController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\ICalSyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas (sin autenticación ni tenant)
Route::prefix('v1')->group(function () {
    // Auth público
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Búsqueda pública de propiedades (para web del cliente)
    Route::get('/public/properties', [PropertyController::class, 'publicIndex']);
    Route::get('/public/properties/{property}', [PropertyController::class, 'publicShow']);
    Route::post('/public/bookings/availability', [BookingController::class, 'checkAvailability']);
    
    // Webhook de Stripe (público, identifica tenant por metadata)
    Route::post('/stripe/webhook', [WebhookController::class, 'handleStripe']);
});

// Rutas protegidas (requieren autenticación y tenant)
Route::middleware(['auth:sanctum', IdentifyTenant::class])->prefix('v1')->group(function () {
    
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Dashboard del propietario
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    
    // Propiedades
    Route::apiResource('properties', PropertyController::class);
    Route::post('/properties/{property}/images', [PropertyController::class, 'uploadImages']);
    Route::delete('/properties/{property}/images/{image}', [PropertyController::class, 'deleteImage']);
    Route::post('/properties/{property}/translate', [PropertyController::class, 'translateWithAI']);
    
    // Tarifas por temporada
    Route::get('/properties/{propertyId}/rates', [SeasonalRateController::class, 'index']);
    Route::post('/properties/rates', [SeasonalRateController::class, 'store']);
    Route::put('/rates/{rate}', [SeasonalRateController::class, 'update']);
    Route::delete('/rates/{rate}', [SeasonalRateController::class, 'destroy']);
    Route::get('/properties/{propertyId}/calendar', [SeasonalRateController::class, 'calendar']);
    
    // Reglas de bloqueo (Booking Rules)
    Route::get('/properties/{propertyId}/rules', [BookingRuleController::class, 'index']);
    Route::post('/booking-rules', [BookingRuleController::class, 'store']);
    Route::put('/booking-rules/{rule}', [BookingRuleController::class, 'update']);
    Route::delete('/booking-rules/{rule}', [BookingRuleController::class, 'destroy']);
    Route::post('/booking-rules/close-all', [BookingRuleController::class, 'closeAllProperties']);
    
    // Reservas
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/confirm-payment', [BookingController::class, 'confirmPayment']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/check-availability', [BookingController::class, 'checkAvailability']);
    
    // Pagos
    Route::get('/bookings/{bookingId}/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
    Route::post('/payments/{payment}/confirm-manual', [PaymentController::class, 'confirmManual']);
    Route::get('/payments/stats', [PaymentController::class, 'stats']);
    
    // Configuración de Stripe del tenant
    Route::get('/stripe/config', [StripeConfigController::class, 'show']);
    Route::post('/stripe/config', [StripeConfigController::class, 'store']);
    Route::put('/stripe/config', [StripeConfigController::class, 'update']);
    Route::post('/stripe/config/validate', [StripeConfigController::class, 'validate']);
    
    // Plantillas de email
    Route::get('/email-templates', [EmailTemplateController::class, 'index']);
    Route::get('/email-templates/{template}', [EmailTemplateController::class, 'show']);
    Route::post('/email-templates', [EmailTemplateController::class, 'store']);
    Route::put('/email-templates/{template}', [EmailTemplateController::class, 'update']);
    Route::post('/email-templates/{template}/translate', [EmailTemplateController::class, 'translateWithAI']);
    
    // Sincronización iCal
    Route::get('/ical-syncs', [ICalSyncController::class, 'index']);
    Route::post('/ical-syncs', [ICalSyncController::class, 'store']);
    Route::put('/ical-syncs/{sync}', [ICalSyncController::class, 'update']);
    Route::delete('/ical-syncs/{sync}', [ICalSyncController::class, 'destroy']);
    Route::post('/ical-syncs/{sync}/sync-now', [ICalSyncController::class, 'syncNow']);
    
    // Traducciones
    Route::get('/translations', [TranslationController::class, 'index']);
    Route::post('/translations', [TranslationController::class, 'store']);
    Route::put('/translations/{translation}', [TranslationController::class, 'update']);
    Route::delete('/translations/{translation}', [TranslationController::class, 'destroy']);
    Route::post('/translations/bulk-translate', [TranslationController::class, 'bulkTranslate']);
});

// Rutas SuperAdmin (solo para usuarios con is_super_admin = true)
Route::middleware(['auth:sanctum'])->prefix('v1/admin')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard']);
    Route::get('/tenants', [SuperAdminController::class, 'tenants']);
    Route::get('/tenants/{tenant}', [SuperAdminController::class, 'tenantDetail']);
    Route::post('/tenants/{tenant}/impersonate', [SuperAdminController::class, 'impersonate']);
    Route::post('/tenants/{tenant}/toggle-status', [SuperAdminController::class, 'toggleTenantStatus']);
    Route::get('/logs', [SuperAdminController::class, 'logs']);
    Route::get('/audit-logs', [SuperAdminController::class, 'auditLogs']);
    Route::get('/tenants/{tenant}/logs', [SuperAdminController::class, 'logs']);
    Route::get('/tenants/{tenant}/audit-logs', [SuperAdminController::class, 'auditLogs']);
});
