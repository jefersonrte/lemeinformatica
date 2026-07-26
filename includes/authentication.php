<?php
if (!defined('LOGIN_MAX_ATTEMPTS')) {
    define('LOGIN_MAX_ATTEMPTS', 5);
}
if (!defined('LOGIN_LOCK_MINUTES')) {
    define('LOGIN_LOCK_MINUTES', 15);
}

function auth_identifier(string $email): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', strtolower(trim($email)) . '|' . $ip);
}

function auth_recent_failures(string $identifier): int
{
    $since = date('Y-m-d H:i:s', time() - (LOGIN_LOCK_MINUTES * 60));
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS total
         FROM auth_login_attempts
         WHERE identificador = ? AND sucesso = 0 AND criado_em >= ?'
    );
    $stmt->bind_param('ss', $identifier, $since);
    $stmt->execute();

    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

function auth_record_attempt(string $identifier, string $email, bool $success): void
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $successInt = $success ? 1 : 0;
    $stmt = db()->prepare(
        'INSERT INTO auth_login_attempts (identificador, email, ip, user_agent, sucesso)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssi', $identifier, $email, $ip, $userAgent, $successInt);
    $stmt->execute();
}

function auth_audit(?int $userId, string $action, string $details = ''): void
{
    try {
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $stmt = db()->prepare(
            'INSERT INTO auth_audit_log (usuario_id, acao, detalhes, ip, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $userId, $action, $details, $ip, $userAgent);
        $stmt->execute();
    } catch (Throwable $e) {
        // A auditoria nao deve impedir o acesso do usuario.
    }
}

function authenticate_credentials(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $identifier = auth_identifier($email);

    if (auth_recent_failures($identifier) >= LOGIN_MAX_ATTEMPTS) {
        auth_audit(null, 'login_bloqueado', 'Limite de tentativas para ' . $email);
        return ['status' => 'blocked'];
    }

    $stmt = db()->prepare(
        'SELECT id, nome, email, senha_hash, perfil, ativo
         FROM usuarios_admin
         WHERE email = ? AND ativo = 1
         LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, (string) $user['senha_hash'])) {
        auth_record_attempt($identifier, $email, false);
        auth_audit(null, 'login_falha', 'Credenciais recusadas para ' . $email);
        return ['status' => 'invalid'];
    }

    auth_record_attempt($identifier, $email, true);
    $delete = db()->prepare('DELETE FROM auth_login_attempts WHERE identificador = ? AND sucesso = 0');
    $delete->bind_param('s', $identifier);
    $delete->execute();

    $id = (int) $user['id'];
    $update = db()->prepare('UPDATE usuarios_admin SET ultimo_login_em = NOW() WHERE id = ?');
    $update->bind_param('i', $id);
    $update->execute();
    auth_audit($id, 'login_sucesso', 'Usuario autenticado.');

    unset($user['senha_hash'], $user['ativo']);
    return ['status' => 'success', 'user' => $user];
}
