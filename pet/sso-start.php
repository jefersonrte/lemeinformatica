<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

apply_page_security_headers();
start_api_session();
$user = current_api_user();
if ($user === null) {
    $_SESSION['pet_sso_destination'] = ($_GET['next'] ?? '') === 'powerbi' ? 'powerbi' : 'pet';
    header('Location: ../login.php?next=pet_sso');
    exit;
}
$destination = ($_SESSION['pet_sso_destination'] ?? '') === 'powerbi' ? 'powerbi' : 'pet';
unset($_SESSION['pet_sso_destination']);

try {
    $conn = db();
    $conn->query('DELETE FROM pet_sso_tokens WHERE codigo_expira_em < DATE_SUB(NOW(), INTERVAL 1 DAY)');

    $userId = (int) $user['id'];
    $revoke = $conn->prepare(
        'UPDATE pet_sso_tokens SET revogado_em = NOW()
         WHERE usuario_id = ? AND revogado_em IS NULL AND token_expira_em < NOW()'
    );
    $revoke->bind_param('i', $userId);
    $revoke->execute();

    $code = bin2hex(random_bytes(32));
    $codeHash = hash('sha256', $code);
    $codeExpires = date('Y-m-d H:i:s', time() + 120);
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $insert = $conn->prepare(
        'INSERT INTO pet_sso_tokens (usuario_id, codigo_hash, codigo_expira_em, ip_criacao)
         VALUES (?, ?, ?, ?)'
    );
    $insert->bind_param('isss', $userId, $codeHash, $codeExpires, $ip);
    $insert->execute();

    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    $callback = 'https://lemesolucoesemti.com.br/pet/callback.php?code=' . rawurlencode($code);
    if ($destination === 'powerbi') {
        $callback .= '&next=powerbi';
    }
    header('Location: ' . $callback);
    exit;
} catch (Throwable $exception) {
    error_log('[PET SSO START] ' . $exception->getMessage());
    http_response_code(503);
    echo 'Nao foi possivel iniciar o acesso integrado.';
}
