<?php
declare(strict_types=1);

use App\Security\SessionManager;

if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

function centralSessionUser(): ?array
{
    if (session_status() !== PHP_SESSION_NONE || empty($_COOKIE['LEME_API_SESSAO'])) {
        return null;
    }

    session_name('LEME_API_SESSAO');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if (!@session_start()) {
        return null;
    }
    $user = !empty($_SESSION['usuario_id']) ? [
        'id' => (int) $_SESSION['usuario_id'],
        'nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
        'email' => (string) ($_SESSION['usuario_email'] ?? ''),
        'perfil' => (string) ($_SESSION['usuario_perfil'] ?? ''),
    ] : null;
    session_write_close();
    return $user;
}

function provisionCentralAdmin(PDO $db, array $centralUser, ?string $knownHash = null): ?array
{
    if (($centralUser['perfil'] ?? '') !== 'admin' || !filter_var($centralUser['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    if ($knownHash === null) {
        $central = $db->prepare('SELECT nome,email,senha_hash,perfil FROM usuarios_admin WHERE id=? AND email=? AND ativo=1 LIMIT 1');
        $central->execute([(int) ($centralUser['id'] ?? 0), (string) $centralUser['email']]);
        $record = $central->fetch();
        if (!$record || $record['perfil'] !== 'admin') {
            return null;
        }
        $centralUser['nome'] = $record['nome'];
        $knownHash = (string) $record['senha_hash'];
    }

    $statement = $db->prepare(
        "INSERT INTO usuarios (nome,email,senha,role,ativo,email_verificado) VALUES (?,?,?,'admin',1,1) "
        . "ON DUPLICATE KEY UPDATE nome=VALUES(nome), senha=VALUES(senha), role='admin', ativo=1, email_verificado=1"
    );
    $statement->execute([(string) $centralUser['nome'], strtolower((string) $centralUser['email']), $knownHash]);
    $find = $db->prepare('SELECT id,nome,email,senha,role,ativo FROM usuarios WHERE email=? LIMIT 1');
    $find->execute([strtolower((string) $centralUser['email'])]);
    return $find->fetch() ?: null;
}

function authenticateCentralAdmin(PDO $db, string $email, string $password): ?array
{
    $statement = $db->prepare('SELECT id,nome,email,senha_hash,perfil,ativo FROM usuarios_admin WHERE email=? AND ativo=1 LIMIT 1');
    $statement->execute([strtolower($email)]);
    $central = $statement->fetch();
    if (!$central || $central['perfil'] !== 'admin' || !password_verify($password, (string) $central['senha_hash'])) {
        return null;
    }
    return provisionCentralAdmin($db, $central, (string) $central['senha_hash']);
}

$centralUser = centralSessionUser();
$cookiePath = (string) (parse_url(APP_URL, PHP_URL_PATH) ?: '/');
$httpsDetected = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
SessionManager::start(SESSION_NAME, SESSION_LIFETIME, SECURE_COOKIES && $httpsDetected, $cookiePath);

if (empty($_SESSION['user_id']) && $centralUser !== null) {
    try {
        $orcaUser = provisionCentralAdmin(getDB(), $centralUser);
        if ($orcaUser) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $orcaUser['id'];
            $_SESSION['user_nome'] = (string) $orcaUser['nome'];
            $_SESSION['user_role'] = (string) $orcaUser['role'];
            $_SESSION['last_active'] = time();
        }
    } catch (Throwable) {
        // A migração inicial pode ainda não ter sido executada.
    }
}

function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    // Timeout por inatividade
    if (!empty($_SESSION['last_active']) && time() - $_SESSION['last_active'] > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_active'] = time();
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: ' . APP_URL . '/cliente/dashboard.php');
        exit;
    }
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserRole(): string {
    return $_SESSION['user_role'] ?? '';
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        return refreshCsrf();
    }
    return $_SESSION['csrf_token'];
}

function refreshCsrf(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrf(bool $abortOnFailure = true): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $valid = is_string($token) && is_string($sessionToken)
        && $token !== '' && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
    if (!$valid && $abortOnFailure) {
        http_response_code(403);
        die('Token CSRF inválido.');
    }
    return $valid;
}

function destroySession(): void {
    SessionManager::destroy();
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}
