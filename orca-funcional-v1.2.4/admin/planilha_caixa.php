<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/file_import.php';

requireAdmin();
$db = getDB();

$itens      = [];
$importErro = '';
$obraId     = 0;
$analiseCatalogo = ['ai_configured' => false, 'ai_used' => false, 'ai_error' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'importar') {
    verifyCsrf();
    $obraId = (int)($_POST['obra_id'] ?? 0);
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $itens = importarPlanilhaCaixa($_FILES['arquivo']['tmp_name']);
        if (!$itens) {
            $importErro = 'Nenhum item encontrado. Verifique se o arquivo é uma planilha padrão CAIXA/SINAPI.';
        } else {
            $analiseCatalogo = (new \App\Domain\Importacao\PlanilhaCatalogAnalyzer($db))->analisar($itens);
            $itens = $analiseCatalogo['items'];
        }
    } else {
        $importErro = 'Selecione um arquivo válido.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_orcamento') {
    verifyCsrf();
    $obraId  = (int)($_POST['obra_id'] ?? 0);
    $titulo  = trim($_POST['titulo'] ?? 'Orçamento Planilha Caixa');

    $descs   = $_POST['item_desc']   ?? [];
    $uns     = $_POST['item_un']     ?? [];
    $qtds    = $_POST['item_qtd']    ?? [];
    $precos  = $_POST['item_preco']  ?? [];
    $cats    = $_POST['item_cat']    ?? [];
    $codigos = $_POST['item_codigo'] ?? [];
    $itensParaSalvar = [];
    foreach ($descs as $i => $desc) {
        $itensParaSalvar[] = [
            'descricao' => $desc,
            'unidade' => $uns[$i] ?? 'UN',
            'quantidade' => $qtds[$i] ?? 1,
            'preco_unitario' => $precos[$i] ?? 0,
            'categoria_id' => $cats[$i] ?? null,
            'codigo' => $codigos[$i] ?? null,
        ];
    }

    try {
        $service = new \App\Domain\Orcamento\OrcamentoService($db);
        $orcId = $service->criar($obraId, $titulo, 'caixa', '', $itensParaSalvar);
    } catch (InvalidArgumentException $exception) {
        setFlash('error', $exception->getMessage());
        redirect(APP_URL . '/admin/planilha_caixa.php');
    }

    setFlash('success','Orçamento criado da planilha Caixa.');
    redirect(APP_URL.'/admin/orcamento_detalhe.php?id='.$orcId);
}

$obras      = $db->query('SELECT o.id,o.nome,o.cliente_id,c.razao_social FROM obras o JOIN clientes c ON c.id=o.cliente_id ORDER BY o.nome')->fetchAll();
$categorias = $db->query('SELECT * FROM categorias WHERE ativo=1 ORDER BY nome')->fetchAll();

