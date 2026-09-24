<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL.'/admin/orcamentos.php');

use App\Domain\Orcamento\OrcamentoService;
use App\Domain\Orcamento\OrcamentoStatus;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $service = new OrcamentoService($db);
    $acao = $_POST['acao'] ?? '';
    try {
        if ($acao === 'status') {
            $novo = (string) ($_POST['status'] ?? '');
            $service->alterarStatus($id, $novo);
            logAction('orcamento_status', 'orcamentos', $id, $novo);
            setFlash('success', 'Status alterado para ' . OrcamentoStatus::rotulo($novo) . '.');
        } elseif ($acao === 'duplicar') {
            $novoId = $service->duplicar($id);
            logAction('orcamento_revisao', 'orcamentos', $novoId, 'revisão de #' . $id);
            setFlash('success', 'Nova revisão criada em rascunho.');
            redirect(APP_URL . '/admin/orcamento_detalhe.php?id=' . $novoId);
        } elseif ($acao === 'excluir') {
            $service->excluir($id);
            logAction('orcamento_excluido', 'orcamentos', $id);
            setFlash('success', 'Orçamento excluído.');
            redirect(APP_URL . '/admin/orcamentos.php');
        }
    } catch (InvalidArgumentException $exception) {
        setFlash('error', $exception->getMessage());
    }
    redirect(APP_URL . '/admin/orcamento_detalhe.php?id=' . $id);
}

$orc = $db->prepare('SELECT o.*,ob.nome as obra_nome,c.razao_social,c.whatsapp as cli_wa FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id JOIN clientes c ON c.id=o.cliente_id WHERE o.id=?');
$orc->execute([$id]);
$orc = $orc->fetch();
if (!$orc) redirect(APP_URL.'/admin/orcamentos.php');

$itens = $db->prepare('SELECT oi.*,cat.nome as categoria,fo.nome as fornecedor_nome FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id=oi.categoria_id LEFT JOIN fornecedores fo ON fo.id=oi.fornecedor_id WHERE oi.orcamento_id=? ORDER BY oi.ordem, oi.id');
$itens->execute([$id]);
$itens = $itens->fetchAll();

// Fornecedores por categoria dos itens
$catIds = array_values(array_unique(array_filter(array_column($itens,'categoria_id'))));
$fornPorCat = [];
if ($catIds) {
    $in = implode(',',array_fill(0,count($catIds),'?'));
    $stmt = $db->prepare("SELECT fc.categoria_id, f.id, f.nome, f.email, f.whatsapp FROM fornecedor_categorias fc JOIN fornecedores f ON f.id=fc.fornecedor_id WHERE fc.categoria_id IN ($in) AND f.ativo=1");
    $stmt->execute($catIds);
    foreach ($stmt->fetchAll() as $row) {
        $fornPorCat[$row['categoria_id']][] = $row;
    }
}

// Itens sem categoria podem ser cotados com qualquer fornecedor ativo
$todosFornecedores = $db->query('SELECT id, nome, email, whatsapp FROM fornecedores WHERE ativo=1 ORDER BY nome')->fetchAll();

// Cotações existentes
$cotacoes = $db->prepare('SELECT co.*,f.nome as fornecedor FROM cotacoes co JOIN fornecedores f ON f.id=co.fornecedor_id WHERE co.orcamento_id=? ORDER BY co.criado_em DESC');
$cotacoes->execute([$id]);
$cotacoes = $cotacoes->fetchAll();

pageHead('Detalhe do Orçamento');
?>
<div class="layout">
<?php sidebar('orcamentos'); ?>
<div class="main">
<?php topbar('Orçamento: ' . sanitize($orc['titulo'])); ?>
<div class="content">
<?php flashMessage(); ?>

