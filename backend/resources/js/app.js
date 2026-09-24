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
