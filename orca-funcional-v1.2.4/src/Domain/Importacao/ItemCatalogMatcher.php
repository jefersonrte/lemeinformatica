<?php
declare(strict_types=1);

namespace App\Domain\Importacao;

final class ItemCatalogMatcher
{
    private const SIMILAR_THRESHOLD = 0.72;
    private const DUPLICATE_THRESHOLD = 0.90;

    /**
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $categories
     * @param list<array<string,mixed>> $products
     * @return list<array<string,mixed>>
     */
    public function analisar(array $items, array $categories, array $products): array
    {
        $result = [];
        foreach ($items as $index => $item) {
            $description = trim((string) ($item['descricao'] ?? ''));
            $code = $this->normalizeCode((string) ($item['codigo'] ?? ''));
            $rankedProducts = [];

            foreach ($products as $product) {
                $productCode = $this->normalizeCode((string) ($product['codigo'] ?? ''));
                $codeMatch = $code !== '' && $productCode !== '' && hash_equals($productCode, $code);
                $nameScore = $this->similarity($description, (string) ($product['nome'] ?? ''));
                $descriptionScore = $this->similarity($description, (string) ($product['descricao'] ?? ''));
                $score = $codeMatch ? 1.0 : max($nameScore, $descriptionScore * 0.94);
                if ($score >= 0.38) {
                    $rankedProducts[] = ['product' => $product, 'score' => $score, 'code_match' => $codeMatch];
                }
            }
            usort($rankedProducts, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            $bestProduct = $rankedProducts[0] ?? null;
            $bestScore = $bestProduct ? (float) $bestProduct['score'] : 0.0;
            $duplicate = $bestProduct !== null
                && ((bool) $bestProduct['code_match'] || $bestScore >= self::DUPLICATE_THRESHOLD);
            $similar = !$duplicate && $bestProduct !== null && $bestScore >= self::SIMILAR_THRESHOLD;

            [$categoryId, $categoryConfidence, $categoryReason] = $this->suggestCategory(
                $item,
                $categories,
                $bestProduct,
                $bestScore
            );

            $candidates = array_map(static function (array $ranked): array {
                $product = $ranked['product'];
                return [
                    'id' => (int) ($product['id'] ?? 0),
                    'nome' => (string) ($product['nome'] ?? ''),
                    'categoria_id' => (int) ($product['categoria_id'] ?? 0),
                    'similaridade' => round((float) $ranked['score'], 4),
                ];
            }, array_slice($rankedProducts, 0, 3));

            $result[] = [
                ...$item,
                'indice_importacao' => $index,
                'categoria_id_sugerida' => $categoryId,
                'categoria_confianca' => round($categoryConfidence, 4),
                'produto_id_similar' => $bestProduct ? (int) ($bestProduct['product']['id'] ?? 0) : null,
                'produto_nome_similar' => $bestProduct ? (string) ($bestProduct['product']['nome'] ?? '') : '',
                'similaridade' => round($bestScore, 4),
                'duplicado' => $duplicate,
                'semelhante' => $similar,
                'analise_origem' => 'local',
                'analise_motivo' => $duplicate
                    ? ((bool) $bestProduct['code_match'] ? 'Mesmo código já cadastrado.' : 'Nome praticamente igual ao produto cadastrado.')
                    : ($similar ? 'Descrição semelhante a um produto cadastrado.' : $categoryReason),
                'candidatos_produtos' => $candidates,
            ];
        }

        return $result;
    }

    /** @return array{0:?int,1:float,2:string} */
    private function suggestCategory(array $item, array $categories, ?array $bestProduct, float $productScore): array
    {
        if ($bestProduct !== null && $productScore >= self::SIMILAR_THRESHOLD) {
            $categoryId = (int) ($bestProduct['product']['categoria_id'] ?? 0);
            if ($categoryId > 0) {
                return [$categoryId, min(1.0, $productScore), 'Categoria herdada do produto semelhante.'];
            }
        }

        $explicit = trim((string) ($item['categoria'] ?? ''));
        $description = (string) ($item['descricao'] ?? '');
        $bestId = null;
        $bestScore = 0.0;
        $reason = 'Categoria não determinada automaticamente.';

        foreach ($categories as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            $categoryName = (string) ($category['nome'] ?? '');
            if ($categoryId <= 0 || $categoryName === '') {
                continue;
            }

            $explicitScore = $explicit !== '' ? $this->similarity($explicit, $categoryName) : 0.0;
            $keywordScore = $this->categoryKeywordScore($description, $categoryName);
            $score = max($explicitScore, $keywordScore);
            if ($score > $bestScore) {
                $bestId = $categoryId;
                $bestScore = $score;
                $reason = $explicitScore >= $keywordScore
                    ? 'Categoria reconhecida pela planilha.'
                    : 'Categoria sugerida pelos termos do item.';
            }
        }

        return $bestScore >= 0.58 ? [$bestId, $bestScore, $reason] : [null, $bestScore, $reason];
    }

    private function categoryKeywordScore(string $description, string $categoryName): float
    {
        $category = $this->normalize($categoryName);
        $description = $this->normalize($description);
        $groups = [
            'hidraul' => ['tubo', 'pvc', 'conexao', 'registro', 'torneira', 'esgoto', 'agua', 'ralo', 'sifao'],
            'eletric' => ['cabo', 'fio', 'disjuntor', 'tomada', 'interruptor', 'quadro', 'eletroduto', 'luminaria'],
            'alvenaria' => ['tijolo', 'bloco', 'cimento', 'argamassa', 'cal', 'areia'],
            'revest' => ['ceramica', 'porcelanato', 'azulejo', 'rejunte', 'revestimento'],
            'tinta' => ['tinta', 'selador', 'verniz', 'massa corrida', 'impermeabilizante'],
            'estrutura' => ['concreto', 'vergalhao', 'aco', 'ferro', 'viga', 'pilar', 'madeira'],
            'madeira' => ['madeira', 'tabua', 'caibro', 'ripa', 'compensado'],
            'cobertura' => ['telha', 'calha', 'rufo', 'cumeeira', 'telhado'],
            'telhado' => ['telha', 'calha', 'rufo', 'cumeeira', 'telhado'],
            'esquadria' => ['porta', 'janela', 'esquadria', 'fechadura'],
            'vidro' => ['vidro', 'espelho', 'box'],
            'piso' => ['piso', 'rodape', 'laminado', 'vinilico'],
            'ferramenta' => ['furadeira', 'serra', 'martelo', 'alicate', 'ferramenta'],
            'epi' => ['capacete', 'luva', 'oculos', 'bota', 'mascara', 'epi'],
        ];

        $matches = 0;
        foreach ($groups as $categoryFragment => $keywords) {
            if (!str_contains($category, $categoryFragment)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if (str_contains($description, $keyword)) {
                    $matches++;
                }
            }
        }
        return $matches > 0 ? min(0.92, 0.68 + (($matches - 1) * 0.08)) : 0.0;
    }

    private function similarity(string $left, string $right): float
    {
        $left = $this->normalize($left);
        $right = $this->normalize($right);
        if ($left === '' || $right === '') {
            return 0.0;
        }
        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percent);
        $leftTokens = array_values(array_unique(array_filter(explode(' ', $left), static fn (string $token): bool => strlen($token) > 2)));
        $rightTokens = array_values(array_unique(array_filter(explode(' ', $right), static fn (string $token): bool => strlen($token) > 2)));
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique([...$leftTokens, ...$rightTokens]));
        $jaccard = $union > 0 ? $intersection / $union : 0.0;

        return min(1.0, max($percent / 100, $jaccard, $jaccard * 0.72 + ($percent / 100) * 0.28));
    }

    private function normalizeCode(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($this->normalize($value))) ?? '';
    }

    private function normalize(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower(is_string($ascii) ? $ascii : $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
