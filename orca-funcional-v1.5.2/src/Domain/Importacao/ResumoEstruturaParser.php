<?php
declare(strict_types=1);

namespace App\Domain\Importacao;

/**
 * Resumo de materiais da estrutura exportado pelos programas de cálculo (Eberick, TQS e similares):
 * "Pavimento | Elemento | Peso do aço (kg) [por bitola] | Área de fôrmas (m²) | Volume de concreto (m³)".
 *
 * Soma as linhas de elementos (ignora os totais por pavimento) e devolve itens de orçamento:
 * concreto e fôrmas separados entre fundação e estrutura, aço por bitola quando a planilha detalha.
 */
final class ResumoEstruturaParser
{
    private const FUNDACAO = '/funda|sapata|bloco|estaca|baldrame|radier|tubul/';

    /**
     * @param list<array{nome:string,linhas:array<int,array<int,mixed>>}> $abas
     * @return array{aba:string,itens:list<array<string,mixed>>,fck:?int}|null
     */
    public function analisar(array $abas): ?array
    {
        usort($abas, static fn (array $a, array $b): int => (int) str_contains(mb_strtolower($b['nome']), 'ajust') <=> (int) str_contains(mb_strtolower($a['nome']), 'ajust'));
        foreach ($abas as $aba) {
            $resultado = $this->analisarAba($aba['linhas']);
            if ($resultado !== null) {
                return ['aba' => $aba['nome']] + $resultado;
            }
        }
        return null;
    }

