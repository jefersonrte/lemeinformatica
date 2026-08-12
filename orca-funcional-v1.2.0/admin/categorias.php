<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $desc = trim($_POST['descricao'] ?? '');
    if (in_array($acao, ['criar','editar'])) {
        if (!$nome) { setFlash('error','Nome obrigatório.'); redirect(APP_URL.'/admin/categorias.php'); }
        $cats = $_POST['fornecedores'] ?? [];
        if ($acao === 'criar') {
            $db->prepare('INSERT INTO categorias (nome,descricao) VALUES (?,?)')->execute([$nome,$desc]);
            $catId = $db->lastInsertId();
        } else {
            $catId = (int)$_POST['categoria_id'];
            $db->prepare('UPDATE categorias SET nome=?,descricao=? WHERE id=?')->execute([$nome,$desc,$catId]);
            $db->prepare('DELETE FROM fornecedor_categorias WHERE categoria_id=?')->execute([$catId]);
        }
        // Vínculos com fornecedores
        $sth = $db->prepare('INSERT IGNORE INTO fornecedor_categorias (fornecedor_id,categoria_id) VALUES (?,?)');
        foreach ($cats as $fid) $sth->execute([(int)$fid, $catId]);
        setFlash('success', $acao === 'criar' ? 'Categoria criada.' : 'Categoria atualizada.');
    } elseif ($acao === 'excluir') {
        $db->prepare('DELETE FROM categorias WHERE id=?')->execute([(int)$_POST['categoria_id']]);
        setFlash('success','Categoria excluída.');
    }
    redirect(APP_URL.'/admin/categorias.php');
}

$categorias = $db->query("SELECT c.*, COUNT(p.id) as qtd_produtos,
    GROUP_CONCAT(f.nome ORDER BY f.nome SEPARATOR ', ') as fornecedores_lista
    FROM categorias c
    LEFT JOIN produtos p ON p.categoria_id=c.id
    LEFT JOIN fornecedor_categorias fc ON fc.categoria_id=c.id
    LEFT JOIN fornecedores f ON f.id=fc.fornecedor_id
    GROUP BY c.id ORDER BY c.nome")->fetchAll();

$fornecedores = $db->query('SELECT id,nome FROM fornecedores WHERE ativo=1 ORDER BY nome')->fetchAll();

// Fornecedores vinculados por categoria (para preencher modal edição)
$vinculos = $db->query('SELECT categoria_id, fornecedor_id FROM fornecedor_categorias')->fetchAll();
$vMap = [];
foreach ($vinculos as $v) $vMap[$v['categoria_id']][] = $v['fornecedor_id'];

pageHead('Categorias');
?>
<div class="layout">
<?php sidebar('categorias'); ?>
<div class="main">
<?php topbar('Categorias de Produtos'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header">
        <h2>Categorias <span class="badge badge-blue"><?= count($categorias) ?></span></h2>
        <button class="btn btn-primary btn-sm" onclick="openModal('modalCat')">+ Nova Categoria</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Categoria</th><th>Fornecedores Vinculados</th><th>Produtos</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $cat): ?>
            <tr>
                <td>
                    <div class="font-bold"><?= sanitize($cat['nome']) ?></div>
                    <?php if ($cat['descricao']): ?><div class="text-xs text-muted"><?= sanitize($cat['descricao']) ?></div><?php endif; ?>
                </td>
                <td class="text-sm"><?= $cat['fornecedores_lista'] ? sanitize($cat['fornecedores_lista']) : '<span class="text-muted">Nenhum</span>' ?></td>
                <td><span class="badge badge-gray"><?= $cat['qtd_produtos'] ?></span></td>
                <td>
                    <div class="td-actions">
                        <button class="btn btn-sm btn-outline" onclick='editarCat(<?= $cat['id'] ?>, <?= json_encode($cat['nome']) ?>, <?= json_encode($cat['descricao']) ?>, <?= json_encode($vMap[$cat['id']] ?? []) ?>)'>✏️</button>
                        <form method="post" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="categoria_id" value="<?= $cat['id'] ?>">
                            <button class="btn btn-sm btn-danger">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$categorias): ?><tr><td colspan="4" class="text-center text-muted">Nenhuma categoria.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalCat">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalCatTitulo">Nova Categoria</h3>
            <button class="btn-close" onclick="closeModal('modalCat')">✕</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="criar" id="catAcao">
                <input type="hidden" name="categoria_id" id="catId">
                <div class="form-group">
                    <label class="form-label">Nome da Categoria *</label>
                    <input type="text" name="nome" id="fCatNome" class="form-control" required maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <input type="text" name="descricao" id="fCatDesc" class="form-control" maxlength="255">
                </div>
                <div class="form-group">
                    <label class="form-label">Fornecedores que atendem esta categoria</label>
                    <div style="border:1px solid var(--neutral-200);border-radius:var(--radius);padding:12px;max-height:200px;overflow-y:auto">
                    <?php foreach ($fornecedores as $f): ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;font-size:.875rem">
                        <input type="checkbox" name="fornecedores[]" value="<?= $f['id'] ?>" class="forn-check" data-fid="<?= $f['id'] ?>">
                        <?= sanitize($f['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                    <?php if (!$fornecedores): ?><span class="text-muted text-sm">Nenhum fornecedor cadastrado.</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalCat')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
<script>
function editarCat(id, nome, desc, vinculados) {
    document.getElementById('modalCatTitulo').textContent = 'Editar Categoria';
    document.getElementById('catAcao').value = 'editar';
    document.getElementById('catId').value   = id;
    document.getElementById('fCatNome').value = nome;
    document.getElementById('fCatDesc').value = desc || '';
    document.querySelectorAll('.forn-check').forEach(cb => {
        cb.checked = vinculados.includes(parseInt(cb.dataset.fid));
    });
    openModal('modalCat');
}
</script>
</div></div></div>
<?php pageFoot(); ?>
