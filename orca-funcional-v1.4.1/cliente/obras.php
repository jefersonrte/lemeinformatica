<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireLogin();
if (isAdmin()) redirect(APP_URL.'/admin/obras.php');

$db  = getDB();
$uid = currentUserId();
$cliente = $db->prepare('SELECT id FROM clientes WHERE usuario_id=?'); $cliente->execute([$uid]); $cid = (int)$cliente->fetchColumn();

$pag = max(1,(int)($_GET['pag']??1)); $limit=12; $offset=($pag-1)*$limit;
$totalStatement = $db->prepare('SELECT COUNT(*) FROM obras WHERE cliente_id=?');
$totalStatement->execute([$cid]);
$total = (int) $totalStatement->fetchColumn();

$obras = $db->prepare('SELECT * FROM obras WHERE cliente_id=? ORDER BY criado_em DESC LIMIT '.$limit.' OFFSET '.$offset);
$obras->execute([$cid]);
$obras = $obras->fetchAll();

pageHead('Minhas Obras');
?>
<div class="layout">
<?php sidebar('obras'); ?>
<div class="main">
<?php topbar('Minhas Obras'); ?>
<div class="content">
<?php flashMessage(); ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
<?php foreach ($obras as $o): ?>
<div class="card" style="display:flex;flex-direction:column">
    <div class="card-body" style="flex:1">
        <div class="flex justify-between items-start mb-2">
            <h3 style="font-size:.95rem;font-weight:700"><?= sanitize($o['nome']) ?></h3>
            <?= statusBadge($o['status']) ?>
        </div>
        <?php if ($o['cidade']): ?>
        <p class="text-xs text-muted mb-2">📍 <?= sanitize($o['cidade'] . ($o['estado'] ? '/'.$o['estado'] : '')) ?></p>
        <?php endif; ?>
        <div class="progress mb-1"><div class="progress-bar" data-width="<?= $o['progresso'] ?>" style="width:0"></div></div>
        <div class="flex justify-between text-xs text-muted">
            <span><?= $o['progresso'] ?>% concluído</span>
            <?php if ($o['data_prev_fim']): ?><span>Prev. <?= date('d/m/y',strtotime($o['data_prev_fim'])) ?></span><?php endif; ?>
        </div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--neutral-100)">
        <a href="<?= APP_URL ?>/cliente/obra_detalhe.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm w-full" style="justify-content:center">Ver Detalhes</a>
    </div>
</div>
<?php endforeach; ?>
<?php if (!$obras): ?>
<div class="card"><div class="card-body text-center text-muted">Nenhuma obra cadastrada ainda.</div></div>
<?php endif; ?>
</div>

<?php paginacao($total,$limit,$pag,APP_URL.'/cliente/obras.php?'); ?>

</div></div></div>
<?php pageFoot(); ?>
