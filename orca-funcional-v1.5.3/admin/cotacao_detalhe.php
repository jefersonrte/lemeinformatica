<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/mailer.php';

requireAdmin();
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL.'/admin/cotacoes.php');

$cot = $db->prepare("SELECT co.*,f.nome as fornecedor_nome,f.email as forn_email,f.whatsapp as forn_wa,
    o.titulo as orcamento,o.obra_id,ob.nome as obra FROM cotacoes co
    JOIN fornecedores f ON f.id=co.fornecedor_id
    JOIN orcamentos o ON o.id=co.orcamento_id
    JOIN obras ob ON ob.id=o.obra_id
    WHERE co.id=?");
$cot->execute([$id]);
$cot = $cot->fetch();
if (!$cot) redirect(APP_URL.'/admin/cotacoes.php');

// Registrar resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'resposta') {
        $resposta = trim($_POST['resposta'] ?? '');
        $precos  = $_POST['item_preco'] ?? [];
        $itemIds = $_POST['item_id']    ?? [];

        // Valida o arquivo antes de gravar qualquer coisa
        $arquivoNovo = null;
        if (isset($_FILES['arquivo_resp']) && $_FILES['arquivo_resp']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['arquivo_resp'];
            $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
            $permitidas = ['pdf', 'xlsx', 'xls', 'csv', 'xml', 'jpg', 'jpeg', 'png'];
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])
                || !in_array($ext, $permitidas, true) || $upload['size'] > UPLOAD_MAX_MB * 1024 * 1024) {
                setFlash('error', 'Arquivo de resposta inválido. Envie PDF, planilha, XML ou imagem de até ' . UPLOAD_MAX_MB . 'MB.');
                redirect(APP_URL . '/admin/cotacao_detalhe.php?id=' . $id);
            }
            $dir = rtrim(UPLOAD_DIR, '/') . '/cotacoes/';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                setFlash('error', 'Não foi possível preparar a pasta de arquivos.');
                redirect(APP_URL . '/admin/cotacao_detalhe.php?id=' . $id);
            }
            $arquivoNovo = 'cotacoes/cotacao_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($upload['tmp_name'], rtrim(UPLOAD_DIR, '/') . '/' . $arquivoNovo)) {
                setFlash('error', 'Falha ao salvar o arquivo de resposta.');
                redirect(APP_URL . '/admin/cotacao_detalhe.php?id=' . $id);
            }
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE cotacoes SET resposta=?,status='respondida',data_resposta=NOW() WHERE id=?")->execute([$resposta, $id]);
            if ($arquivoNovo !== null) {
                $db->prepare('UPDATE cotacoes SET arquivo_resp=? WHERE id=?')->execute([$arquivoNovo, $id]);
            }

            // Atualiza preços nos itens do orçamento (somente itens deste orçamento)
            $atualiza = $db->prepare('UPDATE orcamento_itens SET preco_cotado=?, fornecedor_id=? WHERE id=? AND orcamento_id=?');
            $limpa = $db->prepare('UPDATE orcamento_itens SET preco_cotado=NULL, fornecedor_id=NULL WHERE id=? AND orcamento_id=? AND fornecedor_id=?');
            foreach ($itemIds as $i => $iid) {
                $bruto = trim((string) ($precos[$i] ?? ''));
                $preco = \App\Domain\Orcamento\OrcamentoCalculator::decimal($bruto);
                if ($preco > 0) {
                    $atualiza->execute([$preco, $cot['fornecedor_id'], (int) $iid, $cot['orcamento_id']]);
                } elseif ($bruto === '') {
                    // Campo apagado: remove o preço somente se ele veio deste fornecedor
                    $limpa->execute([(int) $iid, $cot['orcamento_id'], $cot['fornecedor_id']]);
                }
            }

            $tot = $db->prepare('SELECT COALESCE(SUM(total_cotado),0) FROM orcamento_itens WHERE orcamento_id=?');
            $tot->execute([$cot['orcamento_id']]);
            $db->prepare('UPDATE orcamentos SET total_cotado=? WHERE id=?')->execute([$tot->fetchColumn(), $cot['orcamento_id']]);
            $db->prepare("UPDATE orcamentos SET status='cotado' WHERE id=? AND status IN ('rascunho','aguardando_cotacao')")
               ->execute([$cot['orcamento_id']]);
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            error_log('[cotacao_resposta] ' . $exception);
            setFlash('error', 'Não foi possível registrar a resposta. Nenhum preço foi alterado.');
            redirect(APP_URL . '/admin/cotacao_detalhe.php?id=' . $id);
        }
        logAction('cotacao_respondida', 'cotacoes', $id, $cot['fornecedor_nome']);
        setFlash('success','Resposta registrada e preços atualizados.');
        redirect(APP_URL.'/admin/cotacao_detalhe.php?id='.$id);
    } elseif ($acao === 'reenviar') {
        $canal = $_POST['canal_reenvio'] ?? 'email';
        if (!in_array($canal, ['email', 'whatsapp', 'ambos'], true)) $canal = 'email';
        $ok = false;
        if (in_array($canal,['email','ambos']) && $cot['forn_email']) {
            $ok = enviarEmail($cot['forn_email'], $cot['fornecedor_nome'], $cot['orcamento'], $cot['mensagem']) || $ok;
        }
        if (in_array($canal,['whatsapp','ambos']) && $cot['forn_wa']) {
            $ok = enviarWhatsapp($cot['forn_wa'], $cot['mensagem']) || $ok;
        }
        if ($ok) {
            // Uma cotação já respondida continua respondida; só registra o novo envio
            $db->prepare("UPDATE cotacoes SET status=IF(status IN ('pendente','enviada'),'enviada',status),data_envio=NOW(),canal_envio=? WHERE id=?")->execute([$canal,$id]);
            setFlash('success','Cotação reenviada.');
        } else {
            setFlash('error','Falha ao reenviar. Verifique dados do fornecedor e configurações SMTP/WhatsApp.');
        }
        redirect(APP_URL.'/admin/cotacao_detalhe.php?id='.$id);
    }
}

