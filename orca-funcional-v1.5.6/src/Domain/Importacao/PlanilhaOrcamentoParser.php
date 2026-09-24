<?php
declare(strict_types=1);

namespace App\Domain\Importacao;

use App\Domain\Orcamento\OrcamentoCalculator;

/**
 * Interpreta uma aba de planilha orçamentária (matriz de células já calculadas).
 *
 * Reconhece: linha de cabeçalho em qualquer posição (com sub-cabeçalho "Unitário/Total"),
 * etapas numeradas ("1.", "2."), subtotais, preço material + mão de obra, preço sem/com BDI,
 * planilhas só de quantitativos e o total geral informado na própria planilha.
 */
final class PlanilhaOrcamentoParser
{
    private const LINHAS_CABECALHO = 40;

    /**
     * @param array<int, array<int, mixed>> $linhas índice da linha (1..n) => [índice da coluna (0..n) => valor]
     * @return array{aba:string,cabecalho:int,itens:list<array<string,mixed>>,etapas:list<string>,bdi_percentual:float,
     *               total_planilha:?float,total_importado:float,pontuacao:int,colunas:array<string,mixed>,avisos:list<string>}
     */
    public function analisar(array $linhas, string $aba = ''): array
    {
        $vazio = [
            'aba' => $aba, 'cabecalho' => 0, 'itens' => [], 'etapas' => [], 'bdi_percentual' => 0.0,
            'total_planilha' => null, 'total_importado' => 0.0, 'pontuacao' => 0, 'colunas' => [], 'avisos' => [],
            'area' => null, 'data_base' => null, 'texto_topo' => '',
        ];
        ksort($linhas);
        $cabecalho = $this->localizarCabecalho($linhas);
        if ($cabecalho === null) {
            return $vazio;
        }

        [$linhaCab, $usaAbaixo] = $cabecalho;
        $colunas = $this->mapearColunas($linhas, $linhaCab, $usaAbaixo);
        if ($colunas['descricao'] === null || ($colunas['quantidade'] === null && $colunas['unidade'] === null)) {
            return $vazio;
        }

        $inicio = $linhaCab + ($usaAbaixo ? 2 : 1);
        $rotuloDescricao = self::normalizar((string) ($linhas[$linhaCab][$colunas['descricao']] ?? ''));
        $itens = [];
        $etapas = [];
        $etapaAtual = null;
        // Título de seção logo acima do cabeçalho ("Esgoto", "Caixas de passagem")
        for ($acima = $linhaCab - 1; $acima >= $linhaCab - 2; $acima--) {
            $celulasAcima = $linhas[$acima] ?? [];
            if (count($celulasAcima) === 1 && is_string(reset($celulasAcima)) && mb_strlen(trim((string) reset($celulasAcima))) <= 40
                && preg_match('/\p{L}{3,}/u', (string) reset($celulasAcima)) && !preg_match('/planilha|orcament|lista|obra|cliente/i', self::normalizar((string) reset($celulasAcima)))) {
                $etapaAtual = self::titulo((string) reset($celulasAcima));
                break;
            }
        }
        $subetapaAtual = null;
        $totalPlanilha = null;
        $razoesBdi = [];

        foreach ($linhas as $numero => $celulas) {
            if ($numero < $inicio) {
                continue;
            }
            $descricao = self::texto($celulas[$colunas['descricao']] ?? null);
            $numeroItem = $colunas['item'] !== null ? self::texto($celulas[$colunas['item']] ?? null) : '';
            $unidade = $colunas['unidade'] !== null ? self::texto($celulas[$colunas['unidade']] ?? null) : '';
            if ($unidade !== '' && ($this->ehLinhaDeTotal(self::normalizar($unidade)) || mb_strlen($unidade) > 12 || trim($unidade, '-– ') === '')) {
                $unidade = '';
            }
            $quantidade = $colunas['quantidade'] !== null ? self::numero($celulas[$colunas['quantidade']] ?? null) : 0.0;
            $total = $colunas['total'] !== null ? self::numero($celulas[$colunas['total']] ?? null) : 0.0;
            $normalizada = self::normalizar($descricao);

            if ($descricao === '') {
                // Título de seção fora da coluna de descrição ("Esgoto", "PVC Acessórios")
                $textos = array_values(array_filter($celulas, static fn ($v): bool => is_string($v) && preg_match('/\p{L}{3,}/u', $v) === 1));
                if (count($textos) === 1 && count($celulas) === 1 && mb_strlen($textos[0]) <= 60) {
                    $etapaAtual = self::titulo($textos[0]);
                    $etapas[] = $etapaAtual;
                }
                continue;
            }
            if (self::numero($descricao) > 0 && !preg_match('/[a-z]/i', $descricao)) {
                continue;
            }
            foreach ($colunas['complementos'] ?? [] as $complemento) {
                $extra = self::texto($celulas[$complemento] ?? null);
                if ($extra !== '' && !str_contains(mb_strtolower($descricao), mb_strtolower($extra))) {
                    $descricao .= ' ' . $extra;
                }
            }
            if ($this->ehLinhaDeTotal($normalizada)) {
                if (!str_starts_with($normalizada, 'sub') && preg_match('/geral|global|investimento|orcamento|final|obra$/', $normalizada)) {
                    $maior = max(array_map([self::class, 'numero'], $celulas) ?: [0.0]);
                    $totalPlanilha = $total > 0 ? $total : ($maior > 0 ? $maior : $totalPlanilha);
                    if ($itens !== []) {
                        break; // o que vem depois do total geral são observações ("não orçado" etc.)
                    }
                }
                continue;
            }
            if ($normalizada === $rotuloDescricao) {
                continue;
            }

            $preco = 0.0;
            foreach ($colunas['preco'] as $coluna) {
                $preco += self::numero($celulas[$coluna] ?? null);
            }
            $comBdi = 0.0;
            if ($colunas['preco_com_bdi'] !== null && $colunas['preco'] !== []) {
                $comBdi = self::numero($celulas[$colunas['preco_com_bdi']] ?? null);
                if ($preco > 0 && $comBdi > 0) {
                    $razoesBdi[] = $comBdi / $preco;
                }
            }

            // Linha de grupo: sem unidade e sem quantidade (etapa "1.", subgrupo "1.1", meta CAIXA)
            if ($quantidade <= 0 && $unidade === '') {
                $nivel = $numeroItem !== '' ? count(array_filter(explode('.', trim($numeroItem, '. ')), 'strlen')) : 0;
                if ($nivel === 1 || ($nivel === 0 && $preco <= 0 && self::maiuscula($descricao))) {
                    $etapaAtual = self::titulo($descricao);
                    $subetapaAtual = null;
                    $etapas[] = $etapaAtual;
                } elseif ($nivel === 2) {
                    $subetapaAtual = self::titulo($descricao);
                }
                continue;
            }

            if ($quantidade <= 0 && $preco > 0 && $total > 0) {
                $quantidade = $total / $preco;
            }
            if ($preco <= 0 && $quantidade > 0 && $total > 0) {
                $preco = $total / $quantidade;
            }
            if ($quantidade <= 0 && $preco <= 0 && $total > 0 && $unidade !== '') {
                $quantidade = 1.0;
                $preco = $total;
            }
            if ($quantidade <= 0 && $preco > 0 && $unidade !== '') {
                $quantidade = 1.0;
            }
            // O total da própria linha prevalece (ex.: "10 %" com preço já totalizado)
            if ($quantidade > 0 && $total > 0 && abs($quantidade * $preco - $total) > max(0.05, $total * 0.005)) {
                $preco = $total / $quantidade;
            }
            if ($quantidade <= 0) {
                continue;
            }

            $codigo = $colunas['codigo'] !== null ? self::texto($celulas[$colunas['codigo']] ?? null) : '';
            $itens[] = [
                'codigo' => $codigo !== '' ? $codigo : $numeroItem,
                'item' => $numeroItem,
                'etapa' => $etapaAtual,
                '_subetapa' => $subetapaAtual,
                'descricao' => mb_substr(preg_replace('/\s+/u', ' ', $descricao) ?? $descricao, 0, 255),
                'unidade' => $unidade !== '' ? $unidade : (string) ($colunas['unidade_fixa'] ?? 'UN'),
                'quantidade' => round($quantidade, 4),
                'preco_unitario' => round($preco, 4),
                'categoria' => $etapaAtual ?? '',
                '_com_bdi' => $comBdi,
            ];
        }

        $bdi = 0.0;
        if ($razoesBdi !== []) {
            sort($razoesBdi);
            $mediana = $razoesBdi[intdiv(count($razoesBdi), 2)];
            $bdi = $mediana > 1 && $mediana < 3 ? round(($mediana - 1) * 100, 2) : 0.0;
        }
        if ($bdi <= 0) {
            $bdi = $this->bdiDoTopo($linhas, $linhaCab);
        }
        // Orçamentos CAIXA têm uma única "meta" no nível 1: as etapas reais estão no nível 2
        $principais = array_unique(array_filter(array_column($itens, 'etapa')));
        $secundarias = array_unique(array_filter(array_column($itens, '_subetapa')));
        $usarSubetapa = count($principais) <= 1 && count($secundarias) > 1;
        if ($usarSubetapa) {
            $etapas = array_values($secundarias);
        }
        foreach ($itens as &$item) {
            if ($usarSubetapa && $item['_subetapa'] !== null) {
                $item['etapa'] = $item['_subetapa'];
                $item['categoria'] = $item['_subetapa'];
            }
            unset($item['_subetapa']);
            // Preserva o preço final de cada linha quando a planilha usa fatores de BDI diferentes
            if ($bdi > 0 && $item['_com_bdi'] > 0) {
                $item['preco_unitario'] = round($item['_com_bdi'] / (1 + $bdi / 100), 4);
            }
            unset($item['_com_bdi']);
        }
        unset($item);

        $totalImportado = OrcamentoCalculator::total($itens);
        $avisos = [];
        if ($totalPlanilha !== null && $totalPlanilha > 0) {
            $comBdi = round($totalImportado * (1 + $bdi / 100), 2);
            $diferenca = min(abs($totalImportado - $totalPlanilha), abs($comBdi - $totalPlanilha));
            if ($diferenca > max(1.0, $totalPlanilha * 0.005)) {
                $avisos[] = sprintf(
                    'Total importado (R$ %s) difere do total da planilha (R$ %s). Confira os itens.',
                    number_format($comBdi, 2, ',', '.'),
                    number_format($totalPlanilha, 2, ',', '.')
                );
            }
        }
        $comPreco = count(array_filter($itens, static fn (array $i): bool => $i['preco_unitario'] > 0));
        if ($itens !== [] && $comPreco === 0) {
            $avisos[] = 'A planilha tem apenas quantitativos: os preços precisam ser preenchidos.';
        }

        return [
            'aba' => $aba,
            'cabecalho' => $linhaCab,
            'itens' => $itens,
            'etapas' => array_values(array_unique($etapas)),
            'bdi_percentual' => $bdi,
            'total_planilha' => $totalPlanilha,
            'total_importado' => $totalImportado,
            'pontuacao' => count($itens) + 2 * $comPreco,
            'colunas' => $colunas,
            'avisos' => $avisos,
            'area' => $this->areaDoTopo($linhas, $linhaCab),
            'texto_topo' => $this->textoDoTopo($linhas, $linhaCab),
            'data_base' => $this->dataDoTopo($linhas, $linhaCab),
        ];
    }

