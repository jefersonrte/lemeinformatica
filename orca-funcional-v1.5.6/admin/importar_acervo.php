<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Domain\Obra\AcervoPacoteImporter;

requireAdmin();
$db = getDB();
$pasta = rtrim(UPLOAD_DIR, '/') . '/acervo';
$pacote = $pasta . '/pacote.zip';
$importador = new AcervoPacoteImporter($db, UPLOAD_DIR, max(UPLOAD_MAX_MB, 80));
@set_time_limit(300);

// Leituras em JSON para o cadastro "um por um" pelas telas (dados do pacote e conferência do que foi gravado)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['json'])) {
    try {
        if ($_GET['json'] === 'manifesto') {
            jsonResponse(['ok' => true] + $importador->manifesto($pacote));
        }
        if ($_GET['json'] === 'consulta') {
            $cliente = $db->prepare('SELECT c.id FROM clientes c JOIN usuarios u ON u.id = c.usuario_id WHERE u.email = ? LIMIT 1');
            $cliente->execute([(string) ($_GET['email'] ?? '')]);
            $clienteId = (int) $cliente->fetchColumn();
            $obraId = 0;
            $orcamentos = [];
            if ($clienteId > 0 && ($_GET['obra'] ?? '') !== '') {
                $obra = $db->prepare('SELECT id FROM obras WHERE cliente_id = ? AND nome = ? ORDER BY id LIMIT 1');
                $obra->execute([$clienteId, mb_substr((string) $_GET['obra'], 0, 200)]);
                $obraId = (int) $obra->fetchColumn();
            }
            if ($obraId > 0) {
                $lista = $db->prepare('SELECT o.id, o.titulo, o.status, o.total_estimado, o.bdi_percentual, (SELECT COUNT(*) FROM orcamento_itens i WHERE i.orcamento_id = o.id) itens FROM orcamentos o WHERE o.obra_id = ? ORDER BY o.id');
                $lista->execute([$obraId]);
                $orcamentos = $lista->fetchAll(PDO::FETCH_ASSOC);
            }
            jsonResponse(['ok' => true, 'cliente_id' => $clienteId, 'obra_id' => $obraId, 'orcamentos' => $orcamentos]);
        }
        throw new InvalidArgumentException('Consulta desconhecida.');
    } catch (Throwable $exception) {
        jsonResponse(['ok' => false, 'erro' => $exception->getMessage()], 422);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if (!is_dir($pasta) && !mkdir($pasta, 0750, true) && !is_dir($pasta)) {
            throw new RuntimeException('Não foi possível preparar a pasta do pacote.');
        }
        if ($acao === 'parte') {
            // Pacote enviado em partes (o limite de upload do servidor é menor que o pacote)
            $indice = (int) ($_POST['indice'] ?? -1);
            $total = (int) ($_POST['total'] ?? 0);
            $parte = $_FILES['parte'] ?? null;
            if ($indice < 0 || $total <= 0 || $indice >= $total || !$parte || $parte['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($parte['tmp_name'])) {
                throw new InvalidArgumentException('Parte do pacote inválida.');
            }
            $temporario = $pacote . '.parte';
            $esperado = $indice === 0 ? 0 : (int) ($_SESSION['acervo_upload'] ?? -1);
            if ($indice !== $esperado) {
                throw new InvalidArgumentException('Partes fora de ordem: envie o pacote novamente.');
            }
            $destino = fopen($temporario, $indice === 0 ? 'wb' : 'ab');
            $origem = fopen($parte['tmp_name'], 'rb');
            stream_copy_to_stream($origem, $destino);
            fclose($origem);
            fclose($destino);
            $_SESSION['acervo_upload'] = $indice + 1;
            if ($indice + 1 < $total) {
                jsonResponse(['ok' => true, 'recebidas' => $indice + 1]);
            }
            rename($temporario, $pacote);
            unset($_SESSION['acervo_upload']);
            $manifesto = $importador->manifesto($pacote);
            logAction('acervo_pacote', 'obras', 0, 'Pacote do acervo recebido: ' . count($manifesto['obras']) . ' obras');
            jsonResponse(['ok' => true, 'concluido' => true, 'obras' => count($manifesto['obras'])]);
        }
        if ($acao === 'processar') {
            $indice = (int) ($_POST['indice'] ?? 0);
            $resultado = $importador->importarObra($pacote, $indice, currentUserId());
            if ($resultado['orcamentos'] > 0 || $resultado['plantas'] > 0) {
                logAction('acervo_obra', 'obras', $resultado['obra_id'], $resultado['obra'] . ' (acervo)');
            }
            jsonResponse(['ok' => true] + $resultado);
        }
        if ($acao === 'descartar') {
            @unlink($pacote);
            @unlink($pacote . '.parte');
            setFlash('success', 'Pacote removido do servidor. As obras já importadas continuam no sistema.');
            redirect(APP_URL . '/admin/importar_acervo.php');
        }
        throw new InvalidArgumentException('Ação desconhecida.');
    } catch (Throwable $exception) {
        error_log('[importar_acervo] ' . $exception);
        if (in_array($acao, ['parte', 'processar'], true)) {
            jsonResponse(['ok' => false, 'erro' => $exception->getMessage()], 422);
        }
        setFlash('error', $exception->getMessage());
        redirect(APP_URL . '/admin/importar_acervo.php');
    }
}

