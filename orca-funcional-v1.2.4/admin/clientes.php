<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

// Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';

    if (in_array($acao, ['criar', 'editar'])) {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email_cliente'] ?? '');
        $senha  = $_POST['senha'] ?? '';
        $razao  = trim($_POST['razao_social'] ?? '');
        $cnpj   = trim($_POST['cnpj_cpf'] ?? '');
        $tel    = trim($_POST['telefone'] ?? '');
        $wa     = trim($_POST['whatsapp'] ?? '');
        $end    = trim($_POST['endereco'] ?? '');
        $cid    = trim($_POST['cidade'] ?? '');
        $uf     = trim($_POST['estado'] ?? '');
        $cep    = trim($_POST['cep'] ?? '');
        $obs    = trim($_POST['obs'] ?? '');

        if (!$nome || !$email) {
            setFlash('error', 'Nome e e-mail são obrigatórios.');
        } else {
            if ($acao === 'criar') {
                if (!$senha) { setFlash('error', 'Senha obrigatória para novo cliente.'); redirect(APP_URL . '/admin/clientes.php'); }
                // Cria usuário
                $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $db->prepare('INSERT INTO usuarios (nome,email,senha,role,ativo,email_verificado) VALUES (?,?,?,?,1,1)')
                   ->execute([$nome, $email, $hash, 'cliente']);
                $uid = $db->lastInsertId();
                // Cria cliente
                $db->prepare('INSERT INTO clientes (usuario_id,razao_social,cnpj_cpf,telefone,whatsapp,email,endereco,cidade,estado,cep,obs)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                   ->execute([$uid, $razao ?: $nome, $cnpj, $tel, $wa, $email, $end, $cid, $uf, $cep, $obs]);
                logAction('cliente_criado', 'clientes', $db->lastInsertId(), $nome);
                setFlash('success', 'Cliente criado com sucesso.');
            } else {
                $cid_edit = (int)($_POST['cliente_id'] ?? 0);
                $uid_edit = (int)($_POST['usuario_id'] ?? 0);
                $db->prepare('UPDATE usuarios SET nome=?,email=? WHERE id=?')->execute([$nome, $email, $uid_edit]);
                if ($senha) {
                    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                    $db->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([$hash, $uid_edit]);
                }
                $db->prepare('UPDATE clientes SET razao_social=?,cnpj_cpf=?,telefone=?,whatsapp=?,email=?,endereco=?,cidade=?,estado=?,cep=?,obs=? WHERE id=?')
                   ->execute([$razao ?: $nome, $cnpj, $tel, $wa, $email, $end, $cid, $uf, $cep, $obs, $cid_edit]);
                logAction('cliente_editado', 'clientes', $cid_edit, $nome);
                setFlash('success', 'Cliente atualizado.');
            }
        }
    } elseif ($acao === 'excluir') {
        $cid = (int)($_POST['cliente_id'] ?? 0);
        $row = $db->prepare('SELECT usuario_id FROM clientes WHERE id=?');
        $row->execute([$cid]);
        $r = $row->fetch();
        if ($r) {
            $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$r['usuario_id']]);
            logAction('cliente_excluido', 'clientes', $cid);
            setFlash('success', 'Cliente excluído.');
        }
    }
    redirect(APP_URL . '/admin/clientes.php');
}

// Busca
$search = trim($_GET['q'] ?? '');
$pag    = max(1, (int)($_GET['pag'] ?? 1));
$limit  = 15;
$offset = ($pag - 1) * $limit;

$where  = $search ? ' WHERE c.razao_social LIKE ? OR u.email LIKE ? OR c.cnpj_cpf LIKE ?' : '';
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$total = $db->prepare("SELECT COUNT(*) FROM clientes c JOIN usuarios u ON u.id=c.usuario_id $where");
$total->execute($params);
$total = (int)$total->fetchColumn();

