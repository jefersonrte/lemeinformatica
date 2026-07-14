<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios - API principal</title>
    <style>
        :root {
            --azul: #2430a3;
            --azul-escuro: #151f78;
            --borda: #d7dbea;
            --texto: #202334;
            --fundo: #f4f6fb;
            --erro: #9b1c1c;
            --sucesso: #1d6b3d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--fundo);
            color: var(--texto);
            font: 100% Arial, Helvetica, sans-serif;
            line-height: 1.5;
            margin: 0;
        }

        .wrapper {
            margin: 20px auto;
            max-width: 1120px;
            padding: 0 14px;
        }

        header,
        .panel {
            border-radius: 8px;
        }

        header {
            align-items: center;
            background: linear-gradient(135deg, var(--azul), var(--azul-escuro));
            box-shadow: 0 12px 30px rgba(36, 48, 163, .22);
            color: #fff;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            padding: 18px 22px;
        }

        header h1 {
            font-size: 28px;
            margin: 0;
        }

        header p,
        .panel p {
            margin: 4px 0 0;
        }

        .eyebrow {
            font-size: 12px;
            letter-spacing: .12em;
            opacity: .85;
            text-transform: uppercase;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--borda);
            box-shadow: 0 10px 24px rgba(28, 35, 80, .07);
            margin-top: 18px;
            padding: 18px;
        }

        .panel-header {
            align-items: center;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .panel h2 {
            margin: 0;
        }

        .muted,
        .panel p {
            color: #66708f;
        }

        .form-grid {
            align-items: end;
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 16px;
        }

        label {
            color: #39405a;
            display: grid;
            font-weight: 700;
            gap: 6px;
        }

        input,
        select {
            background: #fff;
            border: 1px solid #c9cfe2;
            border-radius: 7px;
            font-size: 15px;
            padding: 11px 12px;
            width: 100%;
        }

        input:focus,
        select:focus {
            border-color: var(--azul);
            outline: 3px solid rgba(36, 48, 163, .14);
        }

        .key-row {
            align-items: end;
            display: grid;
            gap: 10px;
            grid-template-columns: 1fr auto;
        }

        .check-row {
            align-items: center;
            display: flex;
            gap: 8px;
            min-height: 43px;
        }

        .check-row input {
            width: auto;
        }

        .btn {
            background: var(--azul);
            border: 0;
            border-radius: 7px;
            color: #fff;
            cursor: pointer;
            font-weight: 700;
            padding: 11px 14px;
        }

        .btn:hover {
            filter: brightness(.95);
        }

        .btn.ghost {
            background: #eef1f8;
            color: var(--azul);
        }

        .btn.danger {
            background: #8b1a1a;
        }

        .actions,
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        #btnCancelarUsuario {
            display: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            text-align: left;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #e4e8f2;
            padding: 12px 10px;
            vertical-align: middle;
        }

        th {
            background: #eef1f8;
            color: #313854;
        }

        tr:hover td {
            background: #f8f9fd;
        }

        .status-badge {
            border-radius: 999px;
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 9px;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: var(--erro);
        }

        .message-ok {
            color: var(--sucesso);
            font-weight: 700;
        }

        .message-error {
            color: var(--erro);
            font-weight: 700;
        }

        @media (max-width: 860px) {
            header,
            .panel-header {
                align-items: stretch;
                flex-direction: column;
            }

            .form-grid,
            .key-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <header>
            <div>
                <p class="eyebrow">API principal</p>
                <h1>Usuarios</h1>
                <p>Gerenciamento protegido pela chave da API.</p>
            </div>
        </header>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Acesso</h2>
                    <p>Use a mesma chave configurada em <strong>includes/config.php</strong>.</p>
                </div>
                <p id="status" class="muted">Aguardando chave.</p>
            </div>

            <div class="key-row">
                <label>
                    X-API-KEY
                    <input id="apiKey" type="password" autocomplete="off" placeholder="Cole a chave da API">
                </label>
                <button id="btnConectar" class="btn" type="button">Conectar</button>
            </div>
        </section>

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
                    <input id="usuarioNome" type="text" maxlength="100" required disabled>
                </label>
                <label>
                    E-mail
                    <input id="usuarioEmail" type="email" maxlength="150" required disabled>
                </label>
                <label>
                    Perfil
                    <select id="usuarioPerfil" required disabled>
                        <option value="admin">Admin</option>
                        <option value="operador">Operador</option>
                        <option value="visualizador">Visualizador</option>
                    </select>
                </label>
                <label>
                    Senha
                    <input id="usuarioSenha" type="password" minlength="8" autocomplete="new-password" placeholder="Minimo 8 caracteres" disabled>
                </label>
                <label class="check-row">
                    <input id="usuarioAtivo" type="checkbox" checked disabled>
                    Ativo
                </label>
                <div class="form-actions">
                    <button id="btnSalvarUsuario" class="btn" type="submit" disabled>Criar usuario</button>
                    <button id="btnCancelarUsuario" class="btn ghost" type="button" disabled>Cancelar edicao</button>
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
                        <tr><td colspan="6">Informe a chave da API para carregar usuarios.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        const apiKeyInput = document.getElementById('apiKey');
        const btnConectar = document.getElementById('btnConectar');
        const statusEl = document.getElementById('status');
        const usuarioStatus = document.getElementById('usuarioStatus');
        const formUsuario = document.getElementById('formUsuario');
        const tabelaUsuarios = document.getElementById('tabelaUsuarios');
        const usuarioId = document.getElementById('usuarioId');
        const usuarioNome = document.getElementById('usuarioNome');
        const usuarioEmail = document.getElementById('usuarioEmail');
        const usuarioPerfil = document.getElementById('usuarioPerfil');
        const usuarioSenha = document.getElementById('usuarioSenha');
        const usuarioAtivo = document.getElementById('usuarioAtivo');
        const btnSalvarUsuario = document.getElementById('btnSalvarUsuario');
        const btnCancelarUsuario = document.getElementById('btnCancelarUsuario');
        const formFields = [usuarioNome, usuarioEmail, usuarioPerfil, usuarioSenha, usuarioAtivo, btnSalvarUsuario, btnCancelarUsuario];

        let apiKey = '';
        let usuarios = [];

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        async function api(path, options = {}) {
            const method = String(options.method || 'GET').toUpperCase();
            const response = await fetch(path, {
                ...options,
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-API-KEY': apiKey,
                    ...(options.headers || {})
                }
            });

            const data = await response.json().catch(() => ({ ok: false, erro: 'Resposta invalida da API.' }));

            if (!response.ok || data.ok === false) {
                throw new Error(data.erro || 'Erro ao comunicar com a API.');
            }

            return data;
        }

        function setStatus(element, message, type = '') {
            element.textContent = message;
            element.className = type ? `message-${type}` : 'muted';
        }

        function setFormEnabled(enabled) {
            formFields.forEach(field => {
                field.disabled = !enabled;
            });
        }

        function usuarioEstaAtivo(usuario) {
            return usuario.ativo === true || Number(usuario.ativo) === 1;
        }

        function renderUsuarioActions(usuario) {
            const ativo = usuarioEstaAtivo(usuario);
            return `
                <div class="actions">
                    <button class="btn ghost" type="button" data-action="editar" data-id="${usuario.id}">Editar</button>
                    <button class="btn ${ativo ? 'danger' : 'ghost'}" type="button" data-action="${ativo ? 'desativar' : 'ativar'}" data-id="${usuario.id}">
                        ${ativo ? 'Desativar' : 'Ativar'}
                    </button>
                </div>
            `;
        }

        function renderUsuarios() {
            if (usuarios.length === 0) {
                tabelaUsuarios.innerHTML = '<tr><td colspan="6">Nenhum usuario encontrado.</td></tr>';
                return;
            }

            tabelaUsuarios.innerHTML = usuarios.map(usuario => {
                const ativo = usuarioEstaAtivo(usuario);
                return `
                    <tr>
                        <td>${Number(usuario.id)}</td>
                        <td>${escapeHtml(usuario.nome)}</td>
                        <td>${escapeHtml(usuario.email)}</td>
                        <td>${escapeHtml(usuario.perfil)}</td>
                        <td><span class="status-badge ${ativo ? 'active' : 'inactive'}">${ativo ? 'Ativo' : 'Inativo'}</span></td>
                        <td>${renderUsuarioActions(usuario)}</td>
                    </tr>
                `;
            }).join('');
        }

        async function carregarUsuarios() {
            const response = await api('usuarios.php');
            usuarios = response.data || [];
            renderUsuarios();
        }

        function limparFormularioUsuario() {
            usuarioId.value = '';
            formUsuario.reset();
            usuarioPerfil.value = 'operador';
            usuarioAtivo.checked = true;
            usuarioSenha.required = true;
            usuarioSenha.placeholder = 'Minimo 8 caracteres';
            btnSalvarUsuario.textContent = 'Criar usuario';
            btnCancelarUsuario.style.display = 'none';
        }

        function editarUsuario(usuario) {
            usuarioId.value = usuario.id;
            usuarioNome.value = usuario.nome || '';
            usuarioEmail.value = usuario.email || '';
            usuarioPerfil.value = usuario.perfil || 'visualizador';
            usuarioAtivo.checked = usuarioEstaAtivo(usuario);
            usuarioSenha.value = '';
            usuarioSenha.required = false;
            usuarioSenha.placeholder = 'Deixe em branco para manter';
            btnSalvarUsuario.textContent = 'Salvar usuario';
            btnCancelarUsuario.style.display = 'inline-block';
            usuarioNome.focus();
        }

        function usuarioPayloadBase(usuario) {
            return {
                nome: usuario.nome || '',
                email: usuario.email || '',
                perfil: usuario.perfil || 'visualizador',
                ativo: usuarioEstaAtivo(usuario)
            };
        }

        async function alterarStatusUsuario(id, ativo) {
            const usuario = usuarios.find(item => Number(item.id) === Number(id));
            if (!usuario) {
                return;
            }

            if (!confirm(`Deseja ${ativo ? 'ativar' : 'desativar'} este usuario?`)) {
                return;
            }

            setStatus(usuarioStatus, 'Salvando usuario...');

            try {
                await api(`usuarios.php?id=${encodeURIComponent(id)}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        ...usuarioPayloadBase(usuario),
                        ativo
                    })
                });
                await carregarUsuarios();
                setStatus(usuarioStatus, ativo ? 'Usuario ativado.' : 'Usuario desativado.', 'ok');
            } catch (error) {
                setStatus(usuarioStatus, error.message, 'error');
            }
        }

        btnConectar.addEventListener('click', async () => {
            apiKey = apiKeyInput.value.trim();

            if (!apiKey) {
                setStatus(statusEl, 'Informe a chave da API.', 'error');
                return;
            }

            setStatus(statusEl, 'Conectando...');

            try {
                await carregarUsuarios();
                setFormEnabled(true);
                limparFormularioUsuario();
                setStatus(statusEl, 'Conectado.', 'ok');
            } catch (error) {
                setFormEnabled(false);
                setStatus(statusEl, error.message, 'error');
            }
        });

        formUsuario.addEventListener('submit', async (event) => {
            event.preventDefault();

            const id = usuarioId.value;
            const senha = usuarioSenha.value.trim();
            const payload = {
                nome: usuarioNome.value.trim(),
                email: usuarioEmail.value.trim(),
                perfil: usuarioPerfil.value,
                ativo: usuarioAtivo.checked
            };

            if (senha !== '') {
                payload.senha = senha;
            }

            if (!id && senha === '') {
                setStatus(usuarioStatus, 'Informe a senha do novo usuario.', 'error');
                return;
            }

            const method = id ? 'PUT' : 'POST';
            const url = id ? `usuarios.php?id=${encodeURIComponent(id)}` : 'usuarios.php';

            setStatus(usuarioStatus, 'Salvando usuario...');

            try {
                await api(url, {
                    method,
                    body: JSON.stringify(payload)
                });
                limparFormularioUsuario();
                await carregarUsuarios();
                setStatus(usuarioStatus, id ? 'Usuario atualizado.' : 'Usuario criado.', 'ok');
            } catch (error) {
                setStatus(usuarioStatus, error.message, 'error');
            }
        });

        tabelaUsuarios.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-action]');
            if (!button) {
                return;
            }

            const id = Number(button.dataset.id);
            const usuario = usuarios.find(item => Number(item.id) === id);

            if (button.dataset.action === 'editar' && usuario) {
                editarUsuario(usuario);
            }

            if (button.dataset.action === 'ativar') {
                alterarStatusUsuario(id, true);
            }

            if (button.dataset.action === 'desativar') {
                alterarStatusUsuario(id, false);
            }
        });

        btnCancelarUsuario.addEventListener('click', limparFormularioUsuario);
    </script>
</body>
</html>
