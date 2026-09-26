import 'bootstrap';

// Copy buttons: <button data-copy-target="#some-input">. Copies that
// input's value and briefly shows "Copied!" on the button so the user
// knows it worked.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-target]');
    if (!button) {
        return;
    }

    const input = document.querySelector(button.dataset.copyTarget);
    if (!input) {
        return;
    }

    try {
        await navigator.clipboard.writeText(input.value);
    } catch {
        // Clipboard API blocked (e.g. plain http on a non-localhost address):
        // select the text so the user can press Ctrl+C themselves.
        input.select();
        showCopyResult(button, 'Press Ctrl+C', 'btn-outline-warning');
        return;
    }

    showCopyResult(button, 'Copied!', 'btn-success');
});

// Dark code-block copy buttons (docs.css .dc-code), used on API Docs and
// API Logs:
//   data-dc-copy        copies the <pre> in the same .dc-code block
//   data-dc-copy-tab    copies the <pre> of the visible tab in the block
//   data-dc-copy-text   copies the given text (e.g. a URL)
// The button briefly turns green with a tick.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-dc-copy], [data-dc-copy-tab], [data-dc-copy-text]');
    if (!button) {
        return;
    }

    let text = button.dataset.dcCopyText;
    if (text === undefined) {
        const block = button.closest('.dc-code');
        const pre = button.hasAttribute('data-dc-copy-tab')
            ? block?.querySelector('.tab-pane.active pre')
            : block?.querySelector('pre');
        text = pre?.innerText;
    }
    if (!text) {
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
    } catch {
        return; // clipboard blocked (plain http on a non-localhost address)
    }

    if (button.dataset.dcOriginal === undefined) {
        button.dataset.dcOriginal = button.innerHTML;
    }
    clearTimeout(button.dcTimer);
    button.classList.add('is-copied');
    button.innerHTML = button.classList.contains('dc-copy-sm')
        ? '<i class="bi bi-check-lg"></i>'
        : '<i class="bi bi-check-lg"></i> Copied';
    button.dcTimer = setTimeout(() => {
        button.innerHTML = button.dataset.dcOriginal;
        button.classList.remove('is-copied');
    }, 1600);
});

// Password "eye" buttons: <button data-password-toggle="#password">
// (resources/views/components/password-input.blade.php). Switches the
// field between hidden and visible text and swaps the icon.
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');
    if (!button) {
        return;
    }

    const input = document.querySelector(button.dataset.passwordToggle);
    if (!input) {
        return;
    }

    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';

    const label = show ? 'Hide password' : 'Show password';
    button.setAttribute('aria-label', label);
    button.title = label;
    button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});

// Count-up numbers: <span data-count-up="42">42</span> (dashboard), or with
// trailing text kept in the same element: data-count-suffix=" sent". The
// real number is already in the HTML, so without JS — or when the user
// prefers reduced motion — it simply shows as-is.
if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('[data-count-up]').forEach((element) => {
        const target = Number(element.dataset.countUp);
        if (!Number.isFinite(target) || target <= 0) {
            return;
        }

        const suffix = element.dataset.countSuffix ?? '';
        const duration = Math.min(1600, 600 + target * 40);
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = String(Math.round(target * eased)) + suffix;
            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        };

        element.textContent = '0' + suffix;
        requestAnimationFrame(tick);
    });
}

// "On this page" lists on the Terms / Privacy pages (legal.css .tm-toc):
// open by default on large screens (a tap-to-open box on phones), and
// highlight the section currently in view.
const tocDetails = document.querySelector('[data-toc-details]');
if (tocDetails) {
    if (window.matchMedia('(min-width: 992px)').matches) {
        tocDetails.open = true;
    }

    const tocLinks = document.querySelectorAll('[data-toc-link]');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    tocLinks.forEach((link) => link.classList.toggle('active', link.dataset.tocLink === entry.target.id));
                }
            });
        }, { rootMargin: '-20% 0px -70% 0px' });

        document.querySelectorAll('.tm-section').forEach((section) => observer.observe(section));
    }
}

function showCopyResult(button, text, colorClass) {
    if (button.dataset.copyOriginal === undefined) {
        button.dataset.copyOriginal = button.innerHTML;
    }

    clearTimeout(button.copyTimer);
    button.innerHTML = colorClass === 'btn-success'
        ? `<i class="bi bi-check-lg me-1"></i>${text}`
        : text;
    button.classList.remove('btn-outline-secondary', 'btn-success', 'btn-outline-warning');
    button.classList.add(colorClass);

    button.copyTimer = setTimeout(() => {
        button.innerHTML = button.dataset.copyOriginal;
        button.classList.remove(colorClass);
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