<!-- Cabeçalho -->
<div class="card mb-4">
    <div class="card-body">
        <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:12px">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 style="font-size:1.2rem;font-weight:800"><?= sanitize($orc['titulo']) ?></h1>
                    <?= statusBadge($orc['status']) ?>
                </div>
                <div class="flex gap-4 text-sm text-muted" style="flex-wrap:wrap">
                    <span>🏠 <?= sanitize($orc['obra_nome']) ?></span>
                    <span>👥 <?= sanitize($orc['razao_social']) ?></span>
                    <span>📅 <?= date('d/m/Y', strtotime($orc['criado_em'])) ?></span>
                    <span>Origem: <strong><?= $orc['tipo_origem'] ?></strong></span>
                </div>
            </div>
            <div class="flex gap-3" style="flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/orcamentos.php?obra_id=<?= $orc['obra_id'] ?>" class="btn btn-outline btn-sm">← Orçamentos</a>
                <button class="btn btn-primary btn-sm" onclick="openModal('modalEnviarCotacao')">Selecionar Fornecedores e Enviar</button>
                <a href="<?= APP_URL ?>/admin/orcamento_exportar.php?id=<?= $id ?>" class="btn btn-outline btn-sm">Exportar</a>
                <?php if (OrcamentoStatus::editavel($orc['status'])): ?>
                <a href="<?= APP_URL ?>/admin/orcamento_editar.php?id=<?= $id ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-pen"></i> Editar itens</a>
                <?php endif; ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                    <input type="hidden" name="acao" value="duplicar">
                    <button class="btn btn-outline btn-sm" type="submit" title="Copia os itens para uma nova revisão em rascunho"><i class="fa-regular fa-copy"></i> Nova revisão</button>
                </form>
            </div>
        </div>
        <?php $proximos = OrcamentoStatus::proximos($orc['status']); ?>
        <div class="flex gap-2 mt-3" style="flex-wrap:wrap;align-items:center">
            <span class="text-xs text-muted">Alterar status:</span>
            <?php foreach ($proximos as $proximo): if ($proximo === 'aguardando_cotacao' || $proximo === 'cotado') continue; ?>
            <form method="post" style="display:inline" <?= in_array($proximo, ['cancelado','reprovado'], true) ? 'onsubmit="return confirm(\'Confirmar: ' . OrcamentoStatus::rotulo($proximo) . '?\')"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="status">
                <input type="hidden" name="status" value="<?= $proximo ?>">
                <button type="submit" class="btn btn-sm <?= ['aprovado' => 'btn-success', 'reprovado' => 'btn-danger', 'cancelado' => 'btn-outline', 'rascunho' => 'btn-outline'][$proximo] ?? 'btn-outline' ?>">
                    <?= $proximo === 'rascunho' ? 'Reabrir como rascunho' : ['aprovado' => 'Aprovar', 'reprovado' => 'Reprovar', 'cancelado' => 'Cancelar'][$proximo] ?>
                </button>
            </form>
            <?php endforeach; ?>
            <form method="post" style="display:inline;margin-left:auto" onsubmit="return confirm('Excluir definitivamente este orçamento, seus itens e cotações?')">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="excluir">
                <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> Excluir</button>
            </form>
        </div>
        <hr class="divider">
        <div class="flex gap-4" style="flex-wrap:wrap">
            <div style="text-align:center">
                <div style="font-size:1.4rem;font-weight:800;color:var(--neutral-700)"><?= count($itens) ?></div>
                <div class="text-xs text-muted">Itens</div>
            </div>
            <div style="text-align:center">
                <div style="font-size:1.4rem;font-weight:800;color:var(--neutral-700)">R$ <?= number_format($orc['total_estimado'],2,',','.') ?></div>
                <div class="text-xs text-muted">Custo direto</div>
            </div>
            <?php if ((float) $orc['bdi_percentual'] > 0): ?>
            <div style="text-align:center">
                <div style="font-size:1.4rem;font-weight:800;color:var(--primary)">R$ <?= number_format(OrcamentoService::totalComBdi((float) $orc['total_estimado'], (float) $orc['bdi_percentual']),2,',','.') ?></div>
                <div class="text-xs text-muted">Com BDI de <?= number_format((float) $orc['bdi_percentual'],2,',','.') ?>%</div>
            </div>
            <?php endif; ?>
            <div style="text-align:center">
                <div style="font-size:1.4rem;font-weight:800;color:var(--success)">R$ <?= number_format($orc['total_cotado'],2,',','.') ?></div>
                <div class="text-xs text-muted">Total Cotado</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs" id="tabsOrc">
    <button class="tab-link active" data-tab="tabItens" onclick="switchTab('tabsOrc','tabItens')">Itens do Orçamento</button>
    <button class="tab-link" data-tab="tabCotacoes" onclick="switchTab('tabsOrc','tabCotacoes')">Cotações (<?= count($cotacoes) ?>)</button>
