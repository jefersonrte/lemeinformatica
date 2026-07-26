const usuariosContext = document.getElementById('usuariosContext');
const csrfToken = usuariosContext?.dataset.csrf || '';
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
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
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

    tabelaUsuarios.innerHTML = usuarios.map((usuario) => {
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
    btnCancelarUsuario.hidden = true;
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
    btnCancelarUsuario.hidden = false;
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
    const usuario = usuarios.find((item) => Number(item.id) === Number(id));
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
    const usuario = usuarios.find((item) => Number(item.id) === id);

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

limparFormularioUsuario();
carregarUsuarios().catch((error) => {
    setStatus(usuarioStatus, error.message, 'error');
    tabelaUsuarios.innerHTML = '<tr><td colspan="6">Nao foi possivel carregar os usuarios.</td></tr>';
});