// Itens do orçamento
$itens = $db->prepare('SELECT oi.*,cat.nome as categoria FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id=oi.categoria_id WHERE oi.orcamento_id=?');
$itens->execute([$cot['orcamento_id']]);
$itens = $itens->fetchAll();

pageHead('Cotação — ' . sanitize($cot['fornecedor_nome']));
?>
<div class="layout">
<?php sidebar('cotacoes'); ?>
<div class="main">
<?php topbar('Cotação: ' . sanitize($cot['fornecedor_nome'])); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card mb-4">
    <div class="card-body">
        <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:12px">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <strong><?= sanitize($cot['fornecedor_nome']) ?></strong>
                    <?= statusBadge($cot['status']) ?>
                </div>
                <div class="text-sm text-muted">Orçamento: <?= sanitize($cot['orcamento']) ?> | Obra: <?= sanitize($cot['obra']) ?></div>
                <?php if ($cot['forn_email']): ?><div class="text-sm">📧 <?= sanitize($cot['forn_email']) ?></div><?php endif; ?>
                <?php if ($cot['forn_wa']): ?><div class="text-sm">📱 <a href="<?= waLink($cot['forn_wa'], $cot['mensagem']) ?>" target="_blank"><?= sanitize($cot['forn_wa']) ?></a></div><?php endif; ?>
            </div>
            <div class="flex gap-2" style="flex-wrap:wrap">
                <a href="<?= APP_URL ?>/admin/orcamento_detalhe.php?id=<?= $cot['orcamento_id'] ?>" class="btn btn-sm btn-outline">← Orçamento</a>
                <?php if ($cot['forn_wa']): ?>
                <a href="<?= waLink($cot['forn_wa'], $cot['mensagem']) ?>" target="_blank" class="btn btn-sm btn-success">WhatsApp</a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline" onclick="openModal('modalReenviar')">Reenviar</button>
            </div>
        </div>
    </div>
</div>

