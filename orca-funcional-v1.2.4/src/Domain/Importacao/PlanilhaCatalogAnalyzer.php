<?php
declare(strict_types=1);

namespace App\Domain\Importacao;

use App\Infrastructure\OpenAiCatalogClassifier;
use App\Support\Config;
use PDO;
use Throwable;

final class PlanilhaCatalogAnalyzer
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array{items:list<array<string,mixed>>,ai_configured:bool,ai_used:bool,ai_error:bool}
     */
    public function analisar(array $items): array
    {
        $categories = $this->db->query('SELECT id,nome FROM categorias WHERE ativo=1 ORDER BY nome')->fetchAll();
        $products = $this->db->query('SELECT id,categoria_id,codigo,nome,unidade,descricao FROM produtos WHERE ativo=1 ORDER BY nome')->fetchAll();
        $analyzed = (new ItemCatalogMatcher())->analisar($items, $categories, $products);

        $enabled = filter_var(Config::get('ai.enabled', false), FILTER_VALIDATE_BOOL);
        $classifier = new OpenAiCatalogClassifier(
            (string) Config::get('ai.api_key', ''),
            (string) Config::get('ai.model', 'gpt-5.4-mini'),
            (string) Config::get('ai.endpoint', 'https://api.openai.com/v1/responses'),
            (int) Config::get('ai.timeout_seconds', 25)
        );
        $configured = $enabled && $classifier->isConfigured();
        if (!$configured || $analyzed === []) {
            return ['items' => $analyzed, 'ai_configured' => false, 'ai_used' => false, 'ai_error' => false];
        }

        $categoryIds = array_fill_keys(array_map(static fn (array $category): int => (int) $category['id'], $categories), true);
        $productById = [];
        foreach ($products as $product) {
            $productById[(int) $product['id']] = $product;
        }

        $used = false;
        try {
            $aiCandidates = array_values(array_filter(
                $analyzed,
                static fn (array $item): bool => !((bool) ($item['duplicado'] ?? false) && (float) ($item['similaridade'] ?? 0) >= 1.0)
            ));
            $aiResults = $classifier->classificar(array_slice($aiCandidates, 0, 30), array_map(static fn (array $category): array => [
                'id' => (int) $category['id'],
                'nome' => (string) $category['nome'],
            ], $categories));
            foreach ($aiResults as $aiResult) {
                $index = (int) ($aiResult['indice'] ?? -1);
                if (!isset($analyzed[$index])) {
                    continue;
                }
                $confidence = max(0.0, min(1.0, (float) ($aiResult['confianca'] ?? 0)));
                if ($confidence < 0.55) {
                    continue;
                }

                $categoryId = (int) ($aiResult['categoria_id'] ?? 0);
                if ($categoryId > 0 && isset($categoryIds[$categoryId])) {
                    $analyzed[$index]['categoria_id_sugerida'] = $categoryId;
                    $analyzed[$index]['categoria_confianca'] = $confidence;
                }
                $productId = (int) ($aiResult['produto_id_similar'] ?? 0);
                $hasCatalogProduct = $productId > 0 && isset($productById[$productId]);
                if ($hasCatalogProduct) {
                    $product = $productById[$productId];
                    $analyzed[$index]['produto_id_similar'] = $productId;
                    $analyzed[$index]['produto_nome_similar'] = (string) $product['nome'];
                    $analyzed[$index]['semelhante'] = true;
                    $analyzed[$index]['similaridade'] = $confidence;
                }

                // Uma duplicidade precisa apontar para um produto real do catálogo.
                $aiDuplicate = (bool) ($aiResult['duplicado'] ?? false);
                if (!$hasCatalogProduct) {
                    $aiDuplicate = (bool) ($analyzed[$index]['duplicado'] ?? false);
                }
                $analyzed[$index]['duplicado'] = $aiDuplicate;
                $analyzed[$index]['analise_origem'] = 'ia';
                $analyzed[$index]['analise_motivo'] = mb_substr(trim((string) ($aiResult['motivo'] ?? 'Classificação por IA.')), 0, 180);
                $used = true;
            }
        } catch (Throwable $exception) {
            error_log('[planilha_ia] ' . $exception->getMessage());
            return ['items' => $analyzed, 'ai_configured' => true, 'ai_used' => $used, 'ai_error' => true];
        }

        return ['items' => $analyzed, 'ai_configured' => true, 'ai_used' => $used, 'ai_error' => false];
    }
}
