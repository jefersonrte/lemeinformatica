<?php
declare(strict_types=1);

namespace App\Domain\Orcamento;

final class OrcamentoCalculator
{
    public static function decimal(int|float|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim($value);
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
        }
        $normalized = str_replace(',', '.', $normalized);
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    public static function totalItem(int|float|string|null $quantidade, int|float|string|null $preco): float
    {
        return round(self::decimal($quantidade) * self::decimal($preco), 2);
    }

    public static function total(array $itens): float
    {
        return round(array_reduce(
            $itens,
            static fn (float $total, array $item): float => $total + self::totalItem(
                $item['quantidade'] ?? $item['qtd'] ?? 0,
                $item['preco_unitario'] ?? $item['preco'] ?? 0
            ),
            0.0
        ), 2);
    }
}
