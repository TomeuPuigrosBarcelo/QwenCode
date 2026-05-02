<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // 1. Intentar identificar por subdominio
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) >= 2) {
            $subdomain = $parts[0];
            
            // Ignorar www y dominios comunes que no son tenants
            if (!in_array($subdomain, ['www', 'api', 'app'])) {
                $tenant = Tenant::where('subdomain', $subdomain)
                    ->where('status', 'active')
                    ->first();
            }
        }

        // 2. Si no hay subdominio, intentar por dominio personalizado
        if (!$tenant) {
            $tenant = Tenant::where('custom_domain', $host)
                ->where('status', 'active')
                ->first();
        }

        // 3. Si es una ruta de API con tenant_id explícito (para SuperAdmin o tests)
        if (!$tenant && $request->has('tenant_id')) {
            $tenant = Tenant::find($request->input('tenant_id'));
        }

        if ($tenant) {
            // Establecer el tenant en el contenedor de la aplicación
            app()->instance('tenant', $tenant);
            
            // Configurar el scope global para este request
            config(['tenancy.current_tenant_id' => $tenant->id]);
        } else {
            // Permitir continuar para rutas públicas o SuperAdmin
            // El middleware de autenticación se encargará de validar si es necesario
            if (!$this->isPublicRoute($request)) {
                // Para rutas protegidas sin tenant, retornar error
                // excepto si es el SuperAdmin accediendo al panel global
                $user = $request->user();
                if (!$user || !$user->is_super_admin) {
                    return response()->json([
                        'message' => 'Tenant not found or inactive',
                        'error' => 'tenant_not_found'
                    ], 404);
                }
            }
        }

        return $next($request);
    }

    /**
     * Determinar si la ruta es pública y no requiere tenant
     */
    protected function isPublicRoute(Request $request): bool
    {
        $publicPaths = [
            'api/login',
            'api/register',
            'api/public/properties',
            'api/public/bookings/availability',
            'stripe/webhook'
        ];

        foreach ($publicPaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
