(() => {
    'use strict';

    const context = JSON.parse(document.getElementById('petContext')?.textContent || '{}');
    const permissions = context.permissions || {};
    const state = { animals: [], owners: [], services: [], grooming: [], products: [], sales: [] };
    const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const dateTime = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    const titles = { banho_tosa: 'Banho e tosa', produtos: 'Produtos e estoque', vendas: 'Vendas' };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const label = (value) => String(value || '-').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    const money = (value) => currency.format(Number(value) || 0);
    const formatDate = (value) => {
        if (!value) return '-';
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? escapeHtml(value) : dateTime.format(parsed);
    };
    const toLocalDateTime = (value) => value ? String(value).replace(' ', 'T').slice(0, 16) : '';
    const badge = (value) => `<span class="badge ${escapeHtml(String(value || '').toLowerCase())}">${escapeHtml(label(value))}</span>`;

    function toast(message, type = 'success') {
        const region = document.getElementById('toastRegion');
        if (!region) return;
        const item = document.createElement('div');
        item.className = `toast ${type === 'error' ? 'error' : ''}`;
        item.textContent = message;
        region.appendChild(item);
        window.setTimeout(() => item.remove(), 5000);
    }

    async function api(path, options = {}) {
        const method = String(options.method || 'GET').toUpperCase();
        const headers = new Headers({ Accept: 'application/json', ...(options.headers || {}) });
        let body = options.body;
        if (method !== 'GET') headers.set('X-CSRF-TOKEN', context.csrf || '');
        if (body && typeof body !== 'string') {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(body);
        }
        const response = await fetch(`api/${path}`, { ...options, method, headers, body, credentials: 'same-origin' });
        const payload = await response.json().catch(() => ({ ok: false, erro: 'Resposta invalida do servidor.' }));
        if (response.status === 401) {
            window.location.href = '../login.php?next=pet';
            throw new Error('Sessao expirada.');
        }
        if (!response.ok || payload.ok === false) {
            const error = new Error(payload.erro || 'Nao foi possivel concluir a operacao.');
            error.fields = payload.campos || {};
            throw error;
        }
        return payload;
    }

    function readForm(form) {
        const data = {};
        for (const field of form.elements) {
            if (!field.name || ['submit', 'button'].includes(field.type)) continue;
            data[field.name] = field.type === 'checkbox' ? field.checked : field.value;
        }
        return data;
    }

    function fillForm(form, data = {}) {
        form.reset();
        for (const field of form.elements) {
            if (!field.name || !(field.name in data)) continue;
            if (field.type === 'checkbox') field.checked = Number(data[field.name]) === 1 || data[field.name] === true;
            else if (field.type === 'datetime-local') field.value = toLocalDateTime(data[field.name]);
            else field.value = data[field.name] ?? '';
        }
    }

    function openDialog(id) {
        const dialog = document.getElementById(id);
        if (dialog && !dialog.open) dialog.showModal();
    }

    function debounce(callback, delay = 300) {
        let timer;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    }

    async function ensureAnimals() {
        if (!state.animals.length) state.animals = (await api('animais.php?limit=100')).data || [];
        return state.animals;
    }

    async function ensureOwners() {
        if (!state.owners.length) state.owners = (await api('tutores.php?limit=100')).data || [];
        return state.owners;
    }

    async function ensureServices() {
        state.services = (await api('servicos.php')).data || [];
        return state.services;
    }

    async function ensureProducts() {
        state.products = (await api('produtos.php')).data || [];
        return state.products;
    }

    function setOptions(select, records, valueKey, textBuilder, blank = '') {
        if (!select) return;
        select.innerHTML = `${blank ? `<option value="">${escapeHtml(blank)}</option>` : ''}${records.map((item) =>
            `<option value="${escapeHtml(item[valueKey])}">${escapeHtml(textBuilder(item))}</option>`).join('')}`;
    }

    async function loadCommerceDashboard() {
        const payload = await api('relatorios.php');
        const totals = payload.data?.totais || {};
        document.getElementById('metricGroomingToday').textContent = totals.estetica_hoje ?? 0;
        document.getElementById('metricSalesToday').textContent = money(totals.vendas_hoje);
        document.getElementById('metricLowStock').textContent = totals.estoque_baixo ?? 0;
        document.getElementById('metricProducts').textContent = totals.produtos_ativos ?? 0;
    }

    async function loadGrooming() {
        const search = document.getElementById('groomingSearch')?.value.trim() || '';
        const status = document.getElementById('groomingStatusFilter')?.value || '';
        const payload = await api(`banho-tosa.php?q=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`);
        state.grooming = payload.data || [];
        const table = document.getElementById('groomingTable');
        if (!table) return;
        table.innerHTML = state.grooming.length ? state.grooming.map((item) => `
            <tr><td>${formatDate(item.inicio_em)}</td>
            <td><strong>${escapeHtml(item.animal_nome)}</strong><br><small>${escapeHtml(item.tutor_nome)}</small></td>
            <td>${escapeHtml(item.servicos_nomes || '-')}</td><td>${escapeHtml(item.profissional_nome || 'A definir')}</td>
            <td>${money(item.valor_total)}</td><td>${badge(item.status)}</td>
            <td><div class="row-actions">${permissions.gerenciar_estetica ? `<button type="button" data-edit-grooming="${item.id}">Editar</button>` : ''}</div></td></tr>
        `).join('') : '<tr><td colspan="7">Nenhum agendamento encontrado.</td></tr>';
    }

    async function openGrooming(id = null) {
        const form = document.getElementById('groomingForm');
        const title = document.getElementById('groomingDialogTitle');
        if (!form) return;
        const [animals, services] = await Promise.all([ensureAnimals(), ensureServices()]);
        setOptions(form.elements.animal_id, animals, 'id', (item) => `${item.nome} - ${item.tutor_nome}`);
        setOptions(form.elements.servico_id, services, 'id', (item) => `${item.nome} - ${money(item.preco)}`);
        fillForm(form, { status: 'agendado', inicio_em: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16) });
        title.textContent = 'Novo agendamento';
        if (id) {
            const record = (await api(`banho-tosa.php?id=${id}`)).data;
            fillForm(form, { ...record, servico_id: record.servicos?.[0]?.servico_id || '' });
            title.textContent = 'Editar agendamento';
        }
        openDialog('groomingDialog');
    }

    async function saveGrooming(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const data = readForm(form);
        const id = data.id;
        const payload = await api(`banho-tosa.php${id ? `?id=${id}` : ''}`, { method: id ? 'PUT' : 'POST', body: data });
        form.closest('dialog').close();
        toast(payload.mensagem || 'Agendamento salvo.');
        await Promise.all([loadGrooming(), loadCommerceDashboard()]);
    }

    async function saveService(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const payload = await api('servicos.php', { method: 'POST', body: readForm(form) });
        form.closest('dialog').close();
        form.reset();
        state.services = [];
        toast(payload.mensagem || 'Servico salvo.');
    }

    async function loadProducts() {
        const search = document.getElementById('productSearch')?.value.trim() || '';
        const low = document.getElementById('lowStockFilter')?.checked ? 1 : 0;
        const payload = await api(`produtos.php?q=${encodeURIComponent(search)}&estoque_baixo=${low}`);
        state.products = payload.data || [];
        const table = document.getElementById('productsTable');
        if (!table) return;
        table.innerHTML = state.products.length ? state.products.map((item) => `
            <tr><td><strong>${escapeHtml(item.nome)}</strong><br><small>${escapeHtml(item.sku)}${item.marca ? ` - ${escapeHtml(item.marca)}` : ''}</small></td>
            <td>${escapeHtml(label(item.categoria))}</td><td>${money(item.preco_venda)}</td>
            <td class="${Number(item.estoque_baixo) ? 'stock-low' : 'stock-ok'}">${escapeHtml(item.estoque_atual)} ${escapeHtml(item.unidade)}</td>
            <td>${escapeHtml(item.estoque_minimo)} ${escapeHtml(item.unidade)}</td><td>${Number(item.ativo) ? badge('ativo') : badge('inativo')}</td>
            <td><div class="row-actions">
                ${permissions.gerenciar_estoque ? `<button type="button" data-stock-product="${item.id}">Estoque</button>` : ''}
                ${permissions.gerenciar_produtos ? `<button type="button" data-edit-product="${item.id}">Editar</button>` : ''}
            </div></td></tr>
        `).join('') : '<tr><td colspan="7">Nenhum produto encontrado.</td></tr>';
    }

    async function openProduct(id = null) {
        const form = document.getElementById('productForm');
        if (!form) return;
        fillForm(form, { unidade: 'un', estoque_inicial: 0, estoque_minimo: 0, controla_estoque: true, ativo: true });
        document.getElementById('productDialogTitle').textContent = 'Novo produto';
        form.querySelector('[data-initial-stock]').hidden = false;
        if (id) {
            fillForm(form, (await api(`produtos.php?id=${id}`)).data);
            document.getElementById('productDialogTitle').textContent = 'Editar produto';
            form.querySelector('[data-initial-stock]').hidden = true;
        }
        openDialog('productDialog');
    }

    async function saveProduct(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const data = readForm(form);
        const id = data.id;
        const payload = await api(`produtos.php${id ? `?id=${id}` : ''}`, { method: id ? 'PUT' : 'POST', body: data });
        form.closest('dialog').close();
        toast(payload.mensagem || 'Produto salvo.');
        await Promise.all([loadProducts(), loadCommerceDashboard()]);
    }

    async function openStock(productId) {
        const form = document.getElementById('stockForm');
        await ensureProducts();
        setOptions(form.elements.produto_id, state.products, 'id', (item) => `${item.nome} (${item.estoque_atual} ${item.unidade})`);
        fillForm(form, { produto_id: productId, tipo: 'entrada' });
        openDialog('stockDialog');
    }

    async function saveStock(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const payload = await api('estoque.php', { method: 'POST', body: readForm(form) });
        form.closest('dialog').close();
        toast(payload.mensagem || 'Estoque atualizado.');
        await Promise.all([loadProducts(), loadCommerceDashboard()]);
    }

    async function loadSales() {
        const search = document.getElementById('saleSearch')?.value.trim() || '';
        const status = document.getElementById('saleStatusFilter')?.value || '';
        const payload = await api(`vendas.php?q=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`);
        state.sales = payload.data || [];
        const table = document.getElementById('salesTable');
        if (!table) return;
        table.innerHTML = state.sales.length ? state.sales.map((item) => `
            <tr><td><strong>${escapeHtml(item.numero)}</strong><br><small>${escapeHtml(item.atendente_nome || '-')}</small></td>
            <td>${formatDate(item.concluida_em)}</td><td>${escapeHtml(item.tutor_nome || 'Nao identificado')}</td><td>${escapeHtml(item.itens_total)}</td>
            <td>${escapeHtml(label(item.forma_pagamento))}</td><td><strong>${money(item.total)}</strong></td><td>${badge(item.status)}</td>
            <td><div class="row-actions">${permissions.cancelar_venda && item.status === 'concluida' ? `<button type="button" data-cancel-sale="${item.id}">Cancelar</button>` : ''}</div></td></tr>
        `).join('') : '<tr><td colspan="8">Nenhuma venda encontrada.</td></tr>';
    }

    function productOptions(selected = '') {
        return state.products.filter((item) => Number(item.ativo) === 1).map((item) =>
            `<option value="${item.id}" data-price="${escapeHtml(item.preco_venda)}" ${String(item.id) === String(selected) ? 'selected' : ''}>${escapeHtml(item.nome)} - ${money(item.preco_venda)} | ${escapeHtml(item.estoque_atual)} ${escapeHtml(item.unidade)}</option>`
        ).join('');
    }

    function addSaleItem(selected = '') {
        const container = document.getElementById('saleItems');
        const row = document.createElement('div');
        row.className = 'sale-item-row';
        row.innerHTML = `<label>Produto<select data-sale-product required><option value="">Selecione</option>${productOptions(selected)}</select></label>
            <label>Quantidade<input data-sale-quantity type="number" min="0.001" step="0.001" value="1" required></label>
            <output data-sale-subtotal>${money(0)}</output><button class="sale-remove" type="button" data-remove-sale-item aria-label="Remover item">&times;</button>`;
        container.appendChild(row);
        updateSaleTotal();
    }

    function updateSaleTotal() {
        let subtotal = 0;
        document.querySelectorAll('.sale-item-row').forEach((row) => {
            const option = row.querySelector('[data-sale-product]')?.selectedOptions?.[0];
            const quantity = Number(row.querySelector('[data-sale-quantity]')?.value) || 0;
            const line = (Number(option?.dataset.price) || 0) * quantity;
            row.querySelector('[data-sale-subtotal]').textContent = money(line);
            subtotal += line;
        });
        const discount = Number(document.getElementById('saleForm')?.elements.desconto.value) || 0;
        document.getElementById('saleEstimatedTotal').textContent = money(Math.max(0, subtotal - discount));
    }

    async function openSale() {
        const form = document.getElementById('saleForm');
        const [owners] = await Promise.all([ensureOwners(), ensureProducts()]);
        form.reset();
        setOptions(form.elements.tutor_id, owners, 'id', (item) => `${item.nome} - ${item.telefone}`, 'Consumidor nao identificado');
        document.getElementById('saleItems').innerHTML = '';
        addSaleItem();
        openDialog('saleDialog');
    }

    async function saveSale(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const data = readForm(form);
        data.itens = [...document.querySelectorAll('.sale-item-row')].map((row) => ({
            produto_id: row.querySelector('[data-sale-product]').value,
            quantidade: row.querySelector('[data-sale-quantity]').value,
        }));
        const payload = await api('vendas.php', { method: 'POST', body: data });
        form.closest('dialog').close();
        toast(`${payload.mensagem} ${payload.data?.numero || ''}`.trim());
        await Promise.all([loadSales(), loadCommerceDashboard()]);
        state.products = [];
    }

    async function cancelSale(id) {
        const reason = window.prompt('Informe o motivo do cancelamento:');
        if (!reason?.trim()) return;
        const payload = await api(`vendas.php?id=${id}`, { method: 'PUT', body: { motivo: reason.trim() } });
        toast(payload.mensagem || 'Venda cancelada.');
        await Promise.all([loadSales(), loadCommerceDashboard()]);
        state.products = [];
    }

    const loaders = { banho_tosa: loadGrooming, produtos: loadProducts, vendas: loadSales };
    document.querySelectorAll('[data-view]').forEach((button) => {
        if (!loaders[button.dataset.view]) return;
        button.addEventListener('click', () => {
            document.getElementById('pageTitle').textContent = titles[button.dataset.view];
            loaders[button.dataset.view]().catch((error) => toast(error.message, 'error'));
        });
    });

    document.querySelectorAll('[data-commerce-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.getElementById('newGroomingButton')?.addEventListener('click', () => openGrooming().catch((error) => toast(error.message, 'error')));
    document.getElementById('newServiceButton')?.addEventListener('click', () => { document.getElementById('serviceForm').reset(); openDialog('serviceDialog'); });
    document.getElementById('newProductButton')?.addEventListener('click', () => openProduct().catch((error) => toast(error.message, 'error')));
    document.getElementById('newSaleButton')?.addEventListener('click', () => openSale().catch((error) => toast(error.message, 'error')));
    document.getElementById('addSaleItemButton')?.addEventListener('click', () => addSaleItem());
    document.getElementById('groomingForm')?.addEventListener('submit', (event) => saveGrooming(event).catch((error) => toast(error.message, 'error')));
    document.getElementById('serviceForm')?.addEventListener('submit', (event) => saveService(event).catch((error) => toast(error.message, 'error')));
    document.getElementById('productForm')?.addEventListener('submit', (event) => saveProduct(event).catch((error) => toast(error.message, 'error')));
    document.getElementById('stockForm')?.addEventListener('submit', (event) => saveStock(event).catch((error) => toast(error.message, 'error')));
    document.getElementById('saleForm')?.addEventListener('submit', (event) => saveSale(event).catch((error) => toast(error.message, 'error')));
    document.getElementById('groomingSearch')?.addEventListener('input', debounce(() => loadGrooming().catch((error) => toast(error.message, 'error'))));
    document.getElementById('groomingStatusFilter')?.addEventListener('change', () => loadGrooming().catch((error) => toast(error.message, 'error')));
    document.getElementById('productSearch')?.addEventListener('input', debounce(() => loadProducts().catch((error) => toast(error.message, 'error'))));
    document.getElementById('lowStockFilter')?.addEventListener('change', () => loadProducts().catch((error) => toast(error.message, 'error')));
    document.getElementById('saleSearch')?.addEventListener('input', debounce(() => loadSales().catch((error) => toast(error.message, 'error'))));
    document.getElementById('saleStatusFilter')?.addEventListener('change', () => loadSales().catch((error) => toast(error.message, 'error')));
    document.getElementById('saleForm')?.addEventListener('input', updateSaleTotal);
    document.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.editGrooming) openGrooming(button.dataset.editGrooming).catch((error) => toast(error.message, 'error'));
        if (button.dataset.editProduct) openProduct(button.dataset.editProduct).catch((error) => toast(error.message, 'error'));
        if (button.dataset.stockProduct) openStock(button.dataset.stockProduct).catch((error) => toast(error.message, 'error'));
        if (button.dataset.cancelSale) cancelSale(button.dataset.cancelSale).catch((error) => toast(error.message, 'error'));
        if (button.hasAttribute('data-remove-sale-item')) {
            button.closest('.sale-item-row')?.remove();
            if (!document.querySelector('.sale-item-row')) addSaleItem();
            updateSaleTotal();
        }
    });

    loadCommerceDashboard().catch((error) => {
        document.getElementById('commerceMetricGrid')?.setAttribute('data-error', error.message);
    });
})();
