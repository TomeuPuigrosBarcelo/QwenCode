<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Translation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ProcessAiTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10; // segundos entre reintentos

    protected int $tenantId;
    protected string $entityType;
    protected int $entityId;
    protected string $fieldKey;
    protected string $sourceLang;
    protected string $targetLang;
    protected string $content;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $tenantId,
        string $entityType,
        int $entityId,
        string $fieldKey,
        string $sourceLang,
        string $targetLang,
        string $content
    ) {
        $this->tenantId = $tenantId;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->fieldKey = $fieldKey;
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
        $this->content = $content;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 1. Verificar nuevamente si ya existe la traducción (pudo crearse mientras estaba en cola)
            $existing = Translation::where('tenant_id', $this->tenantId)
                ->where('entity_type', $this->entityType)
                ->where('entity_id', $this->entityId)
                ->where('field_key', $this->fieldKey)
                ->where('locale', $this->targetLang)
                ->first();

            if ($existing) {
                Log::info("Traducción ya existe, saltando llamada a IA", [
                    'tenant_id' => $this->tenantId,
                    'entity' => $this->entityType . ':' . $this->entityId
                ]);
                return;
            }

            // 2. Determinar si el texto es "estructural" (candidato a pool global)
            // Textos cortos, genéricos (< 50 palabras) son candidatos
            $isStructural = $this->isStructuralText($this->content);

            // 3. Llamar a API de IA (OpenAI ejemplo)
            $translatedText = $this->callAiApi($this->content, $this->sourceLang, $this->targetLang);

            // 4. Guardar traducción en el tenant
            Translation::create([
                'tenant_id' => $this->tenantId,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'field_key' => $this->fieldKey,
                'locale' => $this->targetLang,
                'value' => $translatedText,
                'is_machine_translated' => true,
            ]);

            // 5. Si es estructural, guardar también en el pool global para reutilización futura
            if ($isStructural) {
                $contentHash = md5($this->content);
                
                // Guardar original en global si no existe
                Translation::firstOrCreate(
                    [
                        'tenant_id' => null, // Global
                        'entity_type' => 'structural',
                        'entity_id' => 0, // No aplica
                        'field_key' => 'content_hash_' . $contentHash,
                        'locale' => $this->sourceLang,
                    ],
                    ['value' => $this->content]
                );

                // Guardar traducción en global
                Translation::firstOrCreate(
                    [
                        'tenant_id' => null, // Global
                        'entity_type' => 'structural',
                        'entity_id' => 0,
                        'field_key' => 'content_hash_' . $contentHash,
                        'locale' => $this->targetLang,
                    ],
                    ['value' => $translatedText]
                );

                Log::info("Traducción guardada en pool global", [
                    'hash' => $contentHash,
                    'from' => $this->sourceLang,
                    'to' => $this->targetLang
                ]);
            }

            Log::info("Traducción completada exitosamente", [
                'tenant_id' => $this->tenantId,
                'entity' => $this->entityType . ':' . $this->entityId,
                'locale' => $this->targetLang,
                'is_structural' => $isStructural
            ]);

        } catch (\Exception $e) {
            Log::error("Error en traducción IA", [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'content_preview' => substr($this->content, 0, 50)
            ]);
            throw $e; // Para que se reintente
        }
    }

    /**
     * Determinar si un texto es candidato a pool global (corto y genérico)
     */
    private function isStructuralText(string $text): bool
    {
        $wordCount = str_word_count($text);
        $charCount = strlen($text);

        // Criterios: menos de 50 palabras y menos de 300 caracteres
        // Ejemplos: "Wifi gratuito", "Check-in 15:00", "No fumar"
        return $wordCount <= 50 && $charCount <= 300;
    }

    /**
     * Llamar a API de IA para traducción
     * Implementación ejemplo con OpenAI
     */
    private function callAiApi(string $text, string $from, string $to): string
    {
        $apiKey = config('services.openai.api_key');
        
        if (!$apiKey) {
            throw new \RuntimeException('API key de OpenAI no configurada');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini', // Modelo económico y rápido
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Eres un traductor profesional. Traduce el siguiente texto de {$from} a {$to}. Devuelve SOLO la traducción, sin explicaciones ni texto adicional."
                ],
                [
                    'role' => 'user',
                    'content' => $text
                ]
            ],
            'temperature' => 0.3, // Baja temperatura para traducciones consistentes
            'max_tokens' => 1000
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error al llamar a API de OpenAI: ' . $response->body());
        }

        $data = $response->json();
        $translatedText = $data['choices'][0]['message']['content'] ?? '';

        // Limpieza básica
        $translatedText = trim($translatedText, " \n\t\"'");

        if (empty($translatedText)) {
            throw new \RuntimeException('La API devolvió una traducción vacía');
        }

        return $translatedText;
    }

    /**
     * Manejo de fallo después de todos los reintentos
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("Traducción IA falló definitivamente tras todos los reintentos", [
            'tenant_id' => $this->tenantId,
            'entity' => $this->entityType . ':' . $this->entityId,
            'error' => $exception->getMessage()
        ]);

        // Podríamos notificar al administrador o marcar la entidad como "traducción pendiente"
    }
}