$manifesto = null;
$erroPacote = null;
if (is_file($pacote)) {
    try {
        $manifesto = $importador->manifesto($pacote);
    } catch (Throwable $exception) {
        $erroPacote = $exception->getMessage();
    }
}
$doAcervo = $db->query(
    "SELECT COUNT(DISTINCT o.id) obras, COUNT(DISTINCT orc.id) orcamentos FROM obras o JOIN clientes c ON c.id = o.cliente_id "
    . "JOIN usuarios u ON u.id = c.usuario_id LEFT JOIN orcamentos orc ON orc.obra_id = o.id WHERE u.email LIKE 'acervo+%@orca.local'"
)->fetch();
$plantasAcervo = (int) $db->query(
    "SELECT COUNT(*) FROM obra_plantas p JOIN obras o ON o.id = p.obra_id JOIN clientes c ON c.id = o.cliente_id "
    . "JOIN usuarios u ON u.id = c.usuario_id WHERE u.email LIKE 'acervo+%@orca.local'"
)->fetchColumn();

pageHead('Importar acervo');
?>
<div class="layout">
<?php sidebar('referencias'); ?>
<div class="main">
<?php topbar('Importar acervo de obras'); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card mb-4">
    <div class="card-body">
        <p class="text-sm">Traz para o sistema as obras de exemplo do acervo: <strong>clientes fictícios</strong>, obras, orçamentos reais (itens, quantidades e preços originais) e as plantas de cada obra. O pacote é gerado no computador que tem as planilhas com <code>php scripts/acervo.php pacote acervo.zip</code>.</p>
        <p class="text-sm mt-2">Já no sistema: <strong><?= (int) $doAcervo['obras'] ?></strong> obras do acervo, <strong><?= (int) $doAcervo['orcamentos'] ?></strong> orçamentos e <strong><?= $plantasAcervo ?></strong> plantas. Reenviar o mesmo pacote só completa o que faltar.</p>
    </div>
</div>

<div class="card mb-4" id="importarAcervo" data-csrf="<?= csrf() ?>" data-url="<?= APP_URL ?>/admin/importar_acervo.php" data-base="<?= APP_URL ?>" data-obras="<?= $manifesto ? count($manifesto['obras']) : 0 ?>">
    <div class="card-header"><h2>1. Enviar o pacote</h2></div>
    <div class="card-body">
        <div class="form-group">
            <label class="form-label" for="arquivoPacote">Pacote do acervo (.zip)</label>
            <input type="file" id="arquivoPacote" class="form-control" accept=".zip">
            <p class="form-text">Enviado em partes de 4 MB; pacotes de centenas de MB funcionam, mas mantenha a página aberta até terminar.</p>
        </div>
        <label class="text-sm" style="display:block;margin-bottom:8px"><input type="checkbox" id="modoTelas" checked> Cadastrar um por um pelas telas (cliente → obra → orçamentos, conferindo cada total) e depois anexar as plantas</label>
        <button type="button" class="btn btn-primary" id="enviarPacote">Enviar e importar</button>
        <?php if ($manifesto): ?>
        <button type="button" class="btn btn-outline" id="processarPacote">Importar o pacote já enviado (<?= count($manifesto['obras']) ?> obras, gerado em <?= sanitize(date('d/m/Y H:i', strtotime((string) $manifesto['gerado_em']))) ?>)</button>
        <button type="button" class="btn btn-outline" id="cadastrarPelasTelas" title="Cadastra cliente, obra e cada orçamento pelos formulários do sistema, conferindo os totais, e depois anexa as plantas">Cadastrar um por um pelas telas</button>
        <?php elseif ($erroPacote): ?>
        <p class="text-sm text-danger mt-2"><?= sanitize($erroPacote) ?></p>
        <?php endif; ?>
        <div class="mt-3" id="progressoAcervo" hidden>
            <div class="text-sm" id="etapaAcervo"></div>
            <progress id="barraAcervo" max="100" value="0" style="width:100%"></progress>
            <ol class="text-xs mt-2" id="logAcervo" style="max-height:320px;overflow:auto"></ol>
        </div>
    </div>
</div>

<?php if ($manifesto || $erroPacote): ?>
<form method="post" class="mb-4">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
    <input type="hidden" name="acao" value="descartar">
    <button class="btn btn-sm btn-outline" type="submit">Remover o pacote do servidor</button>
</form>
<?php endif; ?>

</div></div></div>
<?php pageFoot([APP_URL . '/assets/js/importar-acervo.js?v=' . rawurlencode(APP_VERSION)]); ?>
