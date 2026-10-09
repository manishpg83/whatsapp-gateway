/*
 * Inbox (inbox/index.blade.php):
 *  - the chat fills the rest of the window and opens at its newest message;
 *  - reply box: Enter sends, Shift+Enter adds a line, it grows with the
 *    text, and the button can't be double-clicked into two sends;
 *  - live updates: every few seconds, asks InboxController::updates what
 *    changed and swaps in the new conversation list / chat messages, plus
 *    the unread count (sidebar badge and browser tab title);
 *  - "Load earlier messages" / "Load more chats" load in place (through the
 *    same updates call), keeping the reader's place; "Retry" can't be
 *    double-clicked;
 *  - new-message alerts: when the unread count goes up, a short chime, and
 *    a browser notification if the tab is in the background (never the
 *    message text — it could show on a locked screen). The bell button
 *    turns them on/off, remembered in this browser.
 */
const POLL_SECONDS = 5;

// While the tab is in the background, check only every 3rd time (15 s) —
// enough for alerts, without hammering the server.
const HIDDEN_POLL_EVERY = 3;

const ALERTS_KEY = 'inbox.alerts';

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
        startPolling(shell, thread, setUpAlerts());
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

function startPolling(shell, thread, alerts) {
    const list = document.querySelector('[data-ib-list]');
    const total = document.querySelector('[data-ib-total]');
    const baseTitle = document.title.replace(/^\(\d+\+?\) /, '');
    let busy = false;
    // A "load more" click while a poll is running waits for it.
    let queued = null;
    // Unread total at the last check (null = not known yet, no alert).
    let lastUnread = null;
    let tick = 0;

    // force.list / force.thread: re-render even if nothing changed (more
    // was asked for). force.olderLoaded: keep the same message in view.
    const poll = async (force = {}) => {
        if (busy) {
            if (force.list || force.thread) {
                queued = { ...queued, ...force };
            }

            return;
        }

        // In the background: only every few ticks, just to notice new messages.
        if (document.hidden && !force.list && !force.thread && ++tick % HIDDEN_POLL_EVERY !== 0) {
            return;
        }

        busy = true;

        try {
            const url = new URL(shell.dataset.ibUpdates, window.location.origin);
            url.searchParams.set('list', force.list ? '' : shell.dataset.ibListVersion);
            url.searchParams.set('thread', force.thread ? '' : shell.dataset.ibThreadVersion);
            url.searchParams.set('chats', shell.dataset.ibChats);
            url.searchParams.set('older', shell.dataset.ibOlder);
            url.searchParams.set('seen', document.hidden ? '0' : '1');

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
                // their place while they read older messages. After loading
                // earlier messages, keep the same message where it was.
                const fromBottom = thread.scrollHeight - thread.scrollTop;
                const atBottom = fromBottom - thread.clientHeight < 80;
                const scroll = thread.scrollTop;
                thread.innerHTML = data.thread;

                if (force.olderLoaded) {
                    thread.scrollTop = thread.scrollHeight - fromBottom;
                } else {
                    thread.scrollTop = atBottom ? thread.scrollHeight : scroll;
                }

                shell.dataset.ibThreadVersion = data.thread_version;
            }

            if (total && data.thread_total) {
                total.textContent = data.thread_total;
            }

            showUnread(data.unread_total, baseTitle);

            if (lastUnread !== null && data.unread_total > lastUnread) {
                alerts.newMessage();
            }

            lastUnread = data.unread_total;
        } catch {
            // Offline for a moment — just try again next time.
        } finally {
            busy = false;
            document.querySelectorAll('[data-ib-load-older], [data-ib-load-chats]').forEach((link) => link.classList.remove('is-loading'));

            if (queued) {
                const next = queued;
                queued = null;
                poll(next);
            }
        }
    };

    // "Load earlier messages" / "Load more chats": one more page, in place.
    // (They are plain links too, so they also work before the script loads.)
    const loadMore = (link, key, force) => {
        link.classList.add('is-loading');
        shell.dataset[key] = String(Number(shell.dataset[key]) + 1);

        // Keep it on reload / when shared: the address bar gets the new page count.
        const address = new URL(window.location.href);
        address.searchParams.set(key === 'ibOlder' ? 'older' : 'chats', shell.dataset[key]);
        window.history.replaceState(null, '', address);

        poll(force);
    };

    document.addEventListener('click', (event) => {
        const older = event.target.closest('[data-ib-load-older]');
        const moreChats = event.target.closest('[data-ib-load-chats]');

        if (older) {
            event.preventDefault();
            loadMore(older, 'ibOlder', { thread: true, olderLoaded: true });
        } else if (moreChats) {
            event.preventDefault();
            loadMore(moreChats, 'ibChats', { list: true });
        }
    });

    // Retry: one click = one send.
    document.addEventListener('submit', (event) => {
        if (event.target.matches('[data-ib-retry]')) {
            event.target.querySelector('button').disabled = true;
        }
    });

    setInterval(poll, POLL_SECONDS * 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            poll();
        }
    });
}

