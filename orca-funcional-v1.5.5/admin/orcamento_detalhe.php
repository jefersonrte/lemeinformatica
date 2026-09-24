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

$orc = $db->prepare('SELECT o.*,ob.nome as obra_nome,ob.tipologia as obra_tipologia,ob.area_construida as obra_area,(SELECT COUNT(*) FROM referencias r WHERE r.orcamento_id=o.id) as eh_referencia,c.razao_social,c.whatsapp as cli_wa FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id JOIN clientes c ON c.id=o.cliente_id WHERE o.id=?');
$orc->execute([$id]);
$orc = $orc->fetch();
if (!$orc) redirect(APP_URL.'/admin/orcamentos.php');

$itens = $db->prepare('SELECT oi.*,cat.nome as categoria,fo.nome as fornecedor_nome FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id=oi.categoria_id LEFT JOIN fornecedores fo ON fo.id=oi.fornecedor_id WHERE oi.orcamento_id=? ORDER BY oi.ordem, oi.id');
$itens->execute([$id]);
$itens = $itens->fetchAll();

// Fornecedores para o painel de cotação: categorias atendidas e histórico de respostas.
$fornStmt = $db->prepare("SELECT f.id, f.nome, f.email, f.whatsapp, f.cidade,
        (SELECT GROUP_CONCAT(fc.categoria_id) FROM fornecedor_categorias fc WHERE fc.fornecedor_id=f.id) AS cat_ids,
        (SELECT COUNT(*) FROM cotacoes c1 WHERE c1.fornecedor_id=f.id) AS qtd_pedidos,
        (SELECT COUNT(*) FROM cotacoes c2 WHERE c2.fornecedor_id=f.id AND c2.status IN ('respondida','aceita')) AS respondidas,
        (SELECT c3.status FROM cotacoes c3 WHERE c3.fornecedor_id=f.id AND c3.orcamento_id=? ORDER BY c3.id DESC LIMIT 1) AS status_aqui
    FROM fornecedores f WHERE f.ativo=1 ORDER BY f.nome");
$fornStmt->execute([$id]);
$todosFornecedores = array_map(static fn (array $f): array => [
    'id' => (int) $f['id'], 'nome' => $f['nome'], 'email' => (string) $f['email'], 'whatsapp' => (string) $f['whatsapp'], 'cidade' => (string) $f['cidade'],
    'categorias' => array_map('intval', array_filter(explode(',', (string) $f['cat_ids']))),
    'cotacoes' => (int) $f['qtd_pedidos'], 'respondidas' => (int) $f['respondidas'], 'statusAqui' => $f['status_aqui'],
], $fornStmt->fetchAll());

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
                <button class="btn btn-primary btn-sm" type="button" data-cotar-abrir><i class="fa-solid fa-paper-plane"></i> Pedir cotação</button>
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
            <?php if ($orc['status'] === 'aprovado' && !$orc['eh_referencia']): ?>
            <form method="post" action="<?= APP_URL ?>/admin/referencias.php" style="display:inline" title="Inclui este orçamento (sem dados do cliente) na base usada pela prévia de obra">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="promover">
                <input type="hidden" name="orcamento_id" value="<?= $id ?>">
                <input type="hidden" name="tipologia" value="<?= sanitize((string) ($orc['obra_tipologia'] ?: 'residencial')) ?>">
                <input type="hidden" name="area" value="<?= (float) $orc['obra_area'] ?>">
                <button type="submit" class="btn btn-sm btn-outline"><i class="fa-solid fa-database"></i> Usar como referência</button>
            </form>
            <?php elseif ($orc['eh_referencia']): ?>
            <span class="badge badge-teal" title="Faz parte da base da prévia de obra">Na base de referência</span>
            <?php endif; ?>
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
    <div class="item-toolbar">
        <label class="query-search">
            <i class="fa-solid fa-filter" aria-hidden="true"></i>
            <input type="search" placeholder="Filtrar itens (descrição, etapa, código)…" aria-label="Filtrar itens do orçamento" data-itens-filtro>
        </label>
        <span class="item-toolbar-hint" data-itens-hint><i class="fa-regular fa-square-check"></i> Marque itens ou etapas inteiras para pedir cotação só do que precisa.</span>
    </div>
    <div class="table-wrap">
        <table data-itens-tabela>
            <thead><tr>
                <th class="col-check"><input type="checkbox" data-itens-todos aria-label="Selecionar todos os itens visíveis"></th>
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
            <tr class="etapa-row" style="background:var(--neutral-50)" data-etapa-row>
                <td class="col-check"><input type="checkbox" data-etapa-check aria-label="Selecionar etapa <?= sanitize($etapaAtual !== '' ? $etapaAtual : 'Sem etapa') ?>"></td>
                <td colspan="6" class="font-bold text-sm"><?= sanitize($etapaAtual !== '' ? $etapaAtual : 'Sem etapa') ?></td>
                <td class="font-bold text-sm">R$ <?= number_format($subtotaisEtapa[$etapaAtual],2,',','.') ?></td>
                <td colspan="3" class="text-xs text-muted"><?php $somaEtapas = array_sum($subtotaisEtapa); ?><?= $somaEtapas > 0 ? number_format($subtotaisEtapa[$etapaAtual] / $somaEtapas * 100, 1, ',', '.') . '% do total' : '' ?></td>
            </tr>
            <?php endif; ?>
            <tr data-item='<?= jsonAttr(['id' => (int) $item['id'], 'descricao' => (string) $item['descricao'], 'unidade' => (string) $item['unidade'], 'quantidade' => (float) $item['quantidade'], 'total' => (float) $item['preco_total'], 'etapa' => (string) ($item['etapa'] ?? ''), 'categoria' => $item['categoria_id'] ? (int) $item['categoria_id'] : null]) ?>'>
                <td class="col-check"><input type="checkbox" data-item-check value="<?= (int) $item['id'] ?>" aria-label="Selecionar <?= sanitize($item['descricao']) ?>"></td>
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
                    <td colspan="7" class="text-right">TOTAL</td>
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
        <button class="btn btn-sm btn-primary" type="button" data-cotar-abrir><i class="fa-solid fa-paper-plane"></i> Pedir cotação</button>
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

<!-- Barra de seleção (aparece ao marcar itens) -->
<div class="bulk-bar" data-bulk-bar role="region" aria-label="Itens selecionados" aria-live="polite">
    <span class="bulk-bar-count" data-bulk-count>0 itens</span>
    <span class="bulk-bar-total" data-bulk-total></span>
    <button type="button" class="btn-link" data-bulk-limpar>Limpar</button>
    <button type="button" class="btn btn-primary btn-sm" data-cotar-abrir><i class="fa-solid fa-paper-plane"></i> Pedir cotação</button>
</div>

<!-- Painel lateral: pedido de cotação sem sair do orçamento -->
<div class="drawer-overlay" data-drawer-overlay></div>
<aside class="drawer" id="drawerCotacao" role="dialog" aria-modal="true" aria-labelledby="drawerCotacaoTitulo" aria-hidden="true"
    data-cotacao
    data-orcamento="<?= $id ?>"
    data-csrf="<?= csrf() ?>"
    data-enviar-url="<?= APP_URL ?>/admin/cotacao_enviar.php"
    data-fornecedor-url="<?= APP_URL ?>/admin/fornecedor_rapido.php"
    data-fornecedores='<?= jsonAttr($todosFornecedores) ?>'>
    <div class="drawer-head">
        <div>
            <h3 id="drawerCotacaoTitulo">Pedir cotação</h3>
            <p><?= sanitize($orc['titulo']) ?> · <?= sanitize($orc['obra_nome']) ?></p>
        </div>
        <button class="btn-close" type="button" data-drawer-fechar aria-label="Fechar">✕</button>
    </div>
    <nav class="stepper" aria-label="Etapas do pedido">
        <button type="button" data-passo-ir="1" class="is-active">Itens</button>
        <button type="button" data-passo-ir="2">Fornecedores</button>
        <button type="button" data-passo-ir="3">Envio</button>
    </nav>
    <div class="drawer-body">
        <section class="drawer-step" data-passo="1">
            <div class="drawer-section-title"><span data-resumo-itens>Itens para cotar</span><button type="button" class="btn btn-sm btn-outline" data-drawer-fechar title="Volte à tabela para marcar ou desmarcar itens"><i class="fa-regular fa-square-check"></i> Escolher na tabela</button></div>
            <ul class="pick-list" data-lista-itens></ul>
        </section>
        <section class="drawer-step" data-passo="2" hidden>
            <label class="query-search mb-2">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" placeholder="Buscar fornecedor por nome, cidade ou e-mail…" data-forn-busca aria-label="Buscar fornecedor">
            </label>
            <div class="drawer-section-title"><span data-resumo-forn>Fornecedores</span><button type="button" class="btn btn-sm btn-outline" data-forn-novo><i class="fa-solid fa-plus"></i> Novo fornecedor</button></div>
            <form class="quick-form" data-forn-form hidden>
                <input class="form-control" name="nome" placeholder="Nome / empresa *" required maxlength="180">
                <input class="form-control" name="email" type="email" placeholder="E-mail" maxlength="180">
                <input class="form-control" name="whatsapp" placeholder="WhatsApp (DDD)" maxlength="20" inputmode="tel">
                <button class="btn btn-primary btn-sm" type="submit">Adicionar</button>
            </form>
            <ul class="pick-list" data-lista-forn></ul>
        </section>
        <section class="drawer-step" data-passo="3" hidden>
            <div class="drawer-section-title">Como enviar</div>
            <div class="channel-options" role="radiogroup" aria-label="Canal de envio">
                <label class="channel-option"><input type="radio" name="canalCotacao" value="manual" checked><span><i class="fa-brands fa-whatsapp"></i>Eu mesmo envio<small>Gera a mensagem e abre WhatsApp/e-mail</small></span></label>
                <label class="channel-option"><input type="radio" name="canalCotacao" value="email"><span><i class="fa-regular fa-envelope"></i>E-mail automático<small>Pelo servidor do sistema</small></span></label>
                <label class="channel-option"><input type="radio" name="canalCotacao" value="ambos"><span><i class="fa-solid fa-bolt"></i>E-mail + WhatsApp<small>Automático nos dois</small></span></label>
            </div>
            <div class="form-row mt-3">
                <div class="form-group">
                    <label class="form-label" for="cotPrazo">Responder até</label>
                    <input type="date" id="cotPrazo" class="form-control" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" min="<?= date('Y-m-d') ?>" data-cot-prazo>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="cotComplemento">Observações para o fornecedor (opcional)</label>
                <textarea id="cotComplemento" class="form-control" rows="2" placeholder="Ex.: entrega em Florianópolis/SC; informar prazo e frete." data-cot-complemento></textarea>
            </div>
            <div class="drawer-section-title">Prévia da mensagem <small class="text-muted" data-previa-para></small></div>
            <pre class="message-preview" data-previa>Carregando…</pre>
        </section>
        <section class="drawer-step" data-passo="4" hidden>
            <div class="drawer-section-title">Pedidos registrados</div>
            <div data-resultados></div>
            <p class="text-xs text-muted mt-3">As respostas dos fornecedores ficam na aba <strong>Cotações</strong>; registre os preços recebidos em cada uma ou use a <a href="<?= APP_URL ?>/admin/leitura_cotacao.php">leitura de cotação</a>.</p>
        </section>
    </div>
    <div class="drawer-foot">
        <button type="button" class="btn btn-outline btn-sm" data-passo-voltar>Voltar</button>
        <span class="spacer" data-rodape-info></span>
        <button type="button" class="btn btn-primary btn-sm" data-passo-avancar>Continuar</button>
    </div>
</aside>

</div></div></div>
<?php pageFoot([APP_URL . '/assets/js/cotacao.js?v=' . rawurlencode(APP_VERSION)]); ?>
