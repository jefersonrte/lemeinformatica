<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';
requireAdmin();

$db = getDB();
$totalClientes = (int) $db->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
$totalObras = (int) $db->query('SELECT COUNT(*) FROM obras')->fetchColumn();
$obrasAtivas = (int) $db->query("SELECT COUNT(*) FROM obras WHERE status = 'em_andamento'")->fetchColumn();
$orcamentosAbertos = (int) $db->query("SELECT COUNT(*) FROM orcamentos WHERE status IN ('rascunho', 'aguardando_cotacao')")->fetchColumn();

$financeiro = $db->query(
    'SELECT '
    . '(SELECT COALESCE(SUM(total_estimado), 0) FROM orcamentos WHERE status <> \'cancelado\') AS orcado, '
    . '(SELECT COALESCE(SUM(total_cotado), 0) FROM orcamentos WHERE status <> \'cancelado\') AS cotado, '
    . '(SELECT COALESCE(SUM(valor_total), 0) FROM compras WHERE status <> \'cancelado\') AS realizado'
)->fetch();
$orcado = (float) $financeiro['orcado'];
$cotado = (float) $financeiro['cotado'];
$realizado = (float) $financeiro['realizado'];
$desvio = $realizado - $orcado;
$percentualRealizado = $orcado > 0 ? min(999, ($realizado / $orcado) * 100) : 0;

$obrasFinanceiro = $db->query(
    "SELECT o.id, o.nome, o.status, o.progresso, c.razao_social,
        COALESCE((SELECT SUM(orc.total_estimado) FROM orcamentos orc WHERE orc.obra_id = o.id AND orc.status <> 'cancelado'), 0) AS orcado,
        COALESCE((SELECT SUM(orc.total_cotado) FROM orcamentos orc WHERE orc.obra_id = o.id AND orc.status <> 'cancelado'), 0) AS cotado,
        COALESCE((SELECT SUM(cp.valor_total) FROM compras cp WHERE cp.obra_id = o.id AND cp.status <> 'cancelado'), 0) AS realizado
     FROM obras o JOIN clientes c ON c.id = o.cliente_id
     ORDER BY realizado DESC, orcado DESC, o.criado_em DESC LIMIT 8"
)->fetchAll();

$statusRows = $db->query('SELECT status, COUNT(*) AS total FROM obras GROUP BY status ORDER BY total DESC')->fetchAll();
$statusLabels = [];
$statusValues = [];
foreach ($statusRows as $row) {
    $statusLabels[] = ucwords(str_replace('_', ' ', (string) $row['status']));
    $statusValues[] = (int) $row['total'];
}

$cotacoes = $db->query(
    "SELECT co.id, co.data_envio, f.nome AS fornecedor, orc.titulo AS orcamento
     FROM cotacoes co JOIN fornecedores f ON f.id = co.fornecedor_id
     JOIN orcamentos orc ON orc.id = co.orcamento_id
     WHERE co.status = 'enviada' ORDER BY co.data_envio DESC LIMIT 6"
)->fetchAll();

$chartLabels = array_column($obrasFinanceiro, 'nome');
$chartOrcado = array_map('floatval', array_column($obrasFinanceiro, 'orcado'));
$chartCotado = array_map('floatval', array_column($obrasFinanceiro, 'cotado'));
$chartRealizado = array_map('floatval', array_column($obrasFinanceiro, 'realizado'));

pageHead('Visão financeira');
?>
<div class="layout">
<?php sidebar('dashboard'); ?>
<div class="main">
<?php topbar('Visão financeira'); ?>
<div class="content">
<?php flashMessage(); ?>

