<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/file_import.php';
require_once __DIR__ . '/../helpers/orcamento_editor.php';

requireAdmin();
$db = getDB();

$obraId = (int)($_GET['obra_id'] ?? $_POST['obra_id'] ?? 0);
$obras = $db->query('SELECT o.id,o.nome,c.razao_social FROM obras o JOIN clientes c ON c.id=o.cliente_id ORDER BY o.nome')->fetchAll();
$categorias = $db->query('SELECT * FROM categorias WHERE ativo=1 ORDER BY nome')->fetchAll();

// Importação de arquivo
$importacao = null;
$itensImportados = [];
$importErro = '';
$tipoSelecionado = $_POST['tipo_origem'] ?? 'excel';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'importar') {
    verifyCsrf();
    $arquivo = $_FILES['arquivo'] ?? null;
    if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
        $importErro = 'Selecione um arquivo para importar.';
    } elseif ($arquivo['size'] > UPLOAD_MAX_MB * 1024 * 1024) {
        $importErro = 'Arquivo muito grande. Máximo: ' . UPLOAD_MAX_MB . 'MB.';
    } else {
        $ext = strtolower(pathinfo((string) $arquivo['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls', 'ods', 'xlsb', 'csv'], true)) {
            $abaEscolhida = trim((string) ($_POST['aba'] ?? '')) ?: null;
            $importacao = importarPlanilhaDetalhada($arquivo['tmp_name'], $ext, $abaEscolhida);
            $itensImportados = $importacao['itens'];
            $importErro = $importacao['erro'] ?? '';
            if ($itensImportados !== []) {
                $tipoSelecionado = $tipoSelecionado === 'caixa' ? 'caixa' : 'excel';
            }
        } else {
            $itensImportados = importarArquivo($arquivo);
            if (!$itensImportados) $importErro = 'Nenhum item encontrado no arquivo. Verifique o formato.';
        }
        if ($itensImportados !== []) {
            $analise = (new \App\Domain\Importacao\PlanilhaCatalogAnalyzer($db))->analisar($itensImportados);
            $itensImportados = $analise['items'];
        }
    }
}

// Salvar orçamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    verifyCsrf();
    $oId  = (int)($_POST['obra_id'] ?? 0);
    $tit  = trim($_POST['titulo'] ?? '');
    $obs  = trim($_POST['obs'] ?? '');
    $tipo = $_POST['tipo_origem'] ?? 'manual';
    $bdi  = \App\Domain\Orcamento\OrcamentoCalculator::decimal($_POST['bdi_percentual'] ?? 0);

    try {
        $service = new \App\Domain\Orcamento\OrcamentoService($db);
        $orcId = $service->criar($oId, $tit, $tipo, $obs, orcamentoItensDoPost($_POST), $bdi);
    } catch (InvalidArgumentException $exception) {
        setFlash('error', $exception->getMessage());
        redirect(APP_URL . '/admin/orcamento_novo.php' . ($oId ? '?obra_id=' . $oId : ''));
    }

    logAction('orcamento_criado','orcamentos',$orcId,$tit);
    setFlash('success','Orçamento criado com sucesso.');
    redirect(APP_URL.'/admin/orcamento_detalhe.php?id='.$orcId);
}

pageHead('Novo Orçamento');
?>
<div class="layout">
<?php sidebar('orcamentos'); ?>
<div class="main">
<?php topbar('Novo Orçamento'); ?>
<div class="content">
<?php flashMessage(); ?>

<!-- Stepper -->
<div class="stepper mb-4">
    <div class="step"><div class="step-circle active" id="step1c">1</div><div class="step-line" id="line1"></div></div>
    <div class="step"><div class="step-circle" id="step2c">2</div><div class="step-line" id="line2"></div></div>
    <div class="step"><div class="step-circle" id="step3c">3</div></div>
</div>

