<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Jobs\ProcessAiTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailTemplateController extends Controller
{
    /**
     * Obtener todas las plantillas del tenant
     */
    public function index(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $query = EmailTemplate::where('tenant_id', $tenantId);
        
        if ($request->has('key')) {
            $query->where('key', $request->key);
        }

        $templates = $query->with(['translations'])->get();

        // Incluir traducciones en la respuesta
        $templates->transform(function ($template) {
            $template->translations_by_locale = $template->translations->groupBy('locale')->map(function ($translations) {
                return $translations->pluck('value', 'field_key');
            });
            unset($template->translations);
            
            return $template;
        });

        return response()->json([
            'success' => true,
            'templates' => $templates
        ]);
    }

    /**
     * Obtener plantilla específica
     */
    public function show(int $templateId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $template = EmailTemplate::where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->with(['translations'])
            ->firstOrFail();

        $template->translations_by_locale = $template->translations->groupBy('locale')->map(function ($translations) {
            return $translations->pluck('value', 'field_key');
        });
        unset($template->translations);

        return response()->json([
            'success' => true,
            'template' => $template
        ]);
    }

    /**
     * Crear nueva plantilla
     */
    public function store(Request $request)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $validated = $request->validate([
            'key' => 'required|string|max:100|unique:email_templates,key,NULL,id,tenant_id,' . $tenantId,
            'subject_es' => 'required|string|max:255',
            'body_es' => 'required|string',
            'is_active' => 'boolean'
        ]);

        DB::beginTransaction();
        
        try {
            $template = EmailTemplate::create([
                'tenant_id' => $tenantId,
                'key' => $validated['key'],
                'is_active' => $validated['is_active'] ?? true
            ]);

            // Guardar traducción en español
            \App\Models\Translation::create([
                'tenant_id' => $tenantId,
                'entity_type' => 'email_template',
                'entity_id' => $template->id,
                'field_key' => 'subject',
                'locale' => 'es',
                'value' => $validated['subject_es'],
                'is_machine_translated' => false
            ]);

            \App\Models\Translation::create([
                'tenant_id' => $tenantId,
                'entity_type' => 'email_template',
                'entity_id' => $template->id,
                'field_key' => 'body',
                'locale' => 'es',
                'value' => $validated['body_es'],
                'is_machine_translated' => false
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plantilla creada correctamente',
                'template' => $template->fresh(['translations'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la plantilla: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar plantilla existente
     */
    public function update(Request $request, int $templateId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $template = EmailTemplate::where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'key' => 'sometimes|string|max:100|unique:email_templates,key,' . $templateId . ',id,tenant_id,' . $tenantId,
            'is_active' => 'boolean'
        ]);

        // Actualizar campos básicos
        if (isset($validated['key'])) {
            $template->key = $validated['key'];
        }
        
        if (isset($validated['is_active'])) {
            $template->is_active = $validated['is_active'];
        }

        $template->save();

        // Actualizar traducciones si se proporcionan
        if ($request->has('translations')) {
            foreach ($request->translations as $locale => $fields) {
                foreach ($fields as $fieldKey => $value) {
                    \App\Models\Translation::updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'entity_type' => 'email_template',
                            'entity_id' => $templateId,
                            'field_key' => $fieldKey,
                            'locale' => $locale
                        ],
                        [
                            'value' => $value,
                            'is_machine_translated' => false
                        ]
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Plantilla actualizada correctamente',
            'template' => $template->fresh(['translations'])
        ]);
    }

    /**
     * Traducir plantilla con IA
     */
    public function translateWithAI(Request $request, int $templateId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $template = EmailTemplate::where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $validated = $request->validate([
            'target_locales' => 'required|array',
            'target_locales.*' => 'string|in:en,fr,de,it,pt,nl,ca'
        ]);

        $jobsCreated = 0;

        // Obtener traducciones actuales en español (origen)
        $subjectEs = \App\Models\Translation::where('entity_type', 'email_template')
            ->where('entity_id', $templateId)
            ->where('field_key', 'subject')
            ->where('locale', 'es')
            ->value('value');

        $bodyEs = \App\Models\Translation::where('entity_type', 'email_template')
            ->where('entity_id', $templateId)
            ->where('field_key', 'body')
            ->where('locale', 'es')
            ->value('value');

        foreach ($validated['target_locales'] as $locale) {
            // Crear job para subject
            ProcessAiTranslation::dispatch(
                $tenantId,
                'email_template',
                $templateId,
                'subject',
                'es',
                $locale,
                $subjectEs
            );

            // Crear job para body
            ProcessAiTranslation::dispatch(
                $tenantId,
                'email_template',
                $templateId,
                'body',
                'es',
                $locale,
                $bodyEs
            );

            $jobsCreated += 2;
        }

        return response()->json([
            'success' => true,
            'message' => "Se han creado {$jobsCreated} trabajos de traducción",
            'jobs_created' => $jobsCreated
        ]);
    }

    /**
     * Eliminar plantilla
     */
    public function destroy(int $templateId)
    {
        $tenantId = config('tenancy.current_tenant_id');
        
        $template = EmailTemplate::where('id', $templateId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Eliminar traducciones asociadas
        \App\Models\Translation::where('entity_type', 'email_template')
            ->where('entity_id', $templateId)
            ->delete();

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plantilla eliminada correctamente'
        ]);
    }
}
