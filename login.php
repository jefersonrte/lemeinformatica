<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

apply_page_security_headers();
start_api_session();

if (current_api_user() !== null) {
    header('Location: usuarios-admin.php');
    exit;
}

$error = (string) ($_GET['erro'] ?? '');
$message = match ($error) {
    'credenciais' => 'E-mail ou senha incorretos. Confira os dados e tente novamente.',
    'bloqueado' => 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.',
    'csrf' => 'A sessao de seguranca expirou. Atualize a pagina e tente novamente.',
    'sistema' => 'Nao foi possivel validar o acesso agora. Tente novamente em instantes.',
    default => isset($_GET['saiu']) ? 'Sessao encerrada com seguranca.' : '',
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Leme Informatica</title>
    <style>
        :root { --ink: #1f2937; --muted: #5f6b7a; --line: #d8dee8; --brand: #166534; --brand-dark: #14532d; --error: #991b1b; --ok: #166534; }
        * { box-sizing: border-box; }
        body { align-items: center; background: #eef2f5; color: var(--ink); display: flex; font: 16px Arial, Helvetica, sans-serif; justify-content: center; margin: 0; min-height: 100vh; padding: 20px; }
        main { background: #fff; border: 1px solid var(--line); border-radius: 8px; box-shadow: 0 18px 45px rgba(31, 41, 55, .12); max-width: 430px; padding: 30px; width: 100%; }
        .brand { border-bottom: 4px solid var(--brand); margin-bottom: 24px; padding-bottom: 18px; }
        .eyebrow { color: var(--brand); font-size: 12px; font-weight: 700; margin: 0 0 7px; text-transform: uppercase; }
        h1 { font-size: 28px; margin: 0; }
        .subtitle { color: var(--muted); line-height: 1.5; margin: 9px 0 0; }
        form, label { display: grid; gap: 8px; }
        form { gap: 17px; }
        label { color: #374151; font-weight: 700; }
        input { border: 1px solid #b8c2cf; border-radius: 7px; font-size: 16px; padding: 12px; width: 100%; }
        input:focus { border-color: var(--brand); outline: 3px solid rgba(22, 101, 52, .14); }
        button { background: var(--brand); border: 0; border-radius: 7px; color: #fff; cursor: pointer; font-size: 16px; font-weight: 700; min-height: 46px; padding: 12px 16px; }
        button:hover { background: var(--brand-dark); }
        .alert { background: #fef2f2; border: 1px solid #fecaca; border-radius: 7px; color: var(--error); line-height: 1.45; margin-bottom: 18px; padding: 12px; }
        .alert.ok { background: #f0fdf4; border-color: #bbf7d0; color: var(--ok); }
    </style>
</head>
<body>
    <main>
        <div class="brand">
            <p class="eyebrow">Leme Informatica</p>
            <h1>Acesso ao sistema</h1>
            <p class="subtitle">Use seu e-mail e senha para administrar os usuarios.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert<?= isset($_GET['saiu']) && $error === '' ? ' ok' : '' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login-processa.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(api_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
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
