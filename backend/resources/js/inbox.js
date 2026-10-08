/*
 * Inbox (inbox/index.blade.php):
 *  - the chat fills the rest of the window and opens at its newest message;
 *  - reply box: Enter sends, Shift+Enter adds a line, it grows with the
 *    text, and the button can't be double-clicked into two sends;
 *  - live updates: every few seconds, asks InboxController::updates what
 *    changed and swaps in the new conversation list / chat messages, plus
 *    the unread count (sidebar badge and browser tab title).
 */
const POLL_SECONDS = 5;

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.ib-shell');
    const thread = document.querySelector('[data-ib-thread]');

    // Fill the rest of the window, whatever sits above (title, instance
    // switcher, alerts) — so the reply box is on screen without scrolling.
    const fit = () => {
        if (shell) {
            const top = shell.getBoundingClientRect().top + window.scrollY;
            shell.style.height = `${Math.max(380, window.innerHeight - top - 24)}px`;
        }
    };

    fit();
    window.addEventListener('resize', fit);

    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }

    setUpReplyBox();

    if (shell?.dataset.ibUpdates) {
        startPolling(shell, thread);
    }
});

function setUpReplyBox() {
    const form = document.querySelector('[data-ib-compose]');
    const input = form?.querySelector('[data-ib-input]');

    if (!form || !input) {
        return;
    }

    const grow = () => {
        input.style.height = 'auto';
        input.style.height = `${input.scrollHeight + 2}px`;
    };

    input.addEventListener('input', grow);
    grow();

    // Ready to type, without the browser scrolling the page down to the box
    // (plain autofocus did that, hiding the title and instance switcher).
    // Not on touch screens, where it would pop up the keyboard.
    if (window.matchMedia('(hover: hover)').matches) {
        input.focus({ preventScroll: true });
    }

    // Paperclip: show the chosen file's name with a × to remove it.
    const file = form.querySelector('[data-ib-file]');
    const chip = form.querySelector('[data-ib-file-chip]');
    const showFile = () => {
        const chosen = file.files[0];
        chip.hidden = !chosen;
        form.querySelector('[data-ib-file-name]').textContent = chosen ? chosen.name : '';
        input.placeholder = chosen ? 'Add a caption (optional)' : 'Type a reply';
    };

    file.addEventListener('change', showFile);
    form.querySelector('[data-ib-file-clear]').addEventListener('click', () => {
        file.value = '';
        showFile();
    });

    const hasContent = () => input.value.trim() !== '' || file.files.length > 0;

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();

            if (hasContent()) {
                form.requestSubmit();
            }
        }
    });

    form.addEventListener('submit', (event) => {
        if (!hasContent()) {
            event.preventDefault();
            input.focus();

            return;
        }

        form.querySelector('button[type="submit"]').disabled = true;
    });
}

function startPolling(shell, thread) {
    const list = document.querySelector('[data-ib-list]');
    const total = document.querySelector('[data-ib-total]');
    const baseTitle = document.title.replace(/^\(\d+\+?\) /, '');
    let busy = false;

    const poll = async () => {
        // Nothing to do while the tab is hidden; it catches up when shown.
        if (busy || document.hidden) {
            return;
        }

        busy = true;

        try {
            const url = new URL(shell.dataset.ibUpdates, window.location.origin);
            url.searchParams.set('list', shell.dataset.ibListVersion);
            url.searchParams.set('thread', shell.dataset.ibThreadVersion);
            url.searchParams.set('seen', '1');

            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.list !== null && list) {
                const scroll = list.scrollTop;
                list.innerHTML = data.list;
                list.scrollTop = scroll;
                shell.dataset.ibListVersion = data.list_version;
            }

            if (data.thread !== null && thread) {
                // Stay at the bottom if the owner was there; otherwise keep
                // their place while they read older messages.
                const atBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
                const scroll = thread.scrollTop;
                thread.innerHTML = data.thread;
                thread.scrollTop = atBottom ? thread.scrollHeight : scroll;
                shell.dataset.ibThreadVersion = data.thread_version;
            }

            if (total && data.thread_total) {
                total.textContent = data.thread_total;
            }

            showUnread(data.unread_total, baseTitle);
        } catch {
            // Offline for a moment — just try again next time.
        } finally {
            busy = false;
        }
    };

    setInterval(poll, POLL_SECONDS * 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            poll();
        }
    });
}

function showUnread(count, baseTitle) {
    const badge = document.querySelector('[data-ib-nav-badge]');
    const label = count > 99 ? '99+' : String(count);

    if (badge) {
        badge.textContent = label;
        badge.hidden = count === 0;
    }

    document.title = count > 0 ? `(${label}) ${baseTitle}` : baseTitle;
}
