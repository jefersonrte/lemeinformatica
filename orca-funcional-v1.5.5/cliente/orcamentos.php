<?php
require_once __DIR__ . '/../bootstrap/app.php';

use App\Domain\Consulta\TermoBusca;

requireLogin();
if (isAdmin()) redirect(APP_URL.'/admin/orcamentos.php');

$db  = getDB();
$uid = currentUserId();
$cliente = $db->prepare('SELECT id FROM clientes WHERE usuario_id=?'); $cliente->execute([$uid]);
$cid = (int)$cliente->fetchColumn();

$statusRotulos = ['rascunho' => 'Rascunho', 'aguardando_cotacao' => 'Aguard. cotação', 'cotado' => 'Cotado', 'aprovado' => 'Aprovado', 'reprovado' => 'Reprovado', 'cancelado' => 'Cancelado'];
$statusF = (string) ($_GET['status'] ?? '');
if (!isset($statusRotulos[$statusF])) $statusF = '';
$search = trim((string) ($_GET['q'] ?? ''));
$obraFilter = (int) ($_GET['obra_id'] ?? 0);
$pag=max(1,(int)($_GET['pag']??1)); $limit=12; $offset=($pag-1)*$limit;
$ordem = ordenacaoAtual(['titulo' => 'o.titulo', 'obra' => 'ob.nome', 'estimado' => 'o.total_estimado', 'cotado' => 'o.total_cotado', 'data' => 'o.criado_em'], 'data');

// Sempre restrito aos orçamentos do cliente logado.
$wheres = ['o.cliente_id = ?']; $params = [$cid];
if ($search !== '') $wheres[] = TermoBusca::condicao(['o.titulo', 'ob.nome', "COALESCE(ob.cidade,'')"], TermoBusca::termos($search), $params);
if ($obraFilter) { $wheres[] = 'o.obra_id = ?'; $params[] = $obraFilter; }
$from = 'FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id';

$contagens = $db->prepare("SELECT o.status, COUNT(*) n $from WHERE " . implode(' AND ', $wheres) . ' GROUP BY o.status');
$contagens->execute($params);
$porStatus = $contagens->fetchAll(PDO::FETCH_KEY_PAIR);
$abas = ['' => ['Todos', array_sum($porStatus)]];
foreach ($statusRotulos as $chave => $rotulo) if (!empty($porStatus[$chave]) || $statusF === $chave) $abas[$chave] = [$rotulo, (int) ($porStatus[$chave] ?? 0)];

if ($statusF) { $wheres[] = 'o.status = ?'; $params[] = $statusF; }
$total = (int) ($statusF ? ($porStatus[$statusF] ?? 0) : array_sum($porStatus));

$orcs = $db->prepare("SELECT o.*,ob.nome as obra $from WHERE " . implode(' AND ', $wheres) . " ORDER BY {$ordem['sql']}, o.id DESC LIMIT $limit OFFSET $offset");
$orcs->execute($params); $orcs = $orcs->fetchAll();
$termos = TermoBusca::termos($search);
$obraNome = '';
if ($obraFilter) { $s = $db->prepare('SELECT nome FROM obras WHERE id=? AND cliente_id=?'); $s->execute([$obraFilter, $cid]); $obraNome = (string) $s->fetchColumn(); }

pageHead('Meus Orçamentos');
?>
<div class="layout">
<?php sidebar('orcamentos'); ?>
<div class="main">
<?php topbar('Meus Orçamentos'); ?>
<div class="content">

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Orçamentos</h2>
        <a href="<?= APP_URL ?>/consulta_precos.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-magnifying-glass-dollar"></i> Consultar preços</a>
    </div>
    <form method="get" class="query-bar" role="search">
        <?php foreach (['status' => $statusF, 'obra_id' => $obraFilter ?: ''] as $k => $v): if ($v === '') continue; ?>
        <input type="hidden" name="<?= $k ?>" value="<?= sanitize((string) $v) ?>">
        <?php endforeach; ?>
        <label class="query-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por título, obra ou cidade…" aria-label="Buscar orçamentos" data-autosubmit>
        </label>
        <button class="btn btn-outline btn-sm">Buscar</button>
    </form>
    <?= abasStatus($abas, $statusF) ?>
    <?= chipsFiltros(['q' => $search !== '' ? 'Busca: “' . $search . '”' : '', 'obra_id' => $obraNome !== '' ? 'Obra: ' . $obraNome : '']) ?>
    <div class="table-wrap">
        <table>
            <thead><tr><?= thOrdenavel('Título', 'titulo', $ordem) ?><?= thOrdenavel('Obra', 'obra', $ordem) ?><th>Status</th><?= thOrdenavel('Total Estimado', 'estimado', $ordem) ?><?= thOrdenavel('Total Cotado', 'cotado', $ordem) ?><?= thOrdenavel('Data', 'data', $ordem) ?><th></th></tr></thead>
            <tbody>
            <?php foreach ($orcs as $o): ?>
            <tr>
                <td><a href="<?= APP_URL ?>/cliente/orcamento_ver.php?id=<?= $o['id'] ?>"><?= TermoBusca::destacar(trim($o['titulo']), $termos) ?></a></td>
                <td class="text-sm"><?= TermoBusca::destacar(trim($o['obra']), $termos) ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td>R$ <?= number_format($o['total_estimado'],2,',','.') ?></td>
                <td class="<?= $o['total_cotado']>0?'text-success font-bold':'' ?>">R$ <?= number_format($o['total_cotado'],2,',','.') ?></td>
                <td class="text-xs text-muted"><?= date('d/m/y',strtotime($o['criado_em'])) ?></td>
                <td><a href="<?= APP_URL ?>/cliente/orcamento_ver.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$orcs): ?><tr><td colspan="7" class="empty-cell">
                <i class="fa-regular fa-folder-open" aria-hidden="true"></i>
                <strong>Nenhum orçamento encontrado.</strong>
                <?php if ($search !== '' || $statusF || $obraFilter): ?><span><a href="<?= APP_URL ?>/cliente/orcamentos.php">Limpar filtros</a> ou <a href="<?= APP_URL ?>/consulta_precos.php?q=<?= urlencode($search) ?>">procure nos itens dos seus orçamentos</a>.</span><?php endif; ?>
            </td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="list-footer"><?= resumoResultados($total, $pag, $limit) ?><?php paginacao($total,$limit,$pag,urlConsulta()); ?></div>
</div>
</div></div></div>
<?php pageFoot(); ?>
