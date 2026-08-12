<?php
declare(strict_types=1);

namespace App\Security;

final class SessionManager
{
    public static function start(string $name, int $lifetime, bool $secure, string $cookiePath): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $cookiePath !== '' ? $cookiePath : '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        if (!isset($_SESSION['last_regen']) || time() - (int) $_SESSION['last_regen'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
