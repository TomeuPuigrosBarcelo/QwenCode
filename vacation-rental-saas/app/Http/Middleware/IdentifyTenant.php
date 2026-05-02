<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

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
        $subdomain = $this->getSubdomain($request);
        if ($subdomain) {
            $tenant = Tenant::where('subdomain', $subdomain)
                ->where('status', '!=', 'suspended')
                ->first();
        }

        // 2. Si no, intentar por dominio personalizado
        if (!$tenant) {
            $host = $request->getHost();
            $tenant = Tenant::where('custom_domain', $host)
                ->where('status', '!=', 'suspended')
                ->first();
        }

        // 3. Si no hay tenant, verificar si es ruta de SuperAdmin o API global
        if (!$tenant) {
            // Permitir acceso a rutas globales (login, registro, panel superadmin)
            if ($this->isGlobalRoute($request)) {
                return $next($request);
            }

            // Si es una ruta que requiere tenant y no se encontró, retornar 404 o error
            abort(404, 'Tenant not found');
        }

        // 4. Establecer tenant en el contenedor de la aplicación
        App::instance('tenant', $tenant);
        
        // 5. Configurar locale del tenant
        App::setLocale($tenant->default_locale);

        // 6. Compartir tenant con todas las vistas
        view()->share('tenant', $tenant);

        // 7. Agregar tenant al contexto de logging
        config(['logging.tenant_id' => $tenant->id]);

        return $next($request);
    }

    /**
     * Obtener subdominio desde la request
     */
    protected function getSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Si hay más de 2 partes (ej: tenant.dominio.com), el subdominio es la primera
        if (count($parts) > 2) {
            return $parts[0];
        }

        return null;
    }

    /**
     * Verificar si la ruta es global (no requiere tenant)
     */
    protected function isGlobalRoute(Request $request): bool
    {
        $globalPaths = [
            'api/login',
            'api/register',
            'api/forgot-password',
            'api/reset-password',
            'admin/*', // Panel de SuperAdmin
            '_ignition/*',
            'sanctum/csrf-cookie',
        ];

        $path = trim($request->path(), '/');

        foreach ($globalPaths as $globalPath) {
            $globalPath = trim($globalPath, '/');
            
            if (str_ends_with($globalPath, '*')) {
                $prefix = rtrim($globalPath, '*');
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            } else {
                if ($path === $globalPath) {
                    return true;
                }
            }
        }

        return false;
    }
}
