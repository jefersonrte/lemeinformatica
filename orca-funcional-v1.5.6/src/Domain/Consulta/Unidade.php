<?php
declare(strict_types=1);

namespace App\Domain\Consulta;

/** Normaliza unidades vindas de planilhas (M2 → M², UND → UN, pç → PC...). */
final class Unidade
{
    private const MAPA = [
        'M2' => 'M²', 'M3' => 'M³', 'UND' => 'UN', 'UNID' => 'UN', 'UNIDADE' => 'UN', 'PÇ' => 'PC', 'PÇA' => 'PC',
        'PCS' => 'PC', 'UNI' => 'UN', 'MES' => 'MÊS', 'PECA' => 'PC', 'PEÇA' => 'PC', 'LT' => 'L', 'VERBA' => 'VB', 'MT' => 'M',
    ];

    public static function normalizar(?string $unidade): string
    {
        // Planilhas com codificação quebrada trazem "m\u{FFFD}" no lugar de m²/m³.
        $u = rtrim(mb_strtoupper(trim(str_replace("\u{FFFD}", '?', (string) $unidade)), 'UTF-8'), '.');
        return mb_substr(self::MAPA[$u] ?? ($u !== '' ? $u : 'UN'), 0, 20, 'UTF-8');
    }
}
