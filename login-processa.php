<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/authentication.php';

apply_page_security_headers();
start_api_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: login.php');
    exit;
}

if (!validate_api_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: login.php?erro=csrf');
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['senha'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    header('Location: login.php?erro=credenciais');
    exit;
}

try {
    $result = authenticate_credentials($email, $password);

    if ($result['status'] === 'blocked') {
        header('Location: login.php?erro=bloqueado');
        exit;
    }

    if ($result['status'] !== 'success') {
        header('Location: login.php?erro=credenciais');
        exit;
    }

    login_api_user($result['user']);
    header('Location: usuarios-admin.php');
    exit;
} catch (Throwable $e) {
    header('Location: login.php?erro=sistema');
    exit;
}
