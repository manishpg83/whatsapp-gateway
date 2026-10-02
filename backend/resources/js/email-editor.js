// Admin → Email Templates → edit page (admin/email-templates/edit.blade.php).
// Turns the body box into a Quill rich text editor, copies its HTML into the
// hidden "body" field, lets the placeholder chips insert {name} etc. where
// the cursor is, and refreshes the preview frame as the admin types.
// The server cleans the HTML again on every save/preview (EmailTemplates::clean).
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const form = document.querySelector('[data-et-form]');

if (form) {
    const bodyInput = form.querySelector('[data-et-body]');
    const previewButton = form.querySelector('[data-et-preview-button]');

    // Only what the email layout supports (see EmailTemplates::ALLOWED_TAGS).
    const quill = new Quill('[data-et-editor]', {
        theme: 'snow',
        placeholder: 'Write the email…',
        formats: ['header', 'bold', 'italic', 'underline', 'strike', 'link', 'list', 'blockquote'],
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link'],
                ['clean'],
            ],
        },
    });

    // Links may be a placeholder ({billing_url}), https://… or mailto:…;
    // anything else (e.g. "example.com") gets https:// added.
    const Link = Quill.import('formats/link');
    Link.sanitize = (url) => {
        const value = url.trim();
        return /^(https?:\/\/|mailto:|\{[a-z0-9_]+\})/i.test(value) ? value : `https://${value}`;
    };

    const syncBody = () => {
        bodyInput.value = quill.getLength() > 1 ? quill.getSemanticHTML() : '';
    };

    // Live preview: re-submit the form into the preview frame, at most
    // once every ~0.8 seconds while typing.
    let previewTimer;
    const refreshPreview = () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(() => {
            syncBody();
            form.requestSubmit(previewButton);
        }, 800);
    };

    quill.on('text-change', () => {
        syncBody();
        refreshPreview();
    });
    form.querySelectorAll('[data-et-field]').forEach((input) => input.addEventListener('input', refreshPreview));
    form.addEventListener('submit', syncBody);

    // Placeholder chips insert into whichever field was used last.
    let lastField = null;
    form.querySelectorAll('[data-et-field]').forEach((input) => input.addEventListener('focus', () => { lastField = input; }));
    quill.on('selection-change', (range) => {
        if (range) {
            lastField = null;
        }
    });

    form.querySelectorAll('[data-et-placeholder]').forEach((chip) => {
        chip.addEventListener('click', () => {
            const text = chip.dataset.etPlaceholder;

            if (lastField) {
                const start = lastField.selectionStart ?? lastField.value.length;
                const end = lastField.selectionEnd ?? start;
                lastField.setRangeText(text, start, end, 'end');
                lastField.focus();
                refreshPreview();
                return;
            }

            const range = quill.getSelection(true);
            quill.insertText(range.index, text, 'user');
            quill.setSelection(range.index + text.length, 0);
        });
    });

    // The frame first shows the saved version; show what's in the form
    // instead (it differs after a failed save or a test send).
    syncBody();
    form.requestSubmit(previewButton);
}
