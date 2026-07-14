<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

start_api_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !validate_api_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: usuarios-admin.php');
    exit;
}

clear_api_session();
header('Location: login.php?saiu=1');
exit;
