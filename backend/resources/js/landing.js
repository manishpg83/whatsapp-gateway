// Public landing page only (layouts/landing.blade.php). Plain JS, no
// libraries: scroll-reveal, count-up numbers, and the nav's scrolled state.

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---- Nav: add a border/shadow once the page is scrolled.
const nav = document.querySelector('[data-lp-nav]');

if (nav) {
    const syncNav = () => nav.classList.toggle('is-scrolled', window.scrollY > 8);
    syncNav();
    window.addEventListener('scroll', syncNav, { passive: true });

    // Close the mobile menu after tapping one of its section links.
    nav.querySelectorAll('.nav-link[href^="#"]').forEach((link) => {
        link.addEventListener('click', () => {
            const menu = document.getElementById('lpNavMenu');
            if (menu?.classList.contains('show')) {
                nav.querySelector('.navbar-toggler')?.click();
            }
        });
    });
}

// ---- Count-up: <span data-count-to="1284">1,284</span>. The final number
// is already in the HTML, so without JS (or with reduced motion) it's right.
function countUp(element) {
    const target = Number(element.dataset.countTo);
    if (!Number.isFinite(target) || reduceMotion) {
        return;
    }

    const duration = 1400;
    const start = performance.now();
    const format = new Intl.NumberFormat('en-US');

    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        element.textContent = format.format(Math.round(target * eased));
        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    };

    requestAnimationFrame(tick);
}

// ---- Scroll reveal: [data-reveal] fades/slides in (CSS does the motion),
// [data-animate] just gets .is-visible so its own CSS can start.
const revealTargets = document.querySelectorAll('[data-reveal], [data-animate]');
const counters = document.querySelectorAll('[data-count-to]');

if (reduceMotion || !('IntersectionObserver' in window)) {
    revealTargets.forEach((element) => element.classList.add('is-visible'));
} else {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    revealTargets.forEach((element) => revealObserver.observe(element));

    const countObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                countUp(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.6 });

    counters.forEach((element) => countObserver.observe(element));
}
