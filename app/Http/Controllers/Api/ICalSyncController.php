<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ICalSync;
use App\Models\Property;
use App\Models\BookingRule;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ICalSyncController extends Controller
{
    /**
     * Obtener sincronizaciones iCal del tenant
     */
    public function index(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $query = ICalSync::whereHas('property', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $syncs = $query->with(['property'])->get();

        return response()->json([
            'success' => true,
            'syncs' => $syncs
        ]);
    }

    /**
     * Crear nueva sincronización iCal
     */
    public function store(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'property_id' => 'required|integer|exists:properties,id',
            'url_import' => 'required|url',
            'is_active' => 'boolean'
        ]);

        // Verificar que el property pertenece al tenant
        $property = Property::where('id', $validated['property_id'])
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $sync = ICalSync::create([
            'property_id' => $validated['property_id'],
            'url_import' => $validated['url_import'],
            'last_sync_hash' => null,
            'last_successful_sync' => null,
            'is_active' => $validated['is_active'] ?? true
        ]);

        // Ejecutar primera sincronización
        $this->syncNowInternal($sync);

        return response()->json([
            'success' => true,
            'message' => 'Sincronización iCal creada correctamente',
            'sync' => $sync->fresh()
        ], 201);
    }

    /**
     * Actualizar sincronización existente
     */
    public function update(Request $request, int $syncId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $sync = ICalSync::where('id', $syncId)
            ->whereHas('property', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'url_import' => 'sometimes|url',
            'is_active' => 'boolean'
        ]);

        if (isset($validated['url_import'])) {
            $sync->url_import = $validated['url_import'];
            $sync->last_sync_hash = null; // Resetear hash para forzar resync
        }

        if (isset($validated['is_active'])) {
            $sync->is_active = $validated['is_active'];
        }

        $sync->save();

        return response()->json([
            'success' => true,
            'message' => 'Sincronización actualizada correctamente',
            'sync' => $sync->fresh()
        ]);
    }

    /**
     * Eliminar sincronización
     */
    public function destroy(int $syncId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $sync = ICalSync::where('id', $syncId)
            ->whereHas('property', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->firstOrFail();

        $sync->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sincronización eliminada correctamente'
        ]);
    }

    /**
     * Ejecutar sincronización manual ahora
     */
    public function syncNow(int $syncId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $sync = ICalSync::where('id', $syncId)
            ->whereHas('property', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->firstOrFail();

        $result = $this->syncNowInternal($sync);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Sincronización completada',
                'events_imported' => $result['events_imported'],
                'events_updated' => $result['events_updated']
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error en la sincronización: ' . ($result['error'] ?? 'Desconocido')
            ], 500);
        }
    }

    /**
     * Lógica interna de sincronización
     */
    private function syncNowInternal(ICalSync $sync): array
    {
        try {
            // Descargar calendario iCal
            $response = Http::timeout(30)->get($sync->url_import);
            
            if (!$response->successful()) {
                Log::error('iCal download failed', [
                    'sync_id' => $sync->id,
                    'status' => $response->status()
                ]);
                
                return ['success' => false, 'error' => 'Failed to download calendar'];
            }

            $icalContent = $response->body();
            
            // Calcular hash para detectar cambios
            $currentHash = md5($icalContent);
            
            if ($currentHash === $sync->last_sync_hash) {
                Log::info('iCal content unchanged', ['sync_id' => $sync->id]);
                return ['success' => true, 'events_imported' => 0, 'events_updated' => 0];
            }

            // Parsear iCal (formato simplificado)
            $events = $this->parseICal($icalContent);
            
            $eventsImported = 0;
            $eventsUpdated = 0;

            foreach ($events as $event) {
                // Buscar si ya existe regla con este external_id
                $existingRule = BookingRule::where('property_id', $sync->property_id)
                    ->where('external_id', $event['uid'])
                    ->where('source', 'airbnb_ical')
                    ->first();

                if ($existingRule) {
                    // Actualizar regla existente
                    $existingRule->update([
                        'start_date' => $event['start'],
                        'end_date' => $event['end'],
                        'type' => $event['busy'] ? 'blocked' : 'available'
                    ]);
                    $eventsUpdated++;
                } else {
                    // Crear nueva regla
                    if ($event['busy']) {
                        BookingRule::create([
                            'tenant_id' => Property::find($sync->property_id)->tenant_id,
                            'property_id' => $sync->property_id,
                            'start_date' => $event['start'],
                            'end_date' => $event['end'],
                            'type' => 'reserved_external',
                            'source' => 'airbnb_ical',
                            'external_id' => $event['uid']
                        ]);
                        $eventsImported++;
                    }
                }
            }

            // Actualizar metadata de sincronización
            $sync->update([
                'last_sync_hash' => $currentHash,
                'last_successful_sync' => now()
            ]);

            Log::info('iCal sync completed', [
                'sync_id' => $sync->id,
                'imported' => $eventsImported,
                'updated' => $eventsUpdated
            ]);

            return [
                'success' => true,
                'events_imported' => $eventsImported,
                'events_updated' => $eventsUpdated
            ];

        } catch (\Exception $e) {
            Log::error('iCal sync error', [
                'sync_id' => $sync->id,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parsear contenido iCal (implementación simplificada)
     */
    private function parseICal(string $content): array
    {
        $events = [];
        $lines = explode("\n", $content);
        
        $currentEvent = null;
        $inVevent = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inVevent = true;
                $currentEvent = ['busy' => true];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($currentEvent && isset($currentEvent['start']) && isset($currentEvent['end'])) {
                    $events[] = $currentEvent;
                }
                $inVevent = false;
                $currentEvent = null;
                continue;
            }

            if ($inVevent && $currentEvent) {
                if (strpos($line, 'DTSTART') === 0) {
                    $date = str_replace(['DTSTART;', 'DTSTART:', 'VALUE=DATE:', '-'], ['', '', '', ''], $line);
                    $currentEvent['start'] = Carbon::parse($date)->toDateString();
                }

                if (strpos($line, 'DTEND') === 0) {
                    $date = str_replace(['DTEND;', 'DTEND:', 'VALUE=DATE:', '-'], ['', '', '', ''], $line);
                    $currentEvent['end'] = Carbon::parse($date)->toDateString();
                }

                if (strpos($line, 'UID:') === 0) {
                    $currentEvent['uid'] = str_replace('UID:', '', $line);
                }

                // Detectar si es evento de disponibilidad o bloqueo
                if (strpos($line, 'TRANSP:OPAQUE') !== false) {
                    $currentEvent['busy'] = true;
                }
            }
        }

        return $events;
    }

    /**
     * Obtener URL iCal exportable para este property (para Airbnb/Booking)
     */
    public function exportUrl(int $propertyId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $property = Property::where('id', $propertyId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Generar URL única con token
        $token = md5($property->id . '-' . $tenantId . '-' . config('app.key'));
        
        $exportUrl = route('api.ical.export', ['property' => $propertyId, 'token' => $token]);

        return response()->json([
            'success' => true,
            'export_url' => $exportUrl,
            'property_id' => $propertyId
        ]);
    }
}