    /** @return array{itens:list<array<string,mixed>>,fck:?int}|null */
    private function analisarAba(array $linhas): ?array
    {
        ksort($linhas);
        $cabecalho = null;
        $colunas = [];
        foreach ($linhas as $numero => $celulas) {
            $mapa = ['aco' => null, 'concreto' => null, 'forma' => null, 'elemento' => null];
            foreach ($celulas as $coluna => $valor) {
                $texto = PlanilhaOrcamentoParser::normalizar(str_replace("\n", ' ', (string) $valor));
                if ($mapa['aco'] === null && preg_match('/peso (do )?aco|aco .*\(kg\)/', $texto)) {
                    $mapa['aco'] = $coluna;
                } elseif ($mapa['concreto'] === null && str_contains($texto, 'concreto') && !str_contains($texto, 'consumo')) {
                    $mapa['concreto'] = $coluna;
                } elseif ($mapa['forma'] === null && preg_match('/\bformas?\b/', $texto)) {
                    $mapa['forma'] = $coluna;
                } elseif ($mapa['elemento'] === null && $texto === 'elemento') {
                    $mapa['elemento'] = $coluna;
                }
            }
            if ($mapa['aco'] !== null && $mapa['concreto'] !== null && $mapa['forma'] !== null) {
                $cabecalho = $numero;
                $colunas = $mapa;
                break;
            }
        }
        if ($cabecalho === null) {
            return null;
        }

        // Bitolas no sub-cabeçalho ("ø 5,0 | ø 6,3 | ... | TOTAL") entre a coluna do aço e as seguintes
        $bitolas = [];
        foreach ($linhas[$cabecalho + 1] ?? [] as $coluna => $valor) {
            if (preg_match('/^[øØφ]\s*(\d+(?:[.,]\d+)?)/u', trim((string) $valor), $m) && $coluna >= $colunas['aco'] && $coluna < min($colunas['concreto'], $colunas['forma'])) {
                $bitolas[$coluna] = (float) str_replace(',', '.', $m[1]);
            }
        }

        $somas = ['aco' => [], 'concreto' => ['fundacao' => 0.0, 'estrutura' => 0.0], 'forma' => ['fundacao' => 0.0, 'estrutura' => 0.0]];
        $fck = null;
        $fimDaTabela = false;
        foreach ($linhas as $numero => $celulas) {
            $textos = array_map(static fn ($v): string => PlanilhaOrcamentoParser::normalizar((string) $v), $celulas);
            foreach ($textos as $coluna => $texto) {
                // "fck= | 30 Mpa" ou "Volume concreto (m³) | C-40"
                if ($fck === null && (str_starts_with($texto, 'fck') || preg_match('/^c-?\d{2}$/', $texto))) {
                    $valor = preg_match('/(\d{2})/', $texto . ' ' . ($textos[$coluna + 1] ?? ''), $m) ? (int) $m[1] : null;
                    $fck = $valor !== null && $valor >= 15 && $valor <= 60 ? $valor : null;
                }
            }
            if ($numero <= $cabecalho || $fimDaTabela) {
                continue;
            }
            if (array_filter($textos, static fn (string $t): bool => str_starts_with($t, 'resumo')) !== []) {
                $fimDaTabela = true; // outras tabelas (por bitola, protensão) repetem os mesmos materiais
                continue;
            }
            $rotulos = array_filter($textos, static fn (string $t): bool => preg_match('/\p{L}{3,}/u', $t) === 1);
            if ($rotulos === [] || array_filter($rotulos, static fn (string $t): bool => str_starts_with($t, 'total')) !== []) {
                continue; // totais por pavimento e o total geral já estão nas linhas de elementos
            }
            $elemento = $colunas['elemento'] !== null ? ($textos[$colunas['elemento']] ?? '') : (string) end($rotulos);
            $grupo = preg_match(self::FUNDACAO, $elemento) ? 'fundacao' : 'estrutura';
            $somas['concreto'][$grupo] += self::numero($celulas[$colunas['concreto']] ?? null);
            $somas['forma'][$grupo] += self::numero($celulas[$colunas['forma']] ?? null);
            if ($bitolas !== []) {
                foreach ($bitolas as $coluna => $bitola) {
                    $somas['aco'][(string) $bitola] = ($somas['aco'][(string) $bitola] ?? 0.0) + self::numero($celulas[$coluna] ?? null);
                }
            } else {
                $somas['aco']['total'] = ($somas['aco']['total'] ?? 0.0) + self::numero($celulas[$colunas['aco']] ?? null);
            }
        }

        $itens = [];
        $concreto = 'Concreto usinado bombeado' . ($fck ? ' fck ' . $fck . ' MPa' : ' estrutural');
        foreach (['fundacao' => 'Fundação', 'estrutura' => 'Estrutura'] as $grupo => $etapa) {
            if ($somas['concreto'][$grupo] > 0) {
                $itens[] = ['etapa' => $etapa, 'descricao' => $concreto . ' — ' . mb_strtolower($etapa), 'unidade' => 'M³', 'quantidade' => round($somas['concreto'][$grupo], 2), 'preco_unitario' => 0.0];
            }
            if ($somas['forma'][$grupo] > 0) {
                $itens[] = ['etapa' => $etapa, 'descricao' => 'Fôrma de madeira compensada — ' . mb_strtolower($etapa), 'unidade' => 'M²', 'quantidade' => round($somas['forma'][$grupo], 2), 'preco_unitario' => 0.0];
            }
        }
        foreach ($somas['aco'] as $bitola => $peso) {
            if ($peso <= 0) {
                continue;
            }
            $descricao = $bitola === 'total'
                ? 'Aço CA-50 para armadura, corte e dobra'
                : 'Aço ' . ((float) $bitola <= 5.0 ? 'CA-60' : 'CA-50') . ' ø ' . number_format((float) $bitola, 1, ',', '') . ' mm para armadura, corte e dobra';
            $itens[] = ['etapa' => 'Estrutura', 'descricao' => $descricao, 'unidade' => 'KG', 'quantidade' => round($peso, 2), 'preco_unitario' => 0.0];
        }
        return count($itens) >= 2 ? ['itens' => $itens, 'fck' => $fck] : null;
    }

    private static function numero(mixed $valor): float
    {
        return is_int($valor) || is_float($valor) ? (float) $valor : PlanilhaOrcamentoParser::numero($valor);
    }
}
