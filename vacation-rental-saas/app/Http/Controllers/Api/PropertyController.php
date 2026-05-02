<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Translation;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    /**
     * Listar propiedades del tenant actual
     */
    public function index(Request $request): JsonResponse
    {
        $query = Property::query()
            ->with(['images', 'translations'])
            ->where('tenant_id', $request->tenant->id);

        // Filtros opcionales
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        $properties = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json([
            'properties' => $properties,
        ]);
    }

    /**
     * Obtener detalle de una propiedad
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $property = Property::where('tenant_id', $request->tenant->id)
            ->with(['images', 'translations', 'seasonalRates', 'bookingRules', 'icalSyncs'])
            ->findOrFail($id);

        // Obtener traducciones en el locale solicitado (default: tenant default)
        $locale = $request->get('locale', $request->tenant->default_locale);
        $translations = $this->getPropertyTranslations($property, $locale);

        return response()->json([
            'property' => $property,
            'translations' => $translations,
            'current_locale' => $locale,
        ]);
    }

    /**
     * Crear nueva propiedad
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();

            // Crear propiedad
            $property = Property::create([
                'tenant_id' => $request->tenant->id,
                'name' => $validated['name'], // Nombre temporal, se puede traducir después
                'address' => $validated['address'],
                'google_maps_place_id' => $validated['google_maps_place_id'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'area_house_m2' => $validated['area_house_m2'] ?? null,
                'area_land_m2' => $validated['area_land_m2'] ?? null,
                'min_stay_default' => $validated['min_stay_default'] ?? 1,
                'check_in_time' => $validated['check_in_time'] ?? '15:00:00',
                'check_out_time' => $validated['check_out_time'] ?? '11:00:00',
                'policies_config' => $validated['policies_config'] ?? [],
                'is_active' => true,
            ]);

            // Guardar descripción y otras traducciones iniciales
            if (!empty($validated['description'])) {
                Translation::create([
                    'tenant_id' => $request->tenant->id,
                    'entity_type' => 'property',
                    'entity_id' => $property->id,
                    'field_key' => 'description',
                    'locale' => $validated['description_locale'] ?? $request->tenant->default_locale,
                    'value' => $validated['description'],
                    'is_machine_translated' => false,
                ]);
            }

            // Guardar nombre como traducción también (para multi-idioma)
            Translation::create([
                'tenant_id' => $request->tenant->id,
                'entity_type' => 'property',
                'entity_id' => $property->id,
                'field_key' => 'name',
                'locale' => $validated['name_locale'] ?? $request->tenant->default_locale,
                'value' => $validated['name'],
                'is_machine_translated' => false,
            ]);

            // Procesar imágenes si existen
            if (!empty($validated['images'])) {
                foreach ($validated['images'] as $index => $image) {
                    PropertyImage::create([
                        'property_id' => $property->id,
                        'url' => $image['url'],
                        'sort_order' => $index,
                        'alt_text_key' => "property_{$property->id}_image_{$index}",
                    ]);
                }
            }

            DB::commit();

            $property->load(['images', 'translations']);

            return response()->json([
                'message' => 'Propiedad creada exitosamente',
                'property' => $property,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creando propiedad', [
                'error' => $e->getMessage(),
                'tenant_id' => $request->tenant->id,
            ]);

            return response()->json([
                'message' => 'Error al crear la propiedad',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Actualizar propiedad existente
     */
    public function update(UpdatePropertyRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $property = Property::where('tenant_id', $request->tenant->id)->findOrFail($id);
            $validated = $request->validated();

            // Actualizar campos principales
            $property->update([
                'address' => $validated['address'] ?? $property->address,
                'google_maps_place_id' => $validated['google_maps_place_id'] ?? $property->google_maps_place_id,
                'latitude' => $validated['latitude'] ?? $property->latitude,
                'longitude' => $validated['longitude'] ?? $property->longitude,
                'area_house_m2' => $validated['area_house_m2'] ?? $property->area_house_m2,
                'area_land_m2' => $validated['area_land_m2'] ?? $property->area_land_m2,
                'min_stay_default' => $validated['min_stay_default'] ?? $property->min_stay_default,
                'check_in_time' => $validated['check_in_time'] ?? $property->check_in_time,
                'check_out_time' => $validated['check_out_time'] ?? $property->check_out_time,
                'policies_config' => $validated['policies_config'] ?? $property->policies_config,
                'is_active' => $validated['is_active'] ?? $property->is_active,
            ]);

            // Actualizar traducciones
            if (!empty($validated['description'])) {
                Translation::updateOrCreate(
                    [
                        'tenant_id' => $request->tenant->id,
                        'entity_type' => 'property',
                        'entity_id' => $property->id,
                        'field_key' => 'description',
                        'locale' => $validated['description_locale'] ?? $request->tenant->default_locale,
                    ],
                    [
                        'value' => $validated['description'],
                        'is_machine_translated' => false,
                    ]
                );
            }

            if (!empty($validated['name'])) {
                Translation::updateOrCreate(
                    [
                        'tenant_id' => $request->tenant->id,
                        'entity_type' => 'property',
                        'entity_id' => $property->id,
                        'field_key' => 'name',
                        'locale' => $validated['name_locale'] ?? $request->tenant->default_locale,
                    ],
                    [
                        'value' => $validated['name'],
                        'is_machine_translated' => false,
                    ]
                );
            }

            // Procesar imágenes si se envían nuevas
            if (!empty($validated['images'])) {
                // Eliminar imágenes existentes (simplificado, en prod usar lógica más compleja)
                $property->images()->delete();

                foreach ($validated['images'] as $index => $image) {
                    PropertyImage::create([
                        'property_id' => $property->id,
                        'url' => $image['url'],
                        'sort_order' => $index,
                        'alt_text_key' => "property_{$property->id}_image_{$index}",
                    ]);
                }
            }

            DB::commit();

            $property->load(['images', 'translations']);

            return response()->json([
                'message' => 'Propiedad actualizada exitosamente',
                'property' => $property,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error actualizando propiedad', [
                'error' => $e->getMessage(),
                'tenant_id' => $request->tenant->id,
            ]);

            return response()->json([
                'message' => 'Error al actualizar la propiedad',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Eliminar propiedad (soft delete o hard delete según configuración)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $property = Property::where('tenant_id', $request->tenant->id)->findOrFail($id);

        // Verificar si tiene reservas activas
        $hasActiveBookings = $property->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'message' => 'No se puede eliminar una propiedad con reservas activas',
            ], 400);
        }

        // En lugar de eliminar, desactivamos
        $property->update(['is_active' => false]);

        return response()->json([
            'message' => 'Propiedad desactivada exitosamente',
        ]);
    }

    /**
     * Obtener traducciones de una propiedad para un locale específico
     */
    private function getPropertyTranslations(Property $property, string $locale): array
    {
        $translations = [];
        
        $translationRecords = $property->translations()
            ->whereIn('field_key', ['name', 'description'])
            ->where('locale', $locale)
            ->get();

        foreach ($translationRecords as $translation) {
            $translations[$translation->field_key] = $translation->value;
        }

        // Si no hay traducciones en el locale solicitado, fallback al default del tenant
        if (empty($translations)) {
            $defaultLocale = $property->tenant->default_locale;
            $defaultTranslations = $property->translations()
                ->whereIn('field_key', ['name', 'description'])
                ->where('locale', $defaultLocale)
                ->get();

            foreach ($defaultTranslations as $translation) {
                $translations[$translation->field_key] = $translation->value;
            }
        }

        return $translations;
    }
}
