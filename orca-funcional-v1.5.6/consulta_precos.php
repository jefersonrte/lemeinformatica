<?php
declare(strict_types=1);

// Consulta de preços: histórico de preços praticados nos orçamentos (banco de preços interno).
require_once __DIR__ . '/bootstrap/app.php';
requireLogin();

use App\Domain\Consulta\ConsultaPrecos;
use App\Domain\Consulta\TermoBusca;

$db = getDB();
$admin = isAdmin();
$statusRotulos = ['rascunho' => 'Rascunho', 'aguardando_cotacao' => 'Aguard. cotação', 'cotado' => 'Cotado', 'aprovado' => 'Aprovado', 'reprovado' => 'Reprovado', 'cancelado' => 'Cancelado'];
$ordens = ['relevancia' => 'Mais relevantes', 'preco_asc' => 'Menor preço', 'preco_desc' => 'Maior preço', 'recente' => 'Mais recentes', 'descricao' => 'Descrição (A–Z)'];
$filtros = [
    'q' => trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 120)),
    'unidade' => trim(mb_substr((string) ($_GET['unidade'] ?? ''), 0, 20)),
    'etapa' => trim(mb_substr((string) ($_GET['etapa'] ?? ''), 0, 150)),
    'status' => isset($statusRotulos[$_GET['status'] ?? '']) ? (string) $_GET['status'] : '',
    'ordem' => isset($ordens[$_GET['ordem'] ?? '']) ? (string) $_GET['ordem'] : 'relevancia',
];
$resultado = (new ConsultaPrecos($db, escopoClienteId($db)))->buscar($filtros);
$termos = $resultado['termos'];
$verOrcamento = APP_URL . ($admin ? '/admin/orcamento_detalhe.php?id=' : '/cliente/orcamento_ver.php?id=');
$moeda = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');

if (($_GET['exportar'] ?? '') === 'csv' && $resultado['itens']) {
    $linhas = (static function () use ($resultado, $statusRotulos) {
        foreach ($resultado['itens'] as $i) {
            yield [$i['descricao'], $i['unidade_norm'], csvNumero($i['preco_unitario'], 4), $i['preco_cotado'] !== null ? csvNumero($i['preco_cotado'], 4) : '', $i['etapa'], $i['orcamento'], $statusRotulos[$i['status']] ?? $i['status'], $i['obra'], trim(($i['cidade'] ?? '') . '/' . ($i['estado'] ?? ''), '/'), date('d/m/Y', strtotime($i['criado_em']))];
        }
    })();
    csvDownload('consulta-precos-' . date('Y-m-d') . '.csv', ['Descrição', 'Unidade', 'Preço unitário', 'Preço cotado', 'Etapa', 'Orçamento', 'Status', 'Obra', 'Cidade/UF', 'Data'], $linhas);
}

$pag = max(1, (int) ($_GET['pag'] ?? 1));
$limit = 25;
$pagina = array_slice($resultado['itens'], ($pag - 1) * $limit, $limit);
$resumoPrincipal = $resultado['resumos'][$filtros['unidade']] ?? (reset($resultado['resumos']) ?: null);
$unidadePrincipal = $filtros['unidade'] !== '' && isset($resultado['resumos'][$filtros['unidade']]) ? $filtros['unidade'] : (string) (array_key_first($resultado['resumos']) ?? '');
$exemplos = ['Porcelanato', 'Argamassa', 'Tinta acrílica', 'Concreto usinado', 'Registro de gaveta', 'Drywall'];

pageHead('Consulta de preços');
?>
<div class="layout">
<?php sidebar('consulta'); ?>
<div class="main">
<?php topbar('Consulta de preços'); ?>
<div class="content">

