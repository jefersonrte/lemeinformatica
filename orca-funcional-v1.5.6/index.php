<?php
// index.php — redireciona para login
require_once __DIR__ . '/bootstrap/app.php';

if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . ($_SESSION['user_role'] === 'admin' ? '/admin/dashboard.php' : '/cliente/dashboard.php'));
} else {
    redirect(APP_URL . '/login.php');
}
