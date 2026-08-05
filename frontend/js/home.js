(() => {
    'use strict';

    const toggle = document.querySelector('.menu-toggle');
    const navigation = document.getElementById('domain-nav');
    const year = document.getElementById('current-year');

    if (year) {
        year.textContent = String(new Date().getFullYear());
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
