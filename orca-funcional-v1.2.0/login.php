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
            $db = getDB();
            $stmt = $db->prepare('SELECT id, nome, email, senha, role, ativo FROM usuarios WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !$user['ativo'] || !password_verify($senha, $user['senha'])) {
                $user = authenticateCentralAdmin($db, $email, $senha);
            }

            if ($user && $user['ativo'] && password_verify($senha, $user['senha'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
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
    <div class="login-box">
        <div class="brand">
            <div class="logo-icon" style="display:grid;place-items:center;width:58px;height:58px;margin:0 auto 14px;border-radius:18px;color:#fff;background:linear-gradient(135deg,#2563eb,#14b8a6);box-shadow:0 14px 32px rgba(37,99,235,.3);font-size:1.4rem"><i class="fa-solid fa-compass-drafting"></i></div>
            <h1><?= APP_NAME ?></h1>
            <p>Projetos, plantas e custos sob controle</p>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warning">Sua sessão expirou. Faça login novamente.</div>
        <?php endif; ?>

        <?php if ($erro): ?>
        <div class="alert alert-error"><?= sanitize($erro) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
            <div class="form-group">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control"
                    value="<?= sanitize($_POST['email'] ?? '') ?>"
                    placeholder="seu@email.com" required autofocus maxlength="180">
            </div>
            <div class="form-group">
                <label class="form-label">Senha</label>
                <div style="position:relative">
                    <input type="password" name="senha" id="senhaInput" class="form-control"
                        placeholder="••••••••" required maxlength="128">
                    <button type="button" onclick="toggleSenha()" aria-label="Mostrar ou ocultar senha" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--neutral-500)"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full" style="margin-top:8px">
                Entrar <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <p class="text-center text-sm text-muted mt-4">
            Use o mesmo e-mail e senha de administrador do domínio Leme Informática.
        </p>
    </div>
</div>
<script>
function toggleSenha() {
    const inp = document.getElementById('senhaInput');
    inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
<?php pageFoot(); ?>
