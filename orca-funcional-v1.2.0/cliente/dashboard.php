<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireLogin();
if (isAdmin()) redirect(APP_URL.'/admin/dashboard.php');

$db  = getDB();
$uid = currentUserId();

$cliente = $db->prepare('SELECT * FROM clientes WHERE usuario_id=?');
$cliente->execute([$uid]);
$cliente = $cliente->fetch();
if (!$cliente) { setFlash('error','Perfil de cliente não encontrado.'); redirect(APP_URL.'/logout.php'); }

$obras     = $db->prepare("SELECT o.*,(SELECT COUNT(*) FROM orcamentos WHERE obra_id=o.id) as qtd_orc FROM obras o WHERE o.cliente_id=? ORDER BY o.criado_em DESC LIMIT 5");
$obras->execute([$cliente['id']]);
$obras     = $obras->fetchAll();

$totalObras     = $db->prepare('SELECT COUNT(*) FROM obras WHERE cliente_id=?'); $totalObras->execute([$cliente['id']]); $totalObras = $totalObras->fetchColumn();
$totalOrcamentos = $db->prepare('SELECT COUNT(*) FROM orcamentos WHERE cliente_id=?'); $totalOrcamentos->execute([$cliente['id']]); $totalOrcamentos = $totalOrcamentos->fetchColumn();
$emAndamento    = $db->prepare("SELECT COUNT(*) FROM obras WHERE cliente_id=? AND status='em_andamento'"); $emAndamento->execute([$cliente['id']]); $emAndamento = $emAndamento->fetchColumn();
$cotacoesResp   = $db->prepare("SELECT COUNT(*) FROM cotacoes co JOIN orcamentos o ON o.id=co.orcamento_id WHERE o.cliente_id=? AND co.status='cotado'"); $cotacoesResp->execute([$cliente['id']]); $cotacoesResp = $cotacoesResp->fetchColumn();

pageHead('Meu Painel');
?>
<div class="layout">
<?php sidebar('dashboard'); ?>
<div class="main">
<?php topbar('Meu Painel'); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="mb-4" style="padding:20px 0 4px">
    <h2 style="font-size:1.2rem;font-weight:700">Olá, <?= sanitize($_SESSION['user_nome'] ?? 'Cliente') ?>! 👋</h2>
    <p class="text-muted text-sm"><?= sanitize($cliente['razao_social']) ?></p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7">🏠</div>
        <div class="stat-info"><div class="value" style="color:var(--success)"><?= $totalObras ?></div><div class="label">Minhas Obras</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef9c3">🔨</div>
        <div class="stat-info"><div class="value" style="color:var(--warning)"><?= $emAndamento ?></div><div class="label">Em Andamento</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe">📋</div>
        <div class="stat-info"><div class="value" style="color:var(--primary)"><?= $totalOrcamentos ?></div><div class="label">Orçamentos</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#ccfbf1">💰</div>
        <div class="stat-info"><div class="value" style="color:var(--secondary)"><?= $cotacoesResp ?></div><div class="label">Cotações Recebidas</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Minhas Obras Recentes</h2>
        <a href="<?= APP_URL ?>/cliente/obras.php" class="btn btn-sm btn-outline">Ver todas</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Obra</th><th>Status</th><th>Progresso</th><th>Previsão</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($obras as $o): ?>
            <tr>
                <td><strong><?= sanitize($o['nome']) ?></strong><div class="text-xs text-muted"><?= sanitize(($o['cidade']??'') . ($o['estado'] ? '/'.$o['estado'] : '')) ?></div></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td style="min-width:140px">
                    <div class="progress"><div class="progress-bar" data-width="<?= $o['progresso'] ?>" style="width:0"></div></div>
                    <span class="text-xs text-muted"><?= $o['progresso'] ?>%</span>
                </td>
                <td class="text-xs text-muted"><?= $o['data_prev_fim'] ? date('d/m/Y',strtotime($o['data_prev_fim'])) : '—' ?></td>
                <td><a href="<?= APP_URL ?>/cliente/obra_detalhe.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$obras): ?><tr><td colspan="5" class="text-center text-muted">Nenhuma obra cadastrada.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div></div>
<?php pageFoot(); ?>
