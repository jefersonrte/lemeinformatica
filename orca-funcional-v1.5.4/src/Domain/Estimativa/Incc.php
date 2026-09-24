<?php
declare(strict_types=1);

namespace App\Domain\Estimativa;

use DateTimeImmutable;

/**
 * Atualização monetária aproximada pelo INCC (variação anual acumulada, FGV).
 * Os anos mais recentes são estimativas e podem ser ajustados aqui quando o índice oficial fechar.
 */
final class Incc
{
    /** Variação anual (%) — valores aproximados; 2025 e 2026 estimados. */
    public const VARIACAO_ANUAL = [
        2008 => 11.87, 2009 => 3.25, 2010 => 7.77, 2011 => 7.48, 2012 => 7.12, 2013 => 8.09,
        2014 => 6.95, 2015 => 7.21, 2016 => 6.26, 2017 => 4.04, 2018 => 3.97, 2019 => 4.14,
        2020 => 8.81, 2021 => 13.85, 2022 => 9.28, 2023 => 3.32, 2024 => 5.64, 2025 => 6.00,
        2026 => 5.00,
    ];

    public static function fator(?string $dataBase, ?DateTimeImmutable $ate = null): float
    {
        if ($dataBase === null || !preg_match('/^(\d{4})-(\d{2})/', $dataBase, $m)) {
            return 1.0;
        }
        $ate ??= new DateTimeImmutable('first day of this month');
        $ano = (int) $m[1];
        $mes = (int) $m[2];
        $anoFinal = (int) $ate->format('Y');
        $mesFinal = (int) $ate->format('n');
        $fator = 1.0;
        while ($ano < $anoFinal || ($ano === $anoFinal && $mes < $mesFinal)) {
            $anual = self::VARIACAO_ANUAL[$ano] ?? 5.0;
            $fator *= (1 + $anual / 100) ** (1 / 12);
            if (++$mes > 12) {
                $mes = 1;
                $ano++;
            }
        }
        return round($fator, 6);
    }
}
