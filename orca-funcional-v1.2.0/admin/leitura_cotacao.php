<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/file_import.php';

requireAdmin();
$db = getDB();

$itens      = [];
$importErro = '';
$cotacaoId  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'importar') {
    verifyCsrf();
    $cotacaoId = (int)($_POST['cotacao_id'] ?? 0);
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $itens = importarArquivo($_FILES['arquivo']);
        if (!$itens) $importErro = 'Nenhum item extraído. Verifique o formato do arquivo.';
    } else {
        $importErro = 'Selecione um arquivo.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_resp') {
    verifyCsrf();
    $cotId     = (int)($_POST['cotacao_id'] ?? 0);
    $descs     = $_POST['item_desc']   ?? [];
    $uns       = $_POST['item_un']     ?? [];
    $qtds      = $_POST['item_qtd']    ?? [];
    $precos    = $_POST['item_preco']  ?? [];
    $orcItemIds = $_POST['orcamento_item_id'] ?? [];

    if ($cotId) {
        // Salva itens na cotacao_itens
        $sth = $db->prepare('INSERT INTO cotacao_itens (cotacao_id,orcamento_item_id,descricao,unidade,quantidade,preco_unitario) VALUES (?,?,?,?,?,?)');
        foreach ($descs as $i => $desc) {
            if (!trim($desc)) continue;
            $qtd   = (float)str_replace(',','.',$qtds[$i] ?? 1);
            $preco = (float)str_replace(',','.',$precos[$i] ?? 0);
            $oid   = (int)($orcItemIds[$i] ?? 0) ?: null;
            $sth->execute([$cotId, $oid, trim($desc), $uns[$i]??'UN', $qtd, $preco]);
            // Actualiza preco_cotado no item do orçamento se vinculado
            if ($oid && $preco > 0) {
                $cot = $db->prepare('SELECT fornecedor_id FROM cotacoes WHERE id=?'); $cot->execute([$cotId]);
                $fornId = $cot->fetchColumn();
                $db->prepare('UPDATE orcamento_itens SET preco_cotado=?,fornecedor_id=? WHERE id=?')->execute([$preco,$fornId,$oid]);
            }
        }
        // Marca cotação como respondida
        $db->prepare("UPDATE cotacoes SET status='respondida',data_resposta=NOW() WHERE id=?")->execute([$cotId]);
        // Recalcula total cotado no orçamento
        $orcId = $db->prepare('SELECT orcamento_id FROM cotacoes WHERE id=?'); $orcId->execute([$cotId]);
        $orcId = $orcId->fetchColumn();
        if ($orcId) {
            $tot = $db->prepare('SELECT COALESCE(SUM(total_cotado),0) FROM orcamento_itens WHERE orcamento_id=?');
            $tot->execute([$orcId]);
            $db->prepare('UPDATE orcamentos SET total_cotado=?,status=? WHERE id=?')->execute([$tot->fetchColumn(),'cotado',$orcId]);
        }
        setFlash('success','Resposta da cotação registrada.');
        redirect(APP_URL.'/admin/cotacao_detalhe.php?id='.$cotId);
    }
}

// Cotações abertas para vínculo
$cotacoes = $db->prepare("SELECT co.id,co.orcamento_id,f.nome as fornecedor,o.titulo FROM cotacoes co JOIN fornecedores f ON f.id=co.fornecedor_id JOIN orcamentos o ON o.id=co.orcamento_id WHERE co.status IN('enviada','pendente') ORDER BY co.criado_em DESC");
$cotacoes->execute();
$cotacoes = $cotacoes->fetchAll();

// Itens do orçamento para match automático (se cotação selecionada)
$orcItens = [];
if ($cotacaoId) {
    $ci = $db->prepare('SELECT orcamento_id FROM cotacoes WHERE id=?'); $ci->execute([$cotacaoId]);
    $oid = $ci->fetchColumn();
    if ($oid) {
        $oi = $db->prepare('SELECT oi.*,cat.nome as categoria FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id=oi.categoria_id WHERE oi.orcamento_id=?');
        $oi->execute([$oid]);
        $orcItens = $oi->fetchAll();
    }
}

