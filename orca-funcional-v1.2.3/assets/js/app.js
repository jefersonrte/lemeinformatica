// app.js — GestaoObras global JS

// Menu principal (mobile)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar || !overlay) return;
    const open = sidebar.classList.toggle('open');
    overlay.classList.toggle('open', open);
    document.body.classList.toggle('nav-open', open);
    document.querySelectorAll('.hamburger').forEach(function (button) {
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}

// Modal helpers
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Tabs
function switchTab(groupId, tabId) {
    const group = document.getElementById(groupId) || document;
    group.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
    group.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    const linkEl = group.querySelector('[data-tab="' + tabId + '"]');
    const paneEl = document.getElementById(tabId);
    if (linkEl) linkEl.classList.add('active');
    if (paneEl) paneEl.classList.add('active');
}

// Auto-hide flash messages
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(a) {
        setTimeout(function() {
            a.style.transition = 'opacity .5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        }, 5000);
    });

    // Dropdown menus
    document.querySelectorAll('[data-dropdown]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = document.getElementById(btn.dataset.dropdown);
            if (menu) menu.classList.toggle('open');
        });
    });
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    });
});

// Confirm delete helper
function confirmDelete(form) {
    if (confirm('Tem certeza que deseja excluir? Esta ação não pode ser desfeita.')) {
        form.submit();
    }
    return false;
}

// Format currency (BRL)
function formatBRL(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
}

// Recalculate row totals in budget table
function recalcRow(row) {
    const qty   = parseFloat(row.querySelector('[name$="[qtd]"]')?.value || 0);
    const price = parseFloat(row.querySelector('[name$="[preco]"]')?.value || 0);
    const totalEl = row.querySelector('.row-total');
    if (totalEl) totalEl.textContent = formatBRL(qty * price);
}

// Dropzone helper
function initDropzone(zoneId, inputId) {
    const zone  = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    if (!zone || !input) return;
    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const dt = e.dataTransfer;
        if (dt.files.length) {
            input.files = dt.files;
            zone.querySelector('p').textContent = dt.files[0].name;
        }
    });
    input.addEventListener('change', function() {
        if (this.files.length) zone.querySelector('p').textContent = this.files[0].name;
    });
}

// Progress bar animation on load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.progress-bar[data-width]').forEach(function(bar) {
        setTimeout(() => bar.style.width = bar.dataset.width + '%', 100);
    });
});

// WhatsApp link builder
function waLink(phone, msg) {
    const p = phone.replace(/\D/g, '');
    return 'https://wa.me/55' + p + '?text=' + encodeURIComponent(msg);
}

// AJAX delete with CSRF
async function ajaxDelete(url, csrfToken, callback) {
    if (!confirm('Confirma exclusão?')) return;
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(csrfToken) + '&_method=DELETE',
    });
    const json = await res.json();
    if (json.ok) {
        if (callback) callback();
    } else {
        alert(json.error || 'Erro ao excluir.');
    }
}

