<?php
// helpers/consulta.php — peças das telas de consulta: abas de status, ordenação,
// filtros ativos em chips, exportação CSV e resumo de resultados.

/** URL da página atual com os parâmetros GET alterados (null remove). Mudar filtro volta à página 1. */
function urlConsulta(array $alterar = [], ?string $caminho = null): string
{
    $query = $_GET;
    if (!array_key_exists('pag', $alterar)) {
        unset($query['pag']);
    }
    foreach ($alterar as $chave => $valor) {
        if ($valor === null || $valor === '') {
            unset($query[$chave]);
        } else {
            $query[$chave] = $valor;
        }
    }
    if (!array_key_exists('exportar', $alterar)) {
        unset($query['exportar']);
    }
    $caminho ??= strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
    $qs = http_build_query($query);
    return $caminho . ($qs !== '' ? '?' . $qs : '');
}

/**
 * Ordenação escolhida pelo usuário, limitada às colunas permitidas.
 *
 * @param array<string, string> $colunas chave pública => expressão SQL confiável
 * @return array{chave: string, dir: string, sql: string}
 */
function ordenacaoAtual(array $colunas, string $padrao, string $dirPadrao = 'desc'): array
{
    $chave = (string) ($_GET['ordem'] ?? '');
    if (!isset($colunas[$chave])) {
        $chave = $padrao;
        $dir = $dirPadrao;
    } else {
        $dir = strtolower((string) ($_GET['dir'] ?? '')) === 'asc' ? 'asc' : 'desc';
    }
    return ['chave' => $chave, 'dir' => $dir, 'sql' => $colunas[$chave] . ' ' . strtoupper($dir)];
}

/** Cabeçalho de tabela clicável (alterna asc/desc), como nas planilhas e no GitHub. */
function thOrdenavel(string $rotulo, string $chave, array $ordem, string $classe = ''): string
{
    $ativo = $ordem['chave'] === $chave;
    $proxima = $ativo && $ordem['dir'] === 'desc' ? 'asc' : 'desc';
    $icone = !$ativo ? 'fa-sort' : ($ordem['dir'] === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
    $aria = $ativo ? ' aria-sort="' . ($ordem['dir'] === 'asc' ? 'ascending' : 'descending') . '"' : '';
    return '<th' . $aria . ($classe !== '' ? ' class="' . $classe . '"' : '') . '><a class="th-sort' . ($ativo ? ' is-active' : '') . '" href="'
        . htmlspecialchars(urlConsulta(['ordem' => $chave, 'dir' => $proxima]), ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($rotulo) . ' <i class="fa-solid ' . $icone . '" aria-hidden="true"></i></a></th>';
}

/**
 * Abas de status com contagem (estilo "Open / Closed" do GitHub).
 *
 * @param array<string, array{0: string, 1: int}> $abas status => [rótulo, contagem]; chave '' = todos
 */
function abasStatus(array $abas, string $atual, string $parametro = 'status'): string
{
    $html = '<nav class="status-tabs" aria-label="Filtrar por status">';
    foreach ($abas as $chave => [$rotulo, $contagem]) {
        $ativo = (string) $chave === $atual;
        $html .= '<a href="' . htmlspecialchars(urlConsulta([$parametro => (string) $chave]), ENT_QUOTES, 'UTF-8') . '" class="status-tab' . ($ativo ? ' is-active' : '') . '"'
            . ($ativo ? ' aria-current="true"' : '') . '>' . htmlspecialchars($rotulo) . ' <span class="status-tab-count">' . $contagem . '</span></a>';
    }
    return $html . '</nav>';
}

/**
 * Filtros ativos em chips removíveis + "Limpar filtros".
 *
 * @param array<string, string> $chips parâmetro GET => rótulo exibido
 */
function chipsFiltros(array $chips): string
{
    $chips = array_filter($chips, static fn (string $r): bool => $r !== '');
    if ($chips === []) {
        return '';
    }
    $html = '<div class="filter-chips" aria-label="Filtros ativos">';
    foreach ($chips as $parametro => $rotulo) {
        $remover = array_fill_keys(explode(',', (string) $parametro), null);
        $html .= '<a class="filter-chip" href="' . htmlspecialchars(urlConsulta($remover), ENT_QUOTES, 'UTF-8') . '" title="Remover filtro">'
            . htmlspecialchars($rotulo) . ' <i class="fa-solid fa-xmark" aria-hidden="true"></i></a>';
    }
    $limpar = [];
    foreach (array_keys($_GET) as $parametro) {
        if (!in_array($parametro, ['ordem', 'dir'], true)) {
            $limpar[$parametro] = null;
        }
    }
    return $html . '<a class="filter-clear" href="' . htmlspecialchars(urlConsulta($limpar), ENT_QUOTES, 'UTF-8') . '">Limpar filtros</a></div>';
}

/** "Mostrando 1–15 de 80". */
function resumoResultados(int $total, int $pagina, int $porPagina): string
{
    if ($total === 0) {
        return '<span class="results-summary">Nenhum resultado</span>';
    }
    $inicio = ($pagina - 1) * $porPagina + 1;
    $fim = min($total, $pagina * $porPagina);
    return '<span class="results-summary">Mostrando <strong>' . $inicio . '–' . $fim . '</strong> de <strong>' . $total . '</strong></span>';
}

/** Data dd/mm/aaaa ou aaaa-mm-dd validada (para filtros de período). */
function dataFiltro(string $parametro): string
{
    $valor = trim((string) ($_GET[$parametro] ?? ''));
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $data && $data->format('Y-m-d') === $valor ? $valor : '';
}

/** Envia CSV (separador ; e BOM, abre direto no Excel) e encerra. */
function csvDownload(string $arquivo, array $cabecalho, iterable $linhas): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9_.-]+/i', '-', $arquivo) . '"');
    header('Cache-Control: no-store');
    $saida = fopen('php://output', 'wb');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, $cabecalho, ';', '"', '');
    foreach ($linhas as $linha) {
        // Evita injeção de fórmula ao abrir no Excel.
        $linha = array_map(static fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) === 1 ? "'" . $v : $v, $linha);
        fputcsv($saida, $linha, ';', '"', '');
    }
    fclose($saida);
    exit;
}

/** Número pt-BR para CSV. */
function csvNumero(float|int|string|null $valor, int $casas = 2): string
{
    return number_format((float) $valor, $casas, ',', '');
}

/** Escopo de dados do usuário logado: null para admin, id do cliente (0 se não vinculado) caso contrário. */
function escopoClienteId(PDO $db): ?int
{
    if (isAdmin()) {
        return null;
    }
    $st = $db->prepare('SELECT id FROM clientes WHERE usuario_id = ?');
    $st->execute([currentUserId()]);
    return (int) $st->fetchColumn();
}
