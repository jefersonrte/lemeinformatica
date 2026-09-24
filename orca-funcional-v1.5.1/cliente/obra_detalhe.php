<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireLogin();
if (isAdmin()) redirect(APP_URL.'/admin/obras.php');

$db  = getDB();
$uid = currentUserId();
$cliente = $db->prepare('SELECT id FROM clientes WHERE usuario_id=?'); $cliente->execute([$uid]);
$cid = (int)$cliente->fetchColumn();
$id  = (int)($_GET['id'] ?? 0);

// Garante que a obra pertence a este cliente
$obra = $db->prepare('SELECT * FROM obras WHERE id=? AND cliente_id=?');
$obra->execute([$id, $cid]);
$obra = $obra->fetch();
if (!$obra) redirect(APP_URL.'/cliente/obras.php');

$etapas    = $db->prepare('SELECT * FROM obra_etapas WHERE obra_id=? ORDER BY ordem'); $etapas->execute([$id]); $etapas = $etapas->fetchAll();
$orcamentos = $db->prepare("SELECT o.*,COUNT(oi.id) as qtd_itens FROM orcamentos o LEFT JOIN orcamento_itens oi ON oi.orcamento_id=o.id WHERE o.obra_id=? GROUP BY o.id ORDER BY o.criado_em DESC"); $orcamentos->execute([$id]); $orcamentos = $orcamentos->fetchAll();
$compras   = $db->prepare("SELECT cp.*,f.nome as fornecedor FROM compras cp JOIN fornecedores f ON f.id=cp.fornecedor_id WHERE cp.obra_id=?"); $compras->execute([$id]); $compras = $compras->fetchAll();

pageHead('Obra: ' . sanitize($obra['nome']));
?>
<div class="layout">
<?php sidebar('obras'); ?>
<div class="main">
<?php topbar(sanitize($obra['nome'])); ?>
<div class="content">
<?php flashMessage(); ?>

<!-- Header -->
<div class="card mb-4">
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr auto;gap:20px;align-items:start">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 style="font-size:1.3rem;font-weight:800"><?= sanitize($obra['nome']) ?></h1>
                    <?= statusBadge($obra['status']) ?>
                </div>
                <div class="flex gap-4 text-sm text-muted" style="flex-wrap:wrap">
                    <?php if ($obra['cidade']): ?><span>📍 <?= sanitize($obra['cidade'].'/'.$obra['estado']) ?></span><?php endif; ?>
                    <?php if ($obra['data_inicio']): ?><span>📅 Início: <?= date('d/m/Y',strtotime($obra['data_inicio'])) ?></span><?php endif; ?>
                    <?php if ($obra['data_prev_fim']): ?><span>🏁 Previsão: <?= date('d/m/Y',strtotime($obra['data_prev_fim'])) ?></span><?php endif; ?>
                </div>
            </div>
            <div style="text-align:center">
                <div style="font-size:2.5rem;font-weight:800;color:var(--primary)"><?= $obra['progresso'] ?>%</div>
                <div class="progress mt-1"><div class="progress-bar" data-width="<?= $obra['progresso'] ?>" style="width:0"></div></div>
                <div class="text-xs text-muted mt-1">Progresso</div>
                <a href="<?= APP_URL ?>/plantas.php?obra_id=<?= $id ?>" class="btn btn-sm btn-primary mt-3"><i class="fa-regular fa-map"></i> Ver plantas</a>
            </div>
        </div>
    </div>
</div>

<!-- Etapas (somente leitura) -->
<div class="card mb-4">
    <div class="card-header"><h2>Andamento por Etapas</h2></div>
    <div style="padding:0">
    <?php foreach ($etapas as $e): ?>
    <div style="padding:14px 24px;border-bottom:1px solid var(--neutral-100);display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:160px">
            <div class="flex items-center gap-2 mb-1">
                <strong style="font-size:.9rem"><?= sanitize($e['nome']) ?></strong>
                <?= statusBadge($e['status']) ?>
            </div>
            <div class="progress" style="max-width:250px">
                <div class="progress-bar" data-width="<?= $e['progresso'] ?>" style="width:0"></div>
            </div>
        </div>
        <span style="font-size:1.1rem;font-weight:700;color:var(--primary);min-width:48px;text-align:right"><?= $e['progresso'] ?>%</span>
    </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- Orçamentos -->
<div class="card mb-4">
    <div class="card-header"><h2>Orçamentos</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Título</th><th>Status</th><th>Total Estimado</th><th>Total Cotado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orcamentos as $orc): ?>
            <tr>
                <td><?= sanitize($orc['titulo']) ?></td>
                <td><?= statusBadge($orc['status']) ?></td>
                <td>R$ <?= number_format($orc['total_estimado'],2,',','.') ?></td>
                <td>R$ <?= number_format($orc['total_cotado'],2,',','.') ?></td>
                <td><a href="<?= APP_URL ?>/cliente/orcamento_ver.php?id=<?= $orc['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$orcamentos): ?><tr><td colspan="5" class="text-center text-muted">Nenhum orçamento.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Compras -->
<?php if ($compras): ?>
<div class="card">
    <div class="card-header"><h2>Compras / Pedidos</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fornecedor</th><th>Status</th><th>Valor</th><th>Prev. Entrega</th></tr></thead>
            <tbody>
            <?php foreach ($compras as $cp): ?>
            <tr>
                <td><?= sanitize($cp['fornecedor']) ?></td>
                <td><?= statusBadge($cp['status']) ?></td>
                <td>R$ <?= number_format($cp['valor_total'],2,',','.') ?></td>
                <td class="text-xs"><?= $cp['data_prev_entrega'] ? date('d/m/y',strtotime($cp['data_prev_entrega'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div></div></div>
<?php pageFoot(); ?>
