<?php

namespace App\Jobs;

use App\Models\AiTranslationJob;
use App\Models\Translation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessAiTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AiTranslationJob $job
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Actualizar estado a processing
        $this->job->update([
            'status' => 'processing',
        ]);

        try {
            // Obtener contenido original
            $originalContent = $this->getOriginalContent();

            if (empty($originalContent)) {
                throw new \Exception('No se encontró contenido original para traducir');
            }

            $this->job->update([
                'original_content' => substr($originalContent, 0, 65535), // Limitar longitud
            ]);

            // Llamar a API de IA (ejemplo con OpenAI GPT-4o-mini)
            $translatedContent = $this->translateWithAi(
                $originalContent,
                $this->job->source_lang,
                $this->job->target_lang
            );

            // Guardar traducción en la base de datos
            $translation = Translation::create([
                'tenant_id' => $this->job->tenant_id,
                'entity_type' => $this->job->entity_type,
                'entity_id' => $this->job->entity_id,
                'field_key' => $this->job->field_key,
                'locale' => $this->job->target_lang,
                'value' => $translatedContent,
                'is_machine_translated' => true,
                'is_global' => false,
            ]);

            // Verificar si es candidato para pool global
            $this->checkForGlobalPool($translation, $originalContent);

            // Actualizar job como completado
            $this->job->update([
                'status' => 'completed',
                'translated_content' => substr($translatedContent, 0, 65535),
                'completed_at' => now(),
            ]);

            Log::info('Traducción IA completada', [
                'job_id' => $this->job->id,
                'tenant_id' => $this->job->tenant_id,
                'source_lang' => $this->job->source_lang,
                'target_lang' => $this->job->target_lang,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en traducción IA', [
                'job_id' => $this->job->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Reintentar si es error de API externo
            if ($this->attempts() < 3 && $this->isRetryableError($e)) {
                $this->release(60); // Reintentar en 60 segundos
                return;
            }

            // Marcar como fallido después de 3 intentos
            $this->job->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Obtener contenido original según el tipo de entidad
     */
    private function getOriginalContent(): string
    {
        return match($this->job->entity_type) {
            'property' => $this->getPropertyContent(),
            'email_template' => $this->getEmailTemplateContent(),
            'policy' => $this->getPolicyContent(),
            default => '',
        };
    }

    /**
     * Obtener contenido de propiedad
     */
    private function getPropertyContent(): string
    {
        $property = \App\Models\Property::find($this->job->entity_id);
        
        if (!$property) {
            return '';
        }

        // Buscar traducción en idioma origen
        $translation = Translation::where('entity_type', 'property')
            ->where('entity_id', $property->id)
            ->where('field_key', $this->job->field_key)
            ->where('locale', $this->job->source_lang)
            ->first();

        return $translation?->value ?? '';
    }

    /**
     * Obtener contenido de plantilla de email
     */
    private function getEmailTemplateContent(): string
    {
        $template = \App\Models\EmailTemplate::find($this->job->entity_id);
        
        if (!$template) {
            return '';
        }

        // Buscar traducción en idioma origen
        $translation = Translation::where('entity_type', 'email_template')
            ->where('entity_id', $template->id)
            ->where('field_key', $this->job->field_key)
            ->where('locale', $this->job->source_lang)
            ->first();

        return $translation?->value ?? '';
    }

    /**
     * Obtener contenido de política
     */
    private function getPolicyContent(): string
    {
        // Las políticas suelen estar en JSON, extraer el campo específico
        $property = \App\Models\Property::find($this->job->entity_id);
        
        if (!$property || !isset($property->policies_config[$this->job->field_key])) {
            return '';
        }

        return $property->policies_config[$this->job->field_key];
    }

    /**
     * Traducir usando API de IA
     */
    private function translateWithAi(string $text, string $sourceLang, string $targetLang): string
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            throw new \Exception('API key de OpenAI no configurada');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Eres un traductor profesional. Traduce el siguiente texto de {$sourceLang} a {$targetLang}. Mantén el formato, tono y estilo original. No añadas explicaciones, solo devuelve la traducción."
                ],
                [
                    'role' => 'user',
                    'content' => $text
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 2000,
        ]);

        if ($response->failed()) {
            throw new \Exception("Error en API de OpenAI: " . $response->body());
        }

        $data = $response->json();
        
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Verificar si la traducción es candidata para el pool global
     */
    private function checkForGlobalPool(Translation $translation, string $originalContent): void
    {
        // Solo textos estructurales cortos son candidatos
        $structuralFields = ['subject', 'body', 'cancellation_flexible', 'cancellation_moderate', 'cancellation_strict'];
        $structuralEntityTypes = ['email_template', 'policy'];

        if (!in_array($translation->entity_type, $structuralEntityTypes)) {
            return;
        }

        if (!in_array($translation->field_key, $structuralFields)) {
            return;
        }

        // Si es muy largo, probablemente sea personalizado
        if (strlen($translation->value) > 500) {
            return;
        }

        // Calcular hash del contenido
        $contentHash = md5(strtolower(trim($translation->value)));

        // Verificar si ya existe una traducción global similar
        $existingGlobal = Translation::whereNull('tenant_id')
            ->where('entity_type', $translation->entity_type)
            ->where('field_key', $translation->field_key)
            ->where('locale', $translation->locale)
            ->where('content_hash', $contentHash)
            ->first();

        if (!$existingGlobal) {
            // Crear entrada global
            Translation::create([
                'tenant_id' => null,
                'entity_type' => $translation->entity_type,
                'entity_id' => null,
                'field_key' => $translation->field_key,
                'locale' => $translation->locale,
                'value' => $translation->value,
                'is_machine_translated' => true,
                'is_global' => true,
                'content_hash' => $contentHash,
            ]);

            Log::info('Traducción añadida al pool global', [
                'translation_id' => $translation->id,
                'field_key' => $translation->field_key,
                'locale' => $translation->locale,
            ]);
        }
    }

    /**
     * Determinar si el error es reintentable
     */
    private function isRetryableError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        return str_contains($message, 'timeout') 
            || str_contains($message, 'rate limit')
            || str_contains($message, 'service unavailable')
            || str_contains($message, 'connection');
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Job de traducción falló permanentemente', [
            'job_id' => $this->job->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