<div class="card mb-4">
    <form method="get" class="query-bar query-bar-lg" role="search" data-recent-key="consulta_precos">
        <?php foreach (['ordem' => $filtros['ordem'] !== 'relevancia' ? $filtros['ordem'] : '', 'status' => $filtros['status']] as $k => $v): if ($v === '') continue; ?>
        <input type="hidden" name="<?= $k ?>" value="<?= sanitize($v) ?>">
        <?php endforeach; ?>
        <label class="query-search">
            <i class="fa-solid fa-magnifying-glass-dollar" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= sanitize($filtros['q']) ?>" placeholder="Qual material ou serviço? Ex.: porcelanato 60x60, argamassa AC3, pintura acrílica…" aria-label="Material ou serviço" <?= $filtros['q'] === '' ? 'autofocus' : '' ?>>
        </label>
        <button class="btn btn-primary btn-sm">Consultar</button>
    </form>
    <div class="query-hint">
        <?php if ($filtros['q'] === ''): ?>
        Veja quanto já foi orçado por um material ou serviço <?= $admin ? 'em todos os orçamentos da base' : 'nos seus orçamentos' ?>: faixa de preço, mediana e onde foi usado.
        <?php else: ?>
        <strong><?= $resultado['total'] ?></strong> <?= $resultado['total'] === 1 ? 'item encontrado' : 'itens encontrados' ?> para “<?= sanitize($filtros['q']) ?>”<?= $resultado['truncado'] ? ' (mostrando os ' . ConsultaPrecos::LIMITE_BUSCA . ' mais relevantes — refine a busca)' : '' ?>.
        <?php endif; ?>
    </div>
    <div class="suggestion-chips" data-recent-list="consulta_precos">
        <?php foreach ($exemplos as $e): ?><a class="suggestion-chip" href="?q=<?= urlencode($e) ?>"><?= sanitize($e) ?></a><?php endforeach; ?>
    </div>
</div>

<?php if ($filtros['q'] !== '' && !$resultado['itens']): ?>
<div class="card"><div class="empty-state">
    <i class="fa-solid fa-magnifying-glass-dollar" aria-hidden="true"></i>
    <strong>Nenhum preço encontrado para “<?= sanitize($filtros['q']) ?>”.</strong>
    <span>Use menos palavras ou o nome genérico do material. <?php if ($filtros['unidade'] || $filtros['etapa'] || $filtros['status']): ?><a href="?q=<?= urlencode($filtros['q']) ?>">Remover filtros</a><?php endif; ?></span>
</div></div>
<?php elseif ($resultado['itens']): ?>

