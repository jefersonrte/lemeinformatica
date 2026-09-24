/* consulta.js — paleta de busca global (Ctrl+K / "/"), filtros que aplicam sozinhos
   e buscas recentes. Padrão das paletas do GitHub, Linear e VS Code. */
(function () {
    'use strict';

    const store = {
        get(key) { try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { return []; } },
        push(key, value, max = 6) {
            value = String(value || '').trim();
            if (!value) return;
            try {
                const list = store.get(key).filter(v => v.toLowerCase() !== value.toLowerCase());
                list.unshift(value);
                localStorage.setItem(key, JSON.stringify(list.slice(0, max)));
            } catch (e) { /* armazenamento indisponível: segue sem histórico */ }
        },
    };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fold = s => String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const brl = v => 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function highlight(text, terms) {
        text = String(text ?? '');
        if (!terms.length) return esc(text);
        const f = fold(text);
        const marks = new Array(text.length).fill(false);
        terms.forEach(t => {
            const ft = fold(t);
            if (!ft) return;
            let i = f.indexOf(ft);
            while (i !== -1) { for (let k = i; k < i + ft.length; k++) marks[k] = true; i = f.indexOf(ft, i + ft.length); }
        });
        let out = '', open = false;
        for (let i = 0; i < text.length; i++) {
            if (marks[i] !== open) { out += marks[i] ? '<mark>' : '</mark>'; open = marks[i]; }
            out += esc(text[i]);
        }
        return out + (open ? '</mark>' : '');
    }

    /* ---------- Paleta ---------- */
    const trigger = document.querySelector('[data-search-open]');
    const endpoint = trigger ? trigger.dataset.searchUrl : null;
    let dialog, input, list, status, items = [], active = -1, timer, controller, lastFocus;

    const statusLabel = {
        rascunho: 'Rascunho', aguardando_cotacao: 'Aguard. cotação', cotado: 'Cotado', aprovado: 'Aprovado', reprovado: 'Reprovado', cancelado: 'Cancelado',
        planejamento: 'Planejamento', em_andamento: 'Em andamento', pausada: 'Pausada', concluida: 'Concluída', cancelada: 'Cancelada',
    };

    function pages() {
        const seen = new Set();
        return [...document.querySelectorAll('.sidebar-nav a[href]')].filter(a => !seen.has(a.href) && seen.add(a.href)).map(a => ({
            titulo: a.getAttribute('title') || a.textContent.trim(),
            detalhe: a.closest('.nav-group')?.querySelector('.nav-group-toggle .nav-label')?.textContent.trim() || 'Página',
            url: a.href,
            icone: a.querySelector('i')?.className || 'fa-solid fa-arrow-right',
        }));
    }

    function build() {
        dialog = document.createElement('div');
        dialog.className = 'cmdk-overlay';
        dialog.hidden = true;
        dialog.innerHTML = `
            <div class="cmdk" role="dialog" aria-modal="true" aria-label="Buscar no sistema">
                <div class="cmdk-input">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" placeholder="Buscar orçamentos, obras, clientes, materiais ou páginas…" autocomplete="off" spellcheck="false"
                        role="combobox" aria-expanded="true" aria-controls="cmdkList" aria-autocomplete="list">
                    <kbd>Esc</kbd>
                </div>
                <div class="cmdk-list" id="cmdkList" role="listbox"></div>
                <div class="cmdk-foot">
                    <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span><span><kbd>Enter</kbd> abrir</span><span><kbd>Ctrl</kbd>+<kbd>Enter</kbd> ver todos</span>
                    <span class="cmdk-status" aria-live="polite"></span>
                </div>
            </div>`;
        document.body.appendChild(dialog);
        input = dialog.querySelector('input');
        list = dialog.querySelector('.cmdk-list');
        status = dialog.querySelector('.cmdk-status');
        dialog.addEventListener('mousedown', e => { if (e.target === dialog) close(); });
        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 160); renderLocal(); });
        input.addEventListener('keydown', onKey);
        list.addEventListener('mousemove', e => {
            const el = e.target.closest('[data-idx]');
            if (el && Number(el.dataset.idx) !== active) setActive(Number(el.dataset.idx));
        });
        list.addEventListener('click', e => {
            const el = e.target.closest('[data-idx]');
            if (el) { e.preventDefault(); go(Number(el.dataset.idx)); }
        });
    }

    function open(prefill) {
        if (!endpoint) return;
        if (!dialog) build();
        lastFocus = document.activeElement;
        dialog.hidden = false;
        document.documentElement.classList.add('cmdk-open');
        input.value = prefill || '';
        renderLocal();
        if (input.value) search();
        requestAnimationFrame(() => { input.focus(); input.select(); });
    }

    function close() {
        if (!dialog || dialog.hidden) return;
        dialog.hidden = true;
        document.documentElement.classList.remove('cmdk-open');
        if (controller) controller.abort();
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    function render(groups, terms, empty) {
        items = [];
        let html = '';
        groups.forEach(g => {
            if (!g.itens.length) return;
            html += `<div class="cmdk-group"><div class="cmdk-group-title"><span>${esc(g.titulo)}</span>${g.total > g.itens.length && g.mais ? `<a href="${esc(g.mais)}">ver ${g.total}</a>` : ''}</div>`;
            g.itens.forEach(it => {
                const idx = items.length;
                items.push(it);
                html += `<a class="cmdk-item" href="${esc(it.url)}" role="option" id="cmdkOpt${idx}" data-idx="${idx}">
                    <span class="cmdk-icon"><i class="${esc(it.icone || g.icone || 'fa-solid fa-arrow-right')}" aria-hidden="true"></i></span>
                    <span class="cmdk-text"><strong>${highlight(it.titulo, terms)}</strong>${it.detalhe ? `<small>${highlight(it.detalhe, terms)}</small>` : ''}</span>
                    ${it.valor != null ? `<span class="cmdk-meta">${brl(it.valor)}</span>` : ''}
                    ${it.status ? `<span class="cmdk-badge">${esc(statusLabel[it.status] || it.status)}</span>` : ''}
                    <i class="fa-solid fa-arrow-turn-down cmdk-enter" aria-hidden="true"></i></a>`;
            });
            html += '</div>';
        });
        list.innerHTML = html || `<div class="cmdk-empty">${empty || 'Nada encontrado. Tente outra palavra ou <strong>Ctrl+Enter</strong> para a busca completa.'}</div>`;
        setActive(items.length ? 0 : -1);
    }

    function renderLocal() {
        const q = input.value.trim();
        const terms = q ? q.split(/\s+/) : [];
        const pg = pages().filter(p => terms.every(t => fold(p.titulo + ' ' + p.detalhe).includes(fold(t))));
        const groups = [];
        if (!q) {
            const recent = store.get('orca_busca_recente');
            if (recent.length) groups.push({ titulo: 'Buscas recentes', itens: recent.map(r => ({ titulo: r, url: endpoint + '?q=' + encodeURIComponent(r), icone: 'fa-solid fa-clock-rotate-left', recente: r })) });
            groups.push({ titulo: 'Ir para', itens: pg.slice(0, 8) });
        } else if (pg.length) {
            groups.push({ titulo: 'Páginas', itens: pg.slice(0, 4) });
        }
        render(groups, terms, q.length >= 2 ? '<i class="fa-solid fa-circle-notch fa-spin"></i> Buscando…' : 'Digite ao menos 2 letras.');
        status.textContent = '';
    }

    async function search() {
        const q = input.value.trim();
        if (q.length < 2) return;
        if (controller) controller.abort();
        controller = new AbortController();
        status.textContent = 'Buscando…';
        try {
            const res = await fetch(endpoint + '?formato=json&q=' + encodeURIComponent(q), { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok || !(res.headers.get('content-type') || '').includes('json')) throw new Error('sessao');
            const data = await res.json();
            if (input.value.trim() !== q) return;
            const terms = data.termos || [];
            const pg = pages().filter(p => terms.every(t => fold(p.titulo).includes(fold(t)))).slice(0, 3);
            const groups = [...(data.grupos || [])];
            if (pg.length) groups.push({ titulo: 'Páginas', itens: pg });
            groups.push({ titulo: 'Busca completa', itens: [{ titulo: `Ver todos os resultados para “${q}”`, url: endpoint + '?q=' + encodeURIComponent(q), icone: 'fa-solid fa-list' }] });
            render(groups, terms);
            const total = (data.grupos || []).reduce((s, g) => s + g.total, 0);
            status.textContent = total ? `${total} resultado(s)` : 'Nenhum registro';
        } catch (e) {
            if (e.name === 'AbortError') return;
            status.textContent = e.message === 'sessao' ? 'Sessão expirada — recarregue a página.' : 'Falha na busca.';
        }
    }

    function setActive(i) {
        active = i;
        list.querySelectorAll('.cmdk-item').forEach(el => el.classList.toggle('is-active', Number(el.dataset.idx) === i));
        const el = list.querySelector(`[data-idx="${i}"]`);
        input.setAttribute('aria-activedescendant', el ? el.id : '');
        if (el) el.scrollIntoView({ block: 'nearest' });
    }

    function go(i) {
        const it = items[i];
        if (!it) return;
        const q = input.value.trim();
        if (q.length >= 2) store.push('orca_busca_recente', q);
        if (it.recente) { input.value = it.recente; search(); return; }
        location.href = it.url;
    }

    function onKey(e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (items.length) setActive((active + 1) % items.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); if (items.length) setActive((active - 1 + items.length) % items.length); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const q = input.value.trim();
            if ((e.ctrlKey || e.metaKey) && q) { store.push('orca_busca_recente', q); location.href = endpoint + '?q=' + encodeURIComponent(q); }
            else go(active);
        } else if (e.key === 'Escape') { e.preventDefault(); close(); }
        else if (e.key === 'Tab') { e.preventDefault(); }
    }

    if (trigger) trigger.addEventListener('click', () => open());
    document.addEventListener('keydown', e => {
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); dialog && !dialog.hidden ? close() : open(); }
        else if (e.key === '/' && !typing && !document.querySelector('.modal-overlay.open, .modal-overlay.active')) { e.preventDefault(); open(); }
    });

    /* ---------- Filtros que aplicam sozinhos ---------- */
    document.querySelectorAll('[data-autosubmit]').forEach(el => {
        const form = el.form;
        if (!form) return;
        if (el.type === 'search') {
            // O "x" do campo de busca limpa e já refaz a consulta.
            el.addEventListener('search', () => { if (el.value === '') form.requestSubmit ? form.requestSubmit() : form.submit(); });
        } else {
            el.addEventListener('change', () => form.requestSubmit ? form.requestSubmit() : form.submit());
        }
    });

    /* ---------- Buscas recentes da consulta de preços ---------- */
    document.querySelectorAll('form[data-recent-key]').forEach(form => {
        const key = 'orca_recente_' + form.dataset.recentKey;
        const q = form.querySelector('input[name="q"]');
        if (q && q.value.trim()) store.push(key, q.value);
        form.addEventListener('submit', () => q && store.push(key, q.value));
        const box = document.querySelector(`[data-recent-list="${form.dataset.recentKey}"]`);
        const recent = store.get(key).filter(r => !q || fold(r) !== fold(q.value.trim()));
        if (box && recent.length) {
            box.insertAdjacentHTML('afterbegin', '<span class="suggestion-label">Recentes:</span>'
                + recent.map(r => `<a class="suggestion-chip is-recent" href="?q=${encodeURIComponent(r)}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> ${esc(r)}</a>`).join('')
                + '<span class="suggestion-label">Exemplos:</span>');
        }
    });
})();
