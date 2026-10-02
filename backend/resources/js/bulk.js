// Bulk messages pages (resources/views/bulk/).
//  - New campaign form: live count of the numbers (pasted or from a CSV),
//    message length, a {name} preview, and how long sending will take.
//  - Campaign page (while sending): polls the status endpoint and updates
//    the tiles and progress bar; reloads when the campaign stops so the
//    buttons and number list are up to date.

const form = document.querySelector('[data-bulk-form]');

if (form) {
    const numbers = form.querySelector('[data-bulk-numbers]');
    const message = form.querySelector('[data-bulk-message]');
    const interval = form.querySelector('[data-bulk-interval]');
    const count = form.querySelector('[data-bulk-count]');
    const countWrap = form.querySelector('[data-bulk-count-wrap]');
    const chars = form.querySelector('[data-bulk-chars]');
    const estimate = form.querySelector('[data-bulk-estimate]');
    const limit = Number(form.dataset.limit);
    const named = form.querySelector('[data-bulk-named]');
    const personal = form.querySelector('[data-bulk-personal]');
    const fallback = form.querySelector('[data-bulk-fallback]');
    const preview = form.querySelector('[data-bulk-preview]');
    const previewWho = form.querySelector('[data-bulk-preview-who]');
    const csvInput = form.querySelector('[data-bulk-csv]');
    const csvName = form.querySelector('[data-bulk-csv-name]');
    const sourceInputs = form.querySelectorAll('[data-bulk-source]');
    let csvText = '';

    const source = () => form.querySelector('[data-bulk-source]:checked')?.value ?? 'paste';

    // Close to the server's rules (BulkCampaignController::parseRecipients),
    // which decide for real: "number" or "number, name" lines, numbers
    // split by , ; or tab, an optional "phone,name" header row.
    // Returns a Map of number => name (or null).
    const parseRecipients = (text) => {
        const result = new Map();
        let firstLine = true;

        text.split(/\r\n|\r|\n/).forEach((line) => {
            if (!line.trim()) {
                return;
            }
            const delimiter = line.includes('\t') ? '\t' : (line.includes(';') && !line.includes(',') ? ';' : ',');
            const fields = line.split(delimiter).map((f) => f.trim().replace(/^[\s;,"']+|[\s;,"']+$/g, '')).filter(Boolean);

            const isHeader = firstLine && fields[0] && /^[a-z _.-]*(phone|number|mobile|whatsapp|contact)[a-z _.-]*$/i.test(fields[0]);
            firstLine = false;
            if (isHeader || !fields.length) {
                return;
            }

            const entries = fields.length >= 2 && !/^\+?[\d\s\-().]{7,}$/.test(fields[1])
                ? [[fields[0], fields[1]]]
                : fields.map((f) => [f, null]);

            entries.forEach(([raw, name]) => {
                const number = raw.replace(/[\s\-().]/g, '').replace(/^\++/, '');
                if (/^\d{7,15}$/.test(number) && !result.has(number)) {
                    result.set(number, name || null);
                }
            });
        });

        return result;
    };

    const currentRecipients = () => parseRecipients(source() === 'csv' ? csvText : numbers.value);

    // Same as BulkCampaign::bodyFor() on the server.
    const personalise = (text, name) => {
        const value = (name || fallback.value || '').trim();
        let out = text.replaceAll('{name}', value);
        if (!value) {
            out = out.replace(/[ \t]+([,.!?])/g, '$1').replace(/[ \t]{2,}/g, ' ');
        }
        return out;
    };

    const formatDuration = (seconds) => {
        if (seconds < 60) {
            return `${seconds} seconds`;
        }
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) {
            return `${minutes} minute${minutes === 1 ? '' : 's'}`;
        }
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;
        return `${hours} hour${hours === 1 ? '' : 's'}${rest ? ` ${rest} min` : ''}`;
    };

    const update = () => {
        const recipients = currentRecipients();
        const total = recipients.size;
        const withName = [...recipients.values()].filter(Boolean).length;

        count.textContent = total.toLocaleString();
        named.textContent = withName ? ` (${withName.toLocaleString()} with a name)` : '';
        countWrap.classList.toggle('text-danger', total > limit);
        chars.textContent = message.value.length.toLocaleString();
        estimate.textContent = total > 0 ? formatDuration(Math.max(0, total - 1) * Number(interval.value)) : '—';

        // {name}: show the fallback box and a preview for the first number.
        const usesName = message.value.includes('{name}');
        personal.hidden = !usesName;
        if (usesName) {
            const [firstNumber, firstName] = recipients.entries().next().value ?? [null, null];
            preview.textContent = personalise(message.value, firstName);
            previewWho.textContent = firstNumber ? `for ${firstNumber}` : '';
        }
    };

    [numbers, message, interval, fallback].forEach((input) => input.addEventListener('input', update));

    // "Insert {name}" puts it where the cursor is in the message.
    form.querySelector('[data-bulk-insert-name]').addEventListener('click', () => {
        const start = message.selectionStart ?? message.value.length;
        message.setRangeText('{name}', start, message.selectionEnd ?? start, 'end');
        message.focus();
        update();
    });

    // Paste or upload a CSV: only the visible box is required and sent.
    const syncSource = () => {
        const isCsv = source() === 'csv';
        form.querySelectorAll('[data-bulk-source-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.bulkSourcePanel !== source();
        });
        numbers.required = !isCsv;
        update();
    };
    sourceInputs.forEach((input) => input.addEventListener('change', syncSource));

    csvInput.addEventListener('change', () => {
        const file = csvInput.files[0];
        csvText = '';
        csvName.textContent = file ? file.name : 'Choose a CSV file';
        if (!file) {
            update();
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            csvText = String(reader.result ?? '');
            update();
        };
        reader.readAsText(file);
    });

    syncSource();

    // ---- Saved messages: load one into the message box, or save this one.
    const templateSelect = form.querySelector('[data-bulk-template]');
    if (templateSelect) {
        const bodies = JSON.parse(form.querySelector('[data-bulk-templates]').textContent || '{}');
        templateSelect.addEventListener('change', () => {
            const body = bodies[templateSelect.value];
            if (body === undefined) {
                return;
            }
            if (message.value.trim() && message.value !== body && !window.confirm('Replace the message you have written with this saved message?')) {
                templateSelect.value = '';
                return;
            }
            message.value = body;
            templateSelect.value = '';
            update();
        });
    }

    const saveTemplate = form.querySelector('[data-bulk-save-template]');
    const templateName = form.querySelector('[data-bulk-template-name]');
    saveTemplate.addEventListener('change', () => {
        templateName.hidden = !saveTemplate.checked;
        templateName.required = saveTemplate.checked;
        if (saveTemplate.checked) {
            templateName.focus();
        }
    });

    // ---- When to send: now, or at a date/time in the user's own timezone.
    const scheduleBox = form.querySelector('[data-bulk-schedule]');
    const scheduledAt = form.querySelector('[data-bulk-scheduled-at]');
    const submitLabel = form.querySelector('[data-bulk-submit-label]');
    const submitIcon = form.querySelector('[data-bulk-submit-icon]');
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (timezone) {
        form.querySelector('[data-bulk-timezone]').value = timezone;
        form.querySelector('[data-bulk-tz]').textContent = timezone;
    }

    // "YYYY-MM-DDTHH:MM" in local time, for the datetime-local min/max.
    const localInput = (date) => {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };
    scheduledAt.min = localInput(new Date(Date.now() + 2 * 60 * 1000));
    scheduledAt.max = localInput(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000));

    const syncWhen = () => {
        const later = form.querySelector('[data-bulk-when]:checked')?.value === 'later';
        scheduleBox.hidden = !later;
        scheduledAt.required = later;
        submitLabel.textContent = later ? 'Schedule campaign' : 'Start sending';
        submitIcon.className = later ? 'bi bi-calendar-check' : 'bi bi-send';
    };
    form.querySelectorAll('[data-bulk-when]').forEach((input) => input.addEventListener('change', syncWhen));
    syncWhen();

    // ---- What to send: text, or a file (image / video / document)
    // Limits match App\Services\MediaFetcher::RULES (checked again there).
    const fileRules = {
        image: { accept: 'image/jpeg,image/png,image/webp', hint: 'JPG, PNG or WEBP image, up to 5 MB' },
        video: { accept: 'video/mp4,video/3gpp', hint: 'MP4 or 3GP video, up to 16 MB' },
        document: { accept: '', hint: 'Any file (PDF, Excel, Word…), up to 100 MB' },
    };
    const typeInputs = form.querySelectorAll('[data-bulk-type]');
    const mediaBox = form.querySelector('[data-bulk-media]');
    const fileInput = form.querySelector('[data-bulk-file]');
    const drop = form.querySelector('[data-bulk-drop]');
    const dropEmpty = form.querySelector('[data-bulk-drop-empty]');
    const dropHint = form.querySelector('[data-bulk-drop-hint]');
    const dropPreview = form.querySelector('[data-bulk-drop-preview]');
    const messageLabel = form.querySelector('[data-bulk-message-label]');
    let previewUrl = null;

    const currentType = () => form.querySelector('[data-bulk-type]:checked')?.value ?? 'text';

    const clearPreview = () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        dropPreview.replaceChildren();
        dropPreview.hidden = true;
        dropEmpty.hidden = false;
    };

    // Built with DOM methods (not innerHTML), so a file name can't inject HTML.
    const showPreview = (file) => {
        clearPreview();
        const type = currentType();
        let element;

        if (type === 'image' && file.type.startsWith('image/')) {
            previewUrl = URL.createObjectURL(file);
            element = document.createElement('img');
            element.src = previewUrl;
            element.alt = 'Preview';
        } else if (type === 'video' && file.type.startsWith('video/')) {
            previewUrl = URL.createObjectURL(file);
            element = document.createElement('video');
            element.src = previewUrl;
            element.muted = true;
            element.controls = true;
        } else {
            element = document.createElement('i');
            element.className = 'bi bi-file-earmark-text';
        }

        const name = document.createElement('span');
        name.className = 'small text-break';
        name.textContent = `${file.name} · ${(file.size / 1048576).toFixed(1)} MB — click to change`;

        dropPreview.append(element, name);
        dropPreview.hidden = false;
        dropEmpty.hidden = true;
    };

    const syncType = () => {
        const type = currentType();
        const isText = type === 'text';

        mediaBox.hidden = isText;
        message.required = isText;
        messageLabel.textContent = isText ? 'Message' : 'Caption (optional)';

        if (!isText) {
            fileInput.accept = fileRules[type].accept;
            dropHint.textContent = fileRules[type].hint;
        }

        // A file chosen for another type no longer fits — start over.
        fileInput.value = '';
        clearPreview();
    };

    typeInputs.forEach((input) => input.addEventListener('change', syncType));
    if (currentType() !== 'text') {
        fileInput.accept = fileRules[currentType()].accept;
        dropHint.textContent = fileRules[currentType()].hint;
    }

    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) {
            showPreview(fileInput.files[0]);
        } else {
            clearPreview();
        }
    });

    ['dragenter', 'dragover'].forEach((name) => drop.addEventListener(name, (event) => {
        event.preventDefault();
        drop.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((name) => drop.addEventListener(name, () => drop.classList.remove('is-dragging')));
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        if (event.dataTransfer.files.length) {
            fileInput.files = event.dataTransfer.files;
            showPreview(fileInput.files[0]);
        }
    });
}

