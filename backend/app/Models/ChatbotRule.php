<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One chatbot entry for an instance: when a received message contains one
 * of the keywords (whole word, any case), the answer is the reply. If
 * several entries match, the oldest one (top of the list) wins.
 */
#[Fillable(['whatsapp_session_id', 'question', 'keywords', 'answer'])]
class ChatbotRule extends Model
{
    // Most entries one instance may have.
    public const MAX_PER_INSTANCE = 100;

    // Most keywords one entry may have, and the longest one keyword may be.
    public const MAX_KEYWORDS = 20;

    public const MAX_KEYWORD_LENGTH = 50;

    // Protections when replying for real (see SendChatbotReply): the same
    // entry isn't sent to the same person again within this many minutes...
    public const REPEAT_WAIT_MINUTES = 2;

    // ...and one person gets at most this many bot replies per hour, so two
    // bots answering each other can't loop forever.
    public const MAX_REPLIES_PER_CONTACT_PER_HOUR = 10;

    // Outside business hours, one person gets the "we're closed" message at
    // most once in this many hours.
    public const CLOSED_MESSAGE_WAIT_HOURS = 12;

    // Received message types whose text (or caption) the bot reads.
    public const REPLY_TO_TYPES = ['text', 'image', 'video'];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WhatsappSession, $this>
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    /**
     * The bot replies this entry sent (for its stats).
     *
     * @return HasMany<Message, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The entry with the most matching keywords in $message, or null — in
     * which case the bot stays silent. On a tie, the one higher in the list
     * wins. E.g. "price of these shoes?" picks an entry with "shoes" AND
     * "price" over one with only "shoes".
     *
     * @param  iterable<ChatbotRule>  $rules
     */
    public static function bestMatch(iterable $rules, string $message): ?self
    {
        $best = null;
        $bestScore = 0;

        foreach ($rules as $rule) {
            $score = count($rule->matchedKeywords($message));

            // ">" not ">=": an equal score never replaces an earlier entry.
            if ($score > $bestScore) {
                $best = $rule;
                $bestScore = $score;
            }
        }

        return $best;
    }

    public function matches(string $message): bool
    {
        return $this->matchedKeywords($message) !== [];
    }

    /**
     * The keywords found in $message as whole words or phrases, ignoring
     * case: "price" matches "What's the PRICE?" but "hi" doesn't match
     * "this". Singular and plural count as the same word ("shoe" matches
     * "shoes" and the other way round), and are counted once — so an entry
     * with both "shoe" and "shoes" doesn't score double.
     *
     * @return list<string>
     */
    public function matchedKeywords(string $message): array
    {
        $found = [];

        foreach ($this->keywords as $keyword) {
            $words = explode(' ', $keyword);
            $last = array_pop($words);
            $base = self::singular($last);

            if (isset($found[implode(' ', [...$words, $base])])) {
                continue;
            }

            // Only the last word gets plural forms ("opening time" also
            // matches "opening times"). A space matches any run of spaces.
            $phrase = implode('\s+', array_map(fn ($w) => preg_quote($w, '/'), $words));
            $forms = implode('|', array_map(fn ($w) => preg_quote($w, '/'), self::wordForms($last)));
            $pattern = ($phrase === '' ? '' : $phrase.'\s+').'(?:'.$forms.')';

            // Not preceded or followed by a letter, digit or underscore.
            if (preg_match('/(?<![\p{L}\p{N}_])'.$pattern.'(?![\p{L}\p{N}_])/iu', $message)) {
                $found[implode(' ', [...$words, $base])] = $keyword;
            }
        }

        return array_values($found);
    }

    /**
     * A simple English singular: "shoes" -> "shoe", "boxes" -> "box",
     * "batteries" -> "battery". Short words, words ending in "ss", and
     * anything that isn't plain a-z letters (numbers, ₹, Hindi, ...) are
     * left as they are.
     */
    private static function singular(string $word): string
    {
        if (strlen($word) <= 3 || ! preg_match('/^[a-z]+$/', $word) || str_ends_with($word, 'ss')) {
            return $word;
        }
        if (strlen($word) > 4 && str_ends_with($word, 'ies')) {
            return substr($word, 0, -3).'y';
        }
        if (preg_match('/(s|x|z|ch|sh)es$/', $word)) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Every spelling that counts as the same word: the word as typed, its
     * singular, and that singular's plurals ("shoe", "shoes", "shoees" —
     * an extra nonsense form is harmless).
     *
     * @return list<string>
     */
    private static function wordForms(string $word): array
    {
        if (strlen($word) <= 2 || ! preg_match('/^[a-z]+$/', $word)) {
            return [$word];
        }

        $base = self::singular($word);
        $forms = [$word, $base, $base.'s', $base.'es'];

        if (preg_match('/[^aeiou]y$/', $base)) {
            $forms[] = substr($base, 0, -1).'ies';
        }

        return array_values(array_unique($forms));
    }

    /**
     * Turns what the owner typed ("Price,  COST , price") into a clean,
     * lower-cased list without duplicates (["price", "cost"]).
     *
     * @return list<string>
     */
    public static function parseKeywords(string $input): array
    {
        $keywords = [];

        foreach (explode(',', $input) as $keyword) {
            $keyword = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $keyword)));

            if ($keyword !== '' && ! in_array($keyword, $keywords, true)) {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }
}
