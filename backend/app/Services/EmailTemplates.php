<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds every email we send to users from its template: the admin's
 * edited version (email_templates table) or, if there is none, the
 * built-in default in config/email-templates.php.
 *
 * Admin text is NEVER run as Blade/PHP. {placeholders} are filled with a
 * plain find-and-replace, values are HTML-escaped, and the body HTML is
 * cleaned down to a short allow-list of tags (clean()) both when saved and
 * again when sent.
 */
class EmailTemplates
{
    // Available in every template: name => what it is.
    public const GLOBAL_PLACEHOLDERS = [
        'name' => "The user's name",
        'app_name' => 'Our product name',
        'dashboard_url' => 'Link to the dashboard',
        'instances_url' => 'Link to the Instances page',
        'billing_url' => 'Link to the Billing page',
        'docs_url' => 'Link to the API Docs',
        'contact_url' => 'Link to the Contact page',
        'terms_url' => 'Link to the Terms page',
    ];

    // The tags the editor can produce. Everything else is unwrapped (its
    // text kept) or, for DROPPED_TAGS, removed together with its content.
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'u', 's', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote'];

    private const RENAMED_TAGS = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's', 'h4' => 'h3', 'h5' => 'h3', 'h6' => 'h3'];

    private const DROPPED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'head', 'title', 'template', 'noscript', 'svg', 'math', 'form', 'textarea', 'select', 'img', 'video', 'audio'];

    // Inline styles added at send time — many email apps ignore <style>.
    private const STYLES = [
        '<p>' => '<p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#33413b;">',
        '<h1>' => '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:#10231c;">',
        '<h2>' => '<h2 style="margin:28px 0 12px;font-size:18px;line-height:1.35;font-weight:700;color:#10231c;">',
        '<h3>' => '<h3 style="margin:24px 0 8px;font-size:16px;line-height:1.4;font-weight:700;color:#10231c;">',
        '<ul>' => '<ul style="margin:0 0 16px;padding-left:22px;font-size:15px;line-height:1.65;color:#33413b;">',
        '<ol>' => '<ol style="margin:0 0 16px;padding-left:22px;font-size:15px;line-height:1.65;color:#33413b;">',
        '<li>' => '<li style="margin:0 0 8px;">',
        '<blockquote>' => '<blockquote style="margin:0 0 16px;padding:16px 18px;background:#e7f7ef;border-left:4px solid #0b8457;border-radius:8px;font-size:14px;line-height:1.6;color:#10231c;">',
        '<a href=' => '<a style="color:#0b8457;font-weight:600;text-decoration:underline;" href=',
    ];

    // A paragraph holding only {button} marks where the button goes.
    private const BUTTON_MARKER = '#<p>\s*\{button\}\s*</p>#';

    /**
     * @return array<string, array{label: string, sent_when: string, icon: string, placeholders: array<string, array{0: string, 1: string}>, subject: string, button_text: string, body: string}>
     */
    public static function definitions(): array
    {
        return config('email-templates');
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function definition(string $key): array
    {
        return self::definitions()[$key];
    }

    /**
     * The subject, body and button label in use right now.
     *
     * @return array{subject: string, body: string, button_text: string}
     */
    public static function content(string $key): array
    {
        $saved = EmailTemplate::where('key', $key)->first();
        $default = self::definition($key);

        return [
            'subject' => $saved->subject ?? $default['subject'],
            'body' => $saved->body ?? $default['body'],
            'button_text' => $saved->button_text ?? $default['button_text'],
        ];
    }

    /**
     * Every placeholder this template accepts: name => what it is.
     *
     * @return array<string, string>
     */
    public static function placeholders(string $key): array
    {
        return self::GLOBAL_PLACEHOLDERS + array_map(fn (array $p) => $p[0], self::definition($key)['placeholders']);
    }

    /**
     * Placeholder names in $text that this template doesn't know (typos).
     *
     * @return array<int, string>
     */
    public static function unknownPlaceholders(string $key, string $text): array
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', $text, $matches);
        $known = array_keys(self::placeholders($key));
        $known[] = 'button';

        return array_values(array_unique(array_diff($matches[1], $known)));
    }

    /**
     * The values for the preview and the "send test email" button.
     *
     * @return array<string, string>
     */
    public static function sampleValues(string $key, User $admin): array
    {
        return ['name' => $admin->name]
            + self::globalValues()
            + array_map(fn (array $p) => $p[1], self::definition($key)['placeholders']);
    }

    /**
     * Build the email to send. $values holds 'name' and the template's own
     * placeholders; the global links are added here.
     */
    public static function mail(string $key, array $values, string $actionUrl): MailMessage
    {
        return self::build(self::content($key), $values + self::globalValues(), $actionUrl);
    }

    /**
     * @param  array{subject: string, body: string, button_text: string}  $content
     */
    public static function build(array $content, array $values, string $actionUrl): MailMessage
    {
        // The subject is plain text (the mailer encodes it), so no escaping
        // — just no line breaks.
        $subject = str_replace(["\r", "\n"], ' ', self::fill($content['subject'], $values, escape: false));

        // Split around the {button} line, if there is one; otherwise the
        // button goes after the whole body.
        $parts = preg_split(self::BUTTON_MARKER, self::clean($content['body']), 2);
        $before = self::fill(str_replace('{button}', '', $parts[0]), $values, escape: true);
        $after = self::fill(str_replace('{button}', '', $parts[1] ?? ''), $values, escape: true);

        return (new MailMessage)
            ->subject($subject)
            ->action(self::fill($content['button_text'], $values, escape: false), $actionUrl)
            ->view(['html' => 'emails.branded', 'text' => 'emails.branded-text'], [
                'bodyBefore' => strtr($before, self::STYLES),
                'bodyAfter' => strtr($after, self::STYLES),
                'textBefore' => self::toText($before),
                'textAfter' => self::toText($after),
            ]);
    }

    /**
     * Reduce editor HTML to the allow-listed tags. Only <a href> keeps an
     * attribute, and only for http(s)/mailto links or a {placeholder} link.
     */
    public static function clean(string $html): string
    {
        $html = str_replace(["\u{00A0}", '&nbsp;'], ' ', $html);

        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $body = $doc->getElementsByTagName('body')->item(0);

        return $body ? trim(self::cleanChildren($body)) : '';
    }

    private static function cleanChildren(DOMNode $node): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                // Line breaks between blocks/list items are just formatting.
                if (trim($child->nodeValue) === '' && in_array($node->nodeName, ['body', 'ul', 'ol', 'blockquote'], true)) {
                    continue;
                }
                $out .= htmlspecialchars($child->nodeValue, ENT_NOQUOTES, 'UTF-8');

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue; // comments etc.
            }

            $tag = strtolower($child->tagName);
            $tag = self::RENAMED_TAGS[$tag] ?? $tag;

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                continue;
            }

            if ($tag === 'br') {
                $out .= '<br>';

                continue;
            }

            $inner = self::cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $out .= $inner;

                continue;
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                $out .= self::isSafeHref($href)
                    ? '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.$inner.'</a>'
                    : $inner;

                continue;
            }

            $out .= "<{$tag}>{$inner}</{$tag}>";
        }

        return $out;
    }

    private static function isSafeHref(string $href): bool
    {
        return preg_match('#^(https?://|mailto:)#i', $href) === 1
            || preg_match('#^\{[a-z0-9_]+\}[^\s"<>]*$#i', $href) === 1;
    }

    /**
     * Replace {placeholders} with values. Unknown ones are left as they are.
     */
    private static function fill(string $text, array $values, bool $escape): string
    {
        $pairs = [];
        foreach ($values as $name => $value) {
            $pairs['{'.$name.'}'] = $escape ? e((string) $value) : (string) $value;
        }

        return strtr($text, $pairs);
    }

    /**
     * Plain-text version of cleaned HTML, for the email's text part.
     */
    private static function toText(string $html): string
    {
        $text = preg_replace('#<a href="([^"]*)">(.*?)</a>#s', '$2 ($1)', $html);
        $text = str_replace(['<br>', '<li>'], ["\n", '- '], $text);
        $text = preg_replace('#</(p|h1|h2|h3|blockquote)>#', "\n\n", $text);
        $text = preg_replace('#</(li|ul|ol)>#', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * @return array<string, string>
     */
    private static function globalValues(): array
    {
        return [
            'app_name' => config('app.name'),
            'dashboard_url' => route('dashboard'),
            'instances_url' => route('instances.index'),
            'billing_url' => route('billing.index'),
            'docs_url' => route('docs.index'),
            'contact_url' => route('contact'),
            'terms_url' => route('terms'),
        ];
    }
}
