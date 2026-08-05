import assert from 'node:assert/strict';

const email = process.env.PET_TEST_EMAIL || '';
const password = process.env.PET_TEST_PASSWORD || '';
assert(email && password, 'Defina PET_TEST_EMAIL e PET_TEST_PASSWORD.');

const infoOrigin = 'https://lemeinformatica.com.br';
const dashboardOrigin = 'https://lemesolucoesemti.com.br';

function mergeCookies(current, response) {
    const values = typeof response.headers.getSetCookie === 'function'
        ? response.headers.getSetCookie()
        : [response.headers.get('set-cookie')].filter(Boolean);
    const cookies = new Map(String(current || '').split('; ').filter(Boolean).map((item) => {
        const index = item.indexOf('=');
        return [item.slice(0, index), item.slice(index + 1)];
    }));
    for (const value of values) {
        const pair = value.split(';', 1)[0];
        const index = pair.indexOf('=');
        if (index > 0) cookies.set(pair.slice(0, index), pair.slice(index + 1));
    }
    return [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');
}

function hiddenValue(html, name) {
    return html.match(new RegExp(`name="${name}" value="([^"]+)"`))?.[1] || '';
}

async function login(pass) {
    const page = await fetch(`${infoOrigin}/login.php?next=pet_sso`, { redirect: 'manual' });
    const html = await page.text();
    let cookie = mergeCookies('', page);
    const csrf = hiddenValue(html, 'csrf_token');
    assert.equal(page.status, 200);
    assert(csrf && cookie, 'Login nao forneceu sessao e CSRF.');

    const response = await fetch(`${infoOrigin}/login-processa.php`, {
        method: 'POST',
        redirect: 'manual',
        headers: { Cookie: cookie, 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: csrf, next: 'pet_sso', email, senha: pass }),
    });
    cookie = mergeCookies(cookie, response);
    return { response, cookie };
}

async function petApi(path, cookie, csrf, options = {}) {
    const method = options.method || 'GET';
    const headers = { Accept: 'application/json', Cookie: cookie };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = csrf;
    }
    const response = await fetch(`${infoOrigin}/pet/api/${path}`, {
        method,
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
        redirect: 'manual',
    });
    const payload = await response.json().catch(() => ({}));
    const expected = options.expectedStatus || 200;
    assert.equal(response.status, expected, `${method} ${path}: ${payload.erro || response.status}`);
    return payload;
}

const invalid = await login(`${password}-invalida`);
assert.equal(invalid.response.status, 302);
assert.match(invalid.response.headers.get('location') || '', /erro=credenciais/);

const authenticated = await login(password);
assert.equal(authenticated.response.status, 302);
assert.equal(authenticated.response.headers.get('location'), 'pet/sso-start.php');
const infoCookie = authenticated.cookie;

const petPage = await fetch(`${infoOrigin}/pet/`, { headers: { Cookie: infoCookie } });
assert.equal(petPage.status, 200);
const petHtml = await petPage.text();
const contextText = petHtml.match(/<script id="petContext" type="application\/json">([\s\S]*?)<\/script>/)?.[1] || '';
const context = JSON.parse(contextText);
assert(context.csrf, 'Contexto Pet sem CSRF.');
assert.equal(context.user.profile, 'admin', 'O smoke test completo exige perfil admin.');

const start = await fetch(`${infoOrigin}/pet/sso-start.php`, { headers: { Cookie: infoCookie }, redirect: 'manual' });
assert.equal(start.status, 302);
const callbackUrl = new URL(start.headers.get('location'));
assert.equal(callbackUrl.origin, dashboardOrigin);
assert.match(callbackUrl.pathname, /\/pet\/callback\.php$/);

const callback = await fetch(callbackUrl, { redirect: 'manual' });
assert.equal(callback.status, 302);
assert.equal(callback.headers.get('location'), './');
const dashboardCookie = mergeCookies('', callback);
assert(dashboardCookie, 'Callback nao criou a sessao do dashboard.');

const dashboard = await fetch(`${dashboardOrigin}/pet/`, { headers: { Cookie: dashboardCookie } });
assert.equal(dashboard.status, 200);
const dashboardHtml = await dashboard.text();
assert.match(dashboardHtml, /Dashboard Pet/);
const dashboardCsrf = hiddenValue(dashboardHtml, 'csrf_token');

const dashboardApi = await fetch(`${dashboardOrigin}/pet/api/dashboard.php`, {
    headers: { Cookie: dashboardCookie, Accept: 'application/json' },
});
assert.equal(dashboardApi.status, 200);
const dashboardData = await dashboardApi.json();
assert.equal(dashboardData.ok, true);
assert(Number(dashboardData.data?.totais?.animais) >= 200);

