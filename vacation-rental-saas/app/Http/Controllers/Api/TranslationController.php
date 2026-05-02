<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Models\AiTranslationJob;
use App\Jobs\ProcessAiTranslation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TranslationController extends Controller
{
    /**
     * Obtener traducciones para una entidad específica
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'in:property,email_template,booking,policy,image_alt'],
            'entity_id' => ['required', 'integer'],
        ]);

        $translations = Translation::where('tenant_id', $request->tenant->id)
            ->where('entity_type', $validated['entity_type'])
            ->where('entity_id', $validated['entity_id'])
            ->get()
            ->groupBy('locale');

        return response()->json([
            'translations' => $translations,
        ]);
    }

    /**
     * Solicitar traducción con IA
     */
    public function requestAiTranslation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'in:property,email_template,booking,policy,image_alt'],
            'entity_id' => ['required', 'integer'],
            'field_key' => ['required', 'string', 'max:100'],
            'source_lang' => ['required', 'string', 'size:2'],
            'target_langs' => ['required', 'array', 'min:1'],
            'target_langs.*' => ['string', 'size:2'],
        ]);

        // Verificar si ya existe traducción global compartida
        $globalTranslations = $this->findGlobalTranslations(
            $validated['entity_type'],
            $validated['field_key'],
            $validated['source_lang'],
            $validated['target_langs']
        );

        DB::beginTransaction();
        
        try {
            $jobsCreated = [];
            $existingTranslations = [];

            foreach ($validated['target_langs'] as $targetLang) {
                // Si existe traducción global, usarla
                if (isset($globalTranslations[$targetLang])) {
                    $translation = Translation::updateOrCreate(
                        [
                            'tenant_id' => $request->tenant->id,
                            'entity_type' => $validated['entity_type'],
                            'entity_id' => $validated['entity_id'],
                            'field_key' => $validated['field_key'],
                            'locale' => $targetLang,
                        ],
                        [
                            'value' => $globalTranslations[$targetLang],
                            'is_machine_translated' => true,
                            'is_global' => false, // Copia local de global
                        ]
                    );

                    $existingTranslations[] = [
                        'locale' => $targetLang,
                        'value' => $globalTranslations[$targetLang],
                        'from_global' => true,
                    ];

                    continue;
                }

                // Crear job de traducción
                $job = AiTranslationJob::create([
                    'tenant_id' => $request->tenant->id,
                    'entity_type' => $validated['entity_type'],
                    'entity_id' => $validated['entity_id'],
                    'field_key' => $validated['field_key'],
                    'source_lang' => $validated['source_lang'],
                    'target_lang' => $targetLang,
                    'status' => 'pending',
                ]);

                $jobsCreated[] = $job;

                // Dispatch del Job a la cola
                ProcessAiTranslation::dispatch($job);
            }

            DB::commit();

            return response()->json([
                'message' => 'Traducciones solicitadas',
                'jobs' => $jobsCreated,
                'existing_from_global' => $existingTranslations,
                'note' => count($jobsCreated) > 0 
                    ? 'Las traducciones se procesarán en segundo plano' 
                    : 'Todas las traducciones ya existían en el pool global',
            ], count($jobsCreated) > 0 ? 202 : 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error solicitando traducción IA', [
                'error' => $e->getMessage(),
                'tenant_id' => $request->tenant->id,
            ]);

            return response()->json([
                'message' => 'Error al solicitar traducciones',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Buscar traducciones globales compartidas
     */
    private function findGlobalTranslations(
        string $entityType,
        string $fieldKey,
        string $sourceLang,
        array $targetLangs
    ): array {
        // Solo aplicar pool global para textos estructurales (no descripciones personalizadas)
        $structuralFields = [
            'email_template' => ['subject', 'body'],
            'policy' => ['cancellation_flexible', 'cancellation_moderate', 'cancellation_strict', 'house_rules_*'],
            'amenity' => ['name', 'description'],
        ];

        $isStructural = false;
        
        if ($entityType === 'email_template' && in_array($fieldKey, $structuralFields['email_template'])) {
            $isStructural = true;
        }
        
        if ($entityType === 'policy' && str_starts_with($fieldKey, 'house_rules_')) {
            $isStructural = true;
        }

        if (!$isStructural) {
            return [];
        }

        // Buscar en el pool global (tenant_id = null o is_global = true)
        $globalTranslations = Translation::whereNull('tenant_id')
            ->orWhere('is_global', true)
            ->where('entity_type', $entityType)
            ->where('field_key', $fieldKey)
            ->whereIn('locale', $targetLangs)
            ->get();

        // Agrupar por locale y filtrar solo los que necesitamos
        $result = [];
        foreach ($globalTranslations as $translation) {
            if (in_array($translation->locale, $targetLangs)) {
                $result[$translation->locale] = $translation->value;
            }
        }

        return $result;
    }

    /**
     * Guardar traducción global compartida (solo SuperAdmin o cuando se detecta patrón común)
     */
    public function saveAsGlobal(Request $request): JsonResponse
    {
        // Solo SuperAdmin puede marcar traducciones como globales
        if (!$request->user()->is_super_admin) {
            return response()->json([
                'message' => 'No autorizado',
            ], 403);
        }

        $validated = $request->validate([
            'translation_id' => ['required', 'integer', 'exists:translations,id'],
        ]);

        $translation = Translation::findOrFail($validated['translation_id']);

        // Crear copia global
        $globalTranslation = Translation::create([
            'tenant_id' => null, // Global
            'entity_type' => $translation->entity_type,
            'entity_id' => null, // No asociado a entidad específica
            'field_key' => $translation->field_key,
            'locale' => $translation->locale,
            'value' => $translation->value,
            'is_machine_translated' => $translation->is_machine_translated,
            'is_global' => true,
            'content_hash' => md5(strtolower(trim($translation->value))),
        ]);

        return response()->json([
            'message' => 'Traducción guardada como global',
            'global_translation' => $globalTranslation,
        ]);
    }

    /**
     * Actualizar traducción manualmente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string'],
        ]);

        $translation = Translation::where('tenant_id', $request->tenant->id)
            ->findOrFail($id);

        $translation->update([
            'value' => $validated['value'],
            'is_machine_translated' => false, // Marcamos como editada manualmente
        ]);

        return response()->json([
            'message' => 'Traducción actualizada',
            'translation' => $translation,
        ]);
    }

    /**
     * Obtener estado de jobs de traducción
     */
    public function jobStatus(Request $request, int $jobId): JsonResponse
    {
        $job = AiTranslationJob::where('tenant_id', $request->tenant->id)
            ->findOrFail($jobId);

        return response()->json([
            'job' => $job,
        ]);
    }
}
