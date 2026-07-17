<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepSeekReportAnalysisService
{
    public function configured(): bool
    {
        return filled(config('services.deepseek.api_key'));
    }

    /**
     * @throws RequestException
     */
    public function analyze(array $reportPayload): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('DeepSeek no esta configurado.');
        }

        $baseUrl = rtrim((string) config('services.deepseek.base_url', 'https://api.deepseek.com'), '/');
        $model = (string) config('services.deepseek.model', 'deepseek-v4-flash');
        $maxTokens = (int) config('services.deepseek.max_tokens', 1200);

        $response = Http::timeout((int) config('services.deepseek.timeout', 45))
            ->acceptJson()
            ->withToken((string) config('services.deepseek.api_key'))
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => implode(' ', [
                            'Eres un analista ejecutivo de reportes documentarios de salud ocupacional.',
                            'Devuelve solo JSON valido con las claves resumen_ejecutivo, hallazgos, recomendaciones, riesgos y notas.',
                            'No recalcules ni contradigas los totales recibidos; usalos como fuente unica.',
                            'No inventes datos, no menciones nombres de personas y no solicites informacion sensible.',
                            'Escribe en espanol claro, profesional y breve.',
                        ]),
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Analiza este reporte consolidado y anonimizado: ' . json_encode(
                            $reportPayload,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                    ],
                ],
                'thinking' => ['type' => 'disabled'],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => $maxTokens,
                'stream' => false,
            ])
            ->throw()
            ->json();

        return $this->normalizeResponse((string) data_get($response, 'choices.0.message.content', ''), $model);
    }

    private function normalizeResponse(string $content, string $model): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('DeepSeek no devolvio un JSON valido.');
        }

        return [
            'resumen_ejecutivo' => (string) ($decoded['resumen_ejecutivo'] ?? ''),
            'hallazgos' => $this->stringList($decoded['hallazgos'] ?? []),
            'recomendaciones' => $this->stringList($decoded['recomendaciones'] ?? []),
            'riesgos' => $this->stringList($decoded['riesgos'] ?? []),
            'notas' => $this->stringList($decoded['notas'] ?? []),
            'model' => $model,
            'source' => 'deepseek',
        ];
    }

    private function stringList(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