pageHead('Leitora de Cotação');
?>
<div class="layout">
<?php sidebar('leitura'); ?>
<div class="main">
<?php topbar('Leitora de Cotação (Resposta do Fornecedor)'); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card mb-4">
    <div class="card-header"><h2>Importar Resposta de Cotação</h2></div>
    <div class="card-body">
        <div class="alert alert-info">
            Receba PDF, Excel ou XML enviado pelo fornecedor. O sistema extrai os itens e permite vincular aos itens do orçamento.
        </div>
        <?php if ($importErro): ?><div class="alert alert-error"><?= sanitize($importErro) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <input type="hidden" name="acao" value="importar">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Vincular à Cotação (opcional)</label>
                    <select name="cotacao_id" class="form-control">
                        <option value="">Selecione...</option>
                        <?php foreach ($cotacoes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cotacaoId==$c['id']?'selected':'' ?>>
                            <?= sanitize($c['titulo']) ?> — <?= sanitize($c['fornecedor']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="dropzone" id="dzLeit" onclick="document.getElementById('arquivoLeit').click()">
                <div class="dz-icon">📥</div>
                <p><span>Selecionar resposta</span> do fornecedor (PDF, Excel, CSV, XML)</p>
            </div>
            <input type="file" id="arquivoLeit" name="arquivo" style="display:none" accept=".pdf,.xlsx,.xls,.csv,.xml,.ods">
            <button type="submit" class="btn btn-primary mt-3">Extrair Itens</button>
        </form>
    </div>
</div>

<?php if ($itens): ?>
<div class="card">
    <div class="card-header">
        <h2>Itens Extraídos (<?= count($itens) ?>)</h2>
        <span class="text-sm text-muted">Ajuste os preços e vincule ao orçamento</span>
    </div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="acao" value="salvar_resp">
        <input type="hidden" name="cotacao_id" value="<?= $cotacaoId ?>">
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Descrição</th><th>Unid.</th><th>Qtd</th><th>Preço Unit. (R$)</th><th>Total</th>
                    <?php if ($orcItens): ?><th>Vincular ao Item do Orçamento</th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($itens as $i => $item): ?>
                <tr>
                    <td><input type="text" name="item_desc[]" value="<?= sanitize($item['descricao']) ?>" class="form-control" style="min-width:180px;font-size:.85rem;padding:5px 7px"></td>
                    <td><select name="item_un[]" class="form-control" style="width:80px;font-size:.8rem;padding:4px 6px">
                        <?php foreach (['UN','M','M²','M³','KG','CX','PC','RL','SC','L','GL','KIT','VB'] as $u): ?>
                        <option <?= strtoupper($item['unidade']??'UN')===$u?'selected':'' ?>><?= $u ?></option>
                        <?php endforeach; ?></select></td>
                    <td><input type="number" name="item_qtd[]" value="<?= $item['quantidade'] ?>" step="0.001" class="form-control" style="width:90px;font-size:.85rem;padding:5px 7px"></td>
                    <td><input type="number" name="item_preco[]" value="<?= $item['preco_unitario'] ?? 0 ?>" step="0.01" class="form-control preco-leit" style="width:110px;font-size:.85rem;padding:5px 7px" onchange="calcLeit(this,<?= $item['quantidade'] ?>)"></td>
                    <td class="text-sm font-bold leit-total" style="color:var(--primary)">R$ <?= number_format(($item['quantidade']??1)*($item['preco_unitario']??0),2,',','.') ?></td>
                    <?php if ($orcItens): ?>
                    <td>
                        <select name="orcamento_item_id[]" class="form-control" style="font-size:.8rem;padding:4px 6px">
                            <option value="">— Não vincular</option>
                            <?php foreach ($orcItens as $oi): ?>
                            <option value="<?= $oi['id'] ?>"><?= sanitize(mb_substr($oi['descricao'],0,50)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <?php else: ?>
                    <input type="hidden" name="orcamento_item_id[]" value="">
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--neutral-200)">
            <?php if ($cotacaoId): ?>
            <button type="submit" class="btn btn-success btn-lg">Salvar e Vincular ao Orçamento</button>
            <?php else: ?>
            <a href="#" class="btn btn-outline" onclick="exportarCsv()">Exportar como CSV</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
initDropzone('dzLeit','arquivoLeit');
function calcLeit(inp, qtd) {
    const row  = inp.closest('tr');
    const tot  = row.querySelector('.leit-total');
    if (tot) tot.textContent = 'R$ ' + (parseFloat(inp.value||0)*parseFloat(qtd||1)).toLocaleString('pt-BR',{minimumFractionDigits:2});
}
</script>
</div></div></div>
<?php pageFoot(); ?>
