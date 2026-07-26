<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

apply_page_security_headers();
$currentUser = require_api_page_login(['admin']);
$csrfToken = api_csrf_token();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios - API principal</title>
    <link rel="stylesheet" href="frontend/css/usuarios.css?v=20260714-frontend">
</head>
<body>
    <div
        id="usuariosContext"
        data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        hidden
    ></div>

    <div class="wrapper">
        <header>
            <div>
                <p class="eyebrow">API principal</p>
                <h1>Usuarios</h1>
                <p>Gerenciamento de acessos dos dois sistemas.</p>
            </div>
            <div class="account-actions">
                <p><?= htmlspecialchars($currentUser['nome'], ENT_QUOTES, 'UTF-8') ?></p>
                <a class="btn ghost" href="painel.php">Animais e alimentos</a>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn ghost" type="submit">Sair</button>
                </form>
            </div>
        </header>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Cadastro</h2>
                    <p>Crie usuarios ou edite os dados selecionados na tabela.</p>
                </div>
                <p id="usuarioStatus"></p>
            </div>

            <form id="formUsuario" class="form-grid">
                <input type="hidden" id="usuarioId">
                <label>
                    Nome
                    <input id="usuarioNome" type="text" maxlength="100" required>
                </label>
                <label>
                    E-mail
                    <input id="usuarioEmail" type="email" maxlength="150" required>
                </label>
                <label>
                    Perfil
                    <select id="usuarioPerfil" required>
                        <option value="admin">Admin</option>
                        <option value="operador">Operador</option>
                        <option value="visualizador">Visualizador</option>
                    </select>
                </label>
                <label>
                    Senha
                    <input id="usuarioSenha" type="password" minlength="8" autocomplete="new-password" placeholder="Minimo 8 caracteres">
                </label>
                <label class="check-row">
                    <input id="usuarioAtivo" type="checkbox" checked>
                    Ativo
                </label>
                <div class="form-actions">
                    <button id="btnSalvarUsuario" class="btn" type="submit">Criar usuario</button>
                    <button id="btnCancelarUsuario" class="btn ghost" type="button" hidden>Cancelar edicao</button>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaUsuarios">
                        <tr><td colspan="6">Carregando usuarios...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script src="frontend/js/usuarios.js?v=20260714-frontend"></script>
</body>
</html>
