<?php
declare(strict_types=1);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/orca-funcional-v1.2.0/login.php';
$_SERVER['REQUEST_METHOD'] = 'POST';

require dirname(__DIR__, 2) . '/orca-funcional-v1.2.0/bootstrap/app.php';

$_SESSION['csrf_token'] = str_repeat('a', 64);
$_POST['csrf_token'] = 'token-antigo-simulado';

if (verifyCsrf(false)) {
    fwrite(STDERR, "Token antigo foi aceito.\n");
    exit(1);
}

$renewed = refreshCsrf();
if (!preg_match('/^[a-f0-9]{64}$/', $renewed)) {
    fwrite(STDERR, "Novo token CSRF invalido.\n");
    exit(1);
}

$_POST['csrf_token'] = $renewed;
if (!verifyCsrf(false)) {
    fwrite(STDERR, "Novo token CSRF foi recusado.\n");
    exit(1);
}

echo "Orca v1.2.0 CSRF recovery OK\n";
