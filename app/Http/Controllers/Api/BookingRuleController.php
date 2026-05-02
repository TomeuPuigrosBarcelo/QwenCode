<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingRule;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BookingRuleController extends Controller
{
    /**
     * Obtener reglas de bloqueo para un property
     */
    public function index(Request $request, int $propertyId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $property = Property::where('id', $propertyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $query = BookingRule::where('property_id', $propertyId)
            ->orderByDesc('start_date');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $rules = $query->get();

        return response()->json([
            'success' => true,
            'rules' => $rules
        ]);
    }

    /**
     * Crear regla de bloqueo (cierre manual de fechas)
     */
    public function store(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'property_id' => 'nullable|integer|exists:properties,id', // Null para global
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|in:blocked,maintenance,reserved_external',
            'source' => 'nullable|in:manual,airbnb_ical,booking_xml',
            'external_id' => 'nullable|string|max:255'
        ]);

        // Si hay property_id, verificar que pertenece al tenant
        if (isset($validated['property_id'])) {
            $property = Property::where('id', $validated['property_id'])
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        }

        // Verificar solapamientos con otras reglas
        $overlapping = BookingRule::where(function ($query) use ($validated) {
            if (isset($validated['property_id'])) {
                $query->where('property_id', $validated['property_id']);
            } else {
                $query->whereNull('property_id'); // Regla global
            }
            
            $query->where(function ($q) use ($validated) {
                $q->whereDate('start_date', '<=', $validated['end_date'])
                  ->whereDate('end_date', '>=', $validated['start_date']);
            });
        })
        ->exists();

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una regla de bloqueo para este rango de fechas'
            ], 409);
        }

        $rule = BookingRule::create([
            'tenant_id' => $tenantId,
            'property_id' => $validated['property_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'type' => $validated['type'],
            'source' => $validated['source'] ?? 'manual',
            'external_id' => $validated['external_id'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'rule' => $rule
        ], 201);
    }

    /**
     * Actualizar regla existente
     */
    public function update(Request $request, int $ruleId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $rule = BookingRule::where('id', $ruleId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'type' => 'sometimes|in:blocked,maintenance,reserved_external',
            'source' => 'sometimes|in:manual,airbnb_ical,booking_xml',
            'external_id' => 'nullable|string|max:255'
        ]);

        // Si cambian las fechas, verificar solapamientos
        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $startDate = $validated['start_date'] ?? $rule->start_date;
            $endDate = $validated['end_date'] ?? $rule->end_date;

            $overlapping = BookingRule::where('id', '!=', $ruleId)
                ->where(function ($query) use ($rule, $startDate, $endDate) {
                    if ($rule->property_id) {
                        $query->where('property_id', $rule->property_id);
                    } else {
                        $query->whereNull('property_id');
                    }
                    
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereDate('start_date', '<=', $endDate)
                          ->whereDate('end_date', '>=', $startDate);
                    });
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'El nuevo rango se solapa con otra regla existente'
                ], 409);
            }
        }

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'rule' => $rule->fresh()
        ]);
    }

    /**
     * Eliminar regla de bloqueo
     */
    public function destroy(int $ruleId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $rule = BookingRule::where('id', $ruleId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Regla de bloqueo eliminada correctamente'
        ]);
    }

    /**
     * Cerrar todos los properties del tenant para un rango de fechas
     */
    public function closeAllProperties(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|in:blocked,maintenance',
            'reason' => 'nullable|string|max:500'
        ]);

        $properties = Property::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $createdRules = [];

        foreach ($properties as $property) {
            // Verificar si ya existe regla para este property
            $exists = BookingRule::where('property_id', $property->id)
                ->where(function ($query) use ($validated) {
                    $query->whereDate('start_date', '<=', $validated['end_date'])
                          ->whereDate('end_date', '>=', $validated['start_date']);
                })
                ->exists();

            if (!$exists) {
                $rule = BookingRule::create([
                    'tenant_id' => $tenantId,
                    'property_id' => $property->id,
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'type' => $validated['type'] ?? 'blocked',
                    'source' => 'manual',
                    'external_id' => null
                ]);
                
                $createdRules[] = $rule;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Se han cerrado {$properties->count()} properties",
            'rules_created' => count($createdRules),
            'rules' => $createdRules
        ]);
    }
}
