// Delete buttons: add data-confirm="Delete this book?" to a form.
document.addEventListener('submit', function (event) {
    var message = event.target.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Light and dark switch. The choice is saved in this browser and overrides the computer's setting.
(function () {
    var root = document.documentElement;
    var systemDark = window.matchMedia('(prefers-color-scheme: dark)');
    var sun = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
    var moon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>';

    function current() {
        return root.getAttribute('data-theme') || (systemDark.matches ? 'dark' : 'light');
    }

    function paint() {
        var next = current() === 'dark' ? 'light' : 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.innerHTML = (next === 'light' ? sun : moon) + '<span>' + (next === 'light' ? 'Light' : 'Dark') + '</span>';
            button.setAttribute('aria-label', 'Switch to ' + next + ' mode');
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-theme-toggle]');
        if (!button) return;
        var next = current() === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('theme', next); } catch { /* storage blocked: keep the choice for this visit only */ }
        paint();
    });

    systemDark.addEventListener('change', paint);
    paint();
})();

// Toasts: success and info close after 5 seconds, errors after 8. Hover or focus pauses them. Esc closes all.
(function () {
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function dismiss(toast) {
        if (!toast || toast.classList.contains('is-leaving')) return;
        toast.classList.add('is-leaving');
        window.setTimeout(function () { toast.remove(); }, reduceMotion.matches ? 0 : 220);
    }

    document.querySelectorAll('[data-toast]').forEach(function (toast) {
        var remaining = toast.classList.contains('toast--error') ? 8000 : 5000;
        var started = 0;
        var timer = null;

        function start() {
            if (timer) return;
            started = Date.now();
            timer = window.setTimeout(function () { dismiss(toast); }, remaining);
        }
        function pause() {
            if (!timer) return;
            window.clearTimeout(timer);
            timer = null;
            remaining -= Date.now() - started;
        }

        toast.style.setProperty('--toast-time', remaining + 'ms');
        toast.classList.add('is-timed');
        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', start);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', start);
        start();
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-toast-close]');
        if (button) dismiss(button.closest('[data-toast]'));
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') document.querySelectorAll('[data-toast]').forEach(dismiss);
    });
})();

// Show or hide a password: <button data-reveal="input-id">Show</button>
document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-reveal]');
    if (!button) return;
    var input = document.getElementById(button.getAttribute('data-reveal'));
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', String(show));
    button.setAttribute('aria-label', (show ? 'Hide' : 'Show') + ' password');
    input.focus();
});

// Busy buttons: a form with data-busy-form shows its button's data-busy-label and ignores a second submit.
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (event.defaultPrevented || !form.hasAttribute('data-busy-form')) return;
    if (form.getAttribute('data-busy') === '1') {
        event.preventDefault();
        return;
    }
    form.setAttribute('data-busy', '1');
    var button = event.submitter || form.querySelector('[type="submit"]');
    if (button && button.getAttribute('data-busy-label')) {
        button.setAttribute('data-idle-label', button.textContent);
        button.textContent = button.getAttribute('data-busy-label');
        button.setAttribute('aria-busy', 'true');
    }
});
// Coming back with the browser's Back button: make the forms usable again.
window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-busy="1"]').forEach(function (form) {
        form.removeAttribute('data-busy');
        form.querySelectorAll('[aria-busy="true"]').forEach(function (button) {
            button.textContent = button.getAttribute('data-idle-label') || button.textContent;
            button.removeAttribute('aria-busy');
        });
    });
});

// Password strength: <input data-strength="id-of-.strength"> fills in a 4-step bar.
(function () {
    var labels = ['At least 8 characters', 'Too short: at least 8 characters', 'Fair', 'Good', 'Strong'];

    function level(value) {
        if (value === '') return 0;
        if (value.length < 8) return 1;
        var score = 2;
        if (value.length >= 12) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value) && /\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;
        return Math.min(score, 4);
    }

    document.querySelectorAll('[data-strength]').forEach(function (input) {
        var meter = document.getElementById(input.getAttribute('data-strength'));
        if (!meter) return;
        var label = meter.querySelector('.strength__label');
        input.addEventListener('input', function () {
            var n = level(input.value);
            meter.setAttribute('data-level', String(n));
            label.textContent = labels[n];
        });
    });
})();

// "Type it again" fields: <input data-match="id-of-first-password"> warns before the form is sent.
document.querySelectorAll('[data-match]').forEach(function (confirmInput) {
    var original = document.getElementById(confirmInput.getAttribute('data-match'));
    if (!original) return;
    function check() {
        var differs = confirmInput.value !== '' && confirmInput.value !== original.value;
        confirmInput.setCustomValidity(differs ? 'The two passwords do not match.' : '');
    }
    confirmInput.addEventListener('input', check);
    original.addEventListener('input', check);
});

// Code boxes: keep digits only and show them as "123 456".
document.querySelectorAll('.field__code').forEach(function (input) {
    input.addEventListener('input', function () {
        var digits = input.value.replace(/\D/g, '').slice(0, 6);
        input.value = digits.length > 3 ? digits.slice(0, 3) + ' ' + digits.slice(3) : digits;
    });
});