pageHead('Planilha Padrão Caixa');
?>
<div class="layout">
<?php sidebar('caixa'); ?>
<div class="main">
<?php topbar('Importar Planilha Padrão Caixa / SINAPI'); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card mb-4">
    <div class="card-header">
        <h2>Importar Planilha Orçamentária Caixa (SINAPI)</h2>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            Faça upload da planilha padrão CAIXA (.xlsx/.xls) com as colunas: <strong>Código, Descrição, Unidade, Quantidade, Preço Unitário</strong>.
            O sistema irá identificar automaticamente as colunas pelo cabeçalho.
        </div>
        <?php if ($importErro): ?><div class="alert alert-error"><?= sanitize($importErro) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="acao" value="importar">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Obra / Projeto</label>
                    <select name="obra_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($obras as $ob): ?>
                        <option value="<?= $ob['id'] ?>" <?= $obraId==$ob['id']?'selected':'' ?>><?= sanitize($ob['nome']) ?> — <?= sanitize($ob['razao_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dropzone" id="dzCaixa" onclick="document.getElementById('arquivoCaixa').click()">
                <div class="dz-icon">🏦</div>
                <p><span>Selecionar planilha CAIXA</span> (.xlsx, .xls)</p>
            </div>
            <input type="file" id="arquivoCaixa" name="arquivo" style="display:none" accept=".xlsx,.xls,.ods,.csv">
            <button type="submit" class="btn btn-primary mt-3">Importar e Visualizar</button>
        </form>
    </div>
</div>

<?php if ($itens): ?>
<div class="card">
    <div class="card-header">
        <h2>Itens Importados (<?= count($itens) ?>)</h2>
    </div>
    <div class="alert <?= $analiseCatalogo['ai_error'] ? 'alert-warning' : 'alert-info' ?>" style="margin:16px 24px 0">
        <?php if ($analiseCatalogo['ai_used']): ?>
            <strong>Análise por IA concluída.</strong> Categorias e possíveis duplicidades foram comparadas com o catálogo atual.
        <?php elseif ($analiseCatalogo['ai_error']): ?>
            <strong>IA temporariamente indisponível.</strong> A verificação local de códigos, nomes semelhantes e categorias foi aplicada.
        <?php else: ?>
            <strong>Verificação inteligente local concluída.</strong> Ative a chave de IA no servidor para complementar casos ambíguos.
        <?php endif; ?>
    </div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="acao" value="criar_orcamento">
        <input type="hidden" name="obra_id" value="<?= $obraId ?>">
        <input type="hidden" name="cliente_id" id="cliIdField" value="">
        <div style="padding:16px 24px;border-bottom:1px solid var(--neutral-200)">
            <div class="form-row" style="max-width:600px">
                <div class="form-group">
                    <label class="form-label">Título do Orçamento</label>
                    <input type="text" name="titulo" class="form-control" value="Orçamento Caixa — <?= date('m/Y') ?>" required>
                </div>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Código</th><th>Descrição</th><th>Unid.</th><th>Qtd</th>
                    <th>Preço Unit. (R$)</th><th>Total Est.</th><th>Categoria sugerida</th><th>Análise</th>
                </tr></thead>
                <tbody>
                <?php $totGeral = 0; foreach ($itens as $idx => $item):
                    $tot = $item['quantidade'] * $item['preco_unitario']; $totGeral += $tot; ?>
                <tr>
                    <input type="hidden" name="item_codigo[]" value="<?= sanitize($item['codigo']) ?>">
                    <td class="text-xs text-muted"><?= sanitize($item['codigo'] ?? '') ?></td>
                    <td><input type="text" name="item_desc[]" value="<?= sanitize($item['descricao']) ?>" class="form-control" style="min-width:200px;font-size:.85rem;padding:5px 7px" required></td>
                    <td><select name="item_un[]" class="form-control" style="width:80px;font-size:.8rem;padding:4px 6px">
                        <?php foreach (['UN','M','M²','M³','KG','CX','PC','RL','SC','L','GL','KIT','VB'] as $u): ?>
                        <option <?= strtoupper($item['unidade']??'UN')===$u?'selected':'' ?>><?= $u ?></option>
                        <?php endforeach; ?></select></td>
                    <td><input type="number" name="item_qtd[]" value="<?= $item['quantidade'] ?>" step="0.001" class="form-control" style="width:90px;font-size:.85rem;padding:5px 7px"></td>
                    <td><input type="number" name="item_preco[]" value="<?= $item['preco_unitario'] ?>" step="0.01" class="form-control preco-input" style="width:110px;font-size:.85rem;padding:5px 7px"></td>
                    <td class="text-sm font-bold" style="color:var(--primary)">R$ <?= number_format($tot,2,',','.') ?></td>
                    <td><select name="item_cat[]" class="form-control" style="font-size:.8rem;padding:4px 6px">
                        <option value="">-- Categoria</option>
                        <?php foreach ($categorias as $cat): ?><option value="<?= $cat['id'] ?>" <?= (int)($item['categoria_id_sugerida'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['nome']) ?></option><?php endforeach; ?>
                    </select></td>
                    <td style="min-width:210px">
                        <?php if (!empty($item['duplicado'])): ?>
                            <span class="badge badge-red">Já cadastrado</span>
                        <?php elseif (!empty($item['semelhante'])): ?>
                            <span class="badge badge-yellow">Possível semelhante</span>
                        <?php else: ?>
                            <span class="badge badge-green">Item novo</span>
                        <?php endif; ?>
                        <?php if (!empty($item['produto_nome_similar'])): ?>
                            <div class="text-xs mt-1"><strong><?= sanitize($item['produto_nome_similar']) ?></strong> · <?= (int)round(((float)($item['similaridade'] ?? 0)) * 100) ?>%</div>
                        <?php endif; ?>
                        <div class="text-xs text-muted mt-1"><?= sanitize((string)($item['analise_motivo'] ?? '')) ?> · <?= ($item['analise_origem'] ?? 'local') === 'ia' ? 'IA' : 'catálogo local' ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--neutral-50);font-weight:700">
                        <td colspan="5" class="text-right">TOTAL ESTIMADO</td>
                        <td colspan="3">R$ <?= number_format($totGeral,2,',','.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--neutral-200)">
            <button type="submit" class="btn btn-success btn-lg">Criar Orçamento</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>initDropzone('dzCaixa','arquivoCaixa');</script>
</div></div></div>
<?php pageFoot(); ?>
