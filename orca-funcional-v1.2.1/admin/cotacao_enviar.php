<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/mailer.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(APP_URL.'/admin/orcamentos.php');
verifyCsrf();

$db         = getDB();
$orcId      = (int)($_POST['orcamento_id'] ?? 0);
$canal      = $_POST['canal'] ?? 'email';
$prazo      = $_POST['prazo'] ?? date('Y-m-d', strtotime('+3 days'));
$complemento = trim($_POST['complemento'] ?? '');
$fornsPorCat = $_POST['fornecedores'] ?? []; // [catId => [fornId, ...]]

if (!$orcId) redirect(APP_URL.'/admin/orcamentos.php');

$orc = $db->prepare('SELECT o.*,ob.nome as obra,c.razao_social FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id JOIN clientes c ON c.id=o.cliente_id WHERE o.id=?');
$orc->execute([$orcId]);
$orc = $orc->fetch();
if (!$orc) redirect(APP_URL.'/admin/orcamentos.php');

// Coleta todos os fornecedores selecionados (único por fornecedor, independente de categoria)
$todosForns = [];
foreach ($fornsPorCat as $catId => $fornIds) {
    foreach ($fornIds as $fid) {
        $todosForns[(int)$fid][] = (int)$catId;
    }
}

if (!$todosForns) {
    setFlash('warning','Nenhum fornecedor selecionado.');
    redirect(APP_URL.'/admin/orcamento_detalhe.php?id='.$orcId);
}

// Busca itens do orçamento
$itens = $db->prepare('SELECT oi.*,cat.nome as categoria FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id=oi.categoria_id WHERE oi.orcamento_id=?');
$itens->execute([$orcId]);
$itens = $itens->fetchAll();

$erros  = [];
$enviados = 0;

foreach ($todosForns as $fornId => $catIds) {
    $forn = $db->prepare('SELECT * FROM fornecedores WHERE id=?');
    $forn->execute([$fornId]);
    $forn = $forn->fetch();
    if (!$forn) continue;

    // Filtra apenas itens das categorias deste fornecedor
    $itensDoForn = array_filter($itens, fn($i) => in_array((int)$i['categoria_id'], $catIds) || !$i['categoria_id']);

    if (!$itensDoForn) continue;

    $mensagem = \App\Domain\Cotacao\MensagemCotacao::montar($orc, $forn, $itensDoForn, $prazo, $complemento);

    // Verifica se já existe cotação ativa para este orçamento+fornecedor
    $jaExiste = $db->prepare("SELECT id FROM cotacoes WHERE orcamento_id=? AND fornecedor_id=? AND status NOT IN('recusada','respondida')");
    $jaExiste->execute([$orcId, $fornId]);
    $cotId = $jaExiste->fetchColumn();

    if (!$cotId) {
        $db->prepare('INSERT INTO cotacoes (orcamento_id,fornecedor_id,mensagem,canal_envio,status) VALUES (?,?,?,?,?)')
           ->execute([$orcId, $fornId, $mensagem, $canal, 'pendente']);
        $cotId = $db->lastInsertId();
    } else {
        $db->prepare('UPDATE cotacoes SET mensagem=?,canal_envio=?,status=? WHERE id=?')
           ->execute([$mensagem, $canal, 'pendente', $cotId]);
    }

    $enviouEmail = false;
    $enviouWa    = false;

    if (in_array($canal, ['email','ambos'])) {
        if ($forn['email']) {
            $ok = enviarEmail($forn['email'], $forn['nome'], $orc['titulo'], $mensagem);
            if ($ok) { $enviouEmail = true; }
            else $erros[] = "Falha ao enviar e-mail para {$forn['nome']}.";
        }
    }
    if (in_array($canal, ['whatsapp','ambos'])) {
        if ($forn['whatsapp']) {
            $ok = enviarWhatsapp($forn['whatsapp'], $mensagem);
            if ($ok) { $enviouWa = true; }
            else $erros[] = "Falha ao enviar WhatsApp para {$forn['nome']}.";
        }
    }

    if ($enviouEmail || $enviouWa || $canal === 'manual') {
        $db->prepare("UPDATE cotacoes SET status='enviada',data_envio=NOW() WHERE id=?")->execute([$cotId]);
        $enviados++;
    }
    logAction('cotacao_enviada','cotacoes',$cotId,$forn['nome']);
}

// Atualiza status do orçamento
$db->prepare("UPDATE orcamentos SET status='aguardando_cotacao' WHERE id=? AND status='rascunho'")->execute([$orcId]);

if ($erros) setFlash('warning', "Enviadas com {$enviados} sucesso(s). Erros: " . implode('; ', $erros));
else        setFlash('success', "$enviados cotação(ões) enviada(s) com sucesso.");

redirect(APP_URL.'/admin/orcamento_detalhe.php?id='.$orcId);
