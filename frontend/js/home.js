(() => {
    'use strict';

    const toggle = document.querySelector('.menu-toggle');
    const navigation = document.getElementById('domain-nav');
    const year = document.getElementById('current-year');

    if (year) {
        year.textContent = String(new Date().getFullYear());
    }

    const projectCards = Array.from(document.querySelectorAll('[data-project-card]'));
    const projectButtons = Array.from(document.querySelectorAll('[data-project-filter]'));
    const projectSearch = document.getElementById('project-search');
    const projectCount = document.getElementById('project-count');
    const projectEmpty = document.querySelector('.project-empty');
    let activeProjectFilter = 'all';

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR');

    const updateProjects = () => {
        const term = normalize(projectSearch ? projectSearch.value.trim() : '');
        let visible = 0;

        projectCards.forEach((card) => {
            const matchesModule = activeProjectFilter === 'all' || card.dataset.module === activeProjectFilter;
            const matchesSearch = !term || normalize(card.dataset.search).includes(term);
            const show = matchesModule && matchesSearch;
            card.hidden = !show;
            if (show) visible += 1;
        });

        if (projectCount) projectCount.textContent = String(visible);
        if (projectEmpty) projectEmpty.hidden = visible !== 0;
    };

    projectButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeProjectFilter = button.dataset.projectFilter || 'all';
            projectButtons.forEach((item) => item.classList.toggle('is-active', item === button));
            updateProjects();
        });
    });

    if (projectSearch) {
        projectSearch.addEventListener('input', updateProjects);
    }

    if (!toggle || !navigation) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isOpen = navigation.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', (event) => {
        if (!navigation.contains(event.target) && !toggle.contains(event.target)) {
            navigation.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            navigation.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
    });
})();
