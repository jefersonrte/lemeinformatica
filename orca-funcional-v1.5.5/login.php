<?php
require_once __DIR__ . '/bootstrap/app.php';

// Já logado → redireciona
if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . ($_SESSION['user_role'] === 'admin' ? '/admin/dashboard.php' : '/cliente/dashboard.php'));
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf(false)) {
        refreshCsrf();
        $erro = 'Sua sessão de acesso expirou. O formulário foi atualizado; informe a senha novamente.';
    } else {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 1) {
        $erro = 'Preencha e-mail e senha corretamente.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, nome, email, senha, role, ativo FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$user['ativo'] || !password_verify($senha, $user['senha'])) {
            $user = authenticateCentralAdmin($db, $email, $senha);
        }

        if ($user && $user['ativo'] && password_verify($senha, $user['senha'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_active'] = time();
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            // Atualiza último login
            $db->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')->execute([$user['id']]);
            logAction('login', 'usuarios', $user['id']);

            redirect(APP_URL . ($user['role'] === 'admin' ? '/admin/dashboard.php' : '/cliente/dashboard.php'));
        } else {
            $erro = 'E-mail ou senha inválidos.';
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_MINUTES * 60;
                $_SESSION['login_attempts'] = 0;
                $erro = 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
            }
            // Pequeno delay para dificultar brute-force
            sleep(1);
        }
    }
    }
}

pageHead('Login');
?>
<div class="login-page">
    <div class="login-theme"><?= themePicker('loginThemeMenu', false) ?></div>
    <div class="login-shell">
    <aside class="login-showcase">
        <div>
            <div class="logo-icon"><i class="fa-solid fa-compass-drafting"></i></div>
            <h2>Projetos, plantas e custos sob controle.</h2>
            <p>Do primeiro esboço à última compra, cada número da obra em um só lugar.</p>
        </div>
        <ul class="login-features">
            <li><i class="fa-solid fa-wand-magic-sparkles"></i> Prévia de custo a partir da planta</li>
            <li><i class="fa-solid fa-paper-plane"></i> Cotações e compras com fornecedores</li>
            <li><i class="fa-solid fa-users"></i> Portal do cliente com acompanhamento</li>
        </ul>
    </aside>
    <div class="login-box">
        <div class="brand">
            <span class="eyebrow">Acesso restrito</span>
            <h1><?= APP_NAME ?></h1>
            <p>Entre com seu e-mail e senha.</p>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warning alert-persist"><i class="fa-solid fa-clock"></i><span>Sua sessão expirou. Faça login novamente.</span></div>
        <?php endif; ?>

        <?php if ($erro): ?>
        <div class="alert alert-error alert-persist"><i class="fa-solid fa-circle-xmark"></i><span><?= sanitize($erro) ?></span></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <div class="form-group">
                <label class="form-label" for="emailInput">E-mail</label>
                <input type="email" name="email" id="emailInput" class="form-control"
                    value="<?= sanitize($_POST['email'] ?? '') ?>"
                    placeholder="seu@email.com" required autofocus maxlength="180">
            </div>
            <div class="form-group">
                <label class="form-label" for="senhaInput">Senha</label>
                <div class="password-field">
                    <input type="password" name="senha" id="senhaInput" class="form-control"
                        placeholder="••••••••" required maxlength="128">
                    <button type="button" class="password-toggle" onclick="toggleSenha(this)" aria-label="Mostrar ou ocultar senha"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full" style="margin-top:8px">
                Entrar <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <p class="text-center text-xs text-muted mt-4">
            Use o mesmo e-mail e senha de administrador do domínio Leme Informática.
        </p>
    </div>
    </div>
</div>
<script>
function toggleSenha(button) {
    const inp = document.getElementById('senhaInput');
    const visivel = inp.type === 'password';
    inp.type = visivel ? 'text' : 'password';
    button.querySelector('i').className = visivel ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
}
</script>
<?php pageFoot(); ?>
