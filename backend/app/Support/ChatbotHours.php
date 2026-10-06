<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * An instance's chatbot business hours (stored as JSON in
 * whatsapp_sessions.chatbot_hours). The same hours on every chosen day,
 * read in the owner's timezone. Close earlier than open means overnight:
 * open 20:00, close 02:00 on Friday = Friday 8 PM to Saturday 2 AM.
 */
class ChatbotHours
{
    // ISO day numbers (1 = Monday ... 7 = Sunday) => short name.
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public const DEFAULTS = [
        'enabled' => false,
        'days' => [1, 2, 3, 4, 5, 6],
        'open' => '10:00',
        'close' => '19:00',
        'timezone' => 'Asia/Kolkata',
        'message' => "Thanks for your message! We're closed right now. We'll reply as soon as we're back.",
    ];

    public bool $enabled;

    /** @var list<int> */
    public array $days;

    public string $open;

    public string $close;

    public string $timezone;

    public string $message;

    public function __construct(?array $settings)
    {
        $settings = array_merge(self::DEFAULTS, $settings ?? []);

        $this->enabled = (bool) $settings['enabled'];
        $this->days = array_map('intval', $settings['days']);
        $this->open = $settings['open'];
        $this->close = $settings['close'];
        $this->timezone = $settings['timezone'];
        $this->message = $settings['message'];
    }

    /**
     * Whether $at falls inside the hours. Always true when hours are off.
     */
    public function isOpen(CarbonInterface $at): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $local = $at->copy()->setTimezone($this->timezone);
        $time = $local->format('H:i');

        if ($this->open < $this->close) {
            return $this->isOpenDay($local) && $time >= $this->open && $time < $this->close;
        }

        // Overnight: still open after midnight if yesterday was an open day.
        return ($this->isOpenDay($local) && $time >= $this->open)
            || ($this->isOpenDay($local->copy()->subDay()) && $time < $this->close);
    }

    /**
     * e.g. "Mon–Sat, 10:00–19:00" or "Every day, 09:00–21:00".
     */
    public function summary(): string
    {
        $days = $this->days;
        sort($days);

        $label = match (true) {
            $days === [] => 'No days',
            count($days) === 7 => 'Every day',
            // Three or more days in a row, e.g. Mon–Sat.
            count($days) > 2 && $days === range($days[0], end($days)) => self::DAYS[$days[0]].'–'.self::DAYS[end($days)],
            default => implode(', ', array_map(fn ($d) => self::DAYS[$d], $days)),
        };

        return "{$label}, {$this->open}–{$this->close}";
    }

    private function isOpenDay(CarbonInterface $local): bool
    {
        return in_array($local->isoWeekday(), $this->days, true);
    }
}
