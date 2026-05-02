<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Tenant;

/**
 * Middleware que aplica automáticamente el scope de tenant a todas las consultas Eloquent
 * Esto previene el cruce accidental de datos entre tenants
 */
class EnforceTenantScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = App::instance('tenant');

        if ($tenant instanceof Tenant) {
            // Aplicar Global Scope dinámico para este request
            $this->applyTenantScope($tenant->id);
        }

        return $next($request);
    }

    /**
     * Aplicar scope de tenant a modelos específicos
     */
    protected function applyTenantScope(int $tenantId): void
    {
        // Lista de modelos que deben ser scopeados por tenant
        $models = [
            \App\Models\Property::class,
            \App\Models\Booking::class,
            \App\Models\Payment::class,
            \App\Models\SeasonalRate::class,
            \App\Models\BookingRule::class,
            \App\Models\PropertyImage::class,
            \App\Models\EmailTemplate::class,
            \App\Models\ICalSync::class,
            \App\Models\AuditLog::class,
            \App\Models\SystemLog::class,
            \App\Models\Translation::class,
            \App\Models\AiTranslationJob::class,
        ];

        foreach ($models as $model) {
            if (class_exists($model)) {
                $model::addGlobalScope('tenant', function (Builder $builder) use ($tenantId) {
                    $builder->where('tenant_id', $tenantId);
                });
            }
        }
    }
}
