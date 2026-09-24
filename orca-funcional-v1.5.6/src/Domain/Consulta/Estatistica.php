<?php
declare(strict_types=1);

namespace App\Domain\Consulta;

/** Resumo estatístico de preços (faixa, média, mediana e quartis). */
final class Estatistica
{
    /**
     * @param list<float|int> $valores
     * @return array{n: int, min: float, max: float, media: float, mediana: float, p25: float, p75: float}|null
     */
    public static function resumo(array $valores): ?array
    {
        $valores = array_values(array_filter(array_map('floatval', $valores), static fn (float $v): bool => $v > 0));
        if ($valores === []) {
            return null;
        }
        sort($valores);
        return [
            'n' => count($valores),
            'min' => $valores[0],
            'max' => $valores[count($valores) - 1],
            'media' => round(array_sum($valores) / count($valores), 4),
            'mediana' => self::percentil($valores, .5),
            'p25' => self::percentil($valores, .25),
            'p75' => self::percentil($valores, .75),
        ];
    }

    /** Percentil por interpolação linear sobre uma lista já ordenada. */
    public static function percentil(array $ordenados, float $p): float
    {
        $n = count($ordenados);
        if ($n === 0) {
            return 0.0;
        }
        $pos = ($n - 1) * $p;
        $baixo = (int) floor($pos);
        $alto = (int) ceil($pos);
        $valor = $ordenados[$baixo] + ($ordenados[$alto] - $ordenados[$baixo]) * ($pos - $baixo);
        return round((float) $valor, 4);
    }
}
