<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';
    $fields = ['nome','cnpj_cpf','email','telefone','whatsapp','contato','endereco','cidade','estado','obs'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');
    $cats = $_POST['categorias'] ?? [];

    if (in_array($acao,['criar','editar'])) {
        if (!$data['nome']) { setFlash('error','Nome obrigatório.'); redirect(APP_URL.'/admin/fornecedores.php'); }
        if ($acao === 'criar') {
            $db->prepare('INSERT INTO fornecedores (nome,cnpj_cpf,email,telefone,whatsapp,contato,endereco,cidade,estado,obs) VALUES (?,?,?,?,?,?,?,?,?,?)')
               ->execute(array_values($data));
            $fid = $db->lastInsertId();
        } else {
            $fid = (int)$_POST['fornecedor_id'];
            $db->prepare('UPDATE fornecedores SET nome=?,cnpj_cpf=?,email=?,telefone=?,whatsapp=?,contato=?,endereco=?,cidade=?,estado=?,obs=? WHERE id=?')
               ->execute([...array_values($data), $fid]);
            $db->prepare('DELETE FROM fornecedor_categorias WHERE fornecedor_id=?')->execute([$fid]);
        }
        $sth = $db->prepare('INSERT IGNORE INTO fornecedor_categorias (fornecedor_id,categoria_id) VALUES (?,?)');
        foreach ($cats as $cid) $sth->execute([$fid, (int)$cid]);
        logAction('fornecedor_'.($acao==='criar'?'criado':'editado'),'fornecedores',$fid,$data['nome']);
        setFlash('success','Fornecedor '.($acao==='criar'?'criado':'atualizado').'.');
    } elseif ($acao === 'excluir') {
        $fid = (int)$_POST['fornecedor_id'];
        $db->prepare('DELETE FROM fornecedores WHERE id=?')->execute([$fid]);
        setFlash('success','Fornecedor excluído.');
    }
    redirect(APP_URL.'/admin/fornecedores.php');
}

$search = trim($_GET['q'] ?? '');
$where  = $search ? 'WHERE nome LIKE ? OR email LIKE ?' : '';
$params = $search ? ["%$search%","%$search%"] : [];

$st = $db->prepare("SELECT COUNT(*) FROM fornecedores $where");
$st->execute($params); $total = (int)$st->fetchColumn();

