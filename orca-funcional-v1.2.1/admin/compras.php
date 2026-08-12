<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';
    if (in_array($acao,['criar','editar'])) {
        $obraId  = (int)($_POST['obra_id']  ?? 0);
        $cotId   = (int)($_POST['cotacao_id'] ?? 0);
        $fornId  = (int)($_POST['fornecedor_id'] ?? 0);
        $status  = $_POST['status'] ?? 'solicitado';
        $valor   = (float)str_replace(',','.',$_POST['valor_total'] ?? 0);
        $dtPed   = $_POST['data_pedido'] ?: null;
        $dtEnt   = $_POST['data_prev_entrega'] ?: null;
        $nf      = trim($_POST['nf_numero'] ?? '');
        $obs     = trim($_POST['obs'] ?? '');
        if (!$obraId || !$fornId) { setFlash('error','Obra e fornecedor obrigatórios.'); redirect(APP_URL.'/admin/compras.php'); }
        if ($acao === 'criar') {
            $db->prepare('INSERT INTO compras (obra_id,cotacao_id,fornecedor_id,status,valor_total,data_pedido,data_prev_entrega,nf_numero,obs) VALUES (?,?,?,?,?,?,?,?,?)')
               ->execute([$obraId,$cotId?:null,$fornId,$status,$valor,$dtPed,$dtEnt,$nf,$obs]);
            setFlash('success','Compra registrada.');
        } else {
            $id = (int)$_POST['compra_id'];
            $db->prepare('UPDATE compras SET obra_id=?,fornecedor_id=?,status=?,valor_total=?,data_pedido=?,data_prev_entrega=?,nf_numero=?,obs=? WHERE id=?')
               ->execute([$obraId,$fornId,$status,$valor,$dtPed,$dtEnt,$nf,$obs,$id]);
            setFlash('success','Compra atualizada.');
        }
    } elseif ($acao === 'excluir') {
        $db->prepare('DELETE FROM compras WHERE id=?')->execute([(int)$_POST['compra_id']]);
        setFlash('success','Compra excluída.');
    }
    redirect(APP_URL.'/admin/compras.php');
}

$statusPermitidos = ['solicitado','confirmado','em_producao','enviado','entregue','cancelado'];
$statusF = in_array($_GET['status'] ?? '', $statusPermitidos, true) ? (string) $_GET['status'] : '';
$pag=max(1,(int)($_GET['pag']??1)); $limit=15; $offset=($pag-1)*$limit;
$where = $statusF ? "WHERE cp.status='$statusF'" : '';
$total = (int)$db->query("SELECT COUNT(*) FROM compras cp $where")->fetchColumn();