</div>

<!-- Itens -->
<div id="tabItens" class="tab-pane active">
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Código</th><th>Descrição</th><th>Categoria</th>
                <th>Unid.</th><th>Qtd</th><th>Preço Unit.</th><th>Total Est.</th>
                <th>Preço Cotado</th><th>Total Cotado</th><th>Fornecedor</th>
            </tr></thead>
            <tbody>
            <?php
            $subtotaisEtapa = [];
            foreach ($itens as $item) {
                $chave = (string) ($item['etapa'] ?? '');
                $subtotaisEtapa[$chave] = ($subtotaisEtapa[$chave] ?? 0) + (float) $item['preco_total'];
            }
            $temEtapas = count(array_filter(array_keys($subtotaisEtapa), fn($e) => $e !== '')) > 0;
            $etapaAtual = null;
            ?>
            <?php $totEst=0; $totCot=0; foreach ($itens as $item): $totEst+=$item['preco_total']; $totCot+=$item['total_cotado']; ?>
            <?php if ($temEtapas && (string) ($item['etapa'] ?? '') !== $etapaAtual): $etapaAtual = (string) ($item['etapa'] ?? ''); ?>
            <tr class="etapa-row" style="background:var(--neutral-50)">
                <td colspan="6" class="font-bold text-sm"><?= sanitize($etapaAtual !== '' ? $etapaAtual : 'Sem etapa') ?></td>
                <td class="font-bold text-sm">R$ <?= number_format($subtotaisEtapa[$etapaAtual],2,',','.') ?></td>
                <td colspan="3" class="text-xs text-muted"><?php $somaEtapas = array_sum($subtotaisEtapa); ?><?= $somaEtapas > 0 ? number_format($subtotaisEtapa[$etapaAtual] / $somaEtapas * 100, 1, ',', '.') . '% do total' : '' ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="text-xs text-muted"><?= sanitize($item['obs'] ?? '-') ?></td>
                <td><?= sanitize($item['descricao']) ?></td>
                <td><?= $item['categoria'] ? '<span class="badge badge-blue">'.sanitize($item['categoria']).'</span>' : '<span class="badge badge-gray">—</span>' ?></td>
                <td class="text-sm"><?= sanitize($item['unidade']) ?></td>
                <td class="text-sm"><?= number_format($item['quantidade'],3,',','.') ?></td>
                <td class="text-sm">R$ <?= number_format($item['preco_unitario'],2,',','.') ?></td>
                <td class="text-sm font-bold">R$ <?= number_format($item['preco_total'],2,',','.') ?></td>
                <td class="text-sm"><?= $item['preco_cotado'] !== null ? 'R$ '.number_format($item['preco_cotado'],2,',','.') : '<span class="text-muted">—</span>' ?></td>
                <td class="text-sm"><?= $item['total_cotado'] > 0 ? 'R$ '.number_format($item['total_cotado'],2,',','.') : '<span class="text-muted">—</span>' ?></td>
                <td class="text-xs"><?= sanitize($item['fornecedor_nome'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--neutral-50);font-weight:700">
                    <td colspan="6" class="text-right">TOTAL</td>
                    <td>R$ <?= number_format($totEst,2,',','.') ?></td>
                    <td></td>
                    <td>R$ <?= number_format($totCot,2,',','.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
</div>

<!-- Cotações -->
<div id="tabCotacoes" class="tab-pane">
<div class="card">
    <div class="card-header">
        <h2>Cotações Enviadas</h2>
        <button class="btn btn-sm btn-primary" onclick="openModal('modalEnviarCotacao')">+ Enviar Cotação</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fornecedor</th><th>Canal</th><th>Status</th><th>Enviada</th><th>Respondida</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($cotacoes as $cot): ?>
            <tr>
                <td><?= sanitize($cot['fornecedor']) ?></td>
                <td class="text-sm"><?= $cot['canal_envio'] ?></td>
                <td><?= statusBadge($cot['status']) ?></td>
                <td class="text-xs"><?= $cot['data_envio'] ? date('d/m/y H:i',strtotime($cot['data_envio'])) : '—' ?></td>
                <td class="text-xs"><?= $cot['data_resposta'] ? date('d/m/y H:i',strtotime($cot['data_resposta'])) : '—' ?></td>
                <td>
                    <a href="<?= APP_URL ?>/admin/cotacao_detalhe.php?id=<?= $cot['id'] ?>" class="btn btn-sm btn-outline">Ver</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$cotacoes): ?><tr><td colspan="6" class="text-center text-muted">Nenhuma cotação enviada.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- Modal: Selecionar Fornecedores e Enviar -->
<div class="modal-overlay" id="modalEnviarCotacao">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3>Selecionar Fornecedores e Enviar Cotação</h3>
            <button class="btn-close" onclick="closeModal('modalEnviarCotacao')">✕</button>
        </div>
        <form action="<?= APP_URL ?>/admin/cotacao_enviar.php" method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="orcamento_id" value="<?= $id ?>">
                <p class="text-sm text-muted mb-3">Selecione os fornecedores por categoria e o canal de envio:</p>

                <?php
                // Agrupa itens por categoria
                $itensPorCat = [];
                foreach ($itens as $item) {
                    $catKey = $item['categoria_id'] ?: 0;
                    $catLabel = $item['categoria'] ?: 'Sem Categoria';
                    $itensPorCat[$catKey]['label'] = $catLabel;
                    $itensPorCat[$catKey]['itens'][] = $item;
                    $itensPorCat[$catKey]['forns'] = $catKey ? ($fornPorCat[$catKey] ?? []) : $todosFornecedores;
                }
                ?>
                <?php foreach ($itensPorCat as $catId => $grupo): ?>
                <div style="border:1px solid var(--neutral-200);border-radius:var(--radius);padding:14px;margin-bottom:12px">
                    <div class="flex justify-between items-center mb-2">
                        <strong style="font-size:.9rem"><?= sanitize($grupo['label']) ?></strong>
                        <span class="badge badge-gray"><?= count($grupo['itens']) ?> itens</span>
                    </div>
                    <div class="text-xs text-muted mb-2">
                        <?= implode(', ', array_map(fn($i) => sanitize($i['descricao']), array_slice($grupo['itens'],0,3))) ?>
                        <?= count($grupo['itens'])>3 ? '...' : '' ?>
                    </div>
                    <?php if ($grupo['forns']): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <?php foreach ($grupo['forns'] as $f): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;background:var(--neutral-50);padding:6px 10px;border-radius:6px;cursor:pointer;border:1px solid var(--neutral-200)">
                            <input type="checkbox" name="fornecedores[<?= $catId ?>][]" value="<?= $f['id'] ?>">
                            <?= sanitize($f['nome']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-xs text-muted">Nenhum fornecedor vinculado a esta categoria.</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <hr class="divider">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Canal de Envio</label>
                        <select name="canal" class="form-control">
                            <option value="email">E-mail</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="ambos">Ambos (e-mail + WhatsApp)</option>
                            <option value="manual">Apenas gerar mensagem</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prazo de Retorno</label>
                        <input type="date" name="prazo" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Complemento da Mensagem (opcional)</label>
                    <textarea name="complemento" class="form-control" rows="3" placeholder="Ex: Obra localizada em..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEnviarCotacao')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar Cotações</button>
            </div>
        </form>
    </div>
</div>

</div></div></div>
<?php pageFoot(); ?>
