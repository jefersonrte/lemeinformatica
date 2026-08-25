<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

$statusF = $_GET['status'] ?? '';
$search  = trim($_GET['q'] ?? '');
$pag     = max(1,(int)($_GET['pag']??1));
$limit   = 15; $offset = ($pag-1)*$limit;

$wheres=[]; $params=[];
if ($statusF) { $wheres[]='co.status=?'; $params[]=$statusF; }
if ($search)  { $wheres[]='(f.nome LIKE ? OR o.titulo LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }
$where = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

$st = $db->prepare("SELECT COUNT(*) FROM cotacoes co JOIN fornecedores f ON f.id=co.fornecedor_id JOIN orcamentos o ON o.id=co.orcamento_id $where");
$st->execute($params); $total = (int)$st->fetchColumn();

$stmt = $db->prepare("SELECT co.*,f.nome as fornecedor,o.titulo as orcamento,ob.nome as obra,c.razao_social
    FROM cotacoes co
    JOIN fornecedores f ON f.id=co.fornecedor_id
    JOIN orcamentos o ON o.id=co.orcamento_id
    JOIN obras ob ON ob.id=o.obra_id
    JOIN clientes c ON c.id=o.cliente_id
    $where ORDER BY co.criado_em DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$cotacoes = $stmt->fetchAll();

pageHead('Cotações');
?>
<div class="layout">
<?php sidebar('cotacoes'); ?>
<div class="main">
<?php topbar('Cotações'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Cotações <span class="badge badge-blue"><?= $total ?></span></h2>
        <form method="get" class="flex gap-2" style="flex-wrap:wrap">
            <input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?= sanitize($search) ?>" style="width:180px">
            <select name="status" class="form-control" style="width:150px">
                <option value="">Todos</option>
                <?php foreach (['pendente','enviada','respondida','aceita','recusada'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline btn-sm">Filtrar</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fornecedor</th><th>Orçamento</th><th>Obra</th><th>Canal</th><th>Status</th><th>Enviada</th><th>Resposta</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cotacoes as $c): ?>
            <tr>
                <td><?= sanitize($c['fornecedor']) ?></td>
                <td class="text-sm"><?= sanitize($c['orcamento']) ?></td>
                <td class="text-xs text-muted"><?= sanitize($c['obra']) ?></td>
                <td class="text-xs"><?= $c['canal_envio'] ?></td>
                <td><?= statusBadge($c['status']) ?></td>
                <td class="text-xs"><?= $c['data_envio'] ? date('d/m/y H:i',strtotime($c['data_envio'])) : '—' ?></td>
                <td class="text-xs"><?= $c['data_resposta'] ? date('d/m/y H:i',strtotime($c['data_resposta'])) : '—' ?></td>
                <td><a href="<?= APP_URL ?>/admin/cotacao_detalhe.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$cotacoes): ?><tr><td colspan="8" class="text-center text-muted">Nenhuma cotação.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total,$limit,$pag,APP_URL.'/admin/cotacoes.php?q='.urlencode($search).'&status='.urlencode($statusF)); ?>
</div>
</div></div></div>
<?php pageFoot(); ?>
