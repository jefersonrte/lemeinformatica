<?php
declare(strict_types=1);

namespace App\Domain\Estimativa;

use App\Domain\Importacao\PlanilhaOrcamentoParser;
use App\Infrastructure\PdfTextExtractor;
use App\Infrastructure\PlanilhaLoader;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Prévia de obra: planta (PDF) e/ou planilha de quantitativos → materiais, quantidades e custo aproximado.
 */
final class PreviaObraService
{
    public const EDIFICACOES = ['residencial', 'multifamiliar', 'comercial', 'reforma'];

    public function __construct(
        private readonly PDO $db,
        private readonly PdfTextExtractor $pdf = new PdfTextExtractor(),
        private readonly PlanilhaLoader $planilhas = new PlanilhaLoader(),
    ) {
    }

    /**
     * @param array{planta?:?string,planta_nome?:?string,planilha?:?string,planilha_ext?:?string,area?:?float,tipologia?:?string,padrao?:?string,descricao?:?string} $entrada
     * @return array<string,mixed>
     */
    public function gerar(array $entrada): array
    {
        $avisos = [];
        $planta = null;
        if (!empty($entrada['planta'])) {
            $ext = strtolower(pathinfo((string) ($entrada['planta_nome'] ?? $entrada['planta']), PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                try {
                    $planta = (new PlantaAnalyzer())->analisar($this->pdf->extrair((string) $entrada['planta']));
                    $avisos = array_merge($avisos, $planta['avisos']);
                } catch (Throwable $exception) {
                    $avisos[] = $exception->getMessage();
                }
            } else {
                $avisos[] = 'Plantas em imagem não têm texto legível: informe a área construída para a prévia.';
            }
        }

        $quantitativos = null;
        if (!empty($entrada['planilha'])) {
            $quantitativos = $this->quantitativos((string) $entrada['planilha'], (string) ($entrada['planilha_ext'] ?? ''));
            $avisos = array_merge($avisos, $quantitativos['avisos']);
        }

        $area = (float) ($entrada['area'] ?? 0) ?: (float) ($planta['area_construida'] ?? 0);
        $tipologia = (string) ($entrada['tipologia'] ?? '');
        if (!isset(Classificador::TIPOLOGIAS[$tipologia])) {
            $tipologia = Classificador::tipologia((string) ($entrada['descricao'] ?? '') . ' ' . ($entrada['planta_nome'] ?? ''));
        }

        $estimativa = null;
        if ($area > 0 && in_array($tipologia, self::EDIFICACOES, true)) {
            try {
                $estimativa = (new EstimativaService($this->db))->estimar([
                    'area' => $area, 'tipologia' => $tipologia, 'padrao' => $entrada['padrao'] ?? 'medio',
                    'esquadrias' => $planta['esquadrias'] ?? [],
                ]);
                $avisos = array_merge($avisos, $estimativa['avisos']);
            } catch (InvalidArgumentException $exception) {
                $avisos[] = $exception->getMessage();
            }
        } elseif ($area <= 0 && $quantitativos === null) {
            $avisos[] = 'Não foi possível identificar a área construída. Informe a área em m² e gere novamente.';
        } elseif (!in_array($tipologia, self::EDIFICACOES, true) && $quantitativos === null) {
            $avisos[] = 'Para ' . mb_strtolower(Classificador::TIPOLOGIAS[$tipologia]) . ' o custo por m² varia demais entre obras: importe a planilha de quantitativos para precificar item a item.';
        }

        return [
            'gerado_em' => date('c'),
            'area' => $area,
            'tipologia' => $tipologia,
            'padrao' => $entrada['padrao'] ?? 'medio',
            'planta' => $planta,
            'quantitativos' => $quantitativos,
            'estimativa' => $estimativa,
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /** Lê a planilha de quantitativos (com ou sem preço) e sugere preços pela base de referência. */
    private function quantitativos(string $caminho, string $ext): array
    {
        try {
            $abas = $ext === 'csv'
                ? [['nome' => 'CSV', 'linhas' => $this->csv($caminho)]]
                : $this->planilhas->carregar($caminho, $ext);
        } catch (Throwable $exception) {
            return ['itens' => [], 'total' => 0.0, 'total_sugerido' => 0.0, 'aba' => '', 'avisos' => [$exception->getMessage()]];
        }
        $parser = new PlanilhaOrcamentoParser();
        $itens = [];
        $abasUsadas = [];
        foreach ($abas as $aba) {
            $analise = $parser->analisar($aba['linhas'], $aba['nome']);
            if ($analise['itens'] === []) {
                continue;
            }
            $abasUsadas[] = $aba['nome'];
            foreach ($analise['itens'] as $item) {
                $item['etapa'] = $item['etapa'] ?? (count($abas) > 1 ? $aba['nome'] : null);
                $itens[] = $item;
            }
        }
        if ($itens === []) {
            return ['itens' => [], 'total' => 0.0, 'total_sugerido' => 0.0, 'aba' => '', 'avisos' => ['Nenhum item com descrição, unidade e quantidade foi encontrado na planilha de quantitativos.']];
        }
        $precificados = (new EstimativaService($this->db))->precificar($itens);
        $semPreco = 0;
        foreach ($precificados as &$item) {
            $item['preco_final'] = $item['preco_unitario'] > 0 ? (float) $item['preco_unitario'] : (float) $item['preco_sugerido'];
            $item['origem_preco'] = $item['preco_unitario'] > 0 ? 'planilha' : ($item['preco_sugerido'] > 0 ? 'sugerido' : 'sem preço');
            $item['total'] = round($item['quantidade'] * $item['preco_final'], 2);
            $semPreco += $item['preco_final'] <= 0 ? 1 : 0;
        }
        unset($item);
        $avisos = $semPreco > 0 ? [$semPreco . ' item(ns) sem preço semelhante na base: complete manualmente no orçamento.'] : [];
        return [
            'itens' => $precificados,
            'aba' => implode(', ', $abasUsadas),
            'total' => round(array_sum(array_column($precificados, 'total')), 2),
            'total_sugerido' => round(array_sum(array_map(static fn (array $i): float => $i['origem_preco'] === 'sugerido' ? $i['total'] : 0.0, $precificados)), 2),
            'avisos' => $avisos,
        ];
    }

    /** @return array<int,array<int,string>> */
    private function csv(string $caminho): array
    {
        $linhas = [];
        $numero = 0;
        foreach (file($caminho, FILE_IGNORE_NEW_LINES) ?: [] as $linha) {
            $numero++;
            $separador = substr_count($linha, ';') >= substr_count($linha, ',') ? ';' : ',';
            $linhas[$numero] = array_filter(str_getcsv($linha, $separador, '"', ''), static fn ($v) => $v !== '');
        }
        return $linhas;
    }

    /**
     * Itens de orçamento a partir da prévia.
     *  - "parametrico": materiais-chave + esquadrias da planta + "demais serviços" para fechar cada etapa;
     *  - "quantitativos": os itens da planilha com o preço da planilha ou o sugerido.
     * @return list<array<string,mixed>>
     */
    public function itensOrcamento(array $previa, string $modo): array
    {
        if ($modo === 'quantitativos') {
            return array_map(static fn (array $i): array => [
                'etapa' => $i['etapa'] ?? 'Quantitativos', 'descricao' => $i['descricao'], 'unidade' => $i['unidade'],
                'quantidade' => $i['quantidade'], 'preco_unitario' => $i['preco_final'], 'codigo' => $i['codigo'] ?? null,
            ], $previa['quantitativos']['itens'] ?? []);
        }
        $estimativa = $previa['estimativa'] ?? null;
        if (!$estimativa) {
            throw new InvalidArgumentException('Não há estimativa paramétrica nesta prévia.');
        }
        $porEtapa = [];
        foreach ($estimativa['materiais'] as $m) {
            $porEtapa[$m['etapa']][] = [
                'descricao' => $m['rotulo'], 'unidade' => $m['unidade'], 'quantidade' => $m['quantidade'], 'preco_unitario' => $m['preco_unitario'],
            ];
        }
        foreach ($estimativa['esquadrias']['itens'] ?? [] as $e) {
            $porEtapa['esquadrias'][] = [
                'codigo' => $e['codigo'],
                'descricao' => ucfirst(str_replace('_', '-', $e['tipo'])) . ' ' . $e['codigo'] . ' ' . number_format($e['largura'], 2, ',', '') . ' x ' . number_format($e['altura'], 2, ',', '') . ($e['descricao'] !== '' ? ' — ' . mb_substr($e['descricao'], 0, 120) : ''),
                'unidade' => $e['unidade'],
                'quantidade' => $e['unidade'] === 'UN' ? $e['quantidade'] : $e['area_total'],
                'preco_unitario' => $e['preco_unitario'],
            ];
        }
        $itens = [];
        $etapasPresentes = array_column($estimativa['etapas'], 'chave');
        foreach (array_diff(array_keys($porEtapa), $etapasPresentes) as $semEtapa) {
            $estimativa['etapas'][] = ['chave' => $semEtapa, 'rotulo' => Classificador::rotuloEtapa($semEtapa), 'percentual' => 0, 'valor' => 0.0];
        }
        foreach ($estimativa['etapas'] as $etapa) {
            $linhas = $porEtapa[$etapa['chave']] ?? [];
            $subtotal = array_sum(array_map(static fn (array $l): float => $l['quantidade'] * $l['preco_unitario'], $linhas));
            // Quando os materiais-chave passam do valor paramétrico da etapa, os preços são ajustados
            // proporcionalmente para o orçamento fechar com a prévia (as quantidades são mantidas).
            $fator = $subtotal > $etapa['valor'] && $subtotal > 0 ? $etapa['valor'] / $subtotal : 1.0;
            foreach ($linhas as $linha) {
                if ($fator < 1.0) {
                    $linha['preco_unitario'] = round($linha['preco_unitario'] * $fator, 4);
                }
                $itens[] = $linha + ['etapa' => $etapa['rotulo']];
            }
            $subtotal *= $fator;
            $restante = round($etapa['valor'] - $subtotal, 2);
            if ($restante > 0.01) {
                $itens[] = [
                    'etapa' => $etapa['rotulo'],
                    'descricao' => ($linhas === [] ? '' : 'Demais serviços, insumos e mão de obra — ') . $etapa['rotulo'] . ' (estimativa paramétrica)',
                    'unidade' => 'VB', 'quantidade' => 1, 'preco_unitario' => $restante,
                ];
            }
        }
        return $itens;
    }
}
