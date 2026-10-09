'use strict';

document.documentElement.classList.add('js-enabled');

const globalLoader = document.querySelector('[data-global-loader]');
const globalLoaderLabel = globalLoader?.querySelector('[data-global-loader-label]');
let activeBackgroundRequests = 0;
let navigationPending = false;
let loaderTimer = null;

function renderGlobalLoader(message = 'Loading', immediate = false) {
    if (!globalLoader || (!navigationPending && activeBackgroundRequests === 0)) {
        return;
    }

    if (globalLoaderLabel) {
        globalLoaderLabel.textContent = message;
    }

    const show = () => {
        globalLoader.hidden = false;
        globalLoader.setAttribute('aria-hidden', 'false');
        document.body.classList.add('request-pending');
        document.body.setAttribute('aria-busy', 'true');
    };

    window.clearTimeout(loaderTimer);
    loaderTimer = immediate ? null : window.setTimeout(show, 120);
    if (immediate) {
        show();
    }
}

function hideGlobalLoader() {
    if (!globalLoader || navigationPending || activeBackgroundRequests > 0) {
        return;
    }

    window.clearTimeout(loaderTimer);
    loaderTimer = null;
    globalLoader.hidden = true;
    globalLoader.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('request-pending');
    document.body.removeAttribute('aria-busy');
}

function beginNavigation(message) {
    navigationPending = true;
    renderGlobalLoader(message, true);
}

document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) {
        return;
    }

    if (event.target?.closest('.cancellation-panel')) {
        const selects = Array.from(event.target.querySelectorAll('select[name^="quantities["]'));
        const quantity = selects.reduce((total, select) => total + (Number.parseInt(select.value, 10) || 0), 0);
        if (quantity < 1) {
            event.preventDefault();
            window.alert('Choose at least one ticket to cancel.');
            return;
        }
    }

    const confirmation = event.target?.dataset?.confirm;
    if (confirmation && !window.confirm(confirmation)) {
        event.preventDefault();
        return;
    }

    const submitter = event.submitter;
    if (submitter?.formTarget === '_blank' || event.target?.target === '_blank') {
        return;
    }

    beginNavigation('Processing request');
});

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) {
        return;
    }

    const destination = new URL(link.href, window.location.href);
    if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== window.location.origin) {
        return;
    }

    const current = new URL(window.location.href);
    const sameDocument = destination.pathname === current.pathname
        && destination.search === current.search
        && destination.hash !== '';
    if (!sameDocument) {
        beginNavigation('Loading page');
    }
});

if (window.fetch) {
    const nativeFetch = window.fetch.bind(window);
    window.fetch = (...argumentsList) => {
        activeBackgroundRequests++;
        renderGlobalLoader('Updating');

        return nativeFetch(...argumentsList).finally(() => {
            activeBackgroundRequests = Math.max(0, activeBackgroundRequests - 1);
            hideGlobalLoader();
        });
    };
}

window.addEventListener('beforeunload', () => beginNavigation('Loading page'));
window.addEventListener('pageshow', () => {
    navigationPending = false;
    activeBackgroundRequests = 0;
    hideGlobalLoader();
});

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

document.querySelectorAll('[data-cancellation-form]').forEach((form) => {
    const percentage = Number.parseInt(form.dataset.refundPercentage || '0', 10) || 0;
    const selects = Array.from(form.querySelectorAll('select[name^="quantities["]'));
    const estimate = form.querySelector('[data-cancellation-estimate]');
    const update = () => {
        const selection = selects.reduce((current, select) => {
            const quantity = Number.parseInt(select.value, 10) || 0;
            const price = Number.parseInt(select.closest('[data-cancel-price-paise]')?.dataset.cancelPricePaise || '0', 10) || 0;
            return {quantity: current.quantity + quantity, refund: current.refund + (quantity * Math.round(price * percentage / 100))};
        }, {quantity: 0, refund: 0});
        if (!estimate || selection.quantity < 1) {
            if (estimate) estimate.textContent = 'No tickets selected.';
            return;
        }
        const amount = new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR'}).format(selection.refund / 100);
        estimate.textContent = `${selection.quantity} ticket${selection.quantity === 1 ? '' : 's'} · Estimated ${percentage}% refund ${amount}`;
    };
    selects.forEach((select) => select.addEventListener('change', update));
    update();
});
