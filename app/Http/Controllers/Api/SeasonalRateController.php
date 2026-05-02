<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeasonalRate;
use App\Models\Property;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SeasonalRateController extends Controller
{
    /**
     * Obtener todas las tarifas de un property
     */
    public function index(Request $request, int $propertyId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        // Verificar que el property pertenece al tenant
        $property = Property::where('id', $propertyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $query = SeasonalRate::where('property_id', $propertyId)
            ->orderBy('start_date');

        if ($request->has('year')) {
            $year = $request->year;
            $query->whereYear('start_date', '<=', $year)
                  ->whereYear('end_date', '>=', $year);
        }

        $rates = $query->get();

        return response()->json([
            'success' => true,
            'rates' => $rates
        ]);
    }

    /**
     * Crear una nueva tarifa por temporada
     */
    public function store(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'price_per_night' => 'required|numeric|min:0',
            'min_stay_override' => 'nullable|integer|min:1'
        ]);

        // Verificar que el property pertenece al tenant
        $property = Property::where('id', $validated['property_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Verificar solapamientos con otras tarifas
        $overlapping = SeasonalRate::where('property_id', $validated['property_id'])
            ->where(function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->whereDate('start_date', '<=', $validated['start_date'])
                      ->whereDate('end_date', '>=', $validated['start_date']);
                })
                ->orWhere(function ($q) use ($validated) {
                    $q->whereDate('start_date', '<=', $validated['end_date'])
                      ->whereDate('end_date', '>=', $validated['end_date']);
                });
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una tarifa para este rango de fechas'
            ], 409);
        }

        $rate = SeasonalRate::create([
            'tenant_id' => $tenantId,
            'property_id' => $validated['property_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'price_per_night' => $validated['price_per_night'],
            'min_stay_override' => $validated['min_stay_override'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'rate' => $rate
        ], 201);
    }

    /**
     * Actualizar tarifa existente
     */
    public function update(Request $request, int $rateId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $rate = SeasonalRate::where('id', $rateId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'price_per_night' => 'sometimes|numeric|min:0',
            'min_stay_override' => 'nullable|integer|min:1'
        ]);

        // Si cambian las fechas, verificar solapamientos
        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $startDate = $validated['start_date'] ?? $rate->start_date;
            $endDate = $validated['end_date'] ?? $rate->end_date;

            $overlapping = SeasonalRate::where('property_id', $rate->property_id)
                ->where('id', '!=', $rateId)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->where(function ($q) use ($startDate, $endDate) {
                        $q->whereDate('start_date', '<=', $startDate)
                          ->whereDate('end_date', '>=', $startDate);
                    })
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->whereDate('start_date', '<=', $endDate)
                          ->whereDate('end_date', '>=', $endDate);
                    });
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'El nuevo rango se solapa con otra tarifa existente'
                ], 409);
            }
        }

        $rate->update($validated);

        return response()->json([
            'success' => true,
            'rate' => $rate->fresh()
        ]);
    }

    /**
     * Eliminar tarifa
     */
    public function destroy(int $rateId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $rate = SeasonalRate::where('id', $rateId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $rate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tarifa eliminada correctamente'
        ]);
    }

    /**
     * Obtener calendario de precios para un property
     */
    public function calendar(Request $request, int $propertyId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'year' => 'required|integer|min:2024|max:2030',
            'month' => 'required|integer|min:1|max:12'
        ]);

        $property = Property::where('id', $propertyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $calendar = \App\Services\PricingService::getCalendarMonth(
            $propertyId,
            $validated['year'],
            $validated['month']
        );

        return response()->json([
            'success' => true,
            'calendar' => $calendar
        ]);
    }
}
