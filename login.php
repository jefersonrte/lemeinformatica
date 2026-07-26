<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

apply_page_security_headers();
start_api_session();

$allowedDestinations = [
    'painel' => 'painel.php',
    'pet' => 'pet/',
];
$next = (string) ($_GET['next'] ?? 'painel');
$next = array_key_exists($next, $allowedDestinations) ? $next : 'painel';

if (current_api_user() !== null) {
    header('Location: ' . $allowedDestinations[$next]);
    exit;
}

$error = (string) ($_GET['erro'] ?? '');
$messages = [
    'credenciais' => 'E-mail ou senha incorretos. Confira os dados e tente novamente.',
    'bloqueado' => 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.',
    'csrf' => 'A sessao de seguranca expirou. Atualize a pagina e tente novamente.',
    'sistema' => 'Nao foi possivel validar o acesso agora. Tente novamente em instantes.',
];
$message = $messages[$error] ?? (isset($_GET['saiu']) ? 'Sessao encerrada com seguranca.' : '');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Leme Informatica</title>
    <link rel="stylesheet" href="frontend/css/login.css?v=20260714-frontend">
</head>
<body>
    <main>
        <div class="brand">
            <p class="eyebrow">Leme Informatica</p>
            <h1>Acesso ao sistema</h1>
            <p class="subtitle">Use seu e-mail e senha para acessar o painel.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert<?= isset($_GET['saiu']) && $error === '' ? ' ok' : '' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login-processa.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(api_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
            <label>
                E-mail
                <input type="email" name="email" required autocomplete="username" placeholder="seu@email.com">
            </label>
            <label>
                Senha
                <input type="password" name="senha" required autocomplete="current-password" placeholder="Digite sua senha">
            </label>
            <button type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>