    /** Textos acima do cabeçalho (título da obra), usados só para classificar a tipologia. */
    private function textoDoTopo(array $linhas, int $linhaCab): string
    {
        $partes = [];
        foreach ($linhas as $numero => $celulas) {
            if ($numero >= $linhaCab) {
                break;
            }
            foreach ($celulas as $valor) {
                if (is_string($valor) && preg_match('/\p{L}{3,}/u', $valor)) {
                    $partes[] = trim($valor);
                }
            }
        }
        return mb_substr(implode(' ', $partes), 0, 500);
    }

    /** Área informada no cabeçalho da planilha ("Área | 642,9 | m²"). */
    private function areaDoTopo(array $linhas, int $linhaCab): ?float
    {
        foreach ($linhas as $numero => $celulas) {
            if ($numero >= $linhaCab) {
                break;
            }
            $colunas = array_keys($celulas);
            foreach ($colunas as $posicao => $coluna) {
                $texto = self::normalizar(self::texto($celulas[$coluna]));
                if (preg_match('/^area( constru\w+| total| privativa)?\s*:?\s*([\d.]+,\d+)?\s*(m2|m²)?$/u', $texto, $m)) {
                    $valor = isset($m[2]) && $m[2] !== '' ? OrcamentoCalculator::decimal($m[2]) : 0.0;
                    foreach (array_slice($colunas, $posicao + 1, 2) as $vizinha) {
                        $valor = $valor > 0 ? $valor : self::numero($celulas[$vizinha]);
                    }
                    if ($valor >= 15 && $valor <= 500000) {
                        return round($valor, 2);
                    }
                }
            }
        }
        return null;
    }

