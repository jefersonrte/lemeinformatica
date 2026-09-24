<?php
// Cadastro rápido de fornecedor (JSON) usado pelo painel de cotação sem sair do orçamento.
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['ok' => false, 'erro' => 'Método não permitido.'], 405);
if (!verifyCsrf(false)) jsonResponse(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 403);

$nome = trim(mb_substr((string) ($_POST['nome'] ?? ''), 0, 180));
$email = trim(mb_substr((string) ($_POST['email'] ?? ''), 0, 180));
$whatsapp = trim(mb_substr((string) ($_POST['whatsapp'] ?? ''), 0, 20));
if ($nome === '') jsonResponse(['ok' => false, 'erro' => 'Informe o nome do fornecedor.'], 422);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['ok' => false, 'erro' => 'E-mail inválido.'], 422);
if ($email === '' && $whatsapp === '') jsonResponse(['ok' => false, 'erro' => 'Informe e-mail ou WhatsApp para poder enviar a cotação.'], 422);

$db = getDB();
$existe = $db->prepare('SELECT id, nome, email, whatsapp FROM fornecedores WHERE ativo=1 AND (nome=? OR (email<>\'\' AND email=?)) LIMIT 1');
$existe->execute([$nome, $email]);
if ($f = $existe->fetch()) {
    jsonResponse(['ok' => true, 'existente' => true, 'fornecedor' => ['id' => (int) $f['id'], 'nome' => $f['nome'], 'email' => $f['email'], 'whatsapp' => $f['whatsapp']]]);
}
$db->prepare('INSERT INTO fornecedores (nome,email,whatsapp,telefone) VALUES (?,?,?,?)')->execute([$nome, $email ?: null, $whatsapp ?: null, $whatsapp ?: null]);
$id = (int) $db->lastInsertId();
logAction('fornecedor_criado', 'fornecedores', $id, $nome . ' (cadastro rápido)');
jsonResponse(['ok' => true, 'fornecedor' => ['id' => $id, 'nome' => $nome, 'email' => $email, 'whatsapp' => $whatsapp]]);
