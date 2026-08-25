<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';
    if (in_array($acao,['criar','editar'])) {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $role   = $_POST['role'] ?? 'cliente';
        $ativo  = (int)($_POST['ativo'] ?? 1);
        $senha  = $_POST['senha'] ?? '';
        if (!in_array($role, ['admin', 'cliente'], true)) $role = 'cliente';
        $ativo = $ativo === 1 ? 1 : 0;
        if (!$nome || !filter_var($email, FILTER_VALIDATE_EMAIL)) { setFlash('error','Informe nome e e-mail válidos.'); redirect(APP_URL.'/admin/usuarios.php'); }
        if ($acao === 'criar') {
            if (!$senha) { setFlash('error','Senha obrigatória.'); redirect(APP_URL.'/admin/usuarios.php'); }
            $hash = password_hash($senha,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]);
            $db->prepare('INSERT INTO usuarios (nome,email,senha,role,ativo,email_verificado) VALUES (?,?,?,?,?,1)')->execute([$nome,$email,$hash,$role,$ativo]);
            setFlash('success','Usuário criado.');
        } else {
            $uid = (int)$_POST['usuario_id'];
            $db->prepare('UPDATE usuarios SET nome=?,email=?,role=?,ativo=? WHERE id=?')->execute([$nome,$email,$role,$ativo,$uid]);
            if ($senha) $db->prepare('UPDATE usuarios SET senha=? WHERE id=?')->execute([password_hash($senha,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST]),$uid]);
            setFlash('success','Usuário atualizado.');
        }
    } elseif ($acao === 'excluir') {
        $uid = (int)$_POST['usuario_id'];
        $u = $db->prepare('SELECT role FROM usuarios WHERE id=?'); $u->execute([$uid]); $r = $u->fetchColumn();
        $admins = (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE role='admin' AND ativo=1")->fetchColumn();
        if (!$r) { setFlash('error','Usuário não encontrado.'); }
        elseif (currentUserId() === $uid) { setFlash('error','Não é possível excluir o próprio usuário.'); }
        elseif ($r === 'admin' && $admins <= 1) { setFlash('error','O sistema precisa manter ao menos um administrador ativo.'); }
        else { $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$uid]); setFlash('success','Usuário excluído.'); }
    }
    redirect(APP_URL.'/admin/usuarios.php');
}

$search = trim($_GET['q'] ?? '');
$where  = $search ? 'WHERE nome LIKE ? OR email LIKE ?' : '';
$params = $search ? ["%$search%","%$search%"] : [];
$pag=max(1,(int)($_GET['pag']??1)); $limit=20; $offset=($pag-1)*$limit;

$stT = $db->prepare("SELECT COUNT(*) FROM usuarios $where"); $stT->execute($params); $total=(int)$stT->fetchColumn();
$stmt = $db->prepare("SELECT * FROM usuarios $where ORDER BY role,nome LIMIT $limit OFFSET $offset");
$stmt->execute($params); $usuarios = $stmt->fetchAll();

pageHead('Usuários');
?>
<div class="layout">
<?php sidebar('usuarios'); ?>
<div class="main">
<?php topbar('Usuários do Sistema'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Usuários <span class="badge badge-blue"><?= $total ?></span></h2>
        <div class="flex gap-2">
            <form method="get" class="flex gap-2">
                <input type="text" name="q" class="form-control" placeholder="Buscar..." value="<?= sanitize($search) ?>" style="width:200px">
                <button class="btn btn-outline btn-sm">Buscar</button>
            </form>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalUser')">+ Novo Usuário</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Último Acesso</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= sanitize($u['nome']) ?></td>
                <td class="text-sm"><?= sanitize($u['email']) ?></td>
                <td><span class="badge <?= $u['role']==='admin'?'badge-blue':'badge-teal' ?>"><?= $u['role'] ?></span></td>
                <td><?= $u['ativo'] ? '<span class="badge badge-green">Ativo</span>' : '<span class="badge badge-red">Inativo</span>' ?></td>
                <td class="text-xs text-muted"><?= $u['ultimo_login'] ? date('d/m/y H:i',strtotime($u['ultimo_login'])) : 'Nunca' ?></td>
                <td>
                    <div class="td-actions">
                        <button class="btn btn-sm btn-outline" onclick='editarUser(<?= json_encode($u) ?>)'>✏️</button>
                        <?php if ($u['id'] !== currentUserId()): ?>
                        <form method="post" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-danger">🗑</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?><tr><td colspan="6" class="text-center text-muted">Nenhum usuário.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total,$limit,$pag,APP_URL.'/admin/usuarios.php?q='.urlencode($search)); ?>
</div>

<div class="modal-overlay" id="modalUser">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalUserTitulo">Novo Usuário</h3>
            <button class="btn-close" onclick="closeModal('modalUser')">✕</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="criar" id="userAcao">
                <input type="hidden" name="usuario_id" id="userId">
                <div class="form-group"><label class="form-label">Nome *</label><input type="text" name="nome" id="fUNome" class="form-control" required maxlength="120"></div>
                <div class="form-group"><label class="form-label">E-mail *</label><input type="email" name="email" id="fUEmail" class="form-control" required maxlength="180"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Perfil</label>
                        <select name="role" id="fURole" class="form-control">
                            <option value="cliente">Cliente</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="ativo" id="fUAtivo" class="form-control">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label class="form-label" id="senhaUserLabel">Senha *</label><input type="password" name="senha" id="fUSenha" class="form-control" maxlength="128"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalUser')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
<script>
function editarUser(u) {
    document.getElementById('modalUserTitulo').textContent = 'Editar Usuário';
    document.getElementById('userAcao').value = 'editar';
    document.getElementById('userId').value   = u.id;
    document.getElementById('fUNome').value   = u.nome;
    document.getElementById('fUEmail').value  = u.email;
    document.getElementById('fURole').value   = u.role;
    document.getElementById('fUAtivo').value  = u.ativo;
    document.getElementById('fUSenha').value  = '';
    document.getElementById('senhaUserLabel').textContent = 'Nova Senha (vazio = não altera)';
    openModal('modalUser');
}
</script>
</div></div></div>
<?php pageFoot(); ?>