    /** Data-base do orçamento ("Data: Março/26", "Data:Julho/2015", "10/03/2026"). */
    private function dataDoTopo(array $linhas, int $linhaCab): ?string
    {
        $meses = ['jan' => 1, 'fev' => 2, 'mar' => 3, 'abr' => 4, 'mai' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8, 'set' => 9, 'out' => 10, 'nov' => 11, 'dez' => 12];
        foreach ($linhas as $numero => $celulas) {
            if ($numero >= $linhaCab) {
                break;
            }
            foreach ($celulas as $valor) {
                $texto = self::normalizar(self::texto($valor));
                if (!str_contains($texto, 'data')) {
                    continue;
                }
                if (preg_match('/(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)[a-z]*\.?\s*[\/ de]*\s*(\d{2,4})/', $texto, $m)) {
                    $ano = (int) $m[2] < 100 ? 2000 + (int) $m[2] : (int) $m[2];
                    return sprintf('%04d-%02d-01', $ano, $meses[$m[1]]);
                }
                if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{2,4})/', $texto, $m)) {
                    $ano = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
                    return sprintf('%04d-%02d-01', $ano, (int) $m[2]);
                }
            }
        }
        return null;
    }

    /** @return array{0:int,1:bool}|null linha do cabeçalho e se a linha abaixo é sub-cabeçalho */
    private function localizarCabecalho(array $linhas): ?array
    {
        $melhor = null;
        $melhorPontos = 0;
        $contador = 0;
        foreach ($linhas as $numero => $celulas) {
            if (++$contador > self::LINHAS_CABECALHO) {
                break;
            }
            $pontos = 0;
            $temDescricao = false;
            $temQuantidadeOuUnidade = false;
            $temItem = false;
            foreach ($celulas as $valor) {
                $texto = self::normalizar(self::texto($valor));
                if ($texto === '' || mb_strlen($texto) > 60) {
                    continue;
                }
                if (self::ehRotuloDescricao($texto)) { $pontos += 3; $temDescricao = true; }
                elseif (self::ehRotuloDescricaoFraco($texto)) { $pontos += 2; $temDescricao = true; }
                if (self::ehRotuloUnidade($texto) || self::ehRotuloQuantidade($texto)) { $pontos += 2; $temQuantidadeOuUnidade = true; }
                if (self::ehRotuloItem($texto) || self::ehRotuloCodigo($texto)) { $pontos += 1; $temItem = true; }
                if (str_contains($texto, 'unit') || str_contains($texto, 'total') || str_contains($texto, 'preco') || str_contains($texto, 'valor')) { $pontos += 1; }
            }
            // Listas simples "Item | Unidade | Quantidade" usam a coluna Item como descrição
            $listaSimples = !$temDescricao && $temItem && $pontos >= 5;
            if (($temDescricao || $listaSimples) && $temQuantidadeOuUnidade && $pontos > $melhorPontos) {
                $melhor = $numero;
                $melhorPontos = $pontos;
            }
        }
        if ($melhor === null) {
            return null;
        }
        return [$melhor, $this->ehSubCabecalho($linhas[$melhor + 1] ?? [])];
    }

    private function ehSubCabecalho(array $celulas): bool
    {
        $temRotulo = false;
        foreach ($celulas as $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }
            if (is_int($valor) || is_float($valor)) {
                return false;
            }
            $texto = self::normalizar((string) $valor);
            if (preg_match('/^\d+([.,]\d+)*\.?$/', $texto)) {
                return false;
            }
            if (preg_match('/unit|total|mat|mao|m\.o|preco|valor|custo|bdi/', $texto)) {
                $temRotulo = true;
            }
        }
        return $temRotulo;
    }

    /** @return array{item:?int,codigo:?int,descricao:?int,unidade:?int,quantidade:?int,preco:list<int>,preco_com_bdi:?int,total:?int,unidade_fixa:?string} */
    private function mapearColunas(array $linhas, int $linhaCab, bool $usaAbaixo): array
    {
        $cab = $linhas[$linhaCab] ?? [];
        $acima = $linhas[$linhaCab - 1] ?? [];
        $abaixo = $usaAbaixo ? ($linhas[$linhaCab + 1] ?? []) : [];
        $acimaValido = $this->ehSubCabecalho($acima);
        $ultimaColuna = max(array_merge([0], array_keys($cab), array_keys($abaixo), $acimaValido ? array_keys($acima) : []));

        $grupoCab = '';
        $grupoAcima = '';
        $rotulos = [];
        for ($c = 0; $c <= $ultimaColuna; $c++) {
            $textoCab = self::normalizar(self::texto($cab[$c] ?? null));
            $textoAbaixo = self::normalizar(self::texto($abaixo[$c] ?? null));
            $textoAcima = $acimaValido ? self::normalizar(self::texto($acima[$c] ?? null)) : '';
            if ($textoCab !== '') {
                $grupoCab = $textoCab;
            }
            if ($textoAcima !== '') {
                $grupoAcima = $textoAcima;
            }
            // Cabeçalho mesclado (ex.: "MATERIAL" sobre "Unitário | Total")
            $proprio = trim(($textoCab !== '' ? $textoCab : ($textoAbaixo !== '' ? $grupoCab : '')) . ' ' . $textoAbaixo);
            $grupo = trim($grupoAcima . ' ' . ($textoCab === '' && $textoAbaixo !== '' ? $grupoCab : ''));
            if ($textoCab === '' && $textoAbaixo === '') {
                $grupoCab = '';
                $grupo = $textoAcima;
            }
            $rotulos[$c] = ['proprio' => $proprio, 'celula' => $textoAbaixo !== '' ? $textoAbaixo : $textoCab, 'grupo' => $grupo];
        }

        $mapa = ['item' => null, 'codigo' => null, 'descricao' => null, 'unidade' => null, 'quantidade' => null,
            'preco' => [], 'preco_com_bdi' => null, 'total' => null, 'unidade_fixa' => null, 'complementos' => []];
        $unitarios = [];
        $totais = [];
        foreach ($rotulos as $c => $rotulo) {
            $proprio = $rotulo['proprio'];
            if ($proprio === '') {
                continue;
            }
            $qualificador = $proprio . ' ' . $rotulo['grupo'];
            if ($mapa['descricao'] === null && self::ehRotuloDescricao($proprio)) { $mapa['descricao'] = $c; continue; }
            if ($mapa['unidade'] === null && self::ehRotuloUnidade($proprio)) { $mapa['unidade'] = $c; continue; }
            if ($mapa['quantidade'] === null && self::ehRotuloQuantidade($proprio)) { $mapa['quantidade'] = $c; continue; }
            if ($mapa['item'] === null && self::ehRotuloItem($proprio)) { $mapa['item'] = $c; continue; }
            if ($mapa['codigo'] === null && self::ehRotuloCodigo($proprio)) { $mapa['codigo'] = $c; continue; }
            if (preg_match('/^(item|especificac\w*|dimens\w*|diametro|bitola|modelo|medida|tamanho|secao|espessura)\b/', $proprio)) {
                $mapa['complementos'][] = $c; // "Item: CE- 60x60 cm", "Diâmetro: 100 mm" completam a descrição
                continue;
            }
            // "TOTAL" dentro do grupo "CUSTO/PREÇO UNITÁRIO" é o unitário composto (material + mão de obra), não o total da linha
            $totalDoUnitario = str_contains($rotulo['celula'], 'total') && str_contains($rotulo['grupo'], 'unit') && !preg_match('/preco total|valor total|custo total/', $rotulo['grupo']);
            if (str_contains($rotulo['celula'], 'unit') || $totalDoUnitario || preg_match('/preco|valor|custo/', $rotulo['celula']) && !str_contains($rotulo['celula'], 'total')) {
                $unitarios[$c] = $qualificador;
            } elseif (str_contains($proprio, 'total')) {
                $totais[$c] = $qualificador;
            }
        }
        $inicioDados = $linhaCab + ($usaAbaixo ? 2 : 1);
        if ($mapa['descricao'] === null) {
            // Sem "Descrição": usa a primeira coluna textual entre "Serviço/Material/Item..."
            $candidatas = [];
            foreach ($rotulos as $c => $rotulo) {
                if ((self::ehRotuloDescricaoFraco($rotulo['proprio']) || $c === $mapa['item']) && !isset($unitarios[$c]) && !isset($totais[$c])) {
                    $candidatas[] = $c;
                }
            }
            foreach ($candidatas as $c) {
                if ($this->colunaTextual($linhas, $inicioDados, $c)) {
                    $mapa['descricao'] = $c;
                    if ($c === $mapa['item']) {
                        $mapa['item'] = null;
                    }
                    break;
                }
            }
        }
        if ($mapa['descricao'] === null && $mapa['item'] !== null) {
            // Coluna de descrição sem rótulo ao lado do "Item" ("ITEM | (vazio) | UNID. | QUANT.")
            $ocupadas = array_filter([$mapa['unidade'], $mapa['quantidade'], $mapa['codigo']], static fn ($c): bool => $c !== null);
            $limite = $ocupadas !== [] ? min($ocupadas) : $mapa['item'] + 3;
            for ($c = $mapa['item'] + 1; $c < $limite; $c++) {
                if ($rotulos[$c]['proprio'] === '' && $this->colunaTextual($linhas, $inicioDados, $c)) {
                    $mapa['descricao'] = $c;
                    break;
                }
            }
        }
        $mapa['complementos'] = array_values(array_diff($mapa['complementos'], [$mapa['descricao']]));
        if ($unitarios === []) {
            // Preço dividido em "Material" e "Mão de obra" sem a palavra "unitário"
            $vistos = [];
            foreach ($rotulos as $c => $rotulo) {
                $tipo = preg_match('/^(material|materiais|mat)$/', $rotulo['celula']) ? 'mat' : (preg_match('/^(mao de obra|mao obra|m\.o\.?|mo)$/', $rotulo['celula']) ? 'mao' : null);
                if ($tipo !== null && !isset($vistos[$tipo]) && $c !== $mapa['descricao']) {
                    $vistos[$tipo] = true;
                    $unitarios[$c] = $tipo === 'mat' ? 'material' : 'mao de obra';
                }
            }
        }

        $comBdi = array_filter($unitarios, static fn (string $q): bool => (bool) preg_match('/c\/ ?bdi|com bdi/', $q));
        $semBdi = array_diff_key($unitarios, $comBdi);
        $partes = array_filter($semBdi, static fn (string $q): bool => (bool) preg_match('/mat|mao|m\.o|obra/', $q));
        if (count($partes) >= 2) {
            $mapa['preco'] = array_keys($partes);
        } elseif ($semBdi !== []) {
            $mapa['preco'] = [array_key_first($semBdi)];
        } elseif ($comBdi !== []) {
            $mapa['preco'] = [array_key_first($comBdi)];
            $comBdi = [];
        }
        $mapa['preco_com_bdi'] = $comBdi !== [] ? array_key_first($comBdi) : null;
        if ($totais !== []) {
            $gerais = array_filter($totais, static fn (string $q): bool => !preg_match('/mat|mao|m\.o/', $q));
            $semBdiTotais = array_filter($gerais ?: $totais, static fn (string $q): bool => !preg_match('/c\/ ?bdi|com bdi/', $q));
            $mapa['total'] = array_key_last($semBdiTotais ?: ($gerais ?: $totais));
        }
        return $mapa;
    }

    private function colunaTextual(array $linhas, int $inicio, int $coluna): bool
    {
        $textos = 0;
        $numeros = 0;
        foreach ($linhas as $numero => $celulas) {
            if ($numero < $inicio || !isset($celulas[$coluna])) {
                continue;
            }
            $valor = $celulas[$coluna];
            if (is_int($valor) || is_float($valor) || preg_match('/^[\d.,\s]+$/', (string) $valor)) {
                $numeros++;
            } elseif (preg_match('/\p{L}{3,}/u', (string) $valor)) {
                $textos++;
            }
            if ($textos + $numeros >= 15) {
                break;
            }
        }
        return $textos > $numeros;
    }

    private function bdiDoTopo(array $linhas, int $linhaCab): float
    {
        foreach ($linhas as $numero => $celulas) {
            if ($numero >= $linhaCab) {
                break;
            }
            $colunas = array_keys($celulas);
            foreach ($colunas as $posicao => $coluna) {
                if (!str_contains(self::normalizar(self::texto($celulas[$coluna])), 'bdi')) {
                    continue;
                }
                foreach (array_slice($colunas, $posicao + 1, 3) as $vizinha) {
                    $valor = self::numero($celulas[$vizinha]);
                    if ($valor > 0 && $valor < 1) {
                        return round($valor * 100, 2);
                    }
                    if ($valor >= 1 && $valor <= 100) {
                        return round($valor, 2);
                    }
                }
            }
        }
        return 0.0;
    }

    private function ehLinhaDeTotal(string $texto): bool
    {
        $texto = str_replace(['-', '_'], ' ', $texto);
        return (bool) preg_match('/^(sub ?)?tota(l|is)\b|^soma\b|\btotal (do|da|de|geral|global|item|etapa|parcial)\b|^valor total/', $texto);
    }

    private static function ehRotuloDescricao(string $t): bool
    {
        return (bool) preg_match('/descri|discrimin|especificac/', $t);
    }

    private static function ehRotuloDescricaoFraco(string $t): bool
    {
        return (bool) preg_match('/^(servicos?|material|materiais|produto|insumo|luminaria|familia|nome|especificacao)\b/', $t);
    }

    private static function ehRotuloUnidade(string $t): bool
    {
        return (bool) preg_match('/^(un|und|unid|unidade|unit\.)\.?$|^unid/', $t);
    }

    private static function ehRotuloQuantidade(string $t): bool
    {
        return (bool) preg_match('/^(quant|qtd|qtde|qde|quantidade)|^comprimento \(m\)$|^metragem/', $t);
    }

    private static function ehRotuloItem(string $t): bool
    {
        return (bool) preg_match('/^(item|itens|n|no|nº|n°|seq)\.?$/u', $t);
    }

    private static function ehRotuloCodigo(string $t): bool
    {
        return (bool) preg_match('/^(ref|cod|codigo|sinapi|referencia)\b|codigo/', $t);
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
        return trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
    }

    public static function texto(mixed $valor): string
    {
        if ($valor === null || is_bool($valor)) {
            return '';
        }
        if (is_float($valor) || is_int($valor)) {
            return rtrim(rtrim(number_format((float) $valor, 6, '.', ''), '0'), '.');
        }
        return trim((string) $valor);
    }

    public static function numero(mixed $valor): float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (!is_string($valor)) {
            return 0.0;
        }
        $limpo = trim(str_replace(['R$', ' ', "\u{a0}"], '', $valor));
        if ($limpo === '' || str_starts_with($limpo, '=') || !preg_match('/^-?[\d.,]+%?$/', $limpo)) {
            return 0.0;
        }
        $percentual = str_ends_with($limpo, '%');
        $numero = OrcamentoCalculator::decimal(rtrim($limpo, '%'));
        return $percentual ? $numero / 100 : $numero;
    }

    private static function maiuscula(string $texto): bool
    {
        $letras = preg_replace('/[^\p{L}]/u', '', $texto) ?? '';
        return mb_strlen($letras) >= 4 && mb_strtoupper($letras) === $letras;
    }

    private static function titulo(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
        return mb_substr(self::maiuscula($texto) ? mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE) : $texto, 0, 150);
    }
}
