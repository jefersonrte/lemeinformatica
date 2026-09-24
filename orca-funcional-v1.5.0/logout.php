<?php
require_once __DIR__ . '/bootstrap/app.php';
destroySession();
header('Location: ' . APP_URL . '/login.php');
exit;
