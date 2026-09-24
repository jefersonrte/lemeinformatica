<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function provisionAdminResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    provisionAdminResponse(405, ['ok' => false, 'erro' => 'Método não permitido.']);
}

$configuredKey = (string) \App\Support\Config::get('security.migration_key', '');
$receivedKey = (string) ($_SERVER['HTTP_X_MIGRATION_KEY'] ?? '');
if ($configuredKey === '' || $receivedKey === '' || !hash_equals($configuredKey, $receivedKey)) {
    provisionAdminResponse(401, ['ok' => false, 'erro' => 'Não autorizado.']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw === false ? '' : $raw, true);
if (!is_array($data)) {
    provisionAdminResponse(400, ['ok' => false, 'erro' => 'JSON inválido.']);
}

$name = trim((string) ($data['nome'] ?? 'Administrador Orçamentista'));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['senha'] ?? '');
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    provisionAdminResponse(422, [
        'ok' => false,
        'erro' => 'Informe nome, e-mail válido e senha com pelo menos 10 caracteres.',
    ]);
}

try {
    $db = getDB();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $statement = $db->prepare(
        "INSERT INTO usuarios (nome,email,senha,role,ativo,email_verificado) VALUES (?,?,?,'admin',1,1) "
        . "ON DUPLICATE KEY UPDATE nome=VALUES(nome), senha=VALUES(senha), role='admin', ativo=1, email_verificado=1"
    );
    $statement->execute([$name, $email, $hash]);
    $lookup = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $lookup->execute([$email]);
    $userId = (int) ($lookup->fetchColumn() ?: 0);

    provisionAdminResponse(200, [
        'ok' => true,
        'mensagem' => 'Administrador do Orçamentista provisionado.',
        'usuario' => ['id' => $userId, 'email' => $email, 'role' => 'admin'],
    ]);
} catch (Throwable $exception) {
    error_log('[orca-provision-admin] ' . $exception->getMessage());
    provisionAdminResponse(500, ['ok' => false, 'erro' => 'Falha ao provisionar o administrador.']);
}
