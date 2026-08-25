<?php
// helpers/file_import.php — Leitura de planilhas Excel (CSV/XLSX), XML e PDF para extração de itens de orçamento

require_once __DIR__ . '/../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Recebe um arquivo $_FILES e retorna array de itens:
 * [['descricao'=>..., 'unidade'=>..., 'quantidade'=>..., 'codigo'=>..., 'categoria'=>...], ...]
 */
function importarArquivo(array $file): array {
    $tmp  = $file['tmp_name'];
    $nome = strtolower($file['name']);
    $ext  = pathinfo($nome, PATHINFO_EXTENSION);

    return match(true) {
        in_array($ext, ['xlsx','xls','ods'])          => importarExcel($tmp, $ext),
        $ext === 'csv'                                 => importarCsv($tmp),
        $ext === 'xml'                                 => importarXml($tmp),
        $ext === 'pdf'                                 => importarPdf($tmp),
        default                                        => [],
    };
}

function importarExcel(string $tmp, string $ext): array {
    try {
        $reader = IOFactory::createReaderForFile($tmp);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($tmp)->getActiveSheet();
        return _parseSheet($sheet);
    } catch (Throwable $e) {
        return [];
    }
}

function _parseSheet($sheet): array {
    $itens  = [];
    $header = [];
    $colMap = ['descricao'=>null,'unidade'=>null,'quantidade'=>null,'codigo'=>null,'categoria'=>null,'preco_unitario'=>null];

    foreach ($sheet->getRowIterator() as $rowIdx => $row) {
        $cells = [];
        foreach ($row->getCellIterator() as $cell) {
            $cells[] = trim((string)$cell->getValue());
        }
        if ($rowIdx === 1 || empty(array_filter($cells))) {
            // Detecta header
            foreach ($cells as $i => $val) {
                $v = mb_strtolower($val);
                if (str_contains($v,'descri') || str_contains($v,'item') || str_contains($v,'material')) $colMap['descricao']    = $i;
                if (str_contains($v,'unid'))                                                              $colMap['unidade']      = $i;
                if (str_contains($v,'qtd') || str_contains($v,'quant'))                                  $colMap['quantidade']   = $i;
                if (str_contains($v,'cod'))                                                               $colMap['codigo']      = $i;
                if (str_contains($v,'categ'))                                                             $colMap['categoria']   = $i;
                if (str_contains($v,'pre') || str_contains($v,'valo') || str_contains($v,'unit'))        $colMap['preco_unitario'] = $i;
            }
            $header = $cells;
            continue;
        }
        $desc = $colMap['descricao'] !== null ? ($cells[$colMap['descricao']] ?? '') : ($cells[1] ?? $cells[0] ?? '');
        if (!$desc) continue;
        $itens[] = [
            'codigo'          => $colMap['codigo']       !== null ? ($cells[$colMap['codigo']] ?? '')       : '',
            'descricao'       => $desc,
            'unidade'         => $colMap['unidade']      !== null ? ($cells[$colMap['unidade']] ?? 'UN')    : 'UN',
            'quantidade'      => (float)str_replace(',','.',($colMap['quantidade'] !== null ? ($cells[$colMap['quantidade']] ?? 1) : 1)),
            'preco_unitario'  => (float)str_replace(',','.',($colMap['preco_unitario'] !== null ? ($cells[$colMap['preco_unitario']] ?? 0) : 0)),
            'categoria'       => $colMap['categoria']    !== null ? ($cells[$colMap['categoria']] ?? '')    : '',
        ];
    }
    return $itens;
}

