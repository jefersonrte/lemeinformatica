<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../helpers/mailer.php';

use App\Domain\Cotacao\MensagemCotacao;

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(APP_URL.'/admin/orcamentos.php');

// O painel de cotação (orcamento_detalhe) conversa em JSON; o formulário clássico recebe redirect.
$json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
if (!verifyCsrf(!$json)) jsonResponse(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 403);

$db         = getDB();
$orcId      = (int)($_POST['orcamento_id'] ?? 0);
$canal      = $_POST['canal'] ?? 'email';
if (!in_array($canal, ['email', 'whatsapp', 'ambos', 'manual'], true)) $canal = 'email';
$prazo      = (string) ($_POST['prazo'] ?? date('Y-m-d', strtotime('+3 days')));
$complemento = trim((string) ($_POST['complemento'] ?? ''));
$fornsPorCat = (array) ($_POST['fornecedores'] ?? []); // modo por categoria: [catId => [fornId, ...]]
$itemIds     = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['itens'] ?? [])))));
$fornIds     = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['fornecedor_ids'] ?? [])))));
$previa      = !empty($_POST['previa']);

$falhar = static function (string $mensagem, string $tipo = 'warning') use ($json, $orcId): never {
    if ($json) jsonResponse(['ok' => false, 'erro' => $mensagem], 422);
    setFlash($tipo, $mensagem);
    redirect(APP_URL . ($orcId ? '/admin/orcamento_detalhe.php?id=' . $orcId : '/admin/orcamentos.php'));
};

if (!$orcId) $falhar('Orçamento não informado.', 'error');

$orc = $db->prepare('SELECT o.*,ob.nome as obra,c.razao_social FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id JOIN clientes c ON c.id=o.cliente_id WHERE o.id=?');
$orc->execute([$orcId]);
$orc = $orc->fetch();
if (!$orc) $falhar('Orçamento não encontrado.', 'error');

$itens = $db->prepare('SELECT oi.*,cat.nome as categoria FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id=oi.categoria_id WHERE oi.orcamento_id=? ORDER BY oi.ordem, oi.id');
$itens->execute([$orcId]);
$itens = $itens->fetchAll();

// Destinos: fornecedor => itens que ele deve cotar.
$destinos = [];
if ($itemIds || $fornIds) {
    // Modo painel: os mesmos itens escolhidos vão para cada fornecedor escolhido.
    $escolhidos = array_values(array_filter($itens, static fn (array $i): bool => in_array((int) $i['id'], $itemIds, true)));
    if (!$escolhidos) $falhar('Selecione ao menos um item do orçamento.');
    if (!$fornIds) $falhar('Selecione ao menos um fornecedor.');
    foreach ($fornIds as $fid) $destinos[$fid] = $escolhidos;
} else {
    // Modo clássico por categoria (mantido para compatibilidade).
    foreach ($fornsPorCat as $catId => $ids) {
        foreach ((array) $ids as $fid) {
            $destinos[(int) $fid]['cats'][] = (int) $catId;
        }
    }
    foreach ($destinos as $fid => $d) {
        $cats = $d['cats'];
        $destinos[$fid] = array_values(array_filter($itens, static fn (array $i): bool => in_array((int) $i['categoria_id'], $cats, true) || !$i['categoria_id']));
    }
    $destinos = array_filter($destinos);
    if (!$destinos) $falhar('Nenhum fornecedor selecionado.');
}

$buscaForn = $db->prepare('SELECT * FROM fornecedores WHERE id=? AND ativo=1');

if ($previa) {
    $fid = (int) array_key_first($destinos);
    $buscaForn->execute([$fid]);
    $forn = $buscaForn->fetch() ?: ['nome' => 'Fornecedor'];
    jsonResponse(['ok' => true, 'mensagem' => MensagemCotacao::montar($orc, $forn, $destinos[$fid], $prazo, $complemento)]);
}

$erros = [];
$enviados = 0;
$resultados = [];
$gravaItem = $db->prepare('INSERT INTO cotacao_itens (cotacao_id,orcamento_item_id,descricao,unidade,quantidade) VALUES (?,?,?,?,?)');

