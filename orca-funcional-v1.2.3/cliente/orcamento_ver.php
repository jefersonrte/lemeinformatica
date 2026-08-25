<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireLogin();
if (isAdmin()) redirect(APP_URL.'/admin/orcamentos.php');

$db  = getDB();
$uid = currentUserId();
$cliente = $db->prepare('SELECT id FROM clientes WHERE usuario_id=?'); $cliente->execute([$uid]);
$cid = (int)$cliente->fetchColumn();
$id  = (int)($_GET['id'] ?? 0);

// Garante que o orçamento pertence a este cliente
$orc = $db->prepare('SELECT o.*,ob.nome as obra FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id WHERE o.id=? AND o.cliente_id=?');
$orc->execute([$id,$cid]);
$orc = $orc->fetch();
if (!$orc) redirect(APP_URL.'/cliente/obras.php');

$itens = $db->prepare('SELECT oi.*,cat.nome as categoria,f.nome as fornecedor FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id=oi.categoria_id LEFT JOIN fornecedores f ON f.id=oi.fornecedor_id WHERE oi.orcamento_id=?');
$itens->execute([$id]);
$itens = $itens->fetchAll();

pageHead('Orçamento');
?>
<div class="layout">
<?php sidebar('orcamentos'); ?>
<div class="main">
<?php topbar('Orçamento: ' . sanitize($orc['titulo'])); ?>
<div class="content">

<div class="card mb-4">
    <div class="card-body">
        <div class="flex items-center gap-3 mb-2">
            <h1 style="font-size:1.1rem;font-weight:800"><?= sanitize($orc['titulo']) ?></h1>
            <?= statusBadge($orc['status']) ?>
        </div>
        <div class="text-sm text-muted">Obra: <?= sanitize($orc['obra']) ?> | Data: <?= date('d/m/Y',strtotime($orc['criado_em'])) ?></div>
        <hr class="divider">
        <div class="flex gap-6">
            <div><div class="text-xs text-muted">Total Estimado</div><strong>R$ <?= number_format($orc['total_estimado'],2,',','.') ?></strong></div>
            <div><div class="text-xs text-muted">Total Cotado</div><strong style="color:var(--success)">R$ <?= number_format($orc['total_cotado'],2,',','.') ?></strong></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Itens do Orçamento</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Descrição</th><th>Categoria</th><th>Unid.</th><th>Qtd</th><th>Preço Unit.</th><th>Total</th><th>Preço Cotado</th><th>Fornecedor</th></tr></thead>
            <tbody>
            <?php $totE=0;$totC=0; foreach ($itens as $item): $totE+=$item['preco_total'];$totC+=$item['total_cotado']; ?>
            <tr>
                <td><?= sanitize($item['descricao']) ?></td>
                <td class="text-xs"><?= $item['categoria'] ? '<span class="badge badge-blue">'.sanitize($item['categoria']).'</span>' : '—' ?></td>
                <td class="text-sm"><?= $item['unidade'] ?></td>
                <td class="text-sm"><?= number_format($item['quantidade'],3,',','.') ?></td>
                <td class="text-sm">R$ <?= number_format($item['preco_unitario'],2,',','.') ?></td>
                <td class="font-bold">R$ <?= number_format($item['preco_total'],2,',','.') ?></td>
                <td class="text-sm"><?= $item['preco_cotado'] !== null ? 'R$ '.number_format($item['preco_cotado'],2,',','.') : '<span class="text-muted">—</span>' ?></td>
                <td class="text-xs"><?= sanitize($item['fornecedor'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--neutral-50);font-weight:700">
                    <td colspan="5" class="text-right">TOTAIS</td>
                    <td>R$ <?= number_format($totE,2,',','.') ?></td>
                    <td><?= $totC>0 ? 'R$ '.number_format($totC,2,',','.') : '—' ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

</div></div></div>
<?php pageFoot(); ?>
