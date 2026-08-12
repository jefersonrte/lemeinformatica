<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireLogin();
if (isAdmin()) redirect(APP_URL.'/admin/orcamentos.php');

$db  = getDB();
$uid = currentUserId();
$cliente = $db->prepare('SELECT id FROM clientes WHERE usuario_id=?'); $cliente->execute([$uid]);
$cid = (int)$cliente->fetchColumn();

$statusF = (string) ($_GET['status'] ?? '');
$statusPermitidos = ['rascunho', 'aguardando_cotacao', 'cotado', 'aprovado', 'reprovado', 'cancelado'];
if (!in_array($statusF, $statusPermitidos, true)) $statusF = '';
$pag=max(1,(int)($_GET['pag']??1)); $limit=12; $offset=($pag-1)*$limit;
$where = $statusF ? 'AND o.status = ?' : '';
$params = $statusF ? [$cid, $statusF] : [$cid];
$totalStatement = $db->prepare("SELECT COUNT(*) FROM orcamentos o WHERE o.cliente_id = ? $where");
$totalStatement->execute($params);
$total = (int) $totalStatement->fetchColumn();

$orcs = $db->prepare("SELECT o.*,ob.nome as obra FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id WHERE o.cliente_id=? $where ORDER BY o.criado_em DESC LIMIT $limit OFFSET $offset");
$orcs->execute($params); $orcs = $orcs->fetchAll();

pageHead('Meus Orçamentos');
?>
<div class="layout">
<?php sidebar('orcamentos'); ?>
<div class="main">
<?php topbar('Meus Orçamentos'); ?>
<div class="content">

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Orçamentos <span class="badge badge-blue"><?= $total ?></span></h2>
        <form method="get" class="flex gap-2">
            <select name="status" class="form-control" style="width:160px">
                <option value="">Todos</option>
                <?php foreach (['rascunho','aguardando_cotacao','cotado','aprovado'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= str_replace('_',' ',ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm">Filtrar</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Título</th><th>Obra</th><th>Status</th><th>Total Estimado</th><th>Total Cotado</th><th>Data</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orcs as $o): ?>
            <tr>
                <td><a href="<?= APP_URL ?>/cliente/orcamento_ver.php?id=<?= $o['id'] ?>"><?= sanitize($o['titulo']) ?></a></td>
                <td class="text-sm"><?= sanitize($o['obra']) ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td>R$ <?= number_format($o['total_estimado'],2,',','.') ?></td>
                <td class="<?= $o['total_cotado']>0?'text-success font-bold':'' ?>">R$ <?= number_format($o['total_cotado'],2,',','.') ?></td>
                <td class="text-xs text-muted"><?= date('d/m/y',strtotime($o['criado_em'])) ?></td>
                <td><a href="<?= APP_URL ?>/cliente/orcamento_ver.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$orcs): ?><tr><td colspan="7" class="text-center text-muted">Nenhum orçamento.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total,$limit,$pag,APP_URL.'/cliente/orcamentos.php?status='.urlencode($statusF)); ?>
</div>
</div></div></div>
<?php pageFoot(); ?>