$stmt = $db->prepare("SELECT c.*, u.nome, u.email as email_login, u.ativo, u.ultimo_login
    FROM clientes c JOIN usuarios u ON u.id=c.usuario_id $where
    ORDER BY c.criado_em DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

pageHead('Clientes');
?>
<div class="layout">
<?php sidebar('clientes'); ?>
<div class="main">
<?php topbar('Clientes'); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card">
    <div class="card-header">
        <h2>Clientes Cadastrados <span class="badge badge-blue"><?= $total ?></span></h2>
        <div class="flex gap-2">
            <form method="get" class="flex gap-2">
                <input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?= sanitize($search) ?>" style="width:220px">
                <button class="btn btn-outline btn-sm">Buscar</button>
            </form>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalCliente')">+ Novo Cliente</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Nome / Razão Social</th><th>CNPJ/CPF</th><th>E-mail</th>
                <th>WhatsApp</th><th>Cidade/UF</th><th>Status</th><th>Último Acesso</th><th>Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
            <tr>
                <td>
                    <div class="font-bold"><?= sanitize($c['razao_social']) ?></div>
                    <div class="text-xs text-muted"><?= sanitize($c['nome']) ?></div>
                </td>
                <td class="text-sm"><?= sanitize($c['cnpj_cpf'] ?? '-') ?></td>
                <td class="text-sm"><?= sanitize($c['email_login']) ?></td>
                <td class="text-sm"><?= $c['whatsapp'] ? '<a href="https://wa.me/55' . preg_replace('/\D/','',$c['whatsapp']) . '" target="_blank">' . sanitize($c['whatsapp']) . '</a>' : '-' ?></td>
                <td class="text-sm"><?= sanitize(($c['cidade'] ?? '') . ($c['estado'] ? '/' . $c['estado'] : '')) ?></td>
                <td><?= $c['ativo'] ? '<span class="badge badge-green">Ativo</span>' : '<span class="badge badge-red">Inativo</span>' ?></td>
                <td class="text-xs text-muted"><?= $c['ultimo_login'] ? date('d/m/y H:i', strtotime($c['ultimo_login'])) : 'Nunca' ?></td>
                <td>
                    <div class="td-actions">
                        <a href="<?= APP_URL ?>/admin/obras.php?cliente_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Ver obras">🏠</a>
                        <button class="btn btn-sm btn-outline" onclick='editarCliente(<?= json_encode($c) ?>)' title="Editar">✏️</button>
                        <form method="post" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="cliente_id" value="<?= $c['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$clientes): ?><tr><td colspan="8" class="text-center text-muted">Nenhum cliente encontrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total, $limit, $pag, APP_URL . '/admin/clientes.php?q=' . urlencode($search)); ?>
</div>

<!-- Modal Novo/Editar Cliente -->
<div class="modal-overlay" id="modalCliente">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3 id="modalClienteTitulo">Novo Cliente</h3>
            <button class="btn-close" onclick="closeModal('modalCliente')">✕</button>
        </div>
        <form method="post" id="formCliente">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="criar" id="clienteAcao">
                <input type="hidden" name="cliente_id" value="" id="clienteId">
                <input type="hidden" name="usuario_id" value="" id="usuarioId">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nome Completo *</label>
                        <input type="text" name="nome" id="fNome" class="form-control" required maxlength="120">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Razão Social</label>
                        <input type="text" name="razao_social" id="fRazao" class="form-control" maxlength="180">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">E-mail (login) *</label>
                        <input type="email" name="email_cliente" id="fEmail" class="form-control" required maxlength="180">
                    </div>
                    <div class="form-group">
                        <label class="form-label" id="senhaLabel">Senha *</label>
                        <input type="password" name="senha" id="fSenha" class="form-control" maxlength="128">
                        <p class="form-text" id="senhaHint">Mínimo 8 caracteres</p>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">CNPJ / CPF</label>
                        <input type="text" name="cnpj_cpf" id="fCnpj" class="form-control" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" id="fTel" class="form-control" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" name="whatsapp" id="fWa" class="form-control" maxlength="20">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="endereco" id="fEnd" class="form-control" maxlength="255">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="cidade" id="fCidade" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado</label>
                        <select name="estado" id="fEstado" class="form-control">
                            <option value="">--</option>
                            <?php foreach (['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'] as $uf): ?>
                            <option value="<?= $uf ?>"><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">CEP</label>
                        <input type="text" name="cep" id="fCep" class="form-control" maxlength="10">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea name="obs" id="fObs" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalCliente')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarCliente(c) {
    document.getElementById('modalClienteTitulo').textContent = 'Editar Cliente';
    document.getElementById('clienteAcao').value = 'editar';
    document.getElementById('clienteId').value  = c.id;
    document.getElementById('usuarioId').value  = c.usuario_id;
    document.getElementById('fNome').value   = c.nome || '';
    document.getElementById('fRazao').value  = c.razao_social || '';
    document.getElementById('fEmail').value  = c.email_login || '';
    document.getElementById('fSenha').value  = '';
    document.getElementById('fCnpj').value   = c.cnpj_cpf || '';
    document.getElementById('fTel').value    = c.telefone || '';
    document.getElementById('fWa').value     = c.whatsapp || '';
    document.getElementById('fEnd').value    = c.endereco || '';
    document.getElementById('fCidade').value = c.cidade || '';
    document.getElementById('fEstado').value = c.estado || '';
    document.getElementById('fCep').value    = c.cep || '';
    document.getElementById('fObs').value    = c.obs || '';
    document.getElementById('senhaLabel').textContent = 'Nova Senha (deixe vazio para não alterar)';
    document.getElementById('senhaHint').textContent  = 'Deixe em branco para manter a senha atual.';
    openModal('modalCliente');
}
</script>

</div></div></div>
<?php pageFoot(); ?>