<section class="dashboard-hero">
    <div>
        <span class="version-chip" style="display:inline-flex;color:#dbeafe;background:rgba(255,255,255,.1)">PAINEL EXECUTIVO</span>
        <h1>Custos, obras e decisões em uma única visão.</h1>
        <p>Acompanhe o que foi orçado, o melhor valor cotado e o custo efetivamente realizado em cada projeto.</p>
    </div>
    <div class="hero-metric">
        <small>Realizado sobre o orçado</small>
        <strong><?= number_format($percentualRealizado, 1, ',', '.') ?>%</strong>
        <div class="progress" style="margin-top:12px;background:rgba(255,255,255,.15)"><div class="progress-bar" style="width:<?= min(100, $percentualRealizado) ?>%;background:#5eead4"></div></div>
    </div>
</section>

<div class="stats-grid">
    <div class="stat-card" style="--stat-glow:rgba(37,99,235,.13)"><div class="stat-icon" style="background:#dbeafe;color:#2563eb"><i class="fa-solid fa-building"></i></div><div class="stat-info"><div class="value"><?= $totalObras ?></div><div class="label">Projetos monitorados</div></div></div>
    <div class="stat-card" style="--stat-glow:rgba(20,184,166,.13)"><div class="stat-icon" style="background:#ccfbf1;color:#0f766e"><i class="fa-solid fa-person-digging"></i></div><div class="stat-info"><div class="value"><?= $obrasAtivas ?></div><div class="label">Em andamento</div></div></div>
    <div class="stat-card" style="--stat-glow:rgba(124,58,237,.12)"><div class="stat-icon" style="background:#ede9fe;color:#7c3aed"><i class="fa-solid fa-file-circle-check"></i></div><div class="stat-info"><div class="value"><?= $orcamentosAbertos ?></div><div class="label">Orçamentos abertos</div></div></div>
    <div class="stat-card" style="--stat-glow:rgba(245,158,11,.13)"><div class="stat-icon" style="background:#fef3c7;color:#b45309"><i class="fa-solid fa-users"></i></div><div class="stat-info"><div class="value"><?= $totalClientes ?></div><div class="label">Clientes ativos</div></div></div>
</div>

<div class="finance-grid">
    <div class="card finance-card"><span class="eyebrow">Valor orçado</span><span class="amount" style="color:#2563eb">R$ <?= number_format($orcado, 2, ',', '.') ?></span><div class="trend">Base estimada dos orçamentos ativos</div></div>
    <div class="card finance-card"><span class="eyebrow">Melhor custo cotado</span><span class="amount" style="color:#0f766e">R$ <?= number_format($cotado, 2, ',', '.') ?></span><div class="trend"><?= $orcado > 0 ? number_format((($cotado - $orcado) / $orcado) * 100, 1, ',', '.') : '0,0' ?>% em relação ao orçamento</div></div>
    <div class="card finance-card"><span class="eyebrow">Custo realizado</span><span class="amount" style="color:<?= $desvio > 0 ? '#dc2626' : '#16a34a' ?>">R$ <?= number_format($realizado, 2, ',', '.') ?></span><div class="trend"><?= $desvio > 0 ? 'Acima' : 'Abaixo' ?> do orçamento em R$ <?= number_format(abs($desvio), 2, ',', '.') ?></div></div>
</div>

<div class="charts-grid">
    <section class="card chart-card">
        <div class="card-header"><div><h2>Orçado × cotado × realizado</h2><div class="text-xs text-muted">Comparação por projeto</div></div><a href="<?= APP_URL ?>/admin/orcamentos.php" class="btn btn-sm btn-outline">Abrir orçamentos</a></div>
        <div class="chart-wrap"><canvas id="custosChart" aria-label="Comparativo financeiro por projeto"></canvas></div>
    </section>
    <section class="card chart-card">
        <div class="card-header"><div><h2>Carteira de projetos</h2><div class="text-xs text-muted">Distribuição por status</div></div></div>
        <div class="chart-wrap"><canvas id="statusChart" aria-label="Projetos por status"></canvas></div>
    </section>
</div>

