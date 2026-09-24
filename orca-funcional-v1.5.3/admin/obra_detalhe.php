<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/admin/obras.php');

$obra = $db->prepare('SELECT o.*, c.razao_social, c.whatsapp as cliente_wa FROM obras o JOIN clientes c ON c.id=o.cliente_id WHERE o.id=?');
$obra->execute([$id]);
$obra = $obra->fetch();
if (!$obra) redirect(APP_URL . '/admin/obras.php');

// Atualiza etapa via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $etapaId = (int)($_POST['etapa_id'] ?? 0);
    $status  = $_POST['etapa_status'] ?? '';
    $prog    = max(0, min(100, (int)($_POST['etapa_progresso'] ?? 0)));
    if ($etapaId) {
        $db->prepare('UPDATE obra_etapas SET status=?, progresso=? WHERE id=? AND obra_id=?')
           ->execute([$status, $prog, $etapaId, $id]);
        // Recalcula progresso geral da obra
        $rows = $db->prepare('SELECT progresso FROM obra_etapas WHERE obra_id=?');
        $rows->execute([$id]);
        $progs = $rows->fetchAll(PDO::FETCH_COLUMN);
        $avg   = count($progs) ? (int)round(array_sum($progs) / count($progs)) : 0;
        $novoStatus = $avg >= 100 ? 'concluida' : ($avg > 0 ? 'em_andamento' : $obra['status']);
        $db->prepare('UPDATE obras SET progresso=?, status=? WHERE id=?')->execute([$avg, $novoStatus, $id]);
        setFlash('success', 'Etapa atualizada.');
    }
    redirect(APP_URL . '/admin/obra_detalhe.php?id=' . $id);
}

$etapas    = $db->prepare('SELECT * FROM obra_etapas WHERE obra_id=? ORDER BY ordem');
$etapas->execute([$id]);
$etapas    = $etapas->fetchAll();

$orcamentos = $db->prepare("SELECT o.*, COUNT(oi.id) as qtd_itens FROM orcamentos o LEFT JOIN orcamento_itens oi ON oi.orcamento_id=o.id WHERE o.obra_id=? GROUP BY o.id ORDER BY o.criado_em DESC");
$orcamentos->execute([$id]);
$orcamentos = $orcamentos->fetchAll();

$compras = $db->prepare("SELECT cp.*, f.nome as fornecedor FROM compras cp JOIN fornecedores f ON f.id=cp.fornecedor_id WHERE cp.obra_id=? ORDER BY cp.criado_em DESC");
$compras->execute([$id]);
$compras = $compras->fetchAll();

pageHead('Detalhe da Obra');
?>
<div class="layout">
<?php sidebar('obras'); ?>
<div class="main">
<?php topbar(sanitize($obra['nome'])); ?>
<div class="content">
<?php flashMessage(); ?>

<!-- Header info -->
<div class="card mb-4">
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr auto;gap:20px;align-items:start">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <h1 style="font-size:1.4rem;font-weight:800"><?= sanitize($obra['nome']) ?></h1>
                    <?= statusBadge($obra['status']) ?>
                </div>
                <div class="flex gap-4 text-sm text-muted" style="flex-wrap:wrap">
                    <span>👥 <?= sanitize($obra['razao_social']) ?></span>
                    <?php if ($obra['cidade']): ?><span>📍 <?= sanitize($obra['cidade'] . '/' . $obra['estado']) ?></span><?php endif; ?>
                    <?php if ($obra['data_inicio']): ?><span>📅 Início: <?= date('d/m/Y', strtotime($obra['data_inicio'])) ?></span><?php endif; ?>
                    <?php if ($obra['data_prev_fim']): ?><span>🏁 Previsão: <?= date('d/m/Y', strtotime($obra['data_prev_fim'])) ?></span><?php endif; ?>
                    <span>💰 R$ <?= number_format($obra['valor_total'],2,',','.') ?></span>
                </div>
                <?php if ($obra['descricao']): ?>
                <p class="text-sm mt-3" style="color:var(--neutral-600)"><?= nl2br(sanitize($obra['descricao'])) ?></p>
                <?php endif; ?>
            </div>
            <div style="text-align:center;min-width:100px">
                <div style="font-size:2.5rem;font-weight:800;color:var(--primary)"><?= $obra['progresso'] ?>%</div>
                <div class="progress" style="margin-top:6px">
                    <div class="progress-bar" data-width="<?= $obra['progresso'] ?>" style="width:0"></div>
                </div>
                <div class="text-xs text-muted mt-1">Progresso</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs" id="tabsObra">
    <button class="tab-link active" data-tab="tabEtapas" onclick="switchTab('tabsObra','tabEtapas')">Etapas da Obra</button>
    <button class="tab-link" data-tab="tabOrcamentos" onclick="switchTab('tabsObra','tabOrcamentos')">Orçamentos</button>
    <button class="tab-link" data-tab="tabCompras" onclick="switchTab('tabsObra','tabCompras')">Compras</button>
    <a class="tab-link" href="<?= APP_URL ?>/plantas.php?obra_id=<?= $id ?>"><i class="fa-regular fa-map"></i> Plantas</a>
