<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Jobs\ProcessAiTranslation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TranslationController extends Controller
{
    /**
     * Obtener traducciones para una entidad específica
     */
    public function getTranslations(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        
        $validated = $request->validate([
            'entity_type' => 'required|in:property,email_template,policy,image_alt',
            'entity_id' => 'required|integer',
            'locales' => 'nullable|array', // ej: ['es', 'en', 'fr']
        ]);

        $query = Translation::where('tenant_id', $tenantId)
            ->where('entity_type', $validated['entity_type'])
            ->where('entity_id', $validated['entity_id']);

        if (!empty($validated['locales'])) {
            $query->whereIn('locale', $validated['locales']);
        }

        $translations = $query->get()->groupBy('locale');

        return response()->json($translations);
    }

    /**
     * Solicitar traducción con IA (Async Job)
     */
    public function requestAiTranslation(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        $validated = $request->validate([
            'entity_type' => 'required|in:property,email_template,policy,image_alt',
            'entity_id' => 'required|integer',
            'field_key' => 'required|string',
            'source_lang' => 'required|string|size:2',
            'target_lang' => 'required|string|size:2',
            'content' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // 1. Verificar si ya existe traducción global compartida (POOL GLOBAL)
            $globalTranslation = Translation::whereNull('tenant_id') // Global = null tenant_id
                ->where('entity_type', 'structural') // Tipo especial para textos compartibles
                ->where('field_key', 'content_hash_' . md5($validated['content']))
                ->where('locale', $validated['target_lang'])
                ->first();

            if ($globalTranslation) {
                // Reutilizar traducción global existente
                Translation::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'entity_type' => $validated['entity_type'],
                        'entity_id' => $validated['entity_id'],
                        'field_key' => $validated['field_key'],
                        'locale' => $validated['target_lang'],
                    ],
                    [
                        'value' => $globalTranslation->value,
                        'is_machine_translated' => true,
                    ]
                );

                DB::commit();

                return response()->json([
                    'message' => 'Traducción obtenida del pool global',
                    'translation' => $globalTranslation->value,
                    'from_global' => true
                ]);
            }

            // 2. Verificar si ya existe una traducción previa para este mismo texto en el tenant
            $existingTranslation = Translation::where('tenant_id', $tenantId)
                ->where('entity_type', $validated['entity_type'])
                ->where('entity_id', $validated['entity_id'])
                ->where('field_key', $validated['field_key'])
                ->where('locale', $validated['target_lang'])
                ->first();

            if ($existingTranslation) {
                DB::commit();
                return response()->json([
                    'message' => 'Traducción ya existente',
                    'translation' => $existingTranslation->value,
                    'from_global' => false
                ]);
            }

            // 3. Crear job de traducción asíncrona
            $job = ProcessAiTranslation::dispatch(
                $tenantId,
                $validated['entity_type'],
                $validated['entity_id'],
                $validated['field_key'],
                $validated['source_lang'],
                $validated['target_lang'],
                $validated['content']
            );

            DB::commit();

            return response()->json([
                'message' => 'Traducción solicitada. Se procesará en segundo plano.',
                'job_id' => $job->getJobId(),
                'status' => 'pending'
            ], 202);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al solicitar traducción: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Guardar traducción manual (editada por usuario)
     */
    public function saveManualTranslation(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        $validated = $request->validate([
            'entity_type' => 'required|in:property,email_template,policy,image_alt',
            'entity_id' => 'required|integer',
            'field_key' => 'required|string',
            'locale' => 'required|string|size:2',
            'value' => 'required|string',
        ]);

        $translation = Translation::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'entity_type' => $validated['entity_type'],
                'entity_id' => $validated['entity_id'],
                'field_key' => $validated['field_key'],
                'locale' => $validated['locale'],
            ],
            [
                'value' => $validated['value'],
                'is_machine_translated' => false, // Marcamos como manual
            ]
        );

        return response()->json([
            'message' => 'Traducción guardada exitosamente',
            'translation' => $translation
        ]);
    }

    /**
     * Obtener estadísticas de uso de IA para el tenant
     */
    public function stats(): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        $totalTranslations = Translation::where('tenant_id', $tenantId)
            ->where('is_machine_translated', true)
            ->count();

        $manualTranslations = Translation::where('tenant_id', $tenantId)
            ->where('is_machine_translated', false)
            ->count();

        $globalReuse = Translation::where('tenant_id', $tenantId)
            ->where('is_machine_translated', true)
            // Asumimos que podríamos trackear cuáles vinieron del pool global
            ->count(); // Simplificado

        return response()->json([
            'total_machine_translations' => $totalTranslations,
            'manual_translations' => $manualTranslations,
            'estimated_api_calls_saved' => $globalReuse, // Traducciones reutilizadas del pool
        ]);
    }
}
