<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

$obraFilter = (int)($_GET['obra_id'] ?? 0);
$search     = trim($_GET['q'] ?? '');
$statusF    = $_GET['status'] ?? '';
$pag        = max(1,(int)($_GET['pag']??1));
$limit = 15; $offset = ($pag-1)*$limit;

$wheres = []; $params = [];
if ($obraFilter) { $wheres[] = 'o.obra_id=?'; $params[] = $obraFilter; }
if ($search)     { $wheres[] = 'o.titulo LIKE ?'; $params[] = "%$search%"; }
if ($statusF)    { $wheres[] = 'o.status=?'; $params[] = $statusF; }
$where = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

$stTotal = $db->prepare("SELECT COUNT(*) FROM orcamentos o $where"); $stTotal->execute($params);
$total = (int)$stTotal->fetchColumn();

$stmt = $db->prepare("SELECT o.*,ob.nome as obra_nome,c.razao_social,COUNT(oi.id) as qtd_itens
    FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id JOIN clientes c ON c.id=o.cliente_id
    LEFT JOIN orcamento_itens oi ON oi.orcamento_id=o.id
    $where GROUP BY o.id ORDER BY o.criado_em DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$orcamentos = $stmt->fetchAll();

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
        <h2>Orçamentos <span class="badge badge-blue"><?= $total ?></span></h2>
        <div class="flex gap-2" style="flex-wrap:wrap">
            <form method="get" class="flex gap-2" style="flex-wrap:wrap">
                <input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?= sanitize($search) ?>" style="width:180px">
                <select name="status" class="form-control" style="width:160px">
                    <option value="">Todos status</option>
                    <?php foreach (['rascunho','aguardando_cotacao','cotado','aprovado','reprovado','cancelado'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= str_replace('_',' ',ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm">Filtrar</button>
            </form>
            <a href="<?= APP_URL ?>/admin/orcamento_novo.php" class="btn btn-primary btn-sm">+ Novo Orçamento</a>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Título</th><th>Obra</th><th>Cliente</th><th>Itens</th><th>Status</th><th>Total Est.</th><th>Total Cotado</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($orcamentos as $o): ?>
            <tr>
                <td><a href="<?= APP_URL ?>/admin/orcamento_detalhe.php?id=<?= $o['id'] ?>"><?= sanitize($o['titulo']) ?></a></td>
                <td class="text-sm"><?= sanitize($o['obra_nome']) ?></td>
                <td class="text-sm text-muted"><?= sanitize($o['razao_social']) ?></td>
                <td class="text-sm"><?= $o['qtd_itens'] ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td class="text-sm">R$ <?= number_format($o['total_estimado'],2,',','.') ?></td>
                <td class="text-sm <?= $o['total_cotado']>0?'text-success':'' ?>">R$ <?= number_format($o['total_cotado'],2,',','.') ?></td>
                <td><a href="<?= APP_URL ?>/admin/orcamento_detalhe.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$orcamentos): ?><tr><td colspan="8" class="text-center text-muted">Nenhum orçamento encontrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total,$limit,$pag,APP_URL.'/admin/orcamentos.php?q='.urlencode($search).'&status='.urlencode($statusF)); ?>
</div>
</div></div></div>
<?php pageFoot(); ?>
