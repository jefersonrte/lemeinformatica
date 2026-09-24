<?php
declare(strict_types=1);

namespace App\Infrastructure;

use RuntimeException;

final class OpenAiCatalogClassifier
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-5.4-mini',
        private readonly string $endpoint = 'https://api.openai.com/v1/responses',
        private readonly int $timeoutSeconds = 25
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->model !== '' && str_starts_with($this->endpoint, 'https://');
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param list<array{id:int,nome:string}> $categories
     * @return list<array<string,mixed>>
     */
    public function classificar(array $items, array $categories): array
    {
        if (!$this->isConfigured() || !function_exists('curl_init') || $items === []) {
            return [];
        }

        $inputItems = array_map(static fn (array $item): array => [
            'indice' => (int) ($item['indice_importacao'] ?? 0),
            'codigo' => (string) ($item['codigo'] ?? ''),
            'descricao' => (string) ($item['descricao'] ?? ''),
            'categoria_planilha' => (string) ($item['categoria'] ?? ''),
            'categoria_local_id' => $item['categoria_id_sugerida'] ?? null,
            'candidatos_produtos' => $item['candidatos_produtos'] ?? [],
        ], $items);

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'itens' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'indice' => ['type' => 'integer'],
                            'categoria_id' => ['type' => ['integer', 'null']],
                            'produto_id_similar' => ['type' => ['integer', 'null']],
                            'duplicado' => ['type' => 'boolean'],
                            'confianca' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'motivo' => ['type' => 'string', 'maxLength' => 180],
                        ],
                        'required' => ['indice', 'categoria_id', 'produto_id_similar', 'duplicado', 'confianca', 'motivo'],
                    ],
                ],
            ],
            'required' => ['itens'],
        ];

        $payload = [
            'model' => $this->model,
            'store' => false,
            'instructions' => 'Classifique itens de orçamento de construção civil. Use somente IDs fornecidos. Marque duplicado apenas quando código ou descrição representarem essencialmente o mesmo produto. Responda no schema solicitado, sem dados adicionais.',
            'input' => json_encode(['categorias' => $categories, 'itens' => $inputItems], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'classificacao_catalogo',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
            'reasoning' => ['effort' => 'low'],
            'max_output_tokens' => 2500,
        ];

        $handle = curl_init($this->endpoint);
        if ($handle === false) {
            throw new RuntimeException('Não foi possível iniciar a análise por IA.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => max(10, $this->timeoutSeconds),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $responseBody = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (!is_string($responseBody) || $statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('A análise por IA não respondeu corretamente' . ($curlError !== '' ? ': ' . $curlError : '.'));
        }

        $response = json_decode($responseBody, true);
        if (!is_array($response)) {
            throw new RuntimeException('Resposta inválida da análise por IA.');
        }
        $outputText = (string) ($response['output_text'] ?? '');
        if ($outputText === '') {
            foreach (($response['output'] ?? []) as $output) {
                foreach (($output['content'] ?? []) as $content) {
                    if (($content['type'] ?? '') === 'output_text') {
                        $outputText .= (string) ($content['text'] ?? '');
                    }
                }
            }
        }
        $decoded = json_decode($outputText, true);
        return is_array($decoded['itens'] ?? null) ? $decoded['itens'] : [];
    }
}