/**
 * The bell button and what happens on a new message. Returns
 * { newMessage() } for the poller to call.
 */
function setUpAlerts() {
    const button = document.querySelector('[data-ib-alerts]');
    let on = readSetting() !== 'off';
    let audio = null;

    // Browsers only allow sound after the person has clicked or typed on the
    // page — so the sound player is made ready on the first click/key.
    const unlockSound = () => {
        try {
            audio ??= new (window.AudioContext || window.webkitAudioContext)();
            audio.resume();
        } catch {
            // No Web Audio: alerts are just silent.
        }
    };

    document.addEventListener('click', unlockSound, { once: true });
    document.addEventListener('keydown', unlockSound, { once: true });

    const render = () => {
        if (!button) {
            return;
        }

        button.hidden = false;
        button.querySelector('[data-ib-alerts-icon]').className = `bi ${on ? 'bi-bell' : 'bi-bell-slash'}`;
        button.querySelector('[data-ib-alerts-label]').textContent = on ? 'Alerts on' : 'Alerts off';
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
    };

    button?.addEventListener('click', () => {
        on = !on;
        writeSetting(on ? 'on' : 'off');
        render();

        // Ask once, from the click (browsers only allow asking then).
        if (on && 'Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        if (on) {
            unlockSound(); // this click may be the first one on the page
            chime(audio);
        }
    });

    render();

    return {
        newMessage() {
            if (!on) {
                return;
            }

            chime(audio);

            if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                try {
                    // Same tag: a newer alert replaces the older one instead of stacking up.
                    const notice = new Notification('New WhatsApp message', {
                        // The unread count covers all instances, so no instance name here.
                        body: 'A customer wrote to you. Open the Inbox to read it.',
                        tag: 'instamessage-inbox',
                    });
                    notice.onclick = () => {
                        window.focus();
                        notice.close();
                    };
                } catch {
                    // Some mobile browsers don't allow page notifications.
                }
            }
        },
    };
}

// A short two-tone "ding", made in the browser (no sound file needed).
function chime(audio) {
    if (!audio || audio.state !== 'running') {
        return;
    }

    const start = audio.currentTime;

    [[880, 0], [1320, 0.12]].forEach(([frequency, delay]) => {
        const tone = audio.createOscillator();
        const volume = audio.createGain();
        tone.type = 'sine';
        tone.frequency.value = frequency;
        volume.gain.setValueAtTime(0.0001, start + delay);
        volume.gain.exponentialRampToValueAtTime(0.25, start + delay + 0.02);
        volume.gain.exponentialRampToValueAtTime(0.0001, start + delay + 0.25);
        tone.connect(volume).connect(audio.destination);
        tone.start(start + delay);
        tone.stop(start + delay + 0.3);
    });
}

// The on/off choice is a per-browser convenience; private windows may
// block storage, then alerts are simply on.
function readSetting() {
    try {
        return window.localStorage.getItem(ALERTS_KEY);
    } catch {
        return null;
    }
}

function writeSetting(value) {
    try {
        window.localStorage.setItem(ALERTS_KEY, value);
    } catch {
        // Not remembered — fine.
    }
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
