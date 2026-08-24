<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/file_import.php';

requireAdmin();
$db = getDB();

$obraId    = (int)($_GET['obra_id'] ?? 0);
$clienteId = 0;
if ($obraId) {
    $obra = $db->prepare('SELECT id,nome,cliente_id FROM obras WHERE id=?');
    $obra->execute([$obraId]);
    $obra = $obra->fetch();
    if ($obra) $clienteId = $obra['cliente_id'];
}

$obras     = $db->query('SELECT o.id,o.nome,c.razao_social FROM obras o JOIN clientes c ON c.id=o.cliente_id ORDER BY o.nome')->fetchAll();
$categorias = $db->query('SELECT * FROM categorias WHERE ativo=1 ORDER BY nome')->fetchAll();

// Importação de arquivo
$itensImportados = [];
$importErro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'importar') {
    verifyCsrf();
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
        if ($_FILES['arquivo']['size'] > $maxBytes) {
            $importErro = 'Arquivo muito grande. Máximo: ' . UPLOAD_MAX_MB . 'MB.';
        } else {
            $tipo = $_POST['tipo_origem'] ?? 'excel';
            if ($tipo === 'caixa') {
                $itensImportados = importarPlanilhaCaixa($_FILES['arquivo']['tmp_name']);
            } else {
                $itensImportados = importarArquivo($_FILES['arquivo']);
            }
            if (!$itensImportados) $importErro = 'Nenhum item encontrado no arquivo. Verifique o formato.';
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

    $descs    = $_POST['item_desc']    ?? [];
    $uns      = $_POST['item_un']      ?? [];
    $qtds     = $_POST['item_qtd']     ?? [];
    $precos   = $_POST['item_preco']   ?? [];
    $cats     = $_POST['item_cat']     ?? [];
    $codigos  = $_POST['item_codigo']  ?? [];

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
        $orcId = $service->criar($oId, $tit, $tipo, $obs, $itensParaSalvar);
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
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tipo de Importação</label>
                    <select name="tipo_origem" id="tipoOrigem" class="form-control">
                        <option value="excel">Excel / CSV (genérico)</option>
                        <option value="caixa">Planilha Padrão CAIXA (SINAPI)</option>
                        <option value="xml">XML de Materiais</option>
                        <option value="pdf">PDF (texto extraído)</option>
                        <option value="manual">Manual (sem arquivo)</option>
                    </select>
                </div>
            </div>
            <div id="dzArea">
                <div class="dropzone" id="dz" onclick="document.getElementById('arquivoInput').click()">
                    <div class="dz-icon">📄</div>
                    <p><span>Clique para selecionar</span> ou arraste o arquivo aqui</p>
                    <p style="font-size:.75rem;color:var(--neutral-400);margin-top:4px">Excel (.xlsx/.xls), CSV, XML, PDF — máx <?= UPLOAD_MAX_MB ?>MB</p>
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
    <div class="card-header">
        <h2>Passo 2 — Revisar e Completar Itens</h2>
        <button class="btn btn-sm btn-outline" onclick="adicionarLinha()">+ Adicionar Linha</button>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
            <table id="tabelaItens">
                <thead><tr>
                    <th style="width:90px">Código</th>
                    <th>Descrição *</th>
                    <th style="width:80px">Unid.</th>
                    <th style="width:90px">Qtd</th>
                    <th style="width:110px">Preço Unit.</th>
                    <th>Categoria</th>
                    <th style="width:100px">Total</th>
                    <th style="width:40px"></th>
                </tr></thead>
                <tbody id="itensBody"></tbody>
            </table>
        </div>
        <div style="padding:12px 24px;display:flex;justify-content:flex-end;align-items:center;gap:16px;border-top:1px solid var(--neutral-100)">
            <span class="text-sm text-muted">Total Estimado:</span>
            <strong style="font-size:1.1rem" id="totalGeral">R$ 0,00</strong>
        </div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--neutral-200);display:flex;justify-content:space-between">
        <button class="btn btn-secondary" onclick="irPasso1()">← Voltar</button>
        <button class="btn btn-primary" onclick="irPasso3()">Próximo →</button>
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
            <div id="itensHiddenFields"></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Obra / Projeto *</label>
                    <select name="obra_id" id="fObraSelect" class="form-control" required onchange="atualizarCliente(this)">
                        <option value="">Selecione...</option>
                        <?php foreach ($obras as $ob): ?>
                        <option value="<?= $ob['id'] ?>" data-cliente="<?= $ob['cliente_id'] ?? '' ?>" <?= $obraId==$ob['id']?'selected':'' ?>>
                            <?= sanitize($ob['nome']) ?> — <?= sanitize($ob['razao_social']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="cliente_id" id="fClienteId" value="<?= $clienteId ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Título do Orçamento *</label>
                <input type="text" name="titulo" class="form-control" required maxlength="200" placeholder="Ex: Orçamento de Alvenaria — Março/2025">
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
const CATEGORIAS = <?= json_encode(array_map(fn($c) => ['id'=>$c['id'],'nome'=>$c['nome']], $categorias), JSON_UNESCAPED_UNICODE) ?>;
const ITENS_IMPORTADOS = <?= json_encode($itensImportados, JSON_UNESCAPED_UNICODE) ?>;
let itensAtual = [];

document.addEventListener('DOMContentLoaded', () => {
    initDropzone('dz','arquivoInput');
    document.getElementById('tipoOrigem').addEventListener('change', function() {
        const manual = this.value === 'manual';
        document.getElementById('dzArea').style.display = manual ? 'none' : '';
        document.getElementById('btnImportar').style.display = manual ? 'none' : '';
    });
    if (ITENS_IMPORTADOS.length > 0) irPasso2(ITENS_IMPORTADOS);
});

function selCatHtml(valor) {
    let opts = '<option value="">-- Categoria</option>';
    CATEGORIAS.forEach(c => opts += `<option value="${c.id}" ${String(valor)===String(c.id)?'selected':''}>${c.nome}</option>`);
    return `<select name="item_cat[]" class="form-control" style="font-size:.8rem;padding:4px 6px">${opts}</select>`;
}

function unidadeOpts(valor) {
    const uns = ['UN','M','M²','M³','KG','CX','PC','RL','SC','L','GL','KIT','VB'];
    return uns.map(u => `<option ${u===valor?'selected':''}>${u}</option>`).join('');
}

function renderLinha(item, idx) {
    return `<tr id="row${idx}">
        <td><input type="text" name="item_codigo[]" value="${esc(item.codigo||'')}" class="form-control" style="font-size:.8rem;padding:5px 7px"></td>
        <td><input type="text" name="item_desc[]" value="${esc(item.descricao||'')}" class="form-control" style="font-size:.8rem;padding:5px 7px" required></td>
        <td><select name="item_un[]" class="form-control" style="font-size:.8rem;padding:4px 6px">${unidadeOpts(item.unidade||'UN')}</select></td>
        <td><input type="number" name="item_qtd[]" value="${item.quantidade||1}" step="0.001" min="0" class="form-control qtd-input" style="font-size:.8rem;padding:5px 7px" onchange="recalcTotal()"></td>
        <td><input type="number" name="item_preco[]" value="${item.preco_unitario||0}" step="0.01" min="0" class="form-control preco-input" style="font-size:.8rem;padding:5px 7px" onchange="recalcTotal()"></td>
        <td>${selCatHtml(item.categoria_id||'')}</td>
        <td class="row-total text-sm font-bold" style="color:var(--primary)">R$ 0,00</td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removerLinha(${idx})">×</button></td>
    </tr>`;
}

function esc(s){ return String(s).replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

let nextIdx = 0;
function irPasso2(itens) {
    itensAtual = itens && itens.length ? itens : [{}];
    const tbody = document.getElementById('itensBody');
    tbody.innerHTML = '';
    nextIdx = 0;
    itensAtual.forEach(item => { tbody.innerHTML += renderLinha(item, nextIdx++); });
    recalcTotal();
    document.getElementById('passo1').classList.add('hidden');
    document.getElementById('passo2').classList.remove('hidden');
    document.getElementById('step1c').classList.remove('active'); document.getElementById('step1c').classList.add('done');
    document.getElementById('step2c').classList.add('active');
    document.getElementById('line1').classList.add('done');
}
function irPasso2Last() {
    document.getElementById('passo3').classList.add('hidden');
    document.getElementById('passo2').classList.remove('hidden');
}
function irPasso1() {
    document.getElementById('passo2').classList.add('hidden');
    document.getElementById('passo1').classList.remove('hidden');
}
function adicionarLinha() {
    document.getElementById('itensBody').innerHTML += renderLinha({}, nextIdx++);
}
function removerLinha(idx) {
    const row = document.getElementById('row'+idx);
    if (row) { row.remove(); recalcTotal(); }
}
function recalcTotal() {
    let total = 0;
    document.querySelectorAll('#itensBody tr').forEach(row => {
        const q = parseFloat(row.querySelector('.qtd-input')?.value||0);
        const p = parseFloat(row.querySelector('.preco-input')?.value||0);
        const t = q * p;
        const el = row.querySelector('.row-total');
        if (el) el.textContent = 'R$ ' + t.toLocaleString('pt-BR',{minimumFractionDigits:2});
        total += t;
    });
    document.getElementById('totalGeral').textContent = 'R$ ' + total.toLocaleString('pt-BR',{minimumFractionDigits:2});
}
function irPasso3() {
    const rows = document.querySelectorAll('#itensBody tr');
    let ok = true;
    rows.forEach(r => { if (!r.querySelector('[name="item_desc[]"]')?.value.trim()) ok = false; });
    if (!ok) { alert('Preencha a descrição de todos os itens.'); return; }
    document.getElementById('passo2').classList.add('hidden');
    document.getElementById('passo3').classList.remove('hidden');
    document.getElementById('step2c').classList.remove('active'); document.getElementById('step2c').classList.add('done');
    document.getElementById('step3c').classList.add('active');
    document.getElementById('line2').classList.add('done');
    document.getElementById('tipoOrigemSave').value = document.getElementById('tipoOrigem').value;
}
function atualizarCliente(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('fClienteId').value = opt.dataset.cliente || '';
}
</script>
</div></div></div>
<?php pageFoot(); ?>