<!-- PASSO 1: Importar arquivo -->
<div id="passo1">
<div class="card mb-4">
    <div class="card-header">
        <h2>Passo 1 — Importar Planilha / XML / PDF <span class="text-muted text-sm">(ou adicione manualmente)</span></h2>
    </div>
    <div class="card-body">
        <?php if ($importErro): ?><div class="alert alert-error"><?= sanitize($importErro) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" id="formImportar">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="acao" value="importar">
            <input type="hidden" name="obra_id" value="<?= $obraId ?>">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tipo de Importação</label>
                    <select name="tipo_origem" id="tipoOrigem" class="form-control">
                        <?php foreach (['excel' => 'Planilha de orçamento (Excel / CSV)', 'caixa' => 'Planilha Padrão CAIXA (SINAPI)', 'xml' => 'XML de Materiais', 'pdf' => 'PDF (texto extraído)', 'manual' => 'Manual (sem arquivo)'] as $valorTipo => $rotuloTipo): ?>
                        <option value="<?= $valorTipo ?>" <?= $tipoSelecionado === $valorTipo ? 'selected' : '' ?>><?= $rotuloTipo ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-text">A aba, o cabeçalho, as etapas e o BDI são detectados automaticamente.</p>
                </div>
            </div>
            <div id="dzArea">
                <div class="dropzone" id="dz">
                    <div class="dz-icon">📄</div>
                    <p><span>Clique para selecionar</span> ou arraste o arquivo aqui</p>
                    <p style="font-size:.75rem;color:var(--neutral-400);margin-top:4px">Excel (.xlsx/.xls/.ods), CSV, XML, PDF — máx <?= UPLOAD_MAX_MB ?>MB</p>
                </div>
                <input type="file" id="arquivoInput" name="arquivo" style="display:none" accept=".xlsx,.xls,.ods,.csv,.xml,.pdf">
            </div>
            <div class="flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary" id="btnImportar">Importar Arquivo</button>
                <button type="button" class="btn btn-secondary" onclick="irPasso2([])">Pular / Adicionar Manualmente</button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- PASSO 2: Revisar itens -->
<div id="passo2" class="hidden">
<div class="card mb-4">
    <div class="card-header" style="flex-wrap:wrap;gap:8px">
        <h2>Passo 2 — Revisar e Completar Itens</h2>
        <button type="button" class="btn btn-sm btn-outline" onclick="adicionarLinha()">+ Adicionar Linha</button>
    </div>
    <?php if ($importacao && $itensImportados): ?>
    <div class="card-body" style="padding-bottom:0">
        <div class="alert alert-info alert-persist" style="display:block">
            Aba <strong><?= sanitize($importacao['aba']) ?></strong>: <?= count($itensImportados) ?> itens
            em <?= count(array_unique(array_filter(array_column($itensImportados, 'etapa')))) ?> etapas.
            <?php if ($importacao['bdi_percentual'] > 0): ?> BDI detectado: <strong><?= number_format($importacao['bdi_percentual'], 2, ',', '.') ?>%</strong>.<?php endif; ?>
            <?php if ($importacao['total_planilha']): ?> Total informado na planilha: <strong>R$ <?= number_format($importacao['total_planilha'], 2, ',', '.') ?></strong>.<?php endif; ?>
        </div>
        <?php foreach ($importacao['avisos'] as $aviso): ?><div class="alert alert-warning alert-persist" style="display:block"><?= sanitize($aviso) ?></div><?php endforeach; ?>
        <?php $outrasAbas = array_filter($importacao['abas'], fn($a) => $a['itens'] > 0 && $a['nome'] !== $importacao['aba']); ?>
        <?php if ($outrasAbas): ?>
        <p class="text-sm text-muted">Outras abas com itens: <?= sanitize(implode(', ', array_map(fn($a) => $a['nome'] . ' (' . $a['itens'] . ')', $outrasAbas))) ?>. Para usar outra aba, importe novamente escolhendo-a:
            <select name="aba" form="formImportar" class="form-control" style="display:inline-block;width:auto;padding:3px 8px">
                <option value="">Automático</option>
                <?php foreach ($outrasAbas as $abaAlt): ?><option value="<?= sanitize($abaAlt['nome']) ?>"><?= sanitize($abaAlt['nome']) ?></option><?php endforeach; ?>
            </select>
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="card-body" style="padding:0">
        <?php orcamentoEditorItens($categorias, 'formSalvar', (float) ($importacao['bdi_percentual'] ?? 0)); ?>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--neutral-200);display:flex;justify-content:space-between">
        <button type="button" class="btn btn-secondary" onclick="irPasso1()">← Voltar</button>
        <button type="button" class="btn btn-primary" onclick="irPasso3()">Próximo →</button>
    </div>