const live = document.querySelector('[data-bulk-live]');

if (live) {
    const url = live.dataset.bulkLive;

    const poll = async () => {
        let data;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            data = await response.json();
        } catch {
            return; // offline for a moment — try again next time
        }

        if (data.status !== live.dataset.bulkStatus) {
            window.location.reload();
            return;
        }

        const { counts } = data;
        const total = Math.max(1, counts.total);

        document.querySelectorAll('[data-bulk-count-of]').forEach((el) => {
            el.textContent = (counts[el.dataset.bulkCountOf] ?? 0).toLocaleString();
        });
        document.querySelectorAll('[data-bulk-bar]').forEach((el) => {
            el.style.width = `${((counts[el.dataset.bulkBar] ?? 0) / total) * 100}%`;
        });
        const percent = document.querySelector('[data-bulk-percent]');
        if (percent) {
            percent.textContent = `${Math.floor((counts.done / total) * 100)}%`;
        }
        const eta = document.querySelector('[data-bulk-eta]');
        if (eta && counts.pending > 0) {
            const minutes = Math.ceil((counts.pending * Number(live.dataset.bulkInterval)) / 60);
            eta.textContent = minutes <= 1 ? 'Less than a minute left' : `About ${minutes} min left`;
        }
    };

    setInterval(poll, 4000);
}
