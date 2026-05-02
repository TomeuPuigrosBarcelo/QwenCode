<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    /**
     * Listar propiedades del tenant actual
     */
    public function index(): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $properties = Property::where('tenant_id', $tenantId)
            ->with(['images', 'translations'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($properties);
    }

    /**
     * Obtener detalle de una propiedad con traducciones
     */
    public function show($id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $property = Property::where('tenant_id', $tenantId)
            ->where('id', $id)
            ->with(['images', 'translations'])
            ->firstOrFail();

        return response()->json($property);
    }

    /**
     * Crear nueva propiedad
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'required|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'area_house_m2' => 'nullable|numeric',
                'area_land_m2' => 'nullable|numeric',
                'min_stay_default' => 'integer|min:1',
                'check_in_time' => 'date_format:H:i',
                'check_out_time' => 'date_format:H:i',
                'policies_config' => 'nullable|array',
                'images' => 'nullable|array',
                'locale' => 'required|string|size:2', // ej: 'es', 'en'
            ]);

            // Crear propiedad (sin textos, van a translations)
            $property = Property::create([
                'tenant_id' => $tenantId,
                'reference_code' => 'PROP-' . strtoupper(uniqid()),
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'area_house_m2' => $validated['area_house_m2'] ?? null,
                'area_land_m2' => $validated['area_land_m2'] ?? null,
                'min_stay_default' => $validated['min_stay_default'] ?? 1,
                'check_in_time' => $validated['check_in_time'] ?? '15:00',
                'check_out_time' => $validated['check_out_time'] ?? '11:00',
                'policies_config' => $validated['policies_config'] ?? [],
                'is_active' => true,
            ]);

            // Guardar traducciones iniciales
            if (!empty($validated['name'])) {
                Translation::create([
                    'tenant_id' => $tenantId,
                    'entity_type' => 'property',
                    'entity_id' => $property->id,
                    'field_key' => 'name',
                    'locale' => $validated['locale'],
                    'value' => $validated['name'],
                    'is_machine_translated' => false,
                ]);
            }

            if (!empty($validated['description'])) {
                Translation::create([
                    'tenant_id' => $tenantId,
                    'entity_type' => 'property',
                    'entity_id' => $property->id,
                    'field_key' => 'description',
                    'locale' => $validated['locale'],
                    'value' => $validated['description'],
                    'is_machine_translated' => false,
                ]);
            }

            // Guardar imágenes si existen
            if (!empty($validated['images'])) {
                foreach ($validated['images'] as $index => $image) {
                    $property->images()->create([
                        'url' => $image['url'],
                        'sort_order' => $index,
                        'alt_text_key' => $image['alt_text_key'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Propiedad creada exitosamente',
                'property' => $property->load(['translations', 'images'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear propiedad: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar propiedad
     */
    public function update(Request $request, $id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        DB::beginTransaction();
        try {
            $property = Property::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();
            
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|nullable|string',
                'address' => 'sometimes|required|string',
                'latitude' => 'sometimes|nullable|numeric',
                'longitude' => 'sometimes|nullable|numeric',
                'area_house_m2' => 'sometimes|nullable|numeric',
                'area_land_m2' => 'sometimes|nullable|numeric',
                'min_stay_default' => 'sometimes|integer|min:1',
                'check_in_time' => 'sometimes|date_format:H:i',
                'check_out_time' => 'sometimes|date_format:H:i',
                'policies_config' => 'sometimes|nullable|array',
                'is_active' => 'sometimes|boolean',
                'locale' => 'required|string|size:2',
            ]);

            // Actualizar campos no traducidos
            $updateFields = ['address', 'latitude', 'longitude', 'area_house_m2', 'area_land_m2', 
                           'min_stay_default', 'check_in_time', 'check_out_time', 'policies_config', 'is_active'];
            
            foreach ($updateFields as $field) {
                if (isset($validated[$field])) {
                    $property->$field = $validated[$field];
                }
            }
            $property->save();

            // Actualizar/Crear traducciones
            if (isset($validated['name'])) {
                Translation::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'entity_type' => 'property',
                        'entity_id' => $property->id,
                        'field_key' => 'name',
                        'locale' => $validated['locale'],
                    ],
                    [
                        'value' => $validated['name'],
                        'is_machine_translated' => false,
                    ]
                );
            }

            if (isset($validated['description'])) {
                Translation::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'entity_type' => 'property',
                        'entity_id' => $property->id,
                        'field_key' => 'description',
                        'locale' => $validated['locale'],
                    ],
                    [
                        'value' => $validated['description'],
                        'is_machine_translated' => false,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'message' => 'Propiedad actualizada exitosamente',
                'property' => $property->fresh(['translations', 'images'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar propiedad: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar propiedad
     */
    public function destroy($id): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $property = Property::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();
        
        // Soft delete o hard delete según preferencia
        $property->delete();

        return response()->json(['message' => 'Propiedad eliminada correctamente']);
    }

    /**
     * Obtener traducciones de una propiedad para un locale específico
     */
    public function translations($id, $locale): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $property = Property::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();
        
        $translations = Translation::where('tenant_id', $tenantId)
            ->where('entity_type', 'property')
            ->where('entity_id', $property->id)
            ->where('locale', $locale)
            ->get()
            ->pluck('value', 'field_key');

        return response()->json($translations);
    }
}