function importarCsv(string $tmp): array {
    $itens   = [];
    $header  = null;
    $colMap  = [];
    if (($fh = fopen($tmp, 'r')) === false) return [];
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (!$header) {
            $header = array_map(fn($v) => mb_strtolower(trim($v)), $row);
            foreach ($header as $i => $v) {
                if (str_contains($v,'descri')||str_contains($v,'item'))   $colMap['descricao']   = $i;
                if (str_contains($v,'unid'))                               $colMap['unidade']     = $i;
                if (str_contains($v,'qtd')||str_contains($v,'quant'))     $colMap['quantidade']  = $i;
                if (str_contains($v,'cod'))                                $colMap['codigo']      = $i;
                if (str_contains($v,'categ'))                              $colMap['categoria']   = $i;
                if (str_contains($v,'pre')||str_contains($v,'unit'))      $colMap['preco_unitario'] = $i;
            }
            continue;
        }
        $desc = $colMap['descricao'] ?? null;
        $d    = $desc !== null ? ($row[$desc] ?? '') : ($row[1] ?? '');
        if (!trim($d)) continue;
        $itens[] = [
            'codigo'         => isset($colMap['codigo']) ? ($row[$colMap['codigo']] ?? '') : '',
            'descricao'      => trim($d),
            'unidade'        => isset($colMap['unidade']) ? ($row[$colMap['unidade']] ?? 'UN') : 'UN',
            'quantidade'     => (float)str_replace(',','.',isset($colMap['quantidade']) ? ($row[$colMap['quantidade']] ?? 1) : 1),
            'preco_unitario' => (float)str_replace(',','.',isset($colMap['preco_unitario']) ? ($row[$colMap['preco_unitario']] ?? 0) : 0),
            'categoria'      => isset($colMap['categoria']) ? ($row[$colMap['categoria']] ?? '') : '',
        ];
    }
    fclose($fh);
    return $itens;
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
                'quantidade'     => (float)str_replace(',','.',$find(['quantidade','Quantidade','QTD','qtd','quant']) ?? 1),
                'preco_unitario' => (float)str_replace(',','.',$find(['preco','Preco','PRECO','valor','Valor','preco_unit']) ?? 0),
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
                'quantidade'     => (float)str_replace(',','.',trim($m[4])),
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
function importarPlanilhaCaixa(string $tmp): array {
    try {
        $reader = IOFactory::createReaderForFile($tmp);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($tmp)->getActiveSheet();
        $itens = [];
        $encontrouHeader = false;
        $colCod = $colDesc = $colUn = $colQtd = $colPU = null;

        foreach ($sheet->getRowIterator() as $rowIdx => $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) $cells[] = trim((string)$cell->getValue());

            if (!$encontrouHeader) {
                foreach ($cells as $i => $v) {
                    $vl = mb_strtolower($v);
                    if (str_contains($vl,'código')||str_contains($vl,'codigo')) $colCod  = $i;
                    if (str_contains($vl,'descrição')||str_contains($vl,'descricao')) $colDesc = $i;
                    if (str_contains($vl,'unid')) $colUn = $i;
                    if (str_contains($vl,'quant')||str_contains($vl,'qtd')) $colQtd = $i;
                    if ((str_contains($vl,'preço')||str_contains($vl,'preco'))&&str_contains($vl,'unit')) $colPU = $i;
                }
                if ($colDesc !== null) $encontrouHeader = true;
                continue;
            }
            $desc = $colDesc !== null ? ($cells[$colDesc] ?? '') : '';
            if (!trim($desc)) continue;
            $itens[] = [
                'codigo'         => $colCod  !== null ? ($cells[$colCod]  ?? '') : '',
                'descricao'      => trim($desc),
                'unidade'        => $colUn   !== null ? ($cells[$colUn]   ?? 'UN') : 'UN',
                'quantidade'     => (float)str_replace(',','.',$colQtd !== null ? ($cells[$colQtd] ?? 1) : 1),
                'preco_unitario' => (float)str_replace(',','.',$colPU  !== null ? ($cells[$colPU]  ?? 0) : 0),
                'categoria'      => '',
                'fonte'          => 'caixa',
            ];
        }
        return $itens;
    } catch (Throwable) {
        return [];
    }
}