<?php if ($resumoPrincipal): $r = $resumoPrincipal; $faixa = max(0.0001, $r['max'] - $r['min']); ?>
<div class="price-summary mb-4">
    <div class="price-summary-main">
        <span class="price-summary-label">Mediana por <?= sanitize($unidadePrincipal) ?></span>
        <strong class="price-summary-value"><?= $moeda($r['mediana']) ?></strong>
        <span class="text-xs text-muted">em <?= $r['n'] ?> <?= $r['n'] === 1 ? 'ocorrência' : 'ocorrências' ?> · média <?= $moeda($r['media']) ?></span>
    </div>
    <div class="price-range" aria-label="Faixa de preço">
        <div class="price-range-track">
            <span class="price-range-iqr" style="left:<?= round(($r['p25'] - $r['min']) / $faixa * 100, 1) ?>%;width:<?= max(1, round(($r['p75'] - $r['p25']) / $faixa * 100, 1)) ?>%"></span>
            <span class="price-range-median" style="left:<?= round(($r['mediana'] - $r['min']) / $faixa * 100, 1) ?>%"></span>
        </div>
        <div class="price-range-legend">
            <span>Mín. <strong><?= $moeda($r['min']) ?></strong></span>
            <span>Faixa usual <strong><?= $moeda($r['p25']) ?> – <?= $moeda($r['p75']) ?></strong></span>
            <span>Máx. <strong><?= $moeda($r['max']) ?></strong></span>
        </div>
    </div>
    <?php if (count($resultado['resumos']) > 1): ?>
    <div class="price-summary-units">
        <span class="text-xs text-muted">Outras unidades:</span>
        <?php foreach (array_slice($resultado['resumos'], 0, 6, true) as $u => $ru): if ((string) $u === $unidadePrincipal) continue; ?>
        <a class="suggestion-chip" href="<?= sanitize(urlConsulta(['unidade' => (string) $u])) ?>"><?= sanitize((string) $u) ?> · <?= $moeda($ru['mediana']) ?> <small>(<?= $ru['n'] ?>)</small></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="query-layout">
    <aside class="card facets" aria-label="Refinar resultados">
        <form method="get" class="facet-form">
            <input type="hidden" name="q" value="<?= sanitize($filtros['q']) ?>">
            <?php if ($filtros['unidade']): ?><input type="hidden" name="unidade" value="<?= sanitize($filtros['unidade']) ?>"><?php endif; ?>
            <?php if ($filtros['etapa']): ?><input type="hidden" name="etapa" value="<?= sanitize($filtros['etapa']) ?>"><?php endif; ?>
            <label class="form-label" for="fOrdem">Ordenar por</label>
            <select id="fOrdem" name="ordem" class="form-control" data-autosubmit>
                <?php foreach ($ordens as $k => $r): ?><option value="<?= $k ?>" <?= $filtros['ordem'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
            </select>
            <label class="form-label" for="fStatus">Status do orçamento</label>
            <select id="fStatus" name="status" class="form-control" data-autosubmit>
                <option value="">Todos</option>
                <?php foreach ($statusRotulos as $k => $r): ?><option value="<?= $k ?>" <?= $filtros['status'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-outline btn-sm mt-2">Aplicar</button></noscript>
        </form>
        <div class="facet">
            <div class="facet-title">Unidade</div>
            <?php foreach (array_slice($resultado['unidades'], 0, 10, true) as $u => $n): $u = (string) $u; $ativo = $filtros['unidade'] === $u; ?>
            <a class="facet-option<?= $ativo ? ' is-active' : '' ?>" href="<?= sanitize(urlConsulta(['unidade' => $ativo ? null : $u])) ?>"><span><?= sanitize($u) ?></span><span class="facet-count"><?= $n ?></span></a>
            <?php endforeach; ?>
        </div>
        <div class="facet">
            <div class="facet-title">Etapa</div>
            <?php foreach (array_slice($resultado['etapas'], 0, 12, true) as $e => $n): $e = (string) $e; $ativo = $filtros['etapa'] === $e; ?>
            <a class="facet-option<?= $ativo ? ' is-active' : '' ?>" href="<?= sanitize(urlConsulta(['etapa' => $ativo ? null : $e])) ?>"><span><?= sanitize($e) ?></span><span class="facet-count"><?= $n ?></span></a>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:10px">
            <?= resumoResultados($resultado['total'], $pag, $limit) ?>
            <a href="<?= sanitize(urlConsulta(['exportar' => 'csv'])) ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Exportar</a>
        </div>
        <?= chipsFiltros([
            'unidade' => $filtros['unidade'] !== '' ? 'Unidade: ' . $filtros['unidade'] : '',
            'etapa' => $filtros['etapa'] !== '' ? 'Etapa: ' . $filtros['etapa'] : '',
            'status' => $filtros['status'] !== '' ? 'Status: ' . $statusRotulos[$filtros['status']] : '',
        ]) ?>
        <div class="table-wrap">
            <table class="price-table">
                <thead><tr><th>Descrição</th><th>Un.</th><th class="text-right">Preço unit.</th><th>vs. mediana</th><th>Etapa</th><th>Orçamento / obra</th><th>Data</th></tr></thead>
                <tbody>
                <?php foreach ($pagina as $i):
                    $med = $resultado['resumos'][$i['unidade_norm']]['mediana'] ?? 0;
                    $dif = $med > 0 ? ((float) $i['preco_unitario'] - $med) / $med * 100 : 0; ?>
                <tr>
                    <td><?= TermoBusca::destacar(trim($i['descricao']), $termos) ?><?php if ($i['fornecedor']): ?><div class="text-xs text-muted"><i class="fa-solid fa-truck-field"></i> <?= sanitize($i['fornecedor']) ?></div><?php endif; ?></td>
                    <td class="text-sm"><?= sanitize($i['unidade_norm']) ?></td>
                    <td class="text-right text-sm font-bold"><?= $moeda((float) $i['preco_unitario']) ?><?php if ($i['preco_cotado'] !== null): ?><div class="text-xs text-success" title="Preço cotado com fornecedor">cotado <?= $moeda((float) $i['preco_cotado']) ?></div><?php endif; ?></td>
                    <td><?php if (abs($dif) < 0.5): ?><span class="delta delta-eq">na mediana</span><?php else: ?><span class="delta <?= $dif < 0 ? 'delta-down' : 'delta-up' ?>"><?= $dif < 0 ? '▼' : '▲' ?> <?= number_format(abs($dif), 0, ',', '.') ?>%</span><?php endif; ?></td>
                    <td class="text-xs"><?= sanitize($i['etapa'] !== '' ? $i['etapa'] : '—') ?></td>
                    <td class="text-sm"><a href="<?= $verOrcamento . (int) $i['orcamento_id'] ?>"><?= sanitize($i['orcamento']) ?></a><div class="text-xs text-muted"><?= sanitize($i['obra']) ?><?= $i['cidade'] ? ' · ' . sanitize($i['cidade'] . ($i['estado'] ? '/' . $i['estado'] : '')) : '' ?></div></td>
                    <td class="text-xs text-muted"><?= date('m/Y', strtotime($i['criado_em'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-footer"><?= resumoResultados($resultado['total'], $pag, $limit) ?><?php paginacao($resultado['total'], $limit, $pag, urlConsulta()); ?></div>
    </div>
</div>
<?php endif; ?>

</div></div></div>
<?php pageFoot(); ?>
