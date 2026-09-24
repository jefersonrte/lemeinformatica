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

// Novo registro: restaura o formulário do modal ao estado inicial antes de abrir
// (evita que "+ Novo" após "Editar" sobrescreva o registro editado).
const modalSnapshots = {};
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        const title = modal.querySelector('.modal-header h3');
        modalSnapshots[modal.id] = {
            title: title ? title.textContent : null,
            hidden: Array.from(modal.querySelectorAll('input[type="hidden"]')).map(i => [i, i.value]),
        };
    });
});
function resetModal(id) {
    const modal = document.getElementById(id);
    const snapshot = modalSnapshots[id];
    if (!modal || !snapshot) return;
    modal.querySelectorAll('form').forEach(f => f.reset());
    snapshot.hidden.forEach(([input, value]) => { input.value = value; });
    const title = modal.querySelector('.modal-header h3');
    if (title && snapshot.title !== null) title.textContent = snapshot.title;
}
document.addEventListener('click', function(e) {
    const trigger = e.target.closest('[onclick]');
    const match = trigger && /^\s*openModal\(\s*'([^']+)'\s*\)/.exec(trigger.getAttribute('onclick'));
    if (match) resetModal(match[1]);
}, true);

// Tabs
function switchTab(groupId, tabId) {
    const group = document.getElementById(groupId) || document;
    group.querySelectorAll('.tab-link').forEach(function(t) {
        t.classList.remove('active');
        const pane = t.dataset.tab ? document.getElementById(t.dataset.tab) : null;
        if (pane) pane.classList.remove('active');
    });
    const linkEl = group.querySelector('[data-tab="' + tabId + '"]');
    const paneEl = document.getElementById(tabId);
    if (linkEl) linkEl.classList.add('active');
    if (paneEl) paneEl.classList.add('active');
}

// Auto-hide flash messages
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-persist)');
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

// Temas do site: aplica na hora, grava no cookie (lido pelo PHP no próximo carregamento)
// e avisa outros scripts (ex.: gráficos) pelo evento "orca:theme".
function orcaThemeColor(token) {
    return getComputedStyle(document.documentElement).getPropertyValue('--' + token).trim();
}

function setPreferenceCookie(name, value) {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=31536000; SameSite=Lax' + secure;
}

// Estado do tema personalizado (matiz principal, matiz do degradê e tom claro/escuro).
const orcaCustom = { h1: 210, h2: 170, tone: 'claro' };

function applyTheme(key, option) {
    const root = document.documentElement;
    const custom = key === 'personalizado';
    root.classList.add('theme-switching');
    root.dataset.theme = key;
    if (custom) {
        root.dataset.tone = orcaCustom.tone;
        root.style.setProperty('--h1', orcaCustom.h1);
        root.style.setProperty('--h2', orcaCustom.h2);
        setPreferenceCookie('orca_cor', orcaCustom.h1 + '-' + orcaCustom.h2 + '-' + orcaCustom.tone);
    } else {
        delete root.dataset.tone;
        root.style.removeProperty('--h1');
        root.style.removeProperty('--h2');
    }
    const escuro = custom ? orcaCustom.tone === 'escuro' : option?.dataset.themeScheme === 'dark';
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = custom ? 'hsl(' + orcaCustom.h1 + ', 50%, ' + (escuro ? 9 : 22) + '%)' : (option?.dataset.themeMeta || meta.content);
    const scheme = document.querySelector('meta[name="color-scheme"]');
    if (scheme) scheme.content = escuro ? 'dark' : 'light';
    setPreferenceCookie('orca_tema', key);
    document.querySelectorAll('[data-theme-option]').forEach(function (item) {
        item.setAttribute('aria-checked', item.dataset.themeOption === key ? 'true' : 'false');
    });
    syncCustomControls();
    requestAnimationFrame(function () {
        requestAnimationFrame(function () { root.classList.remove('theme-switching'); });
    });
    document.dispatchEvent(new CustomEvent('orca:theme', { detail: { theme: key } }));
}

