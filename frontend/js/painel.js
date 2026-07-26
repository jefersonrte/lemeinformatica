(() => {
    'use strict';

    const context = document.getElementById('appContext');
    const csrfToken = context?.dataset.csrf || '';
    const canWrite = context?.dataset.canWrite === '1';
    const isViewer = context?.dataset.profile === 'visualizador';

    const byId = id => document.getElementById(id);

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function currency(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
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

        const data = await response.json().catch(() => ({
            ok: false,
            erro: 'A API retornou uma resposta invalida.'
        }));

        if (response.status === 401) {
            window.location.href = 'login.php?erro=sistema';
            throw new Error('Sua sessao expirou.');
        }

        if (!response.ok || data.ok === false) {
            throw new Error(data.erro || 'Nao foi possivel concluir a operacao.');
        }

        return data;
    }

    function setText(id, value) {
        const element = byId(id);
        if (element) {
            element.textContent = value;
        }
    }

    function setStatus(element, message, type = '') {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.className = `form-status${type ? ` ${type}` : ''}`;
    }

    function renderRecentAnimals(rows) {
        const table = byId('recentAnimals');
        if (!table) {
            return;
        }

        table.innerHTML = rows.length
            ? rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.nome)}</td>
                    <td>${escapeHtml(row.raca)}</td>
                    <td>${escapeHtml(row.porte)}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="3">Nenhum animal cadastrado.</td></tr>';
    }

    function renderRecentFoods(rows) {
        const table = byId('recentFoods');
        if (!table) {
            return;
        }

        table.innerHTML = rows.length
            ? rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.nome)}</td>
                    <td>${escapeHtml(row.categoria)}</td>
                    <td>${currency(row.preco)}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="3">Nenhum alimento cadastrado.</td></tr>';
    }

    function renderViewerChart(id, rows, limit = 8) {
        const container = byId(id);
        if (!container) {
            return;
        }

        const chartRows = (Array.isArray(rows) ? rows : [])
            .map(row => ({
                label: String(row.label || 'Sem identificacao'),
                total: Number(row.total || 0)
            }))
            .filter(row => row.total > 0)
            .slice(0, limit);

        if (chartRows.length === 0) {
            container.innerHTML = '<p class="chart-empty">Nenhum dado disponivel.</p>';
            return;
        }

        const max = Math.max(...chartRows.map(row => row.total), 1);
        container.innerHTML = chartRows.map(row => {
            const width = Math.max((row.total / max) * 100, 2);
            return `
                <div class="viewer-bar-row" aria-label="${escapeHtml(row.label)}: ${row.total}">
                    <span class="viewer-bar-label" title="${escapeHtml(row.label)}">${escapeHtml(row.label)}</span>
                    <span class="viewer-bar-track" aria-hidden="true">
                        <span class="viewer-bar-fill" style="width: ${width.toFixed(2)}%"></span>
                    </span>
                    <strong>${row.total.toLocaleString('pt-BR')}</strong>
                </div>
            `;
        }).join('');
    }

    async function loadDashboard() {
        const refreshButton = byId('refreshDashboard');
        const status = byId('dashboardStatus');

        if (refreshButton) {
            refreshButton.disabled = true;
        }
        if (status) {
            status.textContent = 'Consultando o banco principal...';
        }

        try {
            const response = await api('dashboard.php');
            const data = response.data || {};

            setText('totalAnimals', Number(data.total_animais || 0).toLocaleString('pt-BR'));
            setText('totalFoods', Number(data.total_alimentos || 0).toLocaleString('pt-BR'));
            setText('totalBreeds', Array.isArray(data.por_raca) ? data.por_raca.length : 0);
            setText('totalCategories', Array.isArray(data.por_categoria_alimento) ? data.por_categoria_alimento.length : 0);
            renderRecentAnimals(Array.isArray(data.recentes) ? data.recentes : []);
            renderRecentFoods(Array.isArray(data.alimentos_recentes) ? data.alimentos_recentes : []);

            if (isViewer) {
                renderViewerChart('viewerSizeChart', data.por_porte, 10);
                renderViewerChart('viewerCategoryChart', data.por_categoria_alimento, 8);
            }

            const updated = `Atualizado em ${new Date().toLocaleString('pt-BR')}`;
            if (status) {
                status.textContent = updated;
            }
            setText('viewerUpdatedAt', updated);
        } catch (error) {
            if (status) {
                status.textContent = error.message;
            }
            setText('viewerUpdatedAt', error.message);
        } finally {
            if (refreshButton) {
                refreshButton.disabled = false;
            }
        }
    }

    function activateView(viewName) {
        document.querySelectorAll('[data-view-panel]').forEach(panel => {
            panel.hidden = panel.dataset.viewPanel !== viewName;
        });

        document.querySelectorAll('[data-view]').forEach(tab => {
            const active = tab.dataset.view === viewName;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function bindNavigation() {
        document.querySelectorAll('[data-view]').forEach(tab => {
            tab.addEventListener('click', () => activateView(tab.dataset.view));
        });

        document.querySelectorAll('[data-open-view]').forEach(button => {
            button.addEventListener('click', () => activateView(button.dataset.openView));
        });
    }

    function bindAnimalForm() {
        const form = byId('animalForm');
        const status = byId('animalStatus');
        if (!form) {
            return;
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            submit.disabled = true;
            setStatus(status, 'Salvando animal...');

            try {
                await api('animais.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        nome: byId('animalName').value.trim(),
                        raca: byId('animalBreed').value.trim(),
                        porte: byId('animalSize').value
                    })
                });
                form.reset();
                setStatus(status, 'Animal cadastrado com sucesso.', 'success');
                await loadDashboard();
            } catch (error) {
                setStatus(status, error.message, 'error');
            } finally {
                submit.disabled = false;
            }
        });
    }

    function bindFoodForm() {
        const form = byId('foodForm');
        const status = byId('foodStatus');
        if (!form) {
            return;
        }

        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            submit.disabled = true;
            setStatus(status, 'Salvando alimento...');

            try {
                await api('alimentos.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        nome: byId('foodName').value.trim(),
                        categoria: byId('foodCategory').value.trim(),
                        unidade: byId('foodUnit').value,
                        preco: Number(byId('foodPrice').value)
                    })
                });
                form.reset();
                setStatus(status, 'Alimento cadastrado com sucesso.', 'success');
                await loadDashboard();
            } catch (error) {
                setStatus(status, error.message, 'error');
            } finally {
                submit.disabled = false;
            }
        });
    }

    byId('refreshDashboard')?.addEventListener('click', loadDashboard);
    bindNavigation();

    if (canWrite) {
        bindAnimalForm();
        bindFoodForm();
    }

    loadDashboard();
})();
