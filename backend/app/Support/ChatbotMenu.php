<?php

namespace App\Support;

use App\Models\ChatbotRule;
use Illuminate\Support\Collection;

/**
 * An instance's chatbot numbered menu (stored as JSON in
 * whatsapp_sessions.chatbot_menu). When a customer sends one of the menu
 * words, the bot replies with the intro and a numbered list of the entries
 * ticked "Show in menu"; replying with a number sends that entry's answer.
 * "0" (when turned on) means "talk to a person": the bot steps back in
 * that chat and the owner gets an email.
 */
class ChatbotMenu
{
    // How long a customer has to reply with a number after seeing the menu.
    public const VALID_MINUTES = 30;

    // Most options one menu may show (longer menus are hard to read).
    public const MAX_OPTIONS = 15;

    // Most menu words, and the longest one may be.
    public const MAX_KEYWORDS = 10;

    // How long the bot stays quiet after "0", when the owner has chosen
    // "Don't pause" for their own replies.
    public const HUMAN_PAUSE_FALLBACK_MINUTES = 60;

    public const DEFAULTS = [
        'enabled' => false,
        'intro' => 'Hi! Reply with a number:',
        'keywords' => ['menu', 'start'],
        'human_option' => true,
        'human_reply' => "Okay! Someone from our team will reply to you here soon.",
    ];

    public bool $enabled;

    public string $intro;

    /** @var list<string> */
    public array $keywords;

    public bool $humanOption;

    public string $humanReply;

    public function __construct(?array $settings)
    {
        $settings = array_merge(self::DEFAULTS, $settings ?? []);

        $this->enabled = (bool) $settings['enabled'];
        $this->intro = $settings['intro'];
        $this->keywords = array_values($settings['keywords']);
        $this->humanOption = (bool) $settings['human_option'];
        $this->humanReply = $settings['human_reply'];
    }

    /**
     * Whether $message asks for the menu: it has one of the menu words, with
     * the same whole-word matching as entries ("Menu please" counts).
     */
    public function isRequestedBy(string $message): bool
    {
        return (new ChatbotRule(['keywords' => $this->keywords]))->matches($message);
    }

    /**
     * The menu's options: the entries ticked "Show in menu" that are
     * switched on, in the owner's list order, at most MAX_OPTIONS.
     *
     * @param  Collection<int, ChatbotRule>  $rules  in list order
     * @return Collection<int, ChatbotRule>
     */
    public static function options(Collection $rules): Collection
    {
        return $rules->filter(fn (ChatbotRule $rule) => $rule->in_menu && $rule->enabled !== false)
            ->take(self::MAX_OPTIONS)
            ->values();
    }

    /**
     * The WhatsApp message listing the options, e.g.
     * "Hi! Reply with a number:\n1. Prices\n2. Timings\n0. Talk to a person".
     *
     * @param  Collection<int, ChatbotRule>  $options
     */
    public function text(Collection $options): string
    {
        $lines = [$this->intro];

        foreach ($options->values() as $index => $rule) {
            $lines[] = ($index + 1).'. '.$rule->question;
        }

        if ($this->humanOption) {
            $lines[] = '0. Talk to a person';
        }

        return implode("\n", $lines);
    }

    /**
     * The number a reply picks ("2", " 2 ", "2.", "#2", "2)"), or null when
     * the reply is anything else. Only meaningful right after a menu.
     */
    public static function choice(string $message): ?int
    {
        return preg_match('/^\s*#?\s*(\d{1,2})\s*[.)]?\s*$/u', $message, $match) ? (int) $match[1] : null;
    }
}
