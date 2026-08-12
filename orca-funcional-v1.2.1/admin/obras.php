<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

// Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';

    $campos = ['nome','descricao','endereco','cidade','estado','status','data_inicio','data_prev_fim','valor_total','progresso'];
    $data = [];
    foreach ($campos as $c) $data[$c] = trim($_POST[$c] ?? '');

    if (in_array($acao, ['criar', 'editar'])) {
        if (!$data['nome']) { setFlash('error','Nome da obra obrigatório.'); redirect(APP_URL.'/admin/obras.php'); }
        $data['cliente_id'] = (int)($_POST['cliente_id'] ?? 0);
        if (!$data['cliente_id']) { setFlash('error','Selecione o cliente.'); redirect(APP_URL.'/admin/obras.php'); }
        $data['progresso'] = max(0, min(100, (int)$data['progresso']));
        $data['valor_total'] = (float)str_replace(['.', ','], ['', '.'], $data['valor_total']);
        foreach (['data_inicio','data_prev_fim'] as $d) if (!$data[$d]) $data[$d] = null;

        if ($acao === 'criar') {
            $db->prepare('INSERT INTO obras (cliente_id,nome,descricao,endereco,cidade,estado,status,data_inicio,data_prev_fim,valor_total,progresso) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
               ->execute(array_values($data));
            $obraId = $db->lastInsertId();
            // Etapas padrão
            $etapas = ['Fundação','Estrutura','Alvenaria','Cobertura','Elétrica / Hidráulica','Revestimentos','Acabamento'];
            $sth = $db->prepare('INSERT INTO obra_etapas (obra_id,nome,ordem) VALUES (?,?,?)');
            foreach ($etapas as $i => $e) $sth->execute([$obraId, $e, $i+1]);
            logAction('obra_criada','obras',$obraId,$data['nome']);
            setFlash('success','Obra criada com sucesso.');
        } else {
            $id = (int)($_POST['obra_id'] ?? 0);
            $db->prepare('UPDATE obras SET cliente_id=?,nome=?,descricao=?,endereco=?,cidade=?,estado=?,status=?,data_inicio=?,data_prev_fim=?,valor_total=?,progresso=? WHERE id=?')
               ->execute([...array_values($data), $id]);
            logAction('obra_editada','obras',$id,$data['nome']);
            setFlash('success','Obra atualizada.');
        }
    } elseif ($acao === 'excluir') {
        $id = (int)($_POST['obra_id'] ?? 0);
        $db->prepare('DELETE FROM obras WHERE id=?')->execute([$id]);
        logAction('obra_excluida','obras',$id);
        setFlash('success','Obra excluída.');
    }
    $redir = isset($_POST['cliente_id_filter']) && $_POST['cliente_id_filter'] ? '?cliente_id=' . (int)$_POST['cliente_id_filter'] : '';
    redirect(APP_URL . '/admin/obras.php' . $redir);
}

$clienteFilter = (int)($_GET['cliente_id'] ?? 0);
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$pag     = max(1, (int)($_GET['pag'] ?? 1));
$limit   = 15;
$offset  = ($pag - 1) * $limit;

$wheres = []; $params = [];
if ($clienteFilter) { $wheres[] = 'o.cliente_id = ?'; $params[] = $clienteFilter; }
if ($search)        { $wheres[] = 'o.nome LIKE ?'; $params[] = "%$search%"; }
if ($status)        { $wheres[] = 'o.status = ?'; $params[] = $status; }
$where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$totalSth = $db->prepare("SELECT COUNT(*) FROM obras o $where");
$totalSth->execute($params);
$total = (int)$totalSth->fetchColumn();

