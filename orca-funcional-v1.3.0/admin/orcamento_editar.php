<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/orcamento_editor.php';

use App\Domain\Orcamento\OrcamentoCalculator;
use App\Domain\Orcamento\OrcamentoService;
use App\Domain\Orcamento\OrcamentoStatus;

requireAdmin();
$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$orc = $db->prepare('SELECT o.*, ob.nome AS obra_nome, c.razao_social FROM orcamentos o JOIN obras ob ON ob.id = o.obra_id JOIN clientes c ON c.id = o.cliente_id WHERE o.id = ?');
$orc->execute([$id]);
$orc = $orc->fetch();
if (!$orc) {
    redirect(APP_URL . '/admin/orcamentos.php');
}
if (!OrcamentoStatus::editavel($orc['status'])) {
    setFlash('warning', 'Orçamentos ' . mb_strtolower(OrcamentoStatus::rotulo($orc['status'])) . 's não podem ser editados. Reabra como rascunho ou crie uma nova revisão.');
    redirect(APP_URL . '/admin/orcamento_detalhe.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        (new OrcamentoService($db))->atualizar(
            $id,
            (string) ($_POST['titulo'] ?? ''),
            trim((string) ($_POST['obs'] ?? '')),
            OrcamentoCalculator::decimal($_POST['bdi_percentual'] ?? 0),
            orcamentoItensDoPost($_POST)
        );
    } catch (InvalidArgumentException $exception) {
        setFlash('error', $exception->getMessage());
        redirect(APP_URL . '/admin/orcamento_editar.php?id=' . $id);
    }
    logAction('orcamento_editado', 'orcamentos', $id, (string) ($_POST['titulo'] ?? ''));
    setFlash('success', 'Orçamento atualizado.');
    redirect(APP_URL . '/admin/orcamento_detalhe.php?id=' . $id);
}

$itens = $db->prepare('SELECT id, etapa, obs AS codigo, descricao, unidade, quantidade, preco_unitario, categoria_id FROM orcamento_itens WHERE orcamento_id = ? ORDER BY ordem, id');
$itens->execute([$id]);
$itens = array_map(static function (array $item): array {
    $item['quantidade'] = (float) $item['quantidade'];
    $item['preco_unitario'] = (float) $item['preco_unitario'];
    return $item;
}, $itens->fetchAll());
$categorias = $db->query('SELECT * FROM categorias WHERE ativo=1 ORDER BY nome')->fetchAll();

pageHead('Editar Orçamento');
?>
<div class="layout">
<?php sidebar('orcamentos'); ?>
<div class="main">
<?php topbar('Editar: ' . sanitize($orc['titulo'])); ?>
<div class="content">
<?php flashMessage(); ?>

<form method="post" id="formEditar" onsubmit="return validarItens()">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
    <div class="card mb-4">
        <div class="card-body">
            <div class="text-sm text-muted mb-3">🏠 <?= sanitize($orc['obra_nome']) ?> · 👥 <?= sanitize($orc['razao_social']) ?> · <?= statusBadge($orc['status']) ?></div>
            <div class="form-row">
                <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control" required maxlength="200" value="<?= sanitize($orc['titulo']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Observações</label>
                <textarea name="obs" class="form-control"><?= sanitize($orc['obs'] ?? '') ?></textarea>
            </div>
            <?php if ((float) $orc['total_cotado'] > 0): ?>
            <p class="form-text">Os preços cotados dos itens mantidos são preservados. Itens removidos perdem o vínculo com as cotações.</p>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card mb-4">
    <div class="card-header">
        <h2>Itens (<?= count($itens) ?>)</h2>
        <button type="button" class="btn btn-sm btn-outline" onclick="adicionarLinha()">+ Adicionar Linha</button>
    </div>
    <div class="card-body" style="padding:0">
        <?php orcamentoEditorItens($categorias, 'formEditar', (float) $orc['bdi_percentual']); ?>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--neutral-200);display:flex;justify-content:space-between">
        <a href="<?= APP_URL ?>/admin/orcamento_detalhe.php?id=<?= $id ?>" class="btn btn-secondary">Cancelar</a>
        <button type="submit" form="formEditar" class="btn btn-success">Salvar alterações</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => carregarItens(<?= json_encode($itens, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>));
</script>
</div></div></div>
<?php pageFoot(); ?>
