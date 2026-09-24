<?php
require_once __DIR__ . '/../bootstrap/app.php';

use App\Domain\Consulta\TermoBusca;

requireAdmin();
$db = getDB();

$statusRotulos = ['rascunho' => 'Rascunho', 'aguardando_cotacao' => 'Aguard. cotação', 'cotado' => 'Cotado', 'aprovado' => 'Aprovado', 'reprovado' => 'Reprovado', 'cancelado' => 'Cancelado'];
$obraFilter = (int)($_GET['obra_id'] ?? 0);
$search     = trim((string) ($_GET['q'] ?? ''));
$statusF    = (string) ($_GET['status'] ?? '');
if (!isset($statusRotulos[$statusF])) $statusF = '';
$origemF    = (string) ($_GET['origem'] ?? '');
if (!in_array($origemF, ['manual','excel','xml','pdf','caixa'], true)) $origemF = '';
$de = dataFiltro('de'); $ate = dataFiltro('ate');
$pag   = max(1,(int)($_GET['pag']??1));
$limit = 15; $offset = ($pag-1)*$limit;
$ordem = ordenacaoAtual([
    'titulo' => 'o.titulo', 'obra' => 'ob.nome', 'cliente' => 'c.razao_social', 'itens' => 'qtd_itens',
    'estimado' => 'o.total_estimado', 'cotado' => 'o.total_cotado', 'data' => 'o.criado_em',
], 'data');

// Filtros comuns (status fica de fora para as abas mostrarem a contagem de cada um).
$wheres = []; $params = [];
if ($obraFilter) { $wheres[] = 'o.obra_id=?'; $params[] = $obraFilter; }
if ($search !== '') $wheres[] = TermoBusca::condicao(['o.titulo', 'ob.nome', 'c.razao_social', "COALESCE(c.nome_fantasia,'')", "COALESCE(ob.cidade,'')", 'CAST(o.id AS CHAR)'], TermoBusca::termos($search), $params);
if ($origemF)    { $wheres[] = 'o.tipo_origem=?'; $params[] = $origemF; }
if ($de)         { $wheres[] = 'o.criado_em >= ?'; $params[] = $de . ' 00:00:00'; }
if ($ate)        { $wheres[] = 'o.criado_em <= ?'; $params[] = $ate . ' 23:59:59'; }
$from = 'FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id JOIN clientes c ON c.id=o.cliente_id';
$baseWhere = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

$contagens = $db->prepare("SELECT o.status, COUNT(*) n $from $baseWhere GROUP BY o.status");
$contagens->execute($params);
$porStatus = $contagens->fetchAll(PDO::FETCH_KEY_PAIR);
$abas = ['' => ['Todos', array_sum($porStatus)]];
foreach ($statusRotulos as $chave => $rotulo) $abas[$chave] = [$rotulo, (int) ($porStatus[$chave] ?? 0)];

if ($statusF) { $wheres[] = 'o.status=?'; $params[] = $statusF; }
$where = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';
$total = (int) ($statusF ? ($porStatus[$statusF] ?? 0) : array_sum($porStatus));

$sql = "SELECT o.*,ob.nome as obra_nome,c.razao_social,(SELECT COUNT(*) FROM orcamento_itens oi WHERE oi.orcamento_id=o.id) as qtd_itens
    $from $where ORDER BY {$ordem['sql']}, o.id DESC";

if (($_GET['exportar'] ?? '') === 'csv') {
    $st = $db->prepare($sql); $st->execute($params);
    $linhas = (static function () use ($st, $statusRotulos) {
        foreach ($st as $o) yield [$o['id'], $o['titulo'], $o['obra_nome'], $o['razao_social'], $statusRotulos[$o['status']] ?? $o['status'], $o['qtd_itens'], csvNumero($o['total_estimado']), csvNumero($o['total_cotado']), date('d/m/Y', strtotime($o['criado_em']))];
    })();
    csvDownload('orcamentos-' . date('Y-m-d') . '.csv', ['ID','Título','Obra','Cliente','Status','Itens','Total estimado','Total cotado','Criado em'], $linhas);
}

