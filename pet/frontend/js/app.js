(() => {
    'use strict';

    const contextNode = document.getElementById('petContext');
    const context = JSON.parse(contextNode?.textContent || '{}');
    const permissions = context.permissions || {};
    const state = {
        owners: [],
        animals: [],
        appointments: [],
        admissions: [],
        vets: [],
        currentView: 'dashboard',
    };

    const titles = {
        dashboard: 'Dashboard Pet',
        tutores: 'Tutores',
        animais: 'Animais',
        atendimentos: 'Atendimentos',
        internacoes: 'Internacoes',
        equipe: 'Equipe veterinaria',
    };

    const viewLoaders = {
        dashboard: loadDashboard,
        tutores: loadOwners,
        animais: loadAnimals,
        atendimentos: loadAppointments,
        internacoes: loadAdmissions,
        equipe: loadVets,
    };

    const pageTitle = document.getElementById('pageTitle');
    const sidebar = document.getElementById('sidebar');
    const menuButton = document.getElementById('menuButton');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const toastRegion = document.getElementById('toastRegion');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function nl2br(value) {
        return escapeHtml(value || '').replaceAll('\n', '<br>');
    }

    function initials(name) {
        return String(name || '?')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part[0] || '')
            .join('')
            .toUpperCase();
    }

    function avatar(path, name, size = '') {
        if (path) {
            return `<img class="avatar ${size}" src="${escapeHtml(path)}" alt="Foto de ${escapeHtml(name)}">`;
        }
        return `<span class="avatar initials ${size}" aria-hidden="true">${escapeHtml(initials(name))}</span>`;
    }

    function formatDate(value, withTime = false) {
        if (!value) return '-';
        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return escapeHtml(value);
        return new Intl.DateTimeFormat('pt-BR', withTime
            ? { dateStyle: 'short', timeStyle: 'short' }
            : { dateStyle: 'short' }).format(date);
    }

    function toLocalDateTime(value) {
        if (!value) return '';
        return String(value).replace(' ', 'T').slice(0, 16);
    }

    function labelize(value) {
        return String(value || '-').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    function badge(value, extra = '') {
        const safe = String(value || 'indefinido').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        return `<span class="badge ${safe} ${extra}">${escapeHtml(labelize(value))}</span>`;
    }

    function showLoading(visible) {
        if (loadingOverlay) loadingOverlay.hidden = !visible;
    }

    function toast(message, type = 'success') {
        if (!toastRegion) return;
        const item = document.createElement('div');
        item.className = `toast ${type === 'error' ? 'error' : ''}`;
        item.textContent = message;
        toastRegion.appendChild(item);
        window.setTimeout(() => item.remove(), 5000);
    }

    async function api(path, options = {}) {
        const method = String(options.method || 'GET').toUpperCase();
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');

        if (method !== 'GET') {
            headers.set('X-CSRF-TOKEN', context.csrf || '');
        }

        let body = options.body;
        if (body && !(body instanceof FormData) && typeof body !== 'string') {
            headers.set('Content-Type', 'application/json');
            body = JSON.stringify(body);
        }

        const response = await fetch(`api/${path}`, {
            ...options,
            method,
            headers,
            body,
            credentials: 'same-origin',
        });

        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error('O servidor retornou uma resposta invalida.');
        }

        if (response.status === 401) {
            window.location.href = '../login.php?next=pet';
            throw new Error('Sessao expirada.');
        }

        if (!response.ok || payload.ok === false) {
            const error = new Error(payload.erro || 'Nao foi possivel concluir a operacao.');
            error.fields = payload.campos || {};
            error.code = payload.codigo || '';
            throw error;
        }

        return payload;
    }

    function formPayload(form) {
        const payload = {};
        for (const element of form.elements) {
            if (!element.name || element.type === 'file' || element.type === 'submit') continue;
            if (element.type === 'checkbox') {
                payload[element.name] = element.checked;
            } else {
                payload[element.name] = element.value;
            }
        }
        return payload;
    }

    function fillForm(form, data = {}) {
        form.reset();
        form.querySelectorAll('.field-error').forEach((field) => field.classList.remove('field-error'));
        for (const element of form.elements) {
            if (!element.name || !(element.name in data)) continue;
            if (element.type === 'checkbox') {
                element.checked = Number(data[element.name]) === 1 || data[element.name] === true;
            } else if (element.type === 'datetime-local') {
                element.value = toLocalDateTime(data[element.name]);
            } else {
                element.value = data[element.name] ?? '';
            }
        }
        setPhotoPreview(form, data.foto_caminho || '');
    }

    function showFieldErrors(form, fields = {}) {
        let first = null;
        Object.keys(fields).forEach((name) => {
            const field = form.elements.namedItem(name);
            if (field) {
                field.classList.add('field-error');
                field.title = fields[name];
                first ||= field;
            }
        });
        first?.focus();
    }

    function setPhotoPreview(form, path) {
        const preview = form.querySelector('[data-photo-preview]');
        if (!preview) return;
        preview.innerHTML = path ? `<img src="${escapeHtml(path)}" alt="Foto selecionada">` : '<span>Foto</span>';
    }

    async function uploadPhoto(form, type, id) {
        const input = form.elements.namedItem('foto');
        if (!input?.files?.length) return;
        const data = new FormData();
        data.append('tipo', type);
        data.append('id', String(id));
        data.append('foto', input.files[0]);
        await api('fotos.php', { method: 'POST', body: data });
    }

    function openDialog(dialog) {
        if (dialog && !dialog.open) dialog.showModal();
    }

    function closeDialog(dialog) {
        if (dialog?.open) dialog.close();
    }

    async function openView(name) {
        const panel = document.querySelector(`[data-view-panel="${name}"]`);
        if (!panel) return;

        state.currentView = name;
        document.querySelectorAll('[data-view-panel]').forEach((view) => {
            const active = view === panel;
            view.hidden = !active;
            view.classList.toggle('active', active);
        });
        document.querySelectorAll('[data-view]').forEach((button) => {
            button.classList.toggle('active', button.dataset.view === name);
        });
        if (pageTitle) pageTitle.textContent = titles[name] || 'Leme Pet';
        sidebar?.classList.remove('open');
        menuButton?.setAttribute('aria-expanded', 'false');

        try {
            await viewLoaders[name]?.();
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function loadDashboard() {
        const payload = await api('dashboard.php');
        const data = payload.data || {};
        const totals = data.totais || {};

        document.getElementById('metricOwners').textContent = totals.tutores ?? 0;
        document.getElementById('metricAnimals').textContent = totals.animais ?? 0;
        document.getElementById('metricAppointments').textContent = totals.atendimentos_hoje ?? 0;
        document.getElementById('metricAdmissions').textContent = totals.internados ?? 0;

        renderSpecies(data.especies || []);
        if (data.proximos_atendimentos) renderDashboardAppointments(data.proximos_atendimentos);
        if (data.internacoes_ativas) renderDashboardAdmissions(data.internacoes_ativas);
    }

    function renderSpecies(records) {
        const target = document.getElementById('speciesChart');
        if (!target) return;
        if (!records.length) {
            target.innerHTML = '<p class="empty-state">Nenhum animal cadastrado.</p>';
            return;
        }
        const max = Math.max(...records.map((item) => Number(item.total) || 0), 1);
        target.innerHTML = records.map((item) => `
            <div class="bar-row">
                <span title="${escapeHtml(item.nome)}">${escapeHtml(item.nome)}</span>
                <div class="bar-track"><span class="bar-value" style="width:${Math.max(3, Math.round((Number(item.total) / max) * 100))}%"></span></div>
                <strong>${escapeHtml(item.total)}</strong>
            </div>
        `).join('');
    }

    function renderDashboardAppointments(records) {
        const target = document.getElementById('dashboardAppointments');
        if (!target) return;
        target.innerHTML = records.length ? records.map((item) => `
            <tr>
                <td>${formatDate(item.inicio_em, true)}</td>
                <td><strong>${escapeHtml(item.animal_nome)}</strong><br><small>${escapeHtml(item.especie)}</small></td>
                <td>${escapeHtml(item.tutor_nome)}</td>
                <td>${escapeHtml(labelize(item.tipo))}</td>
                <td>${escapeHtml(item.veterinario_nome)}</td>
                <td>${badge(item.status)}</td>
            </tr>
        `).join('') : '<tr><td colspan="6">Nenhum atendimento proximo.</td></tr>';
    }

    function renderDashboardAdmissions(records) {
        const target = document.getElementById('dashboardAdmissions');
        if (!target) return;
        target.innerHTML = records.length ? records.map((item) => `
            <div class="compact-item">
                <div><strong>${escapeHtml(item.animal_nome)}</strong><span>${escapeHtml(item.tutor_nome)} - ${escapeHtml(item.setor || 'Setor nao definido')} / ${escapeHtml(item.leito || 'sem leito')}</span></div>
                ${badge(item.classificacao_risco)}
            </div>
        `).join('') : '<p class="empty-state">Nenhum animal internado.</p>';
    }

    async function loadOwners() {
        const query = document.getElementById('ownerSearch')?.value.trim() || '';
        const payload = await api(`tutores.php?limit=100&q=${encodeURIComponent(query)}`);
        state.owners = payload.data || [];
        const target = document.getElementById('ownersTable');
        const count = document.getElementById('ownerCount');
        if (count) count.textContent = `${payload.meta?.total ?? state.owners.length} registros`;
        if (!target) return;

        target.innerHTML = state.owners.length ? state.owners.map((owner) => `
            <tr>
                <td><div class="person-cell">${avatar(owner.foto_caminho, owner.nome)}<div><strong>${escapeHtml(owner.nome)}</strong><span>${owner.cpf ? `CPF ${escapeHtml(owner.cpf)}` : 'CPF nao informado'}</span></div></div></td>
                <td>${escapeHtml(owner.telefone)}${owner.email ? `<br><small>${escapeHtml(owner.email)}</small>` : ''}</td>
                <td>${escapeHtml([owner.cidade, owner.uf].filter(Boolean).join(' / ') || '-')}</td>
                <td>${escapeHtml(owner.total_animais || 0)}</td>
                <td>${formatDate(owner.criado_em)}</td>
                <td><div class="row-actions">
                    <button type="button" data-owner-animals="${owner.id}">Animais</button>
                    ${permissions.editar_cadastros ? `<button type="button" data-edit-owner="${owner.id}">Editar</button>` : ''}
                </div></td>
            </tr>
        `).join('') : '<tr><td colspan="6">Nenhum tutor encontrado.</td></tr>';
    }

    async function openOwner(id = null) {
        const dialog = document.getElementById('ownerDialog');
        const form = document.getElementById('ownerForm');
        const title = document.getElementById('ownerDialogTitle');
        if (!dialog || !form) return;
        fillForm(form, {});
        title.textContent = 'Novo tutor';
        if (id) {
            const payload = await api(`tutores.php?id=${id}`);
            fillForm(form, payload.data);
            title.textContent = 'Editar tutor';
        }
        openDialog(dialog);
    }

    async function saveOwner(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.elements.id.value;
        showLoading(true);
        try {
            const payload = await api(`tutores.php${id ? `?id=${id}` : ''}`, {
                method: id ? 'PUT' : 'POST',
                body: formPayload(form),
            });
            const savedId = id || payload.data.id;
            await uploadPhoto(form, 'tutor', savedId);
            closeDialog(document.getElementById('ownerDialog'));
            toast(payload.mensagem || 'Tutor salvo.');
            await loadOwners();
        } catch (error) {
            showFieldErrors(form, error.fields);
            toast(error.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    async function loadAnimals(tutorId = '') {
        const query = document.getElementById('animalSearch')?.value.trim() || '';
        const filter = tutorId ? `&tutor_id=${encodeURIComponent(tutorId)}` : '';
        const payload = await api(`animais.php?limit=100&q=${encodeURIComponent(query)}${filter}`);
        state.animals = payload.data || [];
        const target = document.getElementById('animalsTable');
        const count = document.getElementById('animalCount');
        if (count) count.textContent = `${payload.meta?.total ?? state.animals.length} registros`;
        if (!target) return;

        target.innerHTML = state.animals.length ? state.animals.map((animal) => `
            <tr>
                <td><div class="person-cell">${avatar(animal.foto_caminho, animal.nome)}<div><strong>${escapeHtml(animal.nome)}</strong><span>${escapeHtml(animal.especie)}${animal.raca ? ` - ${escapeHtml(animal.raca)}` : ''}</span></div></div></td>
                <td>${escapeHtml(animal.tutor_nome)}<br><small>${escapeHtml(animal.tutor_telefone || '')}</small></td>
                <td>${escapeHtml(labelize(animal.sexo))}</td>
                <td>${formatDate(animal.data_nascimento)}</td>
                <td>${animal.peso_kg ? `${escapeHtml(animal.peso_kg)} kg` : '-'}</td>
                <td>${Number(animal.internado) === 1 ? badge('Internado', 'danger') : badge('Ativo', 'success')}</td>
                <td><div class="row-actions">
                    ${permissions.ver_prontuario ? `<button type="button" data-record="${animal.id}">Prontuario</button>` : ''}
                    ${permissions.editar_cadastros ? `<button type="button" data-edit-animal="${animal.id}">Editar</button>` : ''}
                </div></td>
            </tr>
        `).join('') : '<tr><td colspan="7">Nenhum animal encontrado.</td></tr>';
    }

    async function loadOwnerOptions(select, selected = '') {
        const payload = await api('tutores.php?limit=100');
        select.innerHTML = '<option value="">Selecione</option>' + (payload.data || []).map((owner) =>
            `<option value="${owner.id}">${escapeHtml(owner.nome)} - ${escapeHtml(owner.telefone)}</option>`
        ).join('');
        select.value = String(selected || '');
    }

    async function loadAnimalOptions(select, selected = '') {
        const payload = await api('animais.php?limit=100');
        select.innerHTML = '<option value="">Selecione</option>' + (payload.data || []).map((animal) =>
            `<option value="${animal.id}">${escapeHtml(animal.nome)} - ${escapeHtml(animal.tutor_nome)}</option>`
        ).join('');
        select.value = String(selected || '');
    }

    async function openAnimal(id = null, tutorId = '') {
        const dialog = document.getElementById('animalDialog');
        const form = document.getElementById('animalForm');
        const title = document.getElementById('animalDialogTitle');
        if (!dialog || !form) return;
        fillForm(form, { sexo: 'indefinido', porte: 'nao_aplicavel', tutor_id: tutorId });
        await loadOwnerOptions(form.elements.tutor_id, tutorId);
        title.textContent = 'Novo animal';
        if (id) {
            const payload = await api(`animais.php?id=${id}`);
            await loadOwnerOptions(form.elements.tutor_id, payload.data.tutor_id);
            fillForm(form, payload.data);
            title.textContent = 'Editar animal';
        }
        openDialog(dialog);
    }

    async function saveAnimal(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.elements.id.value;
        showLoading(true);
        try {
            const payload = await api(`animais.php${id ? `?id=${id}` : ''}`, {
                method: id ? 'PUT' : 'POST',
                body: formPayload(form),
            });
            const savedId = id || payload.data.id;
            await uploadPhoto(form, 'animal', savedId);
            closeDialog(document.getElementById('animalDialog'));
            toast(payload.mensagem || 'Animal salvo.');
            await loadAnimals();
        } catch (error) {
            showFieldErrors(form, error.fields);
            toast(error.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    async function loadAppointments() {
        const query = document.getElementById('appointmentSearch')?.value.trim() || '';
        const status = document.getElementById('appointmentStatusFilter')?.value || '';
        const payload = await api(`atendimentos.php?limit=100&q=${encodeURIComponent(query)}&status=${encodeURIComponent(status)}`);
        state.appointments = payload.data || [];
        const target = document.getElementById('appointmentsTable');
        if (!target) return;

        target.innerHTML = state.appointments.length ? state.appointments.map((item) => `
            <tr>
                <td>${formatDate(item.inicio_em, true)}<br><small>${escapeHtml(labelize(item.tipo))}</small></td>
                <td><div class="person-cell">${avatar(item.animal_foto, item.animal_nome)}<div><strong>${escapeHtml(item.animal_nome)}</strong><span>${escapeHtml(item.especie)}</span></div></div></td>
                <td>${escapeHtml(item.tutor_nome)}</td>
                <td>${escapeHtml(item.motivo)}</td>
                <td>${escapeHtml(item.veterinario_nome)}</td>
                <td>${badge(item.status)}</td>
                <td><div class="row-actions"><button type="button" data-edit-appointment="${item.id}">Abrir</button><button type="button" data-record="${item.animal_id}">Historico</button></div></td>
            </tr>
        `).join('') : '<tr><td colspan="7">Nenhum atendimento encontrado.</td></tr>';
    }

    async function loadVetOptions(select, selected = '') {
        if (!select) return;
        const payload = await api('veterinarios.php');
        state.vets = payload.data || [];
        select.innerHTML = '<option value="">A definir</option>' + state.vets
            .filter((vet) => Number(vet.ativo) === 1 || String(vet.id) === String(selected))
            .map((vet) => `<option value="${vet.id}">${escapeHtml(vet.nome)} - CRMV ${escapeHtml(vet.crmv)}/${escapeHtml(vet.uf_crmv)}</option>`)
            .join('');
        select.value = String(selected || '');
    }

    async function openAppointment(id = null, animalId = '') {
        const dialog = document.getElementById('appointmentDialog');
        const form = document.getElementById('appointmentForm');
        const title = document.getElementById('appointmentDialogTitle');
        if (!dialog || !form) return;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        fillForm(form, { animal_id: animalId, tipo: 'consulta', status: 'agendado', inicio_em: now.toISOString().slice(0, 16) });
        await loadAnimalOptions(form.elements.animal_id, animalId);
        if (form.elements.veterinario_id) await loadVetOptions(form.elements.veterinario_id);
        title.textContent = 'Novo atendimento';
        if (id) {
            const payload = await api(`atendimentos.php?id=${id}`);
            await loadAnimalOptions(form.elements.animal_id, payload.data.animal_id);
            if (form.elements.veterinario_id) await loadVetOptions(form.elements.veterinario_id, payload.data.veterinario_id);
            fillForm(form, payload.data);
            title.textContent = 'Atendimento';
        }
        openDialog(dialog);
    }

    async function saveAppointment(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.elements.id.value;
        showLoading(true);
        try {
            const payload = await api(`atendimentos.php${id ? `?id=${id}` : ''}`, {
                method: id ? 'PUT' : 'POST',
                body: formPayload(form),
            });
            closeDialog(document.getElementById('appointmentDialog'));
            toast(payload.mensagem || 'Atendimento salvo.');
            await loadAppointments();
        } catch (error) {
            showFieldErrors(form, error.fields);
            toast(error.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    async function loadAdmissions() {
        const status = document.getElementById('admissionStatusFilter')?.value || '';
        const payload = await api(`internacoes.php?status=${encodeURIComponent(status)}`);
        state.admissions = payload.data || [];
        const target = document.getElementById('admissionsGrid');
        if (!target) return;

        target.innerHTML = state.admissions.length ? state.admissions.map((item) => `
            <article class="admission-card risk-${escapeHtml(item.classificacao_risco)}">
                <div class="admission-card-header">
                    <div><h3>${escapeHtml(item.animal_nome)}</h3><p>${escapeHtml(item.especie)} - Tutor: ${escapeHtml(item.tutor_nome)}</p></div>
                    ${badge(item.classificacao_risco)}
                </div>
                <div class="admission-meta">
                    <div><span>Entrada</span><strong>${formatDate(item.entrada_em, true)}</strong></div>
                    <div><span>Local</span><strong>${escapeHtml(item.setor || '-')} / ${escapeHtml(item.leito || '-')}</strong></div>
                    <div><span>Veterinario</span><strong>${escapeHtml(item.veterinario_nome)}</strong></div>
                    <div><span>Evolucoes</span><strong>${escapeHtml(item.total_evolucoes || 0)}</strong></div>
                </div>
                <p>${escapeHtml(item.motivo)}</p>
                <div class="row-actions">
                    <button type="button" data-record="${item.animal_id}">Prontuario</button>
                    ${permissions.editar_prontuario && item.status === 'ativa' ? `<button type="button" data-evolution="${item.id}">Evolucao</button>` : ''}
                    ${permissions.gerenciar_internacao ? `<button type="button" data-edit-admission="${item.id}">Editar</button>` : ''}
                </div>
            </article>
        `).join('') : '<p class="empty-state">Nenhuma internacao encontrada.</p>';
    }

    async function openAdmission(id = null, animalId = '') {
        const dialog = document.getElementById('admissionDialog');
        const form = document.getElementById('admissionForm');
        const title = document.getElementById('admissionDialogTitle');
        if (!dialog || !form) return;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        fillForm(form, { animal_id: animalId, status: 'ativa', classificacao_risco: 'moderado', entrada_em: now.toISOString().slice(0, 16) });
        await loadAnimalOptions(form.elements.animal_id, animalId);
        await loadVetOptions(form.elements.veterinario_responsavel_id);
        title.textContent = 'Nova internacao';
        if (id) {
            const payload = await api(`internacoes.php?id=${id}`);
            await loadAnimalOptions(form.elements.animal_id, payload.data.animal_id);
            await loadVetOptions(form.elements.veterinario_responsavel_id, payload.data.veterinario_responsavel_id);
            fillForm(form, payload.data);
            title.textContent = 'Internacao';
        }
        openDialog(dialog);
    }

    async function saveAdmission(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.elements.id.value;
        showLoading(true);
        try {
            const payload = await api(`internacoes.php${id ? `?id=${id}` : ''}`, {
                method: id ? 'PUT' : 'POST',
                body: formPayload(form),
            });
            closeDialog(document.getElementById('admissionDialog'));
            toast(payload.mensagem || 'Internacao salva.');
            await loadAdmissions();
        } catch (error) {
            showFieldErrors(form, error.fields);
            toast(error.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    function openEvolution(admissionId) {
        const dialog = document.getElementById('evolutionDialog');
        const form = document.getElementById('evolutionForm');
        if (!dialog || !form) return;
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        fillForm(form, { internacao_id: admissionId, registrado_em: now.toISOString().slice(0, 16) });
        openDialog(dialog);
    }

    async function saveEvolution(event) {
        event.preventDefault();
        const form = event.currentTarget;
        showLoading(true);
        try {
            const payload = await api('evolucoes.php', { method: 'POST', body: formPayload(form) });
            closeDialog(document.getElementById('evolutionDialog'));
            toast(payload.mensagem || 'Evolucao registrada.');
            await loadAdmissions();
        } catch (error) {
            showFieldErrors(form, error.fields);
            toast(error.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    async function loadVets() {
        const payload = await api('veterinarios.php');
        state.vets = payload.data || [];
        const target = document.getElementById('vetsTable');
        if (!target) return;
        target.innerHTML = state.vets.length ? state.vets.map((vet) => `
            <tr>
                <td><div class="person-cell">${avatar(vet.foto_caminho, vet.nome)}<div><strong>${escapeHtml(vet.nome)}</strong><span>${escapeHtml(vet.email)}</span></div></div></td>
                <td>${escapeHtml(vet.crmv)}/${escapeHtml(vet.uf_crmv)}</td>
                <td>${escapeHtml(vet.especialidade || '-')}</td>
                <td>${escapeHtml(vet.telefone_profissional || '-')}</td>
                <td>${Number(vet.ativo) === 1 ? badge('Ativo', 'success') : badge('Inativo')}</td>
                <td><div class="row-actions"><button type="button" data-edit-vet="${vet.id}">Editar</button></div></td>
            </tr>
        `).join('') : '<tr><td colspan="6">Nenhum veterinario vinculado.</td></tr>';
    }

    async function loadEligibleUsers(select, current = null) {
        const payload = await api('veterinarios.php?usuarios=1');
        const users = payload.data || [];
        if (current) {
            users.unshift({ id: current.usuario_id, nome: current.nome, email: current.email, perfil: '' });
        }
        const unique = [...new Map(users.map((user) => [String(user.id), user])).values()];
        select.innerHTML = '<option value="">Selecione</option>' + unique.map((user) =>
            `<option value="${user.id}">${escapeHtml(user.nome)} - ${escapeHtml(user.email)}</option>`
        ).join('');
        select.value = current ? String(current.usuario_id) : '';
    }

    async function openVet(id = null) {
        const dialog = document.getElementById('vetDialog');
        const form = document.getElementById('vetForm');
        const title = document.getElementById('vetDialogTitle');
        if (!dialog || !form) return;
        fillForm(form, { ativo: true });
        title.textContent = 'Vincular veterinario';
        let record = null;
        if (id) {
            record = state.vets.find((vet) => String(vet.id) === String(id)) || null;
            if (!record) await loadVets();
            record = state.vets.find((vet) => String(vet.id) === String(id)) || null;
        }
        await loadEligibleUsers(form.elements.usuario_id, record);
        if (record) {
            fillForm(form, record);
            title.textContent = 'Editar veterinario';
        }
        openDialog(dialog);
    }

    async function saveVet(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const id = form.elements.id.value;
        showLoading(true);
        try {
            const payload = await api(`veterinarios.php${id ? `?id=${id}` : ''}`, {
                method: id ? 'PUT' : 'POST',
                body: formPayload(form),
            });
            const savedId = id || payload.data.id;
            await uploadPhoto(form, 'veterinario', savedId);
            closeDialog(document.getElementById('vetDialog'));
            toast(payload.mensagem || 'Veterinario salvo.');
            await loadVets();
        } catch (error) {
            showFieldErrors(form, error.fields);
            toast(error.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    async function openRecord(animalId) {
        const dialog = document.getElementById('recordDialog');
        const content = document.getElementById('recordContent');
        const title = document.getElementById('recordTitle');
        if (!dialog || !content) return;
        content.innerHTML = '<p class="empty-state">Carregando prontuario...</p>';
        openDialog(dialog);

        try {
            const payload = await api(`historico.php?animal_id=${animalId}`);
            const data = payload.data;
            const animal = data.animal;
            title.textContent = `Prontuario de ${animal.nome}`;

            const appointments = (data.atendimentos || []).map((item) => ({
                date: item.inicio_em,
                html: `
                    <article class="timeline-item">
                        <h4>${escapeHtml(labelize(item.tipo))} ${badge(item.status)}</h4>
                        <time>${formatDate(item.inicio_em, true)} - ${escapeHtml(item.veterinario_nome || 'A definir')}</time>
                        <div class="clinical-copy">
                            <p><strong>Motivo:</strong> ${nl2br(item.motivo || '-')}</p>
                            ${item.anamnese ? `<p><strong>Anamnese:</strong> ${nl2br(item.anamnese)}</p>` : ''}
                            ${item.exame_clinico ? `<p><strong>Exame clinico:</strong> ${nl2br(item.exame_clinico)}</p>` : ''}
                            ${item.diagnostico ? `<p><strong>Diagnostico:</strong> ${nl2br(item.diagnostico)}</p>` : ''}
                            ${item.conduta ? `<p><strong>Conduta:</strong> ${nl2br(item.conduta)}</p>` : ''}
                            ${item.prescricao ? `<p><strong>Prescricao:</strong> ${nl2br(item.prescricao)}</p>` : ''}
                        </div>
                    </article>`,
            }));

            const admissions = (data.internacoes || []).map((item) => ({
                date: item.entrada_em,
                html: `
                    <article class="timeline-item">
                        <h4>Internacao ${badge(item.status)} ${badge(item.classificacao_risco)}</h4>
                        <time>${formatDate(item.entrada_em, true)}${item.saida_em ? ` ate ${formatDate(item.saida_em, true)}` : ''}</time>
                        <div class="clinical-copy">
                            <p><strong>Motivo:</strong> ${nl2br(item.motivo || '-')}</p>
                            ${item.diagnostico_inicial ? `<p><strong>Diagnostico inicial:</strong> ${nl2br(item.diagnostico_inicial)}</p>` : ''}
                            ${item.plano_cuidados ? `<p><strong>Plano de cuidados:</strong> ${nl2br(item.plano_cuidados)}</p>` : ''}
                            ${(item.evolucoes || []).map((evolution) => `
                                <p><strong>Evolucao ${formatDate(evolution.registrado_em, true)}:</strong> ${nl2br(evolution.observacoes)}<br><small>${escapeHtml(evolution.veterinario_nome || '')}</small></p>
                            `).join('')}
                            ${item.resumo_alta ? `<p><strong>Resumo de alta:</strong> ${nl2br(item.resumo_alta)}</p>` : ''}
                        </div>
                    </article>`,
            }));

            const timeline = [...appointments, ...admissions]
                .sort((a, b) => new Date(String(b.date).replace(' ', 'T')) - new Date(String(a.date).replace(' ', 'T')));

            content.innerHTML = `
                <div class="record-summary">
                    ${avatar(animal.foto_caminho, animal.nome, 'large')}
                    <div>
                        <h3>${escapeHtml(animal.nome)}</h3>
                        <p>${escapeHtml(animal.especie)}${animal.raca ? ` - ${escapeHtml(animal.raca)}` : ''} | Tutor: ${escapeHtml(animal.tutor_nome)} | ${escapeHtml(animal.tutor_telefone)}</p>
                        <p>${animal.alergias ? `<strong>Alergias:</strong> ${escapeHtml(animal.alergias)}` : 'Sem alergias registradas.'}</p>
                    </div>
                </div>
                <div class="timeline">
                    <h3>Linha do tempo</h3>
                    ${timeline.length ? timeline.map((item) => item.html).join('') : '<p class="empty-state">Nenhum evento clinico registrado.</p>'}
                </div>
            `;
        } catch (error) {
            content.innerHTML = `<p class="empty-state">${escapeHtml(error.message)}</p>`;
        }
    }

    function debounce(callback, delay = 320) {
        let timer;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), delay);
        };
    }

    document.querySelectorAll('[data-view]').forEach((button) => {
        button.addEventListener('click', () => openView(button.dataset.view));
    });
    document.querySelectorAll('[data-open-view]').forEach((button) => {
        button.addEventListener('click', () => openView(button.dataset.openView));
    });
    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => closeDialog(button.closest('dialog')));
    });
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) closeDialog(dialog);
        });
    });
    document.querySelectorAll('input[type="file"][name="foto"]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = () => setPhotoPreview(input.form, String(reader.result || ''));
            reader.readAsDataURL(file);
        });
    });

    menuButton?.addEventListener('click', () => {
        const open = sidebar?.classList.toggle('open') || false;
        menuButton.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        try {
            if (button.dataset.editOwner) await openOwner(button.dataset.editOwner);
            if (button.dataset.ownerAnimals) {
                await openView('animais');
                await loadAnimals(button.dataset.ownerAnimals);
            }
            if (button.dataset.editAnimal) await openAnimal(button.dataset.editAnimal);
            if (button.dataset.editAppointment) await openAppointment(button.dataset.editAppointment);
            if (button.dataset.editAdmission) await openAdmission(button.dataset.editAdmission);
            if (button.dataset.evolution) openEvolution(button.dataset.evolution);
            if (button.dataset.editVet) await openVet(button.dataset.editVet);
            if (button.dataset.record) await openRecord(button.dataset.record);
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    document.getElementById('refreshDashboard')?.addEventListener('click', () => loadDashboard().catch((error) => toast(error.message, 'error')));
    document.getElementById('newOwnerButton')?.addEventListener('click', () => openOwner().catch((error) => toast(error.message, 'error')));
    document.getElementById('newAnimalButton')?.addEventListener('click', () => openAnimal().catch((error) => toast(error.message, 'error')));
    document.getElementById('newAppointmentButton')?.addEventListener('click', () => openAppointment().catch((error) => toast(error.message, 'error')));
    document.getElementById('newAdmissionButton')?.addEventListener('click', () => openAdmission().catch((error) => toast(error.message, 'error')));
    document.getElementById('newVetButton')?.addEventListener('click', () => openVet().catch((error) => toast(error.message, 'error')));

    document.getElementById('ownerForm')?.addEventListener('submit', saveOwner);
    document.getElementById('animalForm')?.addEventListener('submit', saveAnimal);
    document.getElementById('appointmentForm')?.addEventListener('submit', saveAppointment);
    document.getElementById('admissionForm')?.addEventListener('submit', saveAdmission);
    document.getElementById('evolutionForm')?.addEventListener('submit', saveEvolution);
    document.getElementById('vetForm')?.addEventListener('submit', saveVet);

    document.getElementById('ownerSearch')?.addEventListener('input', debounce(() => loadOwners().catch((error) => toast(error.message, 'error'))));
    document.getElementById('animalSearch')?.addEventListener('input', debounce(() => loadAnimals().catch((error) => toast(error.message, 'error'))));
    document.getElementById('appointmentSearch')?.addEventListener('input', debounce(() => loadAppointments().catch((error) => toast(error.message, 'error'))));
    document.getElementById('appointmentStatusFilter')?.addEventListener('change', () => loadAppointments().catch((error) => toast(error.message, 'error')));
    document.getElementById('admissionStatusFilter')?.addEventListener('change', () => loadAdmissions().catch((error) => toast(error.message, 'error')));

    const currentDate = document.getElementById('currentDate');
    if (currentDate) {
        currentDate.textContent = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full' }).format(new Date());
    }

    openView('dashboard');
})();
