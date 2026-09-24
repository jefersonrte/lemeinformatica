<?php
declare(strict_types=1);

namespace App\Domain\Estimativa;

use App\Domain\Orcamento\OrcamentoCalculator;

/**
 * Extrai dados quantitativos do texto de uma planta arquitetônica (PDF com texto):
 * área construída, terreno, pavimentos, ambientes e quadro de esquadrias.
 */
final class PlantaAnalyzer
{
    /**
     * @return array{area_construida:?float,area_origem:string,area_terreno:?float,pavimentos:int,
     *   ambientes:array{total:int,area_soma:float,banheiros:int,cozinhas:int,lavanderias:int,dormitorios:int},
     *   esquadrias:list<array{codigo:string,tipo:string,quantidade:int,largura:float,altura:float,area_unitaria:float,descricao:string}>,
     *   texto_util:bool,avisos:list<string>}
     */
    public function analisar(string $texto): array
    {
        $texto = str_replace(["\u{00a0}", "\t"], ' ', $texto);
        $plano = mb_strtolower($texto);
        $semAcento = strtr($plano, ['á' => 'a', 'â' => 'a', 'ã' => 'a', 'à' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
        $avisos = [];

        [$area, $origem] = $this->areaConstruida($semAcento);
        $ambientes = $this->ambientes($semAcento);
        if ($area === null && $ambientes['area_soma'] > 20) {
            $area = round($ambientes['area_soma'] * 1.12, 2); // paredes/circulação não rotuladas
            $origem = 'aproximada pela soma dos ambientes (+12% de paredes)';
            $avisos[] = 'A planta não traz o quadro de áreas em texto: a área foi aproximada pela soma dos ambientes. Confira e informe a área construída correta para uma prévia confiável.';
        }

        $terreno = $this->maiorValor($semAcento, '/area (?:total )?do terreno\s*[:=]?\s*([\d.]+,\d{1,2})\s*m/');
        $esquadrias = $this->esquadrias($texto);
        $pavimentos = $this->pavimentos($semAcento);

        if (mb_strlen(trim($texto)) < 80) {
            $avisos[] = 'O PDF quase não tem texto (provavelmente é imagem). Informe a área manualmente.';
        }
        if ($esquadrias === []) {
            $avisos[] = 'Quadro de esquadrias não encontrado; portas e janelas serão estimadas pela área.';
        }

        return [
            'area_construida' => $area,
            'area_origem' => $origem,
            'area_terreno' => $terreno,
            'pavimentos' => $pavimentos,
            'ambientes' => $ambientes,
            'esquadrias' => $esquadrias,
            'texto_util' => mb_strlen(trim($texto)) >= 80,
            'avisos' => $avisos,
        ];
    }

    /** @return array{0:?float,1:string} */
    private function areaConstruida(string $t): array
    {
        $padroes = [
            'quadro de áreas (área total construída)' => '/area total (?:da |de )?constru(?:ida|cao)\s*[:=]?\s*([\d.]+,\d{1,2})\s*m/',
            'quadro de áreas (área construída)' => '/area constru(?:ida|cao)(?: total)?\s*[:=]?\s*([\d.]+,\d{1,2})\s*m/',
            'planta (área interna construída)' => '/a(?:rea)? interna constru(?:ida|cao)\s*[:=]?\s*([\d.]+,\d{1,2})\s*m/',
            'quadro de áreas (total)' => '/total\s+([\d.]+,\d{2})\s*m[²2]\s+[\d.]+,\d{2}\s*m[²2]/',
        ];
        unset($padroes['quadro de áreas (total)']);
        foreach ($padroes as $origem => $padrao) {
            $valor = $this->maiorValor($t, $padrao);
            if ($valor !== null && $valor >= 15 && $valor <= 200000) {
                return [$valor, $origem];
            }
        }
        // Linha "TOTAL a m² b m² c m²" do quadro de áreas: o total é o valor que soma os outros dois
        if (preg_match_all('/total\s+([\d.]+,\d{2})\s*m[²2]\s+([\d.]+,\d{2})\s*m[²2]\s+([\d.]+,\d{2})\s*m[²2]/u', $t, $m, PREG_SET_ORDER)) {
            $maior = null;
            foreach ($m as $linha) {
                [$a, $b, $c] = array_map(static fn (string $v): float => OrcamentoCalculator::decimal($v), [$linha[1], $linha[2], $linha[3]]);
                $total = abs($c - ($a + $b)) <= 0.05 ? $c : (abs($a - ($b + $c)) <= 0.05 ? $a : max($a, $b, $c));
                $maior = max($maior ?? 0, $total);
            }
            if ($maior !== null && $maior >= 15) {
                return [$maior, 'quadro de áreas (total)'];
            }
        }
        return [null, 'não encontrada'];
    }

    private function maiorValor(string $t, string $padrao): ?float
    {
        if (!preg_match_all($padrao, $t, $m)) {
            return null;
        }
        $valores = array_map(static fn (string $v): float => OrcamentoCalculator::decimal($v), $m[1]);
        $valores = array_filter($valores, static fn (float $v): bool => $v > 0);
        return $valores === [] ? null : max($valores);
    }

    /** @return array{total:int,area_soma:float,banheiros:int,cozinhas:int,lavanderias:int,dormitorios:int} */
    private function ambientes(string $t): array
    {
        preg_match_all('/\ba\s*[:=]\s*([\d.]+,\d{1,2})\s*m[²2]/u', $t, $m);
        $areas = array_map(static fn (string $v): float => OrcamentoCalculator::decimal($v), $m[1]);
        // As mesmas salas se repetem em várias pranchas (planta, layout, forro): usa a prancha de maior área
        $maiorPrancha = 0.0;
        foreach (explode("\f", $t) as $prancha) {
            preg_match_all('/\ba\s*[:=]\s*([\d.]+,\d{1,2})\s*m[²2]/u', $prancha, $mp);
            $maiorPrancha = max($maiorPrancha, array_sum(array_map(static fn (string $v): float => OrcamentoCalculator::decimal($v), $mp[1])));
        }
        $conta = static fn (string $padrao): int => preg_match_all($padrao, $t);
        return [
            'total' => count($areas),
            'area_soma' => round(str_contains($t, "\f") ? $maiorPrancha : array_sum($areas), 2),
            'banheiros' => $conta('/\b(banheiro|bwc|wc|lavabo|sanitario)\b/'),
            'cozinhas' => $conta('/\b(cozinha|gourmet|copa)\b/'),
            'lavanderias' => $conta('/\b(lavanderia|area de servico)\b/'),
            'dormitorios' => $conta('/\b(dormitorio|quarto|suite)\b/'),
        ];
    }

    private function pavimentos(string $t): int
    {
        preg_match_all('/\b(terreo|pavimento superior|pavimento inferior|\d+[ºo°]? ?pavimento|pavto\.? ?\d|subsolo|mezanino|cobertura)\b/', $t, $m);
        $unicos = array_unique(array_map(static fn (string $p): string => preg_replace('/\s+/', ' ', $p) ?? $p, $m[1]));
        return max(1, min(20, count($unicos)));
    }

    /** Linhas do quadro de esquadrias: "P1 14 0,80 2,10 DE ABRIR..." ou "J2 4 1,50 1,20 0,90 BASCULANTE". */
    private function esquadrias(string $texto): array
    {
        $encontradas = [];
        $padrao = '/\b((?:PJ|PV|JV|PC|PM|PA|J|P|V|B|M)\d{1,2}[A-Z]?)\s+(\d{1,3})\s+(\d,\d{2})\s+(\d,\d{2})((?:\s+\d,\d{2})?)\s+([^\n]{0,60}?)(?=\s+\d+,\d{2}\s*m[²2]|\s+(?:PJ|PV|JV|P|J)\d{1,2}\s+\d|\n|$)/u';
        $candidatas = [];
        if (preg_match_all($padrao, $texto, $linhas, PREG_SET_ORDER)) {
            foreach ($linhas as $linha) {
                $candidatas[] = [$linha[1], (int) $linha[2], OrcamentoCalculator::decimal($linha[3]), OrcamentoCalculator::decimal($linha[4]), $linha[6]];
            }
        }
        // Quadro exportado do Revit: "P04 Porta Giro 70x210cm 1 Porta 1 folha de giro..."
        $revit = '/\b((?:PJ|PV|JV|P|J|V)\d{1,2}[A-Z]?)\s+[^\n]{0,70}?\b(\d{2,3})\s?x\s?(\d{2,3})\s?cm\s+(\d{1,3})\s+([^\n]{0,70})/iu';
        if (preg_match_all($revit, $texto, $linhas, PREG_SET_ORDER)) {
            foreach ($linhas as $linha) {
                $candidatas[] = [$linha[1], (int) $linha[4], ((int) $linha[2]) / 100, ((int) $linha[3]) / 100, $linha[5]];
            }
        }
        foreach ($candidatas as [$codigoBruto, $quantidade, $largura, $altura, $descricao]) {
            $linha = [6 => $descricao];
            $codigo = strtoupper($codigoBruto);
            if ($quantidade <= 0 || $quantidade > 500 || $largura <= 0.2 || $largura > 12 || $altura <= 0.2 || $altura > 6) {
                continue;
            }
            $tipo = str_starts_with($codigo, 'PJ') || str_starts_with($codigo, 'PV') ? 'porta_janela'
                : (str_starts_with($codigo, 'P') ? 'porta' : 'janela');
            if (preg_match('/port[aã]o/iu', (string) $descricao)) {
                $tipo = 'portao';
            }
            // O mesmo quadro costuma se repetir em várias pranchas: mantém a maior quantidade informada
            if (!isset($encontradas[$codigo]) || $encontradas[$codigo]['quantidade'] < $quantidade) {
                $encontradas[$codigo] = [
                    'codigo' => $codigo,
                    'tipo' => $tipo,
                    'quantidade' => $quantidade,
                    'largura' => $largura,
                    'altura' => $altura,
                    'area_unitaria' => round($largura * $altura, 2),
                    'descricao' => trim(preg_replace('/\s+/u', ' ', $linha[6]) ?? ''),
                ];
            }
        }
        ksort($encontradas, SORT_NATURAL);
        return array_values($encontradas);
    }
}
