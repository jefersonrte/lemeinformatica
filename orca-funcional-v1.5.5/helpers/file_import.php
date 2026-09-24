<?php
// helpers/file_import.php — Leitura de planilhas Excel (CSV/XLSX), XML e PDF para extração de itens de orçamento

require_once __DIR__ . '/../vendor/autoload.php'; // PhpSpreadsheet


/**
 * Recebe um arquivo $_FILES e retorna array de itens:
 * [['descricao'=>..., 'unidade'=>..., 'quantidade'=>..., 'codigo'=>..., 'categoria'=>...], ...]
 */
function importarArquivo(array $file): array {
    $tmp  = $file['tmp_name'];
    $nome = strtolower($file['name']);
    $ext  = pathinfo($nome, PATHINFO_EXTENSION);

    return match(true) {
        in_array($ext, ['xlsx','xls','ods','xlsb'])   => importarExcel($tmp, $ext),
        $ext === 'csv'                                 => importarCsv($tmp),
        $ext === 'xml'                                 => importarXml($tmp),
        $ext === 'pdf'                                 => importarPdf($tmp),
        default                                        => [],
    };
}

function importarExcel(string $tmp, string $ext): array {
    return importarPlanilhaDetalhada($tmp, $ext)['itens'];
}

/**
 * Importa uma planilha orçamentária escolhendo a aba mais completa.
 * Retorna itens com etapa, o BDI detectado, o total da planilha e avisos para conferência.
 *
 * @return array{itens:list<array<string,mixed>>,aba:string,abas:list<array{nome:string,itens:int}>,bdi_percentual:float,
 *               total_planilha:?float,total_importado:float,avisos:list<string>,erro:?string}
 */
function importarPlanilhaDetalhada(string $tmp, string $ext = '', ?string $abaPreferida = null): array {
    $resultado = ['itens' => [], 'aba' => '', 'abas' => [], 'bdi_percentual' => 0.0, 'total_planilha' => null,
        'total_importado' => 0.0, 'avisos' => [], 'erro' => null];
    try {
        $abas = strtolower($ext) === 'csv'
            ? [['nome' => 'CSV', 'oculta' => false, 'linhas' => _lerCsvComoMatriz($tmp)]]
            : (new \App\Infrastructure\PlanilhaLoader())->carregar($tmp, $ext);
    } catch (Throwable $e) {
        $resultado['erro'] = $e->getMessage();
        return $resultado;
    }

    $parser = new \App\Domain\Importacao\PlanilhaOrcamentoParser();
    $melhor = null;
    $escolhaFixa = false;
    foreach ($abas as $aba) {
        $analise = $parser->analisar($aba['linhas'], $aba['nome']);
        $resultado['abas'][] = ['nome' => $aba['nome'], 'itens' => count($analise['itens'])];
        if ($abaPreferida !== null && $aba['nome'] === $abaPreferida && $analise['itens'] !== []) {
            $melhor = $analise;
            $escolhaFixa = true;
        } elseif (!$escolhaFixa && ($melhor === null || $analise['pontuacao'] > $melhor['pontuacao'])) {
            $melhor = $analise;
        }
    }
    if ($melhor === null || $melhor['itens'] === []) {
        $resultado['erro'] = 'Nenhuma aba com cabeçalho de orçamento (descrição, unidade e quantidade) foi encontrada.';
        return $resultado;
    }
    foreach (['itens', 'aba', 'bdi_percentual', 'total_planilha', 'total_importado', 'avisos'] as $campo) {
        $resultado[$campo] = $melhor[$campo];
    }
    return $resultado;
}

function _lerCsvComoMatriz(string $tmp): array {
    $conteudo = (string) file_get_contents($tmp);
    if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
        $conteudo = substr($conteudo, 3);
    }
    if (!mb_check_encoding($conteudo, 'UTF-8')) {
        $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
    }
    $primeira = strtok($conteudo, "\n") ?: '';
    $separador = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';
    $linhas = [];
    $numero = 0;
    foreach (preg_split('/\r\n|\n|\r/', $conteudo) ?: [] as $linha) {
        $numero++;
        if (trim($linha) === '') {
            continue;
        }
        $linhas[$numero] = array_filter(str_getcsv($linha, $separador, '"', ''), static fn ($v) => $v !== '');
    }
    return $linhas;
}

function importarCsv(string $tmp): array {
    return importarPlanilhaDetalhada($tmp, 'csv')['itens'];
}

function importarXml(string $tmp): array {
    $itens = [];
    try {
        $xml = simplexml_load_file($tmp);
        if (!$xml) return [];
        // Suporta dois formatos comuns: <itens><item> e <orcamento><item>
        $nodes = $xml->xpath('//item') ?: $xml->xpath('//Item') ?: $xml->xpath('//ITEM') ?: [];
        foreach ($nodes as $node) {
            $attrs = (array)$node;
            $find = fn(array $keys) => array_reduce($keys, fn($carry, $k) =>
                $carry ?? (isset($attrs[$k]) ? (string)$attrs[$k] : null), null);

            $desc = $find(['descricao','Descricao','DESCRICAO','descr','material','MATERIAL','nome','Nome']);
            if (!$desc) continue;
            $itens[] = [
                'codigo'         => $find(['codigo','Codigo','CODIGO','cod']) ?? '',
                'descricao'      => $desc,
                'unidade'        => $find(['unidade','Unidade','UNIDADE','un','UN']) ?? 'UN',
                'quantidade'     => \App\Domain\Orcamento\OrcamentoCalculator::decimal($find(['quantidade','Quantidade','QTD','qtd','quant']) ?? 1),
                'preco_unitario' => \App\Domain\Orcamento\OrcamentoCalculator::decimal($find(['preco','Preco','PRECO','valor','Valor','preco_unit']) ?? 0),
                'categoria'      => $find(['categoria','Categoria','CATEGORIA','cat']) ?? '',
            ];
        }
    } catch (Throwable) {}
    return $itens;
}

function importarPdf(string $tmp): array {
    // Extração de texto do PDF usando pdftotext (Poppler) se disponível
    $itens = [];
    $text  = '';
    if (function_exists('shell_exec')) {
        $safe = escapeshellarg($tmp);
        $text = @shell_exec("pdftotext -layout $safe -");
    }
    if (!$text) return []; // Sem pdftotext, retorna vazio — instrução no README

    $lines = array_filter(array_map('trim', explode("\n", $text)));
    foreach ($lines as $line) {
        // Heurística: linha com número de quantidade seguida de texto
        if (preg_match('/^(\S+)?\s+(.{5,80}?)\s+(UN|M2?|M³|KG|CX|PC|RL|SC|L|GL|KIT|VB|m²|m³)\s+([\d.,]+)/iu', $line, $m)) {
            $itens[] = [
                'codigo'         => '',
                'descricao'      => trim($m[2]),
                'unidade'        => strtoupper(trim($m[3])),
                'quantidade'     => \App\Domain\Orcamento\OrcamentoCalculator::decimal(trim($m[4])),
                'preco_unitario' => 0,
                'categoria'      => '',
            ];
        }
    }
    return $itens;
}

/**
 * Importação específica da planilha padrão CAIXA (SINAPI)
 * Reconhece colunas: Código, Descrição, Unidade, Quantidade, Preço Unitário
 */
function importarPlanilhaCaixa(string $tmp, string $ext = ''): array {
    return array_map(
        static fn (array $item): array => $item + ['fonte' => 'caixa'],
        importarPlanilhaDetalhada($tmp, $ext)['itens']
    );
}
