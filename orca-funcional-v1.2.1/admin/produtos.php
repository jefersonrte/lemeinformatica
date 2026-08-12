<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';
    if (in_array($acao, ['criar','editar'])) {
        $nome  = trim($_POST['nome'] ?? '');
        $cod   = trim($_POST['codigo'] ?? '');
        $catId = (int)($_POST['categoria_id'] ?? 0);
        $un    = trim($_POST['unidade'] ?? 'UN');
        $desc  = trim($_POST['descricao'] ?? '');
        if (!$nome || !$catId) { setFlash('error','Nome e categoria obrigatórios.'); redirect(APP_URL.'/admin/produtos.php'); }
        if ($acao === 'criar') {
            $db->prepare('INSERT INTO produtos (categoria_id,codigo,nome,unidade,descricao) VALUES (?,?,?,?,?)')
               ->execute([$catId,$cod,$nome,$un,$desc]);
            setFlash('success','Produto criado.');
        } else {
            $db->prepare('UPDATE produtos SET categoria_id=?,codigo=?,nome=?,unidade=?,descricao=? WHERE id=?')
               ->execute([$catId,$cod,$nome,$un,$desc,(int)$_POST['produto_id']]);
            setFlash('success','Produto atualizado.');
        }
    } elseif ($acao === 'excluir') {
        $db->prepare('DELETE FROM produtos WHERE id=?')->execute([(int)$_POST['produto_id']]);
        setFlash('success','Produto excluído.');
    }
    redirect(APP_URL.'/admin/produtos.php?' . http_build_query(array_filter(['q'=>$_POST['q']??'','cat'=>$_POST['cat_filter']??''])));
}

$search = trim($_GET['q'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$pag    = max(1,(int)($_GET['pag']??1));
$limit  = 20; $offset = ($pag-1)*$limit;

$wheres = []; $params = [];
if ($search)    { $wheres[] = 'p.nome LIKE ?'; $params[] = "%$search%"; }
if ($catFilter) { $wheres[] = 'p.categoria_id = ?'; $params[] = $catFilter; }
$where = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

$stTotal = $db->prepare("SELECT COUNT(*) FROM produtos p $where");
$stTotal->execute($params);
$total = (int)$stTotal->fetchColumn();

$stmt = $db->prepare("SELECT p.*, c.nome as categoria FROM produtos p JOIN categorias c ON c.id=p.categoria_id $where ORDER BY c.nome, p.nome LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$produtos = $stmt->fetchAll();

$categorias = $db->query('SELECT * FROM categorias WHERE ativo=1 ORDER BY nome')->fetchAll();

pageHead('Produtos');
?>
<div class="layout">
<?php sidebar('produtos'); ?>
<div class="main">
<?php topbar('Produtos / Materiais'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Produtos <span class="badge badge-blue"><?= $total ?></span></h2>
        <div class="flex gap-2" style="flex-wrap:wrap">
            <form method="get" class="flex gap-2" style="flex-wrap:wrap">
                <input type="text" name="q" class="form-control" placeholder="Buscar produto..." value="<?= sanitize($search) ?>" style="width:180px">
                <select name="cat" class="form-control" style="width:180px">
                    <option value="">Todas categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $catFilter==$cat['id']?'selected':'' ?>><?= sanitize($cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm">Filtrar</button>
            </form>
            <button class="btn btn-primary btn-sm" onclick="openModal('modalProduto')">+ Novo Produto</button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Código</th><th>Produto</th><th>Categoria</th><th>Unidade</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($produtos as $p): ?>
            <tr>
                <td class="text-sm text-muted"><?= sanitize($p['codigo'] ?? '-') ?></td>
                <td>
                    <div><?= sanitize($p['nome']) ?></div>
                    <?php if ($p['descricao']): ?><div class="text-xs text-muted truncate" style="max-width:300px"><?= sanitize($p['descricao']) ?></div><?php endif; ?>
                </td>
                <td><span class="badge badge-blue"><?= sanitize($p['categoria']) ?></span></td>
                <td class="text-sm"><?= sanitize($p['unidade']) ?></td>
                <td>
                    <div class="td-actions">
                        <button class="btn btn-sm btn-outline" onclick='editarProduto(<?= json_encode($p) ?>)'>✏️</button>
                        <form method="post" onsubmit="return confirmDelete(this)">
                            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="produto_id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-danger">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$produtos): ?><tr><td colspan="5" class="text-center text-muted">Nenhum produto encontrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php paginacao($total, $limit, $pag, APP_URL.'/admin/produtos.php?q='.urlencode($search).'&cat='.$catFilter); ?>
</div>

<div class="modal-overlay" id="modalProduto">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalProdutoTitulo">Novo Produto</h3>
            <button class="btn-close" onclick="closeModal('modalProduto')">✕</button>
        </div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="criar" id="produtoAcao">
                <input type="hidden" name="produto_id" id="produtoId">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" id="fPCod" class="form-control" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unidade</label>
                        <select name="unidade" id="fPUn" class="form-control">
                            <?php foreach (['UN','M','M²','M³','KG','CX','PC','RL','SC','L','GL','KIT','VB'] as $u): ?>
                            <option><?= $u ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nome do Produto *</label>
                    <input type="text" name="nome" id="fPNome" class="form-control" required maxlength="200">
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria *</label>
                    <select name="categoria_id" id="fPCat" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= sanitize($cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" id="fPDesc" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalProduto')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
<script>
function editarProduto(p) {
    document.getElementById('modalProdutoTitulo').textContent = 'Editar Produto';
    document.getElementById('produtoAcao').value = 'editar';
    document.getElementById('produtoId').value   = p.id;
    document.getElementById('fPCod').value  = p.codigo || '';
    document.getElementById('fPNome').value = p.nome;
    document.getElementById('fPCat').value  = p.categoria_id;
    document.getElementById('fPUn').value   = p.unidade;
    document.getElementById('fPDesc').value = p.descricao || '';
    openModal('modalProduto');
}
</script>
</div></div></div>
<?php pageFoot(); ?>
