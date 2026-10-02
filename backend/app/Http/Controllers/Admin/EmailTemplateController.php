<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\AdminAudit;
use App\Services\EmailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Notifications\EmailTemplateTest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Admin → Email Templates: edit the subject, body and button label of the
 * emails we send to users. The list of emails and their defaults is in
 * config/email-templates.php; an edited version is one email_templates
 * row, and "Reset to default" deletes it.
 */
class EmailTemplateController extends Controller
{
    public function index(): View
    {
        $saved = EmailTemplate::with('editor')->get()->keyBy('key');

        return view('admin.email-templates.index', [
            'templates' => EmailTemplates::definitions(),
            'saved' => $saved,
        ]);
    }

    public function edit(string $key): View
    {
        $this->ensureExists($key);

        return view('admin.email-templates.edit', [
            'key' => $key,
            'template' => EmailTemplates::definition($key),
            'content' => EmailTemplates::content($key),
            'saved' => EmailTemplate::with('editor')->where('key', $key)->first(),
            'placeholders' => EmailTemplates::placeholders($key),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $this->ensureExists($key);
        $data = $this->validated($request, $key);

        $template = EmailTemplate::firstOrNew(['key' => $key]);
        $template->fill($data + ['updated_by' => $request->user()->id]);

        $changed = array_keys(array_filter([
            'subject' => $template->isDirty('subject'),
            'body' => $template->isDirty('body'),
            'button' => $template->isDirty('button_text'),
        ]));

        if ($changed) {
            $template->save();
            AdminAudit::record($request, 'email_template.updated', $template, ['changed' => implode(', ', $changed)]);
        }

        return redirect()->route('admin.email-templates.edit', $key)
            ->with('status', $changed ? 'Email template saved. It is used from the next email sent.' : 'No changes to save.');
    }

    public function reset(Request $request, string $key): RedirectResponse
    {
        $this->ensureExists($key);

        $template = EmailTemplate::where('key', $key)->first();

        if ($template) {
            $template->delete();
            AdminAudit::record($request, 'email_template.reset', $template);
        }

        return redirect()->route('admin.email-templates.edit', $key)
            ->with('status', 'Email template reset to the built-in default.');
    }

    /**
     * GET: the saved version. POST: whatever is in the form right now
     * (unsaved), from the editor's "Preview" button. Sample values only.
     */
    public function preview(Request $request, string $key): Response
    {
        $this->ensureExists($key);

        try {
            $content = $request->isMethod('post')
                ? $this->validated($request, $key)
                : EmailTemplates::content($key);
        } catch (ValidationException $e) {
            // Shown inside the preview frame, instead of redirecting it.
            return response(view('admin.email-templates.preview-error', ['errors' => $e->validator->errors()->all()]), 422);
        }

        $html = EmailTemplates::build($content, EmailTemplates::sampleValues($key, $request->user()), route('dashboard'))->render();

        return response($html);
    }

    /**
     * Sends the form's current (unsaved) version, with sample values, to
     * the admin's own address only.
     */
    public function sendTest(Request $request, string $key): RedirectResponse
    {
        $this->ensureExists($key);
        $content = $this->validated($request, $key);
        $admin = $request->user();

        $mail = EmailTemplates::build($content, EmailTemplates::sampleValues($key, $admin), route('dashboard'));
        $mail->subject('[Test] '.$mail->subject);

        try {
            Notification::route('mail', $admin->email)->notifyNow(new EmailTemplateTest($mail));
        } catch (Throwable $e) {
            Log::warning('Could not send test email template', ['key' => $key, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'The test email could not be sent. Check the mail settings in .env and the log.');
        }

        return back()->withInput()->with('status', "Test email sent to {$admin->email}. It uses sample values, not real user data.");
    }

    private function ensureExists(string $key): void
    {
        abort_unless(EmailTemplates::exists($key), 404);
    }

    /**
     * @return array{subject: string, body: string, button_text: string}
     */
    private function validated(Request $request, string $key): array
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:60000'],
            'button_text' => ['required', 'string', 'max:60'],
        ]);

        $data['subject'] = trim(str_replace(["\r", "\n"], ' ', $data['subject']));
        $data['button_text'] = trim($data['button_text']);
        $data['body'] = EmailTemplates::clean($data['body']);

        $errors = [];

        if (trim(strip_tags($data['body'])) === '') {
            $errors['body'] = 'The email body is empty.';
        }

        foreach (['subject', 'body', 'button_text'] as $field) {
            $unknown = EmailTemplates::unknownPlaceholders($key, $data[$field]);
            if ($unknown) {
                $errors[$field] = 'Unknown placeholder: {'.implode('}, {', $unknown).'}. Use one from the list on the right.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
