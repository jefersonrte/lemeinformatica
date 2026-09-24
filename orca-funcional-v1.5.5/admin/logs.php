<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

$pag=max(1,(int)($_GET['pag']??1)); $limit=30; $offset=($pag-1)*$limit;
$total = (int)$db->query('SELECT COUNT(*) FROM logs')->fetchColumn();
$logs  = $db->query("SELECT l.*,u.nome as usuario FROM logs l LEFT JOIN usuarios u ON u.id=l.usuario_id ORDER BY l.criado_em DESC LIMIT $limit OFFSET $offset")->fetchAll();

pageHead('Logs do Sistema');
?>
<div class="layout">
<?php sidebar('logs'); ?>
<div class="main">
<?php topbar('Logs de Atividade'); ?>
<div class="content">
<div class="card">
    <div class="card-header"><h2>Logs <span class="badge badge-gray"><?= $total ?></span></h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Data/Hora</th><th>Usuário</th><th>Ação</th><th>Tabela</th><th>Registro</th><th>IP</th><th>Detalhe</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td class="text-xs text-muted"><?= date('d/m/y H:i:s',strtotime($l['criado_em'])) ?></td>
                <td class="text-sm"><?= sanitize($l['usuario'] ?? '—') ?></td>
                <td class="text-sm"><code><?= sanitize($l['acao']) ?></code></td>
                <td class="text-xs"><?= sanitize($l['tabela'] ?? '—') ?></td>
                <td class="text-xs"><?= $l['registro_id'] ?? '—' ?></td>
                <td class="text-xs text-muted"><?= sanitize($l['ip'] ?? '—') ?></td>
                <td class="text-xs text-muted truncate" style="max-width:200px"><?= sanitize($l['detalhe'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="7" class="text-center text-muted">Sem registros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total,$limit,$pag,APP_URL.'/admin/logs.php?'); ?>
</div>
</div></div></div>
<?php pageFoot(); ?>