<div class="dashboard-grid" style="display:grid;grid-template-columns:minmax(0,1.3fr) minmax(340px,.7fr);gap:20px">
    <section class="card">
        <div class="card-header"><h2>Projetos em foco</h2><a href="<?= APP_URL ?>/admin/obras.php" class="btn btn-sm btn-outline">Ver todos</a></div>
        <div class="table-wrap"><table><thead><tr><th>Projeto</th><th>Status</th><th>Progresso</th><th>Realizado</th><th></th></tr></thead><tbody>
        <?php foreach ($obrasFinanceiro as $obra): ?>
        <tr>
            <td><strong><?= sanitize($obra['nome']) ?></strong><div class="text-xs text-muted"><?= sanitize($obra['razao_social']) ?></div></td>
            <td><?= statusBadge($obra['status']) ?></td>
            <td style="min-width:120px"><div class="progress"><div class="progress-bar" data-width="<?= (int) $obra['progresso'] ?>" style="width:0"></div></div><span class="text-xs text-muted"><?= (int) $obra['progresso'] ?>%</span></td>
            <td class="font-bold">R$ <?= number_format((float) $obra['realizado'], 2, ',', '.') ?></td>
            <td><a class="btn btn-sm btn-outline" href="<?= APP_URL ?>/admin/obra_detalhe.php?id=<?= $obra['id'] ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$obrasFinanceiro): ?><tr><td colspan="5" class="text-center text-muted">Cadastre a primeira obra para iniciar o painel.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="card">
        <div class="card-header"><h2>Cotações aguardando</h2><a href="<?= APP_URL ?>/admin/cotacoes.php" class="btn btn-sm btn-outline">Ver todas</a></div>
        <div class="card-body" style="padding-top:10px">
        <?php foreach ($cotacoes as $cotacao): ?>
            <a href="<?= APP_URL ?>/admin/cotacao_detalhe.php?id=<?= $cotacao['id'] ?>" style="display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--neutral-100)">
                <span class="stat-icon" style="width:38px;height:38px;background:#eff6ff;color:#2563eb;font-size:.95rem"><i class="fa-regular fa-paper-plane"></i></span>
                <span style="min-width:0;flex:1"><strong class="truncate" style="display:block;color:var(--neutral-800);font-size:.82rem"><?= sanitize($cotacao['fornecedor']) ?></strong><small class="truncate text-muted" style="display:block"><?= sanitize($cotacao['orcamento']) ?></small></span>
                <small class="text-muted"><?= $cotacao['data_envio'] ? date('d/m', strtotime($cotacao['data_envio'])) : '-' ?></small>
            </a>
        <?php endforeach; ?>
        <?php if (!$cotacoes): ?><div class="empty-state" style="min-height:240px;border:0"><span class="empty-icon"><i class="fa-solid fa-check"></i></span><h2>Tudo em dia</h2><p>Nenhuma cotação aguardando resposta.</p></div><?php endif; ?>
        </div>
    </section>
</div>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = 'Manrope, Segoe UI, sans-serif';
    Chart.defaults.color = '#64748b';
    const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
    new Chart(document.getElementById('custosChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            datasets: [
                { label: 'Orçado', data: <?= json_encode($chartOrcado) ?>, backgroundColor: '#93c5fd', borderRadius: 7 },
                { label: 'Cotado', data: <?= json_encode($chartCotado) ?>, backgroundColor: '#5eead4', borderRadius: 7 },
                { label: 'Realizado', data: <?= json_encode($chartRealizado) ?>, backgroundColor: '#1e3a8a', borderRadius: 7 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 18 } }, tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + money.format(ctx.raw); } } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#eef2f7' }, ticks: { callback: function (value) { return money.format(value); } } } } }
    });
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>, datasets: [{ data: <?= json_encode($statusValues) ?>, backgroundColor: ['#2563eb','#14b8a6','#f59e0b','#94a3b8','#ef4444'], borderWidth: 0, hoverOffset: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14 } } } }
    });
})();
</script>
<style>@media(max-width:1000px){.dashboard-grid{grid-template-columns:1fr!important}}</style>
<?php pageFoot(); ?>