const csrf = context.csrf;
const animals = await petApi('animais.php?limit=5', infoCookie, csrf);
const owners = await petApi('tutores.php?limit=5', infoCookie, csrf);
await petApi('dashboard.php', infoCookie, csrf);
await petApi('atendimentos.php?limit=5', infoCookie, csrf);
await petApi('internacoes.php?status=ativa', infoCookie, csrf);
await petApi('servicos.php', infoCookie, csrf);
await petApi('banho-tosa.php', infoCookie, csrf);
await petApi('produtos.php', infoCookie, csrf);
await petApi('estoque.php', infoCookie, csrf);
await petApi('vendas.php', infoCookie, csrf);
await petApi('relatorios.php', infoCookie, csrf);
assert(animals.data?.length && owners.data?.length, 'Cadastros demonstrativos ausentes.');

const suffix = Date.now().toString(36).toUpperCase();
const servicePayload = {
    codigo: `QA-${suffix}`,
    nome: `Servico QA ${suffix}`,
    categoria: 'outro',
    duracao_minutos: 20,
    preco: 25,
    descricao: 'Registro automatizado de validacao.',
    ativo: true,
};
const service = await petApi('servicos.php', infoCookie, csrf, { method: 'POST', body: servicePayload, expectedStatus: 201 });
const serviceId = service.data.id;

const productPayload = {
    sku: `QA-${suffix}`,
    nome: `Produto QA ${suffix}`,
    categoria: 'outro',
    unidade: 'un',
    marca: 'Leme QA',
    codigo_barras: '',
    preco_custo: 10,
    preco_venda: 20,
    estoque_inicial: 5,
    estoque_minimo: 1,
    controla_estoque: true,
    ativo: true,
};
const product = await petApi('produtos.php', infoCookie, csrf, { method: 'POST', body: productPayload, expectedStatus: 201 });
const productId = product.data.id;

await petApi('estoque.php', infoCookie, csrf, {
    method: 'POST',
    body: { produto_id: productId, tipo: 'entrada', quantidade: 2, custo_unitario: 10, motivo: 'Validacao automatizada' },
});
const sale = await petApi('vendas.php', infoCookie, csrf, {
    method: 'POST',
    expectedStatus: 201,
    body: { tutor_id: owners.data[0].id, forma_pagamento: 'pix', desconto: 0, itens: [{ produto_id: productId, quantidade: 1 }] },
});
const saleId = sale.data.id;
await petApi('vendas.php', infoCookie, csrf, {
    method: 'POST',
    expectedStatus: 409,
    body: { forma_pagamento: 'pix', desconto: 0, itens: [{ produto_id: productId, quantidade: 999 }] },
});
await petApi(`vendas.php?id=${saleId}`, infoCookie, csrf, { method: 'PUT', body: { motivo: 'Encerramento do teste automatizado' } });

const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 16);
const groomingBase = {
    animal_id: animals.data[0].id,
    servico_id: serviceId,
    inicio_em: tomorrow,
    status: 'agendado',
    profissional_nome: 'Equipe QA',
    observacoes_entrada: 'Validacao automatizada.',
};
const grooming = await petApi('banho-tosa.php', infoCookie, csrf, { method: 'POST', body: groomingBase, expectedStatus: 201 });
await petApi(`banho-tosa.php?id=${grooming.data.id}`, infoCookie, csrf, {
    method: 'PUT',
    body: { ...groomingBase, status: 'cancelado', observacoes_saida: 'Teste concluido.' },
});

await petApi(`produtos.php?id=${productId}`, infoCookie, csrf, {
    method: 'PUT',
    body: { ...productPayload, estoque_inicial: 0, ativo: false },
});
await petApi(`servicos.php?id=${serviceId}`, infoCookie, csrf, {
    method: 'PUT',
    body: { ...servicePayload, ativo: false },
});

const logout = await fetch(`${dashboardOrigin}/pet/logout.php`, {
    method: 'POST',
    redirect: 'manual',
    headers: { Cookie: dashboardCookie, 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ csrf_token: dashboardCsrf }),
});
assert.equal(logout.status, 302);
const afterLogout = await fetch(`${dashboardOrigin}/pet/api/dashboard.php`, {
    headers: { Cookie: dashboardCookie, Accept: 'application/json' },
});
assert.equal(afterLogout.status, 401);

console.log(JSON.stringify({
    ok: true,
    versao: dashboardData.versao,
    totais: dashboardData.data.totais,
    leituras: ['dashboard', 'tutores', 'animais', 'atendimentos', 'internacoes', 'estetica', 'produtos', 'estoque', 'vendas', 'relatorios'],
    operacoes: ['servico', 'agendamento_cancelado', 'produto_inativo', 'estoque', 'venda_cancelada', 'estorno', 'saldo_insuficiente'],
    sso: ['senha_incorreta', 'login', 'codigo_unico', 'callback', 'dashboard', 'logout'],
}, null, 2));
