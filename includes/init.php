<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

apply_cors();
start_api_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$GLOBALS['API_AUTH_MODE'] = require_api_or_session();
require_session_csrf_for_state_change($GLOBALS['API_AUTH_MODE']);
