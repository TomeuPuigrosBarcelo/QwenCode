<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Si hay un tenant actual en la configuración del request
        if ($tenantId = config('tenancy.current_tenant_id')) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
            return;
        }

        // Si el usuario autenticado es SuperAdmin, no aplicar el scope
        // Esto permite al SuperAdmin ver todos los datos cuando usa "Login as"
        $user = Auth::guard('api')->user();
        
        if ($user && $user->is_super_admin) {
            // No aplicar restricción de tenant para SuperAdmin
            // Pero registrar en logs si es necesario
            return;
        }

        // Para usuarios normales, siempre filtrar por tenant_id del usuario
        if ($user && $user->tenant_id) {
            $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
        } else {
            // Si no hay usuario ni tenant, filtrar para que no devuelva nada
            $builder->whereRaw('1 = 0');
        }
    }
}