$stmt = $db->prepare("SELECT o.*, c.razao_social FROM obras o JOIN clientes c ON c.id=o.cliente_id $where ORDER BY o.criado_em DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$obras = $stmt->fetchAll();

$clientes = $db->query('SELECT id, razao_social FROM clientes ORDER BY razao_social')->fetchAll();
$clienteAtual = null;
if ($clienteFilter) {
    $clienteAtualStatement = $db->prepare('SELECT razao_social FROM clientes WHERE id = ?');
    $clienteAtualStatement->execute([$clienteFilter]);
    $clienteAtual = $clienteAtualStatement->fetchColumn();
}

$urlBase = APP_URL . '/admin/obras.php?cliente_id=' . $clienteFilter . '&q=' . urlencode($search) . '&status=' . urlencode($status);

pageHead('Obras / Projetos');
?>
<div class="layout">
<?php sidebar('obras'); ?>
<div class="main">
<?php topbar('Obras / Projetos' . ($clienteAtual ? ' — ' . sanitize($clienteAtual) : '')); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Obras <span class="badge badge-blue"><?= $total ?></span></h2>
        <div class="flex gap-2" style="flex-wrap:wrap">
            <form method="get" class="flex gap-2" style="flex-wrap:wrap">
                <?php if ($clienteFilter): ?><input type="hidden" name="cliente_id" value="<?= $clienteFilter ?>"><?php endif; ?>
                <input type="text" name="q" class="form-control" placeholder="Buscar obra..." value="<?= sanitize($search) ?>" style="width:180px">
                <select name="status" class="form-control" style="width:160px">
                    <option value="">Todos status</option>
                    <?php foreach (['planejamento','em_andamento','pausada','concluida','cancelada'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm">Filtrar</button>
            </form>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalObra')">+ Nova Obra</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Obra</th><th>Cliente</th><th>Status</th>
                <th>Progresso</th><th>Valor</th><th>Previsão</th><th>Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($obras as $o): ?>
            <tr>
                <td>
                    <a href="<?= APP_URL ?>/admin/obra_detalhe.php?id=<?= $o['id'] ?>" class="font-bold"><?= sanitize($o['nome']) ?></a>
                    <div class="text-xs text-muted"><?= $o['cidade'] ? sanitize($o['cidade'] . ($o['estado'] ? '/' . $o['estado'] : '')) : '' ?></div>
                </td>
                <td class="text-sm"><?= sanitize($o['razao_social']) ?></td>
                <td><?= statusBadge($o['status']) ?></td>
                <td style="min-width:120px">
                    <div class="progress"><div class="progress-bar" data-width="<?= $o['progresso'] ?>" style="width:0"></div></div>
                    <span class="text-xs text-muted"><?= $o['progresso'] ?>%</span>
                </td>
                <td class="text-sm">R$ <?= number_format($o['valor_total'],2,',','.') ?></td>
                <td class="text-xs text-muted"><?= $o['data_prev_fim'] ? date('d/m/y', strtotime($o['data_prev_fim'])) : '-' ?></td>
                <td>
                    <div class="td-actions">
                        <a href="<?= APP_URL ?>/admin/obra_detalhe.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Ver</a>
                        <a href="<?= APP_URL ?>/plantas.php?obra_id=<?= $o['id'] ?>" class="btn btn-sm btn-outline" title="Plantas"><i class="fa-regular fa-map"></i></a>
                        <a href="<?= APP_URL ?>/admin/orcamentos.php?obra_id=<?= $o['id'] ?>" class="btn btn-sm btn-outline" title="Orçamentos"><i class="fa-solid fa-file-invoice-dollar"></i></a>
                        <button class="btn btn-sm btn-outline" onclick='editarObra(<?= json_encode($o) ?>)' title="Editar"><i class="fa-solid fa-pen"></i></button>
                        <form method="post" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="obra_id" value="<?= $o['id'] ?>">
                            <input type="hidden" name="cliente_id_filter" value="<?= $clienteFilter ?>">
                            <button class="btn btn-sm btn-danger" type="submit" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$obras): ?><tr><td colspan="7" class="text-center text-muted">Nenhuma obra encontrada.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total, $limit, $pag, $urlBase); ?>
</div>

<!-- Modal Obra -->
<div class="modal-overlay" id="modalObra">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3 id="modalObraTitulo">Nova Obra / Projeto</h3>
            <button class="btn-close" onclick="closeModal('modalObra')">✕</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="criar" id="obraAcao">
                <input type="hidden" name="obra_id" value="" id="obraId">
                <input type="hidden" name="cliente_id_filter" value="<?= $clienteFilter ?>">
                <div class="form-row">
                    <div class="form-group" style="grid-column:span 2">
                        <label class="form-label">Nome da Obra *</label>
                        <input type="text" name="nome" id="fObraNome" class="form-control" required maxlength="200">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="grid-column:span 2">
                        <label class="form-label">Cliente *</label>
                        <select name="cliente_id" id="fObraCliente" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= $clienteFilter == $cl['id'] ? 'selected' : '' ?>><?= sanitize($cl['razao_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" id="fObraDesc" class="form-control"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" id="fObraEnd" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="fObraCidade" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">UF</label>
                        <select name="estado" id="fObraUf" class="form-control">
                            <option value="">--</option>
                            <?php foreach (['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'] as $uf): ?>
                            <option value="<?= $uf ?>"><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="fObraStatus" class="form-control">
                            <option value="planejamento">Planejamento</option>
                            <option value="em_andamento">Em Andamento</option>
                            <option value="pausada">Pausada</option>
                            <option value="concluida">Concluída</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Progresso (%)</label>
                        <input type="number" name="progresso" id="fObraProgresso" class="form-control" min="0" max="100" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valor Total (R$)</label>
                        <input type="text" name="valor_total" id="fObraValor" class="form-control" placeholder="0,00">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Data Início</label>
                        <input type="date" name="data_inicio" id="fObraInicio" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Previsão de Término</label>
                        <input type="date" name="data_prev_fim" id="fObraFim" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalObra')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarObra(o) {
    document.getElementById('modalObraTitulo').textContent = 'Editar Obra';
    document.getElementById('obraAcao').value    = 'editar';
    document.getElementById('obraId').value      = o.id;
    document.getElementById('fObraNome').value   = o.nome || '';
    document.getElementById('fObraCliente').value = o.cliente_id || '';
    document.getElementById('fObraDesc').value   = o.descricao || '';
    document.getElementById('fObraEnd').value    = o.endereco || '';
    document.getElementById('fObraCidade').value = o.cidade || '';
    document.getElementById('fObraUf').value     = o.estado || '';
    document.getElementById('fObraStatus').value = o.status || 'planejamento';
    document.getElementById('fObraProgresso').value = o.progresso || 0;
    document.getElementById('fObraValor').value  = o.valor_total || '0';
    document.getElementById('fObraInicio').value = o.data_inicio || '';
    document.getElementById('fObraFim').value    = o.data_prev_fim || '';
    openModal('modalObra');
}
</script>

</div></div></div>
<?php pageFoot(); ?>
