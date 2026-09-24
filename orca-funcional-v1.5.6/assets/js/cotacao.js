/* cotacao.js — pedido de cotação dentro da tela do orçamento.
   Seleção de itens na própria tabela (como no Gmail/Drive), barra de ações flutuante
   e painel lateral em 3 passos: itens → fornecedores → envio. */
(function () {
    'use strict';

    const drawer = document.querySelector('[data-cotacao]');
    const table = document.querySelector('[data-itens-tabela]');
    if (!drawer || !table) return;

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fold = s => String(s ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const brl = v => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = v => Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
    const plural = (n, um, varios) => `${n} ${n === 1 ? um : varios}`;

    const overlay = $('[data-drawer-overlay]');
    const bulk = $('[data-bulk-bar]');
    const cfg = drawer.dataset;
    let fornecedores = [];
    try { fornecedores = JSON.parse(cfg.fornecedores || '[]'); } catch (e) { fornecedores = []; }

    /* ---------- Seleção na tabela ---------- */
    const rows = $$('tr[data-item]', table).map(tr => ({ tr, data: JSON.parse(tr.dataset.item), check: $('[data-item-check]', tr) }));
    const byId = new Map(rows.map(r => [r.data.id, r]));
    const selItens = new Set();
    const selForn = new Set();
    const etapaRows = $$('tr[data-etapa-row]', table).map(tr => {
        const membros = [];
        for (let n = tr.nextElementSibling; n && !n.matches('[data-etapa-row]'); n = n.nextElementSibling) {
            if (n.dataset.item) membros.push(byId.get(JSON.parse(n.dataset.item).id));
        }
        return { tr, check: $('[data-etapa-check]', tr), membros };
    });
    const todos = $('[data-itens-todos]', table);
    const visiveis = lista => lista.filter(r => !r.tr.classList.contains('is-filtered'));

    function sync() {
        rows.forEach(r => { const on = selItens.has(r.data.id); r.check.checked = on; r.tr.classList.toggle('is-selected', on); });
        const marcar = (check, lista) => {
            const n = lista.filter(r => selItens.has(r.data.id)).length;
            check.checked = lista.length > 0 && n === lista.length;
            check.indeterminate = n > 0 && n < lista.length;
        };
        etapaRows.forEach(e => marcar(e.check, visiveis(e.membros)));
        if (todos) marcar(todos, visiveis(rows));
        const total = [...selItens].reduce((s, id) => s + (byId.get(id)?.data.total || 0), 0);
        $('[data-bulk-count]').textContent = plural(selItens.size, 'item selecionado', 'itens selecionados');
        $('[data-bulk-total]').textContent = selItens.size ? 'estimado ' + brl(total) : '';
        bulk.classList.toggle('is-visible', selItens.size > 0 && !drawer.classList.contains('is-open'));
    }

    rows.forEach(r => r.check.addEventListener('change', () => { r.check.checked ? selItens.add(r.data.id) : selItens.delete(r.data.id); sync(); }));
    etapaRows.forEach(e => e.check.addEventListener('change', () => {
        visiveis(e.membros).forEach(r => e.check.checked ? selItens.add(r.data.id) : selItens.delete(r.data.id));
        sync();
    }));
    if (todos) todos.addEventListener('change', () => { visiveis(rows).forEach(r => todos.checked ? selItens.add(r.data.id) : selItens.delete(r.data.id)); sync(); });
    $('[data-bulk-limpar]').addEventListener('click', () => { selItens.clear(); sync(); });

    // Shift+clique seleciona um intervalo, como nos gerenciadores de arquivos.
    let ultimo = null;
    rows.forEach((r, i) => r.check.addEventListener('click', ev => {
        if (ev.shiftKey && ultimo !== null) {
            const [a, b] = [Math.min(ultimo, i), Math.max(ultimo, i)];
            visiveis(rows.slice(a, b + 1)).forEach(x => r.check.checked ? selItens.add(x.data.id) : selItens.delete(x.data.id));
            sync();
        }
        ultimo = i;
    }));

    const filtro = $('[data-itens-filtro]');
    if (filtro) filtro.addEventListener('input', () => {
        const termos = fold(filtro.value).split(/\s+/).filter(Boolean);
        rows.forEach(r => {
            const alvo = fold(r.tr.textContent + ' ' + r.data.etapa);
            r.tr.classList.toggle('is-filtered', !termos.every(t => alvo.includes(t)));
        });
        etapaRows.forEach(e => e.tr.classList.toggle('is-filtered', visiveis(e.membros).length === 0));
        const hint = $('[data-itens-hint]');
        if (hint) hint.textContent = termos.length ? `${visiveis(rows).length} de ${rows.length} itens — marque o topo da tabela para selecionar todos os filtrados.` : 'Marque itens ou etapas inteiras para pedir cotação só do que precisa.';
        sync();
    });

    /* ---------- Painel ---------- */
    let passo = 1;
    let ultimoFoco = null;

    function abrir() {
        if (!selItens.size) rows.forEach(r => selItens.add(r.data.id)); // nada marcado: cota o orçamento inteiro
        ultimoFoco = document.activeElement;
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('cmdk-open');
        irPara(passo === 4 ? 1 : passo);
        sync();
        setTimeout(() => $('.drawer-body', drawer).focus?.(), 50);
    }
    function fechar() {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('cmdk-open');
        if (passo === 4) { location.hash = 'cotacoes'; location.reload(); return; }
        sync();
        if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
    }
    $$('[data-cotar-abrir]').forEach(b => b.addEventListener('click', abrir));
    $$('[data-drawer-fechar]', drawer).forEach(b => b.addEventListener('click', fechar));
    overlay.addEventListener('click', fechar);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && drawer.classList.contains('is-open') && !document.querySelector('.cmdk-overlay:not([hidden])')) fechar(); });

    function irPara(n) {
        if (n >= 2 && !selItens.size) n = 1;
        if (n >= 3 && !selForn.size) n = 2;
        passo = n;
        $$('[data-passo]', drawer).forEach(s => { s.hidden = Number(s.dataset.passo) !== n; });
        $$('[data-passo-ir]', drawer).forEach(b => {
            const k = Number(b.dataset.passoIr);
            b.classList.toggle('is-active', k === n);
            b.classList.toggle('is-done', k < n);
            b.disabled = n === 4;
        });
        if (n === 1) renderItens();
        if (n === 2) renderForn();
        if (n === 3) previa();
        rodape();
    }
    $$('[data-passo-ir]', drawer).forEach(b => b.addEventListener('click', () => irPara(Number(b.dataset.passoIr))));
    $('[data-passo-voltar]', drawer).addEventListener('click', () => passo > 1 ? irPara(passo - 1) : fechar());
    $('[data-passo-avancar]', drawer).addEventListener('click', () => {
        if (passo < 3) irPara(passo + 1);
        else if (passo === 3) enviar();
        else fechar();
    });

    function rodape() {
        const avancar = $('[data-passo-avancar]', drawer);
        const voltar = $('[data-passo-voltar]', drawer);
        const info = $('[data-rodape-info]', drawer);
        const canal = $('input[name="canalCotacao"]:checked', drawer)?.value;
        voltar.style.visibility = passo === 4 ? 'hidden' : '';
        voltar.textContent = passo === 1 ? 'Cancelar' : 'Voltar';
        info.textContent = passo === 4 ? '' : `${plural(selItens.size, 'item', 'itens')} · ${plural(selForn.size, 'fornecedor', 'fornecedores')}`;
        avancar.disabled = (passo === 1 && !selItens.size) || (passo >= 2 && passo < 4 && !selForn.size);
        avancar.innerHTML = {
            1: 'Escolher fornecedores <i class="fa-solid fa-arrow-right"></i>',
            2: 'Revisar envio <i class="fa-solid fa-arrow-right"></i>',
            3: canal === 'manual' ? `<i class="fa-solid fa-paper-plane"></i> Gerar ${plural(selForn.size, 'pedido', 'pedidos')}` : `<i class="fa-solid fa-paper-plane"></i> Enviar para ${plural(selForn.size, 'fornecedor', 'fornecedores')}`,
            4: 'Concluir',
        }[passo];
    }

    /* Passo 1: itens */
    function renderItens() {
        const ul = $('[data-lista-itens]', drawer);
        const ids = rows.map(r => r.data.id).filter(id => selItens.has(id));
        const total = ids.reduce((s, id) => s + byId.get(id).data.total, 0);
        $('[data-resumo-itens]', drawer).textContent = `${plural(ids.length, 'item', 'itens')} · ${brl(total)} estimado`;
        const limite = 150;
        ul.innerHTML = ids.slice(0, limite).map(id => {
            const d = byId.get(id).data;
            return `<li class="pick-row"><span class="pick-main"><strong title="${esc(d.descricao)}">${esc(d.descricao)}</strong><small>${num(d.quantidade)} ${esc(d.unidade)}${d.etapa ? ' · ' + esc(d.etapa) : ''}</small></span>
                <span class="pick-meta">${brl(d.total)}</span>
                <button type="button" class="pick-remove" data-remover="${id}" aria-label="Remover ${esc(d.descricao)}"><i class="fa-solid fa-xmark"></i></button></li>`;
        }).join('') + (ids.length > limite ? `<li class="pick-row"><span class="pick-main"><small>… e mais ${ids.length - limite} itens</small></span></li>` : '')
            || '<li class="pick-row"><span class="pick-main"><small>Nenhum item. Feche o painel e marque os itens na tabela.</small></span></li>';
        rodape();
    }
    $('[data-lista-itens]', drawer).addEventListener('click', e => {
        const b = e.target.closest('[data-remover]');
        if (!b) return;
        selItens.delete(Number(b.dataset.remover));
        sync();
        renderItens();
    });

    /* Passo 2: fornecedores */
    function categoriasSelecionadas() {
        return new Set([...selItens].map(id => byId.get(id)?.data.categoria).filter(Boolean));
    }
    function renderForn() {
        const busca = fold($('[data-forn-busca]', drawer).value).split(/\s+/).filter(Boolean);
        const cats = categoriasSelecionadas();
        const lista = fornecedores
            .map(f => ({ ...f, atende: f.categorias.some(c => cats.has(c)) }))
            .filter(f => busca.every(t => fold(`${f.nome} ${f.cidade} ${f.email}`).includes(t)))
            .sort((a, b) => (b.atende - a.atende) || (b.respondidas - a.respondidas) || a.nome.localeCompare(b.nome, 'pt-BR'));
        $('[data-resumo-forn]', drawer).textContent = selForn.size ? `${plural(selForn.size, 'selecionado', 'selecionados')} de ${fornecedores.length}` : `${plural(fornecedores.length, 'fornecedor ativo', 'fornecedores ativos')}`;
        const statusTxt = { pendente: 'pendente aqui', enviada: 'já enviado aqui', respondida: 'já respondeu aqui', aceita: 'aceita aqui', recusada: 'recusou aqui' };
        $('[data-lista-forn]', drawer).innerHTML = lista.map(f => {
            const contato = [f.email && '<i class="fa-regular fa-envelope"></i> ' + esc(f.email), f.whatsapp && '<i class="fa-brands fa-whatsapp"></i> ' + esc(f.whatsapp)].filter(Boolean).join(' · ');
            const tags = [
                f.atende ? '<span class="pick-tag">Atende a categoria</span>' : '',
                f.respondidas ? `<span class="pick-tag">Respondeu ${f.respondidas}×</span>` : '',
                f.statusAqui ? `<span class="pick-tag is-warn">${esc(statusTxt[f.statusAqui] || f.statusAqui)}</span>` : '',
                !f.email && !f.whatsapp ? '<span class="pick-tag is-warn">Sem contato</span>' : '',
            ].join('');
            return `<li><label class="pick-row"><input type="checkbox" value="${f.id}" ${selForn.has(f.id) ? 'checked' : ''} data-forn-check>
                <span class="pick-main"><strong>${esc(f.nome)}</strong><small>${contato || 'Sem e-mail ou WhatsApp cadastrado'}${f.cidade ? ' · ' + esc(f.cidade) : ''}</small></span>${tags}</label></li>`;
        }).join('') || `<li class="pick-row"><span class="pick-main"><small>${fornecedores.length ? 'Nenhum fornecedor com esse nome.' : 'Nenhum fornecedor cadastrado ainda.'} Use <strong>Novo fornecedor</strong> para cadastrar sem sair daqui.</small></span></li>`;
        rodape();
    }
    $('[data-lista-forn]', drawer).addEventListener('change', e => {
        const c = e.target.closest('[data-forn-check]');
        if (!c) return;
        c.checked ? selForn.add(Number(c.value)) : selForn.delete(Number(c.value));
        $('[data-resumo-forn]', drawer).textContent = `${plural(selForn.size, 'selecionado', 'selecionados')} de ${fornecedores.length}`;
        rodape();
    });
    $('[data-forn-busca]', drawer).addEventListener('input', renderForn);

    const formForn = $('[data-forn-form]', drawer);
    $('[data-forn-novo]', drawer).addEventListener('click', () => {
        formForn.hidden = !formForn.hidden;
        if (!formForn.hidden) {
            const termo = $('[data-forn-busca]', drawer).value.trim();
            if (termo && !formForn.nome.value) formForn.nome.value = termo;
            formForn.nome.focus();
        }
    });
    formForn.addEventListener('submit', async e => {
        e.preventDefault();
        const botao = $('button[type="submit"]', formForn);
        botao.disabled = true;
        try {
            const body = new URLSearchParams(new FormData(formForn));
            body.set('csrf_token', cfg.csrf);
            const res = await fetch(cfg.fornecedorUrl, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await res.json().catch(() => ({ ok: false, erro: 'Resposta inválida do servidor.' }));
            if (!data.ok) throw new Error(data.erro || 'Não foi possível cadastrar.');
            const f = data.fornecedor;
            if (!fornecedores.some(x => x.id === f.id)) fornecedores.push({ ...f, cidade: '', categorias: [], cotacoes: 0, respondidas: 0, statusAqui: null });
            selForn.add(f.id);
            formForn.reset();
            formForn.hidden = true;
            $('[data-forn-busca]', drawer).value = '';
            renderForn();
            if (typeof showToast === 'function') showToast(data.existente ? `${f.nome} já estava cadastrado e foi selecionado.` : `${f.nome} cadastrado e selecionado.`, 'success');
        } catch (err) {
            alert(err.message);
        } finally {
            botao.disabled = false;
        }
    });

    /* Passo 3: envio */
    function corpo(extra = {}) {
        const body = new URLSearchParams();
        body.set('csrf_token', cfg.csrf);
        body.set('orcamento_id', cfg.orcamento);
        body.set('canal', $('input[name="canalCotacao"]:checked', drawer).value);
        body.set('prazo', $('[data-cot-prazo]', drawer).value);
        body.set('complemento', $('[data-cot-complemento]', drawer).value);
        rows.forEach(r => { if (selItens.has(r.data.id)) body.append('itens[]', r.data.id); });
        selForn.forEach(id => body.append('fornecedor_ids[]', id));
        Object.entries(extra).forEach(([k, v]) => body.set(k, v));
        return body;
    }
    async function post(body) {
        const res = await fetch(cfg.enviarUrl, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json().catch(() => ({ ok: false, erro: 'Sessão expirada ou resposta inválida. Recarregue a página.' }));
        if (!data.ok) throw new Error(data.erro || 'Falha ao processar.');
        return data;
    }
    let timerPrevia;
    async function previa() {
        clearTimeout(timerPrevia);
        timerPrevia = setTimeout(async () => {
            const alvo = $('[data-previa]', drawer);
            const primeiro = fornecedores.find(f => selForn.has(f.id));
            $('[data-previa-para]', drawer).textContent = primeiro ? `(para ${primeiro.nome}${selForn.size > 1 ? ` e mais ${selForn.size - 1}` : ''})` : '';
            try { alvo.textContent = (await post(corpo({ previa: 1 }))).mensagem; } catch (err) { alvo.textContent = err.message; }
        }, 250);
    }
    $$('input[name="canalCotacao"]', drawer).forEach(r => r.addEventListener('change', rodape));
    $('[data-cot-prazo]', drawer).addEventListener('change', previa);
    $('[data-cot-complemento]', drawer).addEventListener('input', previa);

    async function enviar() {
        const botao = $('[data-passo-avancar]', drawer);
        botao.disabled = true;
        botao.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Enviando…';
        try {
            const data = await post(corpo());
            passo = 4;
            $('[data-resultados]', drawer).innerHTML = data.resultados.map((r, i) => `
                <div class="send-result">
                    <span class="send-result-icon ${r.ok ? 'ok' : 'warn'}"><i class="fa-solid ${r.ok ? 'fa-check' : 'fa-exclamation'}"></i></span>
                    <span class="pick-main"><strong>${esc(r.fornecedor)}</strong><small class="${r.ok ? '' : 'is-error'}">${esc(r.detalhe)}</small></span>
                    ${r.whatsapp ? `<a class="btn btn-sm btn-outline" href="${esc(r.whatsapp)}" target="_blank" rel="noopener" title="Abrir conversa com a mensagem pronta"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>` : ''}
                    ${r.email ? `<a class="btn btn-sm btn-outline" href="${esc(r.email)}" title="Abrir no seu e-mail"><i class="fa-regular fa-envelope"></i> E-mail</a>` : ''}
                    ${r.mensagem ? `<button type="button" class="btn btn-sm btn-outline" data-copiar="${i}" title="Copiar mensagem"><i class="fa-regular fa-copy"></i> Copiar</button>` : ''}
                    ${r.url ? `<a class="btn btn-sm btn-outline" href="${esc(r.url)}" title="Abrir cotação">Ver</a>` : ''}
                </div>`).join('');
            $('[data-resultados]', drawer).onclick = async e => {
                const b = e.target.closest('[data-copiar]');
                if (!b) return;
                try { await navigator.clipboard.writeText(data.resultados[Number(b.dataset.copiar)].mensagem); b.innerHTML = '<i class="fa-solid fa-check"></i> Copiado'; } catch (err) { alert('Não foi possível copiar.'); }
            };
            irPara(4);
            if (typeof showToast === 'function') showToast(`${plural(data.resultados.length, 'pedido registrado', 'pedidos registrados')}.`, 'success');
        } catch (err) {
            alert(err.message);
            rodape();
        }
    }

    // Voltar do painel direto para a aba de cotações.
    if (location.hash === '#cotacoes' && typeof switchTab === 'function') switchTab('tabsOrc', 'tabCotacoes');
    sync();
    // Vindo de "Pedir cotação" em outra tela: abre o painel direto.
    if (location.hash === '#cotar') { history.replaceState(null, '', location.pathname + location.search); abrir(); }
})();
