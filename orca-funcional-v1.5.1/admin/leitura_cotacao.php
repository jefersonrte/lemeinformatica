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
        $cot = $db->prepare('SELECT fornecedor_id, orcamento_id FROM cotacoes WHERE id=?');
        $cot->execute([$cotId]);
        $cot = $cot->fetch();
        if (!$cot) { setFlash('error','Cotação não encontrada.'); redirect(APP_URL.'/admin/leitura_cotacao.php'); }
        $orcId = (int) $cot['orcamento_id'];

        $db->beginTransaction();
        try {
            // Uma nova leitura substitui a anterior desta cotação (evita linhas duplicadas)
            $db->prepare('DELETE FROM cotacao_itens WHERE cotacao_id=?')->execute([$cotId]);
            $sth = $db->prepare('INSERT INTO cotacao_itens (cotacao_id,orcamento_item_id,descricao,unidade,quantidade,preco_unitario) VALUES (?,?,?,?,?,?)');
            $atualiza = $db->prepare('UPDATE orcamento_itens SET preco_cotado=?,fornecedor_id=? WHERE id=? AND orcamento_id=?');
            foreach ($descs as $i => $desc) {
                if (!trim($desc)) continue;
                $qtd   = \App\Domain\Orcamento\OrcamentoCalculator::decimal($qtds[$i] ?? 1);
                $preco = \App\Domain\Orcamento\OrcamentoCalculator::decimal($precos[$i] ?? 0);
                $oid   = (int)($orcItemIds[$i] ?? 0) ?: null;
                $sth->execute([$cotId, $oid, trim($desc), normalizarUnidade($uns[$i] ?? 'UN'), $qtd, $preco]);
                if ($oid && $preco > 0) {
                    $atualiza->execute([$preco, $cot['fornecedor_id'], $oid, $orcId]);
                }
            }
            $db->prepare("UPDATE cotacoes SET status='respondida',data_resposta=NOW() WHERE id=?")->execute([$cotId]);
            $tot = $db->prepare('SELECT COALESCE(SUM(total_cotado),0) FROM orcamento_itens WHERE orcamento_id=?');
            $tot->execute([$orcId]);
            $db->prepare('UPDATE orcamentos SET total_cotado=? WHERE id=?')->execute([$tot->fetchColumn(), $orcId]);
            $db->prepare("UPDATE orcamentos SET status='cotado' WHERE id=? AND status IN ('rascunho','aguardando_cotacao')")->execute([$orcId]);
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            error_log('[leitura_cotacao] ' . $exception);
            setFlash('error','Não foi possível salvar a leitura. Nenhum preço foi alterado.');
            redirect(APP_URL.'/admin/leitura_cotacao.php');
        }
        logAction('cotacao_lida','cotacoes',$cotId);
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
            <div class="dropzone" id="dzLeit">
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
                        <?= unidadeOptions($item['unidade'] ?? 'UN') ?></select></td>
                    <td><input type="number" name="item_qtd[]" value="<?= $item['quantidade'] ?>" step="any" class="form-control" style="width:90px;font-size:.85rem;padding:5px 7px"></td>
                    <td><input type="number" name="item_preco[]" value="<?= $item['preco_unitario'] ?? 0 ?>" step="any" class="form-control preco-leit" style="width:110px;font-size:.85rem;padding:5px 7px" onchange="calcLeit(this,<?= $item['quantidade'] ?>)"></td>
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
            <button type="button" class="btn btn-outline" onclick="exportarCsv()">Exportar como CSV</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
initDropzone('dzLeit','arquivoLeit');
function exportarCsv() {
    const cel = v => '"' + String(v).replace(/"/g, '""') + '"';
    const linhas = [['Descrição','Unidade','Quantidade','Preço unitário'].map(cel).join(';')];
    document.querySelectorAll('input[name="item_desc[]"]').forEach(function (desc) {
        const row = desc.closest('tr');
        const num = n => String(row.querySelector('[name="' + n + '"]').value || '0').replace('.', ',');
        linhas.push([desc.value, row.querySelector('[name="item_un[]"]').value, num('item_qtd[]'), num('item_preco[]')].map(cel).join(';'));
    });
    const blob = new Blob(['\ufeff' + linhas.join('\r\n')], {type: 'text/csv;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'leitura-cotacao.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}
function calcLeit(inp, qtd) {
    const row  = inp.closest('tr');
    const tot  = row.querySelector('.leit-total');
    if (tot) tot.textContent = 'R$ ' + (parseFloat(inp.value||0)*parseFloat(qtd||1)).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
}
</script>
</div></div></div>
<?php pageFoot(); ?>