</div>

<!-- Etapas -->
<div id="tabEtapas" class="tab-pane active">
    <div class="card">
        <div class="card-header">
            <h2>Etapas / Andamento</h2>
            <div class="flex gap-2">
                <a href="<?= APP_URL ?>/plantas.php?obra_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="fa-regular fa-map"></i> Ver plantas</a>
                <a href="<?= APP_URL ?>/admin/obras.php" class="btn btn-sm btn-outline"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
        <div class="card-body" style="padding:0">
        <?php foreach ($etapas as $etapa): ?>
        <div style="padding:16px 24px;border-bottom:1px solid var(--neutral-100);display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <strong style="font-size:.9rem"><?= sanitize($etapa['nome']) ?></strong>
                    <?= statusBadge($etapa['status']) ?>
                </div>
                <div class="progress" style="max-width:300px">
                    <div class="progress-bar" data-width="<?= $etapa['progresso'] ?>" style="width:0"></div>
                </div>
                <span class="text-xs text-muted"><?= $etapa['progresso'] ?>%</span>
            </div>
            <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="etapa_id" value="<?= $etapa['id'] ?>">
                <select name="etapa_status" class="form-control" style="width:140px;padding:5px 8px;font-size:.8rem">
                    <?php foreach (['pendente','em_andamento','concluida'] as $s): ?>
                    <option value="<?= $s ?>" <?= $etapa['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="etapa_progresso" value="<?= $etapa['progresso'] ?>" min="0" max="100" class="form-control" style="width:70px;padding:5px 8px;font-size:.8rem">
                <button class="btn btn-sm btn-primary" type="submit">OK</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (!$etapas): ?><div class="text-center text-muted" style="padding:24px">Nenhuma etapa cadastrada.</div><?php endif; ?>
        </div>
    </div>
</div>

<!-- Orçamentos -->
<div id="tabOrcamentos" class="tab-pane">
    <div class="card">
        <div class="card-header">
            <h2>Orçamentos desta Obra</h2>
            <a href="<?= APP_URL ?>/admin/orcamento_novo.php?obra_id=<?= $id ?>" class="btn btn-sm btn-primary">+ Novo Orçamento</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Título</th><th>Itens</th><th>Status</th><th>Total Estimado</th><th>Total Cotado</th><th>Ações</th></tr></thead>
                <tbody>
                <?php foreach ($orcamentos as $orc): ?>
                <tr>
                    <td><?= sanitize($orc['titulo']) ?></td>
                    <td><?= $orc['qtd_itens'] ?></td>
                    <td><?= statusBadge($orc['status']) ?></td>
                    <td>R$ <?= number_format($orc['total_estimado'],2,',','.') ?></td>
                    <td>R$ <?= number_format($orc['total_cotado'],2,',','.') ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/admin/orcamento_detalhe.php?id=<?= $orc['id'] ?>" class="btn btn-sm btn-outline">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$orcamentos): ?><tr><td colspan="6" class="text-center text-muted">Nenhum orçamento.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Compras -->
<div id="tabCompras" class="tab-pane">
    <div class="card">
        <div class="card-header"><h2>Compras / Pedidos</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fornecedor</th><th>Status</th><th>Valor</th><th>Pedido em</th><th>Prev. Entrega</th><th>NF</th></tr></thead>
                <tbody>
                <?php foreach ($compras as $cp): ?>
                <tr>
                    <td><?= sanitize($cp['fornecedor']) ?></td>
                    <td><?= statusBadge($cp['status']) ?></td>
                    <td>R$ <?= number_format($cp['valor_total'],2,',','.') ?></td>
                    <td class="text-xs"><?= $cp['data_pedido'] ? date('d/m/y',strtotime($cp['data_pedido'])) : '-' ?></td>
                    <td class="text-xs"><?= $cp['data_prev_entrega'] ? date('d/m/y',strtotime($cp['data_prev_entrega'])) : '-' ?></td>
                    <td class="text-xs"><?= sanitize($cp['nf_numero'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$compras): ?><tr><td colspan="6" class="text-center text-muted">Nenhuma compra.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div></div></div>
<?php pageFoot(); ?>