foreach ($destinos as $fornId => $itensDoForn) {
    $buscaForn->execute([$fornId]);
    $forn = $buscaForn->fetch();
    if (!$forn || !$itensDoForn) continue;

    $mensagem = MensagemCotacao::montar($orc, $forn, $itensDoForn, $prazo, $complemento);

    $db->beginTransaction();
    try {
        // Reaproveita cotação ativa deste orçamento+fornecedor
        $jaExiste = $db->prepare("SELECT id FROM cotacoes WHERE orcamento_id=? AND fornecedor_id=? AND status NOT IN('recusada','respondida','aceita')");
        $jaExiste->execute([$orcId, $fornId]);
        $cotId = (int) $jaExiste->fetchColumn();
        if (!$cotId) {
            $db->prepare('INSERT INTO cotacoes (orcamento_id,fornecedor_id,mensagem,canal_envio,status) VALUES (?,?,?,?,?)')
               ->execute([$orcId, $fornId, $mensagem, $canal, 'pendente']);
            $cotId = (int) $db->lastInsertId();
        } else {
            $db->prepare('UPDATE cotacoes SET mensagem=?,canal_envio=?,status=? WHERE id=?')
               ->execute([$mensagem, $canal, 'pendente', $cotId]);
        }
        // Registra exatamente quais itens foram pedidos (a resposta mostra só esses).
        $db->prepare('DELETE FROM cotacao_itens WHERE cotacao_id=? AND preco_unitario IS NULL')->execute([$cotId]);
        foreach ($itensDoForn as $item) {
            $gravaItem->execute([$cotId, (int) $item['id'], mb_substr((string) $item['descricao'], 0, 255), $item['unidade'], $item['quantidade']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[cotacao_enviar] ' . $e);
        $erros[] = "Não foi possível registrar a cotação de {$forn['nome']}.";
        $resultados[] = ['fornecedor' => $forn['nome'], 'ok' => false, 'detalhe' => 'Erro ao registrar a cotação.'];
        continue;
    }

    $enviouEmail = false;
    $enviouWa    = false;
    $avisos = [];

    $semContato = ($canal === 'email' && !$forn['email'])
        || ($canal === 'whatsapp' && !$forn['whatsapp'])
        || ($canal === 'ambos' && !$forn['email'] && !$forn['whatsapp']);
    if ($semContato) {
        $erros[] = "{$forn['nome']} não tem contato cadastrado para o canal escolhido (cotação ficou pendente).";
        $avisos[] = 'Sem contato para o canal escolhido';
    }

    if (in_array($canal, ['email','ambos'], true) && $forn['email']) {
        $enviouEmail = enviarEmail($forn['email'], $forn['nome'], $orc['titulo'], $mensagem);
        if (!$enviouEmail) { $erros[] = "Falha ao enviar e-mail para {$forn['nome']}."; $avisos[] = 'E-mail não enviado'; }
    }
    if (in_array($canal, ['whatsapp','ambos'], true) && $forn['whatsapp']) {
        $enviouWa = enviarWhatsapp($forn['whatsapp'], $mensagem);
        if (!$enviouWa) { $erros[] = "Falha ao enviar WhatsApp para {$forn['nome']}."; $avisos[] = 'WhatsApp automático indisponível — use o botão para abrir a conversa'; }
    }

    $ok = $enviouEmail || $enviouWa || $canal === 'manual';
    if ($ok) {
        $db->prepare("UPDATE cotacoes SET status='enviada',data_envio=NOW() WHERE id=?")->execute([$cotId]);
        $enviados++;
    }
    logAction('cotacao_enviada','cotacoes',$cotId,$forn['nome']);
    $feitos = array_filter([$enviouEmail ? 'e-mail enviado' : '', $enviouWa ? 'WhatsApp enviado' : '', $canal === 'manual' ? 'mensagem gerada' : '']);
    $resultados[] = [
        'fornecedor' => $forn['nome'],
        'ok' => $ok,
        'detalhe' => count($itensDoForn) . ' ' . (count($itensDoForn) === 1 ? 'item' : 'itens') . ($feitos ? ' · ' . implode(' e ', $feitos) : '') . ($avisos ? ' · ' . implode('; ', $avisos) : ''),
        'whatsapp' => MensagemCotacao::linkWhatsapp($forn['whatsapp'] ?? null, $mensagem),
        'email' => $forn['email'] ? 'mailto:' . rawurlencode($forn['email']) . '?subject=' . rawurlencode('Cotação: ' . $orc['titulo']) . '&body=' . rawurlencode($mensagem) : null,
        'mensagem' => $mensagem,
        'url' => APP_URL . '/admin/cotacao_detalhe.php?id=' . $cotId,
    ];
}

// Atualiza status do orçamento
if ($enviados > 0) {
    $db->prepare("UPDATE orcamentos SET status='aguardando_cotacao' WHERE id=? AND status='rascunho'")->execute([$orcId]);
}

if ($json) {
    jsonResponse(['ok' => true, 'enviados' => $enviados, 'resultados' => $resultados, 'erros' => $erros]);
}

if ($erros) setFlash('warning', "Enviadas com {$enviados} sucesso(s). Erros: " . implode('; ', $erros));
else        setFlash('success', "$enviados cotação(ões) enviada(s) com sucesso.");

redirect(APP_URL.'/admin/orcamento_detalhe.php?id='.$orcId);
