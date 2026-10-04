'use strict';

document.documentElement.classList.add('js-enabled');

const navToggle = document.querySelector('.nav-toggle');
const primaryNavigation = document.querySelector('#primary-navigation');

if (navToggle && primaryNavigation) {
    navToggle.addEventListener('click', () => {
        const isOpen = document.body.classList.toggle('nav-open');
        navToggle.setAttribute('aria-expanded', String(isOpen));
    });
}

const dashboardMenuButton = document.querySelector('.dashboard-menu-button');
const dashboardBackdrop = document.querySelector('.dashboard-backdrop');

function setDashboardMenu(open) {
    document.body.classList.toggle('dashboard-menu-open', open);
    dashboardMenuButton?.setAttribute('aria-expanded', String(open));
}

dashboardMenuButton?.addEventListener('click', () => {
    setDashboardMenu(!document.body.classList.contains('dashboard-menu-open'));
});
dashboardBackdrop?.addEventListener('click', () => setDashboardMenu(false));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.body.classList.remove('nav-open');
        navToggle?.setAttribute('aria-expanded', 'false');
        setDashboardMenu(false);
    }
});

document.querySelectorAll('.ticket-selection-form').forEach((form) => {
    const selects = Array.from(form.querySelectorAll('select[name^="tickets["]'));
    const summary = form.querySelector('[data-ticket-summary]');
    const error = form.querySelector('[data-ticket-error]');
    const submitButton = form.querySelector('button[type="submit"]');

    const selection = () => selects.reduce((current, select) => {
        const quantity = Number.parseInt(select.value, 10) || 0;
        const option = select.closest('[data-ticket-price-paise]');
        const pricePaise = Number.parseInt(option?.dataset.ticketPricePaise || '0', 10) || 0;
        return {
            quantity: current.quantity + quantity,
            totalPaise: current.totalPaise + (quantity * pricePaise),
        };
    }, {quantity: 0, totalPaise: 0});

    const updateSummary = () => {
        const current = selection();
        if (summary) {
            const total = new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR',
            }).format(current.totalPaise / 100);
            summary.textContent = current.quantity === 0
                ? 'No tickets selected.'
                : `${current.quantity} ticket${current.quantity === 1 ? '' : 's'} selected · ${total}`;
        }
        if (error && current.quantity > 0) {
            error.hidden = true;
        }
    };

    selects.forEach((select) => select.addEventListener('change', updateSummary));
    updateSummary();

    form.addEventListener('submit', (event) => {
        if (selection().quantity < 1) {
            event.preventDefault();
            if (error) {
                error.hidden = false;
                error.focus?.();
            }
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Reserving…';
        }
    });
});