<div class="tabs" id="tabsCot">
    <button class="tab-link active" data-tab="tabMsg" onclick="switchTab('tabsCot','tabMsg')">Mensagem Enviada</button>
    <button class="tab-link" data-tab="tabResp" onclick="switchTab('tabsCot','tabResp')">Registrar Resposta</button>
</div>

<div id="tabMsg" class="tab-pane active">
    <div class="card">
        <div class="card-header"><h2>Mensagem de Cotação</h2></div>
        <div class="card-body">
            <pre style="white-space:pre-wrap;font-family:var(--font);font-size:.875rem;line-height:1.7;color:var(--neutral-700)"><?= sanitize($cot['mensagem']) ?></pre>
        </div>
    </div>
</div>

<div id="tabResp" class="tab-pane">
    <div class="card">
        <div class="card-header"><h2>Registrar Resposta do Fornecedor</h2></div>
        <form method="post" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="resposta">
                <div class="form-group">
                    <label class="form-label">Texto da Resposta</label>
                    <textarea name="resposta" class="form-control" rows="4"><?= sanitize($cot['resposta'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Arquivo de Resposta (PDF/Excel/XML)</label>
                    <input type="file" name="arquivo_resp" class="form-control" accept=".pdf,.xlsx,.xls,.csv,.xml">
                    <?php if ($cot['arquivo_resp']): ?>
                    <p class="form-text">Arquivo atual: <a href="<?= APP_URL ?>/admin/cotacao_arquivo.php?id=<?= $id ?>" target="_blank"><?= sanitize(basename($cot['arquivo_resp'])) ?></a></p>
                    <?php endif; ?>
                </div>

                <h3 style="font-size:.9rem;font-weight:700;margin:20px 0 12px">Atualizar Preços Cotados:</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Descrição</th><th>Categ.</th><th>Unid.</th><th>Qtd</th><th>Preço Cotado (R$)</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($itens as $item): ?>
                        <tr>
                            <td><input type="hidden" name="item_id[]" value="<?= $item['id'] ?>"><?= sanitize($item['descricao']) ?></td>
                            <td class="text-xs"><?= sanitize($item['categoria'] ?? '—') ?></td>
                            <td class="text-sm"><?= sanitize($item['unidade']) ?></td>
                            <td class="text-sm"><?= number_format($item['quantidade'],3,',','.') ?></td>
                            <td><input type="number" name="item_preco[]" step="any" min="0" class="form-control preco-resp" style="width:120px;padding:5px 8px;font-size:.85rem" value="<?= $item['preco_cotado'] ?? '' ?>" onchange="calcTotalResp(this)"></td>
                            <td class="text-sm font-bold total-resp-col">—</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="padding:16px 24px">
                <button type="submit" class="btn btn-success btn-lg">Salvar Resposta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reenviar -->
<div class="modal-overlay" id="modalReenviar">
    <div class="modal" style="max-width:400px">
        <div class="modal-header"><h3>Reenviar Cotação</h3><button class="btn-close" onclick="closeModal('modalReenviar')">✕</button></div>
        <form method="post">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <input type="hidden" name="acao" value="reenviar">
                <div class="form-group">
                    <label class="form-label">Canal</label>
                    <select name="canal_reenvio" class="form-control">
                        <option value="email">E-mail</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="ambos">Ambos</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalReenviar')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Reenviar</button>
            </div>
        </form>
    </div>
</div>

<script>
const QTD_MAP = <?= json_encode(array_combine(array_column($itens,'id'), array_column($itens,'quantidade'))) ?>;
function calcTotalResp(inp) {
    const row = inp.closest('tr');
    const id  = row.querySelector('[name="item_id[]"]').value;
    const qtd = parseFloat(QTD_MAP[id] || 0);
    const prc = parseFloat(inp.value || 0);
    const tot = row.querySelector('.total-resp-col');
    if (tot) tot.textContent = 'R$ ' + (qtd * prc).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
}
// Inicializa totais na carga
document.querySelectorAll('.preco-resp').forEach(inp => { if (inp.value) calcTotalResp(inp); });
</script>

</div></div></div>
<?php pageFoot(); ?>