</div>
</div>

<!-- PASSO 3: Dados do orçamento e salvar -->
<div id="passo3" class="hidden">
<div class="card">
    <div class="card-header"><h2>Passo 3 — Dados do Orçamento</h2></div>
    <form method="post" id="formSalvar">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="tipo_origem" id="tipoOrigemSave" value="manual">
            <div class="form-group">
                <label class="form-label">Obra / Projeto *</label>
                <select name="obra_id" id="fObraSelect" class="form-control" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($obras as $ob): ?>
                    <option value="<?= $ob['id'] ?>" <?= $obraId==$ob['id']?'selected':'' ?>>
                        <?= sanitize($ob['nome']) ?> — <?= sanitize($ob['razao_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Título do Orçamento *</label>
                <input type="text" name="titulo" class="form-control" required maxlength="200" placeholder="Ex: Orçamento executivo — Revisão 1"
                       value="<?= sanitize($importacao ? pathinfo((string) ($_FILES['arquivo']['name'] ?? ''), PATHINFO_FILENAME) : '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Observações</label>
                <textarea name="obs" class="form-control"></textarea>
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--neutral-200);display:flex;justify-content:space-between">
            <button type="button" class="btn btn-secondary" onclick="irPasso2Last()">← Voltar</button>
            <button type="submit" class="btn btn-success">Salvar Orçamento</button>
        </div>
    </form>
</div>
</div>

<script>
const ITENS_IMPORTADOS = <?= json_encode($itensImportados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

document.addEventListener('DOMContentLoaded', () => {
    initDropzone('dz','arquivoInput');
    const tipo = document.getElementById('tipoOrigem');
    const alternar = () => {
        const manual = tipo.value === 'manual';
        document.getElementById('dzArea').style.display = manual ? 'none' : '';
        document.getElementById('btnImportar').style.display = manual ? 'none' : '';
    };
    tipo.addEventListener('change', alternar);
    alternar();
    if (ITENS_IMPORTADOS.length > 0) irPasso2(ITENS_IMPORTADOS);
});

function marcarPasso(ativo) {
    [1, 2, 3].forEach(n => {
        const c = document.getElementById('step' + n + 'c');
        c.classList.toggle('active', n === ativo);
        c.classList.toggle('done', n < ativo);
    });
    document.getElementById('line1').classList.toggle('done', ativo > 1);
    document.getElementById('line2').classList.toggle('done', ativo > 2);
}
function mostrar(passo) {
    [1, 2, 3].forEach(n => document.getElementById('passo' + n).classList.toggle('hidden', n !== passo));
    marcarPasso(passo);
}
function irPasso1() { mostrar(1); }
function irPasso2(itens) { carregarItens(itens); mostrar(2); }
function irPasso2Last() { mostrar(2); }
function irPasso3() {
    if (!validarItens()) return;
    document.getElementById('tipoOrigemSave').value = ITENS_IMPORTADOS.length > 0 ? document.getElementById('tipoOrigem').value : 'manual';
    mostrar(3);
}
</script>
</div></div></div>
<?php pageFoot(); ?>