// Leitor animado de plantas e documentos
document.addEventListener('DOMContentLoaded', function () {
    const viewer = document.getElementById('plantViewer');
    const triggers = Array.from(document.querySelectorAll('.plant-preview-trigger'));
    if (!viewer || triggers.length === 0) return;

    const stage = document.getElementById('plantViewerStage');
    const title = document.getElementById('plantViewerTitle');
    const meta = document.getElementById('plantViewerMeta');
    const description = document.getElementById('plantViewerDescription');
    const zoomLabel = document.getElementById('plantViewerZoom');
    const originalLink = viewer.querySelector('[data-viewer-open]');
    let activeIndex = 0;
    let zoom = 1;
    let renderSequence = 0;
    let loadTimer = null;

    function applyZoom() {
        const image = stage.querySelector('.plant-viewer-image');
        if (image) image.style.transform = 'scale(' + zoom + ')';
        zoomLabel.textContent = Math.round(zoom * 100) + '%';
    }

    function render(index) {
        const currentRender = ++renderSequence;
        if (loadTimer) window.clearTimeout(loadTimer);
        activeIndex = (index + triggers.length) % triggers.length;
        const item = triggers[activeIndex].dataset;
        zoom = 1;
        title.textContent = item.title || 'Planta';
        meta.textContent = (item.project || '') + (item.client ? ' — ' + item.client : '') + ' · versão ' + (item.version || '1');
        description.textContent = item.description || 'Documento técnico do projeto.';
        originalLink.href = item.source;
        stage.classList.remove('is-ready', 'is-error');
        stage.innerHTML = '<div class="plant-viewer-loader"><i class="fa-solid fa-circle-notch fa-spin"></i></div>';

        function showLoadError() {
            if (currentRender !== renderSequence) return;
            if (loadTimer) window.clearTimeout(loadTimer);
            stage.classList.remove('is-ready');
            stage.classList.add('is-error');
            stage.innerHTML = '';
            const errorBox = document.createElement('div');
            errorBox.className = 'plant-viewer-error';
            errorBox.innerHTML = '<i class="fa-solid fa-file-circle-exclamation"></i><strong>Arquivo indisponível</strong><span>O documento não foi encontrado ou a sessão expirou.</span>';
            const retryButton = document.createElement('button');
            retryButton.type = 'button';
            retryButton.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Tentar novamente';
            retryButton.addEventListener('click', function () { render(activeIndex); });
            errorBox.appendChild(retryButton);
            stage.appendChild(errorBox);
        }

        const media = item.mime && item.mime.startsWith('image/')
            ? document.createElement('img')
            : document.createElement('iframe');
        media.className = item.mime && item.mime.startsWith('image/') ? 'plant-viewer-image' : 'plant-viewer-frame';
        media.setAttribute(item.mime && item.mime.startsWith('image/') ? 'alt' : 'title', item.title || 'Documento técnico');
        media.addEventListener('load', function () {
            if (currentRender !== renderSequence) return;
            if (media instanceof HTMLImageElement && media.naturalWidth === 0) {
                showLoadError();
                return;
            }
            if (loadTimer) window.clearTimeout(loadTimer);
            stage.querySelector('.plant-viewer-loader')?.remove();
            stage.classList.add('is-ready');
            applyZoom();
        }, { once: true });
        media.addEventListener('error', showLoadError, { once: true });
        media.src = item.source + (item.mime === 'application/pdf' ? '#toolbar=1&navpanes=0' : '');
        stage.appendChild(media);
        loadTimer = window.setTimeout(showLoadError, 15000);
        viewer.querySelectorAll('[data-viewer-zoom-in],[data-viewer-zoom-out],[data-viewer-zoom-reset]').forEach(function (button) {
            button.disabled = !(item.mime && item.mime.startsWith('image/'));
        });
    }

    function open(index) {
        render(index);
        viewer.classList.add('open');
        viewer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('viewer-open');
        viewer.querySelector('[data-viewer-close]')?.focus();
    }

    function close() {
        if (loadTimer) window.clearTimeout(loadTimer);
        viewer.classList.remove('open');
        viewer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('viewer-open');
        triggers[activeIndex]?.focus();
    }

    triggers.forEach(function (trigger, index) { trigger.addEventListener('click', function () { open(index); }); });
    viewer.querySelectorAll('[data-viewer-close]').forEach(function (button) { button.addEventListener('click', close); });
    viewer.querySelector('[data-viewer-prev]').addEventListener('click', function () { render(activeIndex - 1); });
    viewer.querySelector('[data-viewer-next]').addEventListener('click', function () { render(activeIndex + 1); });
    viewer.querySelector('[data-viewer-zoom-in]').addEventListener('click', function () { zoom = Math.min(2.5, zoom + .2); applyZoom(); });
    viewer.querySelector('[data-viewer-zoom-out]').addEventListener('click', function () { zoom = Math.max(.6, zoom - .2); applyZoom(); });
    viewer.querySelector('[data-viewer-zoom-reset]').addEventListener('click', function () { zoom = 1; applyZoom(); });
    stage.addEventListener('wheel', function (event) {
        if (!stage.querySelector('.plant-viewer-image')) return;
        event.preventDefault();
        zoom = Math.max(.6, Math.min(2.5, zoom + (event.deltaY < 0 ? .1 : -.1)));
        applyZoom();
    }, { passive: false });
    document.addEventListener('keydown', function (event) {
        if (!viewer.classList.contains('open')) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') render(activeIndex - 1);
        if (event.key === 'ArrowRight') render(activeIndex + 1);
        if (event.key === '+' || event.key === '=') { zoom = Math.min(2.5, zoom + .2); applyZoom(); }
        if (event.key === '-') { zoom = Math.max(.6, zoom - .2); applyZoom(); }
    });
});