$stmt = $db->prepare("$sql LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$orcamentos = $stmt->fetchAll();
$termos = TermoBusca::termos($search);

$obraNome = '';
if ($obraFilter) { $s = $db->prepare('SELECT nome FROM obras WHERE id=?'); $s->execute([$obraFilter]); $obraNome = (string) $s->fetchColumn(); }
$origens = ['manual' => 'Manual', 'excel' => 'Planilha', 'xml' => 'XML', 'pdf' => 'PDF', 'caixa' => 'Caixa/SINAPI'];

pageHead('Orçamentos');
?>
<div class="layout">
<?php sidebar('orcamentos'); ?>
<div class="main">
<?php topbar('Orçamentos'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Orçamentos</h2>
        <div class="flex gap-2" style="flex-wrap:wrap">
            <a href="<?= sanitize(urlConsulta(['exportar' => 'csv'])) ?>" class="btn btn-outline btn-sm" title="Baixar a consulta atual em planilha"><i class="fa-solid fa-file-csv"></i> Exportar</a>
            <a href="<?= APP_URL ?>/admin/orcamento_novo.php" class="btn btn-primary btn-sm">+ Novo Orçamento</a>
        </div>
    </div>
    <form method="get" class="query-bar" role="search">
        <?php foreach (['status' => $statusF, 'obra_id' => $obraFilter ?: '', 'ordem' => $_GET['ordem'] ?? '', 'dir' => $_GET['dir'] ?? ''] as $k => $v): if ($v === '' || $v === null) continue; ?>
        <input type="hidden" name="<?= $k ?>" value="<?= sanitize((string) $v) ?>">
        <?php endforeach; ?>
        <label class="query-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por título, obra, cliente, cidade ou nº…" aria-label="Buscar orçamentos" data-autosubmit>
        </label>
        <select name="origem" class="form-control" aria-label="Origem" data-autosubmit>
            <option value="">Qualquer origem</option>
            <?php foreach ($origens as $k => $r): ?><option value="<?= $k ?>" <?= $origemF === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
        </select>
        <label class="query-date"><span>De</span><input type="date" name="de" value="<?= $de ?>" class="form-control" data-autosubmit></label>
        <label class="query-date"><span>Até</span><input type="date" name="ate" value="<?= $ate ?>" class="form-control" data-autosubmit></label>
        <button class="btn btn-outline btn-sm">Filtrar</button>
    </form>
    <?= abasStatus($abas, $statusF) ?>
    <?= chipsFiltros([
        'q' => $search !== '' ? 'Busca: “' . $search . '”' : '',
        'obra_id' => $obraNome !== '' ? 'Obra: ' . $obraNome : '',
        'origem' => $origemF ? 'Origem: ' . $origens[$origemF] : '',
        'de' => $de ? 'A partir de ' . date('d/m/Y', strtotime($de)) : '',
        'ate' => $ate ? 'Até ' . date('d/m/Y', strtotime($ate)) : '',
    ]) ?>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <?= thOrdenavel('Título', 'titulo', $ordem) ?><?= thOrdenavel('Obra', 'obra', $ordem) ?><?= thOrdenavel('Cliente', 'cliente', $ordem) ?>
                <?= thOrdenavel('Itens', 'itens', $ordem) ?><th>Status</th><?= thOrdenavel('Total Est.', 'estimado', $ordem) ?><?= thOrdenavel('Total Cotado', 'cotado', $ordem) ?><?= thOrdenavel('Data', 'data', $ordem) ?><th>Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($orcamentos as $o): ?>
            <tr>
                <td><a href="<?= APP_URL ?>/admin/orcamento_detalhe.php?id=<?= $o['id'] ?>"><?= TermoBusca::destacar(trim($o['titulo']), $termos) ?></a></td>
                <td class="text-sm"><a class="link-quiet" href="<?= sanitize(urlConsulta(['obra_id' => $o['obra_id']])) ?>" title="Ver orçamentos desta obra"><?= TermoBusca::destacar(trim($o['obra_nome']), $termos) ?></a></td>
                <td class="text-sm text-muted"><?= TermoBusca::destacar(trim($o['razao_social']), $termos) ?></td>
                <td class="text-sm"><?= $o['qtd_itens'] ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td class="text-sm" style="white-space:nowrap">R$ <?= number_format($o['total_estimado'],2,',','.') ?></td>
                <td class="text-sm <?= $o['total_cotado']>0?'text-success':'' ?>" style="white-space:nowrap">R$ <?= number_format($o['total_cotado'],2,',','.') ?></td>
                <td class="text-xs text-muted"><?= date('d/m/y', strtotime($o['criado_em'])) ?></td>
                <td><a href="<?= APP_URL ?>/admin/orcamento_detalhe.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$orcamentos): ?><tr><td colspan="9" class="empty-cell">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <strong>Nenhum orçamento encontrado.</strong>
                <span>Tente outras palavras ou <a href="<?= APP_URL ?>/admin/orcamentos.php">limpe os filtros</a>.<?php if ($search !== ''): ?> Procurando um material? <a href="<?= APP_URL ?>/consulta_precos.php?q=<?= urlencode($search) ?>">Consulte os preços de “<?= sanitize($search) ?>”</a>.<?php endif; ?></span>
            </td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="list-footer"><?= resumoResultados($total, $pag, $limit) ?><?php paginacao($total,$limit,$pag,urlConsulta()); ?></div>
</div>
</div></div></div>
<?php pageFoot(); ?>