function syncCustomControls() {
    document.querySelectorAll('[data-custom-theme]').forEach(function (box) {
        box.querySelectorAll('[data-hue]').forEach(function (slider) { slider.value = orcaCustom[slider.dataset.hue]; });
        box.querySelectorAll('[data-tone-option]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.dataset.toneOption === orcaCustom.tone ? 'true' : 'false');
        });
        box.querySelectorAll('[data-gradient]').forEach(function (chip) {
            chip.setAttribute('aria-pressed', chip.dataset.gradient === orcaCustom.h1 + ',' + orcaCustom.h2 ? 'true' : 'false');
        });
    });
    document.querySelectorAll('[data-custom-preview]').forEach(function (swatch) {
        swatch.style.setProperty('--h1', orcaCustom.h1);
        swatch.style.setProperty('--h2', orcaCustom.h2);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const box = document.querySelector('[data-custom-theme]');
    if (!box) return;
    orcaCustom.h1 = parseInt(box.dataset.h1, 10) || 0;
    orcaCustom.h2 = parseInt(box.dataset.h2, 10) || 0;
    orcaCustom.tone = box.dataset.tone === 'escuro' ? 'escuro' : 'claro';
    syncCustomControls();

    document.querySelectorAll('[data-custom-theme]').forEach(function (container) {
        container.addEventListener('click', function (event) { event.stopPropagation(); });
        container.querySelectorAll('[data-hue]').forEach(function (slider) {
            slider.addEventListener('input', function () {
                orcaCustom[slider.dataset.hue] = parseInt(slider.value, 10);
                applyTheme('personalizado');
            });
        });
        container.querySelectorAll('[data-gradient]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                const [h1, h2] = chip.dataset.gradient.split(',').map(Number);
                orcaCustom.h1 = h1;
                orcaCustom.h2 = h2;
                applyTheme('personalizado');
            });
        });
        container.querySelectorAll('[data-tone-option]').forEach(function (button) {
            button.addEventListener('click', function () {
                orcaCustom.tone = button.dataset.toneOption;
                applyTheme('personalizado');
            });
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const pickers = document.querySelectorAll('[data-theme-picker]');
    if (pickers.length === 0) return;

    function closeAll(except) {
        pickers.forEach(function (picker) {
            if (picker === except) return;
            picker.classList.remove('open');
            picker.querySelector('.theme-toggle')?.setAttribute('aria-expanded', 'false');
        });
    }

    pickers.forEach(function (picker) {
        const toggle = picker.querySelector('.theme-toggle');
        const options = Array.from(picker.querySelectorAll('[data-theme-option]'));
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            const open = !picker.classList.contains('open');
            closeAll(picker);
            picker.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) (options.find(o => o.getAttribute('aria-checked') === 'true') || options[0])?.focus();
        });
        options.forEach(function (option, index) {
            option.addEventListener('click', function (event) {
                event.stopPropagation();
                applyTheme(option.dataset.themeOption, option);
            });
            option.addEventListener('keydown', function (event) {
                const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];
                if (step) {
                    event.preventDefault();
                    options[(index + step + options.length) % options.length].focus();
                } else if (event.key === 'Escape') {
                    closeAll();
                    toggle.focus();
                }
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-theme-picker]')) closeAll();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAll();
    });
});

// Menu em grupos: acordeão na disposição lateral e na gaveta mobile;
// painel suspenso (clique/toque ou passar o mouse) no topo e no trilho.
function navIsInline() {
    return document.documentElement.dataset.layout === 'lateral' || window.matchMedia('(max-width: 900px)').matches;
}

function syncNavGroups() {
    const inline = navIsInline();
    document.querySelectorAll('[data-nav-group]').forEach(function (group) {
        const open = inline && group.classList.contains('has-active');
        group.classList.toggle('open', open);
        group.querySelector('.nav-group-toggle')?.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}

function applyLayout(key) {
    document.documentElement.dataset.layout = key;
    setPreferenceCookie('orca_layout', key);
    document.querySelectorAll('[data-layout-option]').forEach(function (item) {
        item.setAttribute('aria-checked', item.dataset.layoutOption === key ? 'true' : 'false');
    });
    syncNavGroups();
    window.dispatchEvent(new Event('resize'));
}

document.addEventListener('DOMContentLoaded', function () {
    const groups = Array.from(document.querySelectorAll('[data-nav-group]'));
    syncNavGroups();
    window.matchMedia('(max-width: 900px)').addEventListener('change', syncNavGroups);

    groups.forEach(function (group) {
        const toggle = group.querySelector('.nav-group-toggle');
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            const open = !group.classList.contains('open');
            if (!navIsInline()) {
                groups.forEach(function (other) {
                    other.classList.remove('open');
                    other.querySelector('.nav-group-toggle')?.setAttribute('aria-expanded', 'false');
                });
            }
            group.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        group.addEventListener('focusout', function (event) {
            if (navIsInline() || group.contains(event.relatedTarget)) return;
            group.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', function (event) {
        if (navIsInline() || event.target.closest('[data-nav-group]')) return;
        groups.forEach(function (group) {
            group.classList.remove('open');
            group.querySelector('.nav-group-toggle')?.setAttribute('aria-expanded', 'false');
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || navIsInline()) return;
        const aberto = groups.find(g => g.classList.contains('open') || g.contains(document.activeElement));
        if (!aberto) return;
        aberto.classList.remove('open');
        aberto.querySelector('.nav-group-toggle')?.setAttribute('aria-expanded', 'false');
        aberto.querySelector('.nav-group-toggle')?.focus();
    });

    document.querySelectorAll('[data-layout-option]').forEach(function (option) {
        option.addEventListener('click', function (event) {
            event.stopPropagation();
            applyLayout(option.dataset.layoutOption);
        });
    });
});
