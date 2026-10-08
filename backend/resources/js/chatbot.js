/*
 * Chatbot page: every save redirects back with a #hash (#hours, #menu,
 * #pause, #test, #rule-5, #new-entry …). Open the tab that holds that
 * element and scroll to it. Switching tabs puts the tab's name in the URL,
 * so a reload stays on it.
 */
function showTabFor(hash) {
    const target = hash.length > 1 ? document.getElementById(decodeURIComponent(hash.slice(1))) : null;
    const pane = target?.closest('.tab-pane');

    if (!pane) {
        return;
    }

    const trigger = document.querySelector(`[data-bs-toggle="tab"][data-bs-target="#${pane.id}"]`);

    if (trigger && !trigger.classList.contains('active')) {
        trigger.click();
    }

    if (target !== pane) {
        // After the tab's fade-in, so the element has a position.
        setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    showTabFor(window.location.hash);

    document.querySelectorAll('.cb-tabs [data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', () => {
            history.replaceState(null, '', tab.dataset.bsTarget);
        });
    });
});

window.addEventListener('hashchange', () => showTabFor(window.location.hash));