$stmt = $db->prepare("SELECT cp.*,f.nome as fornecedor,ob.nome as obra,c.razao_social
    FROM compras cp JOIN fornecedores f ON f.id=cp.fornecedor_id JOIN obras ob ON ob.id=cp.obra_id JOIN clientes c ON c.id=ob.cliente_id
    $where ORDER BY cp.criado_em DESC LIMIT $limit OFFSET $offset");
$stmt->execute();
$compras = $stmt->fetchAll();

$obras      = $db->query('SELECT o.id,o.nome,o.cliente_id FROM obras o ORDER BY o.nome')->fetchAll();
$fornecs    = $db->query('SELECT id,nome FROM fornecedores WHERE ativo=1 ORDER BY nome')->fetchAll();
$cotAbertos = $db->query("SELECT co.id,f.nome as fornecedor,orc.titulo FROM cotacoes co JOIN fornecedores f ON f.id=co.fornecedor_id JOIN orcamentos orc ON orc.id=co.orcamento_id WHERE co.status='respondida' ORDER BY co.criado_em DESC")->fetchAll();

pageHead('Compras');
?>
<div class="layout">
<?php sidebar('compras'); ?>
<div class="main">
<?php topbar('Compras / Pedidos'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Compras <span class="badge badge-blue"><?= $total ?></span></h2>
        <div class="flex gap-2" style="flex-wrap:wrap">
            <form method="get" class="flex gap-2">
                <select name="status" class="form-control" style="width:150px">
                    <option value="">Todos</option>
                    <?php foreach (['solicitado','confirmado','em_producao','enviado','entregue','cancelado'] as $s): ?>
                    <option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= str_replace('_',' ',ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm">Filtrar</button>
            </form>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalCompra')">+ Nova Compra</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Obra</th><th>Cliente</th><th>Fornecedor</th><th>Status</th><th>Valor</th><th>Pedido</th><th>Prev. Entrega</th><th>NF</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($compras as $cp): ?>
            <tr>
                <td class="text-sm"><?= sanitize($cp['obra']) ?></td>
                <td class="text-xs text-muted"><?= sanitize($cp['razao_social']) ?></td>
                <td class="text-sm"><?= sanitize($cp['fornecedor']) ?></td>
                <td><?= statusBadge($cp['status']) ?></td>
                <td class="text-sm">R$ <?= number_format($cp['valor_total'],2,',','.') ?></td>
                <td class="text-xs"><?= $cp['data_pedido'] ? date('d/m/y',strtotime($cp['data_pedido'])) : '—' ?></td>
                <td class="text-xs"><?= $cp['data_prev_entrega'] ? date('d/m/y',strtotime($cp['data_prev_entrega'])) : '—' ?></td>
                <td class="text-xs"><?= sanitize($cp['nf_numero'] ?? '—') ?></td>
                <td>
                    <div class="td-actions">
                        <button class="btn btn-sm btn-outline" onclick='editarCompra(<?= json_encode($cp) ?>)'>✏️</button>
                        <form method="post" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="compra_id" value="<?= $cp['id'] ?>">
                            <button class="btn btn-sm btn-danger">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$compras): ?><tr><td colspan="9" class="text-center text-muted">Nenhuma compra.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total,$limit,$pag,APP_URL.'/admin/compras.php?status='.urlencode($statusF)); ?>
</div>

<div class="modal-overlay" id="modalCompra">
    <div class="modal" style="max-width:620px">
        <div class="modal-header"><h3 id="modalCompraTitulo">Nova Compra</h3><button class="btn-close" onclick="closeModal('modalCompra')">✕</button></div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="criar" id="compraAcao">
                <input type="hidden" name="compra_id" id="compraId">
                <div class="form-row">
                    <div class="form-group" style="grid-column:span 2">
                        <label class="form-label">Obra *</label>
                        <select name="obra_id" id="fCompraObra" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($obras as $ob): ?><option value="<?= $ob['id'] ?>"><?= sanitize($ob['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Fornecedor *</label>
                        <select name="fornecedor_id" id="fCompraForn" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($fornecs as $f): ?><option value="<?= $f['id'] ?>"><?= sanitize($f['nome']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cotação (opcional)</label>
                        <select name="cotacao_id" id="fCompraCot" class="form-control">
                            <option value="">— Sem cotação</option>
                            <?php foreach ($cotAbertos as $co): ?><option value="<?= $co['id'] ?>"><?= sanitize($co['titulo']) ?> — <?= sanitize($co['fornecedor']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="fCompraStatus" class="form-control">
                            <?php foreach (['solicitado','confirmado','em_producao','enviado','entregue','cancelado'] as $s): ?>
                            <option value="<?= $s ?>"><?= str_replace('_',' ',ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valor Total (R$)</label>
                        <input type="text" name="valor_total" id="fCompraValor" class="form-control" placeholder="0,00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Data do Pedido</label><input type="date" name="data_pedido" id="fCompraDtPed" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Prev. Entrega</label><input type="date" name="data_prev_entrega" id="fCompraDtEnt" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Nº NF</label><input type="text" name="nf_numero" id="fCompraNf" class="form-control" maxlength="50"></div>
                </div>
                <div class="form-group"><label class="form-label">Observações</label><textarea name="obs" id="fCompraObs" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalCompra')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
<script>
function editarCompra(cp) {
    document.getElementById('modalCompraTitulo').textContent = 'Editar Compra';
    document.getElementById('compraAcao').value  = 'editar';
    document.getElementById('compraId').value    = cp.id;
    document.getElementById('fCompraObra').value   = cp.obra_id;
    document.getElementById('fCompraForn').value   = cp.fornecedor_id;
    document.getElementById('fCompraCot').value    = cp.cotacao_id || '';
    document.getElementById('fCompraStatus').value = cp.status;
    document.getElementById('fCompraValor').value  = cp.valor_total;
    document.getElementById('fCompraDtPed').value  = cp.data_pedido || '';
    document.getElementById('fCompraDtEnt').value  = cp.data_prev_entrega || '';
    document.getElementById('fCompraNf').value     = cp.nf_numero || '';
    document.getElementById('fCompraObs').value    = cp.obs || '';
    openModal('modalCompra');
}
</script>
</div></div></div>
<?php pageFoot(); ?>