$pag = max(1,(int)($_GET['pag']??1)); $limit=15; $offset=($pag-1)*$limit;
$stmt = $db->prepare("SELECT f.*,
    GROUP_CONCAT(c.nome ORDER BY c.nome SEPARATOR ', ') as cats
    FROM fornecedores f
    LEFT JOIN fornecedor_categorias fc ON fc.fornecedor_id=f.id
    LEFT JOIN categorias c ON c.id=fc.categoria_id
    $where GROUP BY f.id ORDER BY f.nome LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$fornecedores = $stmt->fetchAll();

$categorias = $db->query('SELECT * FROM categorias WHERE ativo=1 ORDER BY nome')->fetchAll();

// Mapa de vínculos para edição
$vMap = [];
$vRows = $db->query('SELECT fornecedor_id, categoria_id FROM fornecedor_categorias')->fetchAll();
foreach ($vRows as $v) $vMap[$v['fornecedor_id']][] = $v['categoria_id'];

pageHead('Fornecedores');
?>
<div class="layout">
<?php sidebar('fornecedores'); ?>
<div class="main">
<?php topbar('Fornecedores'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Fornecedores <span class="badge badge-blue"><?= $total ?></span></h2>
        <div class="flex gap-2">
            <form method="get" class="flex gap-2">
                <input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?= sanitize($search) ?>" style="width:200px">
                <button class="btn btn-outline btn-sm">Buscar</button>
            </form>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalForn')">+ Novo Fornecedor</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nome</th><th>Contato</th><th>Email</th><th>WhatsApp</th><th>Categorias</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($fornecedores as $f): ?>
            <tr>
                <td>
                    <div class="font-bold"><?= sanitize($f['nome']) ?></div>
                    <?php if ($f['cnpj_cpf']): ?><div class="text-xs text-muted"><?= sanitize($f['cnpj_cpf']) ?></div><?php endif; ?>
                </td>
                <td class="text-sm"><?= sanitize($f['contato'] ?? '-') ?></td>
                <td class="text-sm"><?= $f['email'] ? '<a href="mailto:'.sanitize($f['email']).'">'.sanitize($f['email']).'</a>' : '-' ?></td>
                <td class="text-sm"><?= $f['whatsapp'] ? '<a href="https://wa.me/55'.preg_replace('/\D/','',$f['whatsapp']).'" target="_blank">'.sanitize($f['whatsapp']).'</a>' : '-' ?></td>
                <td style="max-width:220px;font-size:.78rem"><?= $f['cats'] ? sanitize($f['cats']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <div class="td-actions">
                        <button class="btn btn-sm btn-outline" onclick='editarForn(<?= $f['id'] ?>, <?= json_encode($f) ?>, <?= json_encode($vMap[$f['id']] ?? []) ?>)'>✏️</button>
                        <form method="post" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="fornecedor_id" value="<?= $f['id'] ?>">
                            <button class="btn btn-sm btn-danger">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$fornecedores): ?><tr><td colspan="6" class="text-center text-muted">Nenhum fornecedor.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total,$limit,$pag,APP_URL.'/admin/fornecedores.php?q='.urlencode($search)); ?>
</div>

<div class="modal-overlay" id="modalForn">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3 id="modalFornTitulo">Novo Fornecedor</h3>
            <button class="btn-close" onclick="closeModal('modalForn')">✕</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="criar" id="fornAcao">
                <input type="hidden" name="fornecedor_id" id="fornId">
                <div class="form-row">
                    <div class="form-group" style="grid-column:span 2">
                        <label class="form-label">Nome / Razão Social *</label>
                        <input type="text" name="nome" id="fFNome" class="form-control" required maxlength="180">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">CNPJ/CPF</label>
                        <input type="text" name="cnpj_cpf" id="fFCnpj" class="form-control" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contato (pessoa)</label>
                        <input type="text" name="contato" id="fFContato" class="form-control" maxlength="120">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" id="fFEmail" class="form-control" maxlength="180">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" id="fFTel" class="form-control" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" name="whatsapp" id="fFWa" class="form-control" maxlength="20">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="grid-column:span 2">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="endereco" id="fFEnd" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="fFCidade" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">UF</label>
                        <select name="estado" id="fFUf" class="form-control">
                            <option value="">--</option>
                            <?php foreach (['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'] as $uf): ?>
                            <option value="<?= $uf ?>"><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea name="obs" id="fFObs" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Categorias atendidas</label>
                    <div style="border:1px solid var(--neutral-200);border-radius:var(--radius);padding:12px;max-height:160px;overflow-y:auto;display:grid;grid-template-columns:1fr 1fr;gap:4px">
                    <?php foreach ($categorias as $cat): ?>
                    <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;cursor:pointer">
                        <input type="checkbox" name="categorias[]" value="<?= $cat['id'] ?>" class="cat-check" data-cid="<?= $cat['id'] ?>">
                        <?= sanitize($cat['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalForn')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
<script>
function editarForn(id, f, vinc) {
    document.getElementById('modalFornTitulo').textContent = 'Editar Fornecedor';
    document.getElementById('fornAcao').value = 'editar';
    document.getElementById('fornId').value   = id;
    document.getElementById('fFNome').value    = f.nome || '';
    document.getElementById('fFCnpj').value    = f.cnpj_cpf || '';
    document.getElementById('fFContato').value = f.contato || '';
    document.getElementById('fFEmail').value   = f.email || '';
    document.getElementById('fFTel').value     = f.telefone || '';
    document.getElementById('fFWa').value      = f.whatsapp || '';
    document.getElementById('fFEnd').value     = f.endereco || '';
    document.getElementById('fFCidade').value  = f.cidade || '';
    document.getElementById('fFUf').value      = f.estado || '';
    document.getElementById('fFObs').value     = f.obs || '';
    document.querySelectorAll('.cat-check').forEach(cb => {
        cb.checked = vinc.includes(parseInt(cb.dataset.cid));
    });
    openModal('modalForn');
}
</script>
</div></div></div>
<?php pageFoot(); ?>
