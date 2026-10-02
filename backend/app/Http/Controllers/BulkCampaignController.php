<?php

namespace App\Http\Controllers;

use App\Exceptions\MediaFetchException;
use App\Models\BulkCampaign;
use App\Models\BulkTemplate;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Services\MediaFetcher;
use App\Services\PlanLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk messages: send one message to a list of numbers from the
 * dashboard. Sending happens in the background, one message every
 * `interval_seconds` (App\Jobs\SendBulkMessage).
 *
 * Every campaign/instance lookup is scoped to the logged-in user
 * (CLAUDE.md §5) — another user's campaign id is just a 404.
 */
class BulkCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = $request->user()->bulkCampaigns()
            ->with('whatsappSession')
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'recipients as failed_count' => fn ($q) => $q->where('status', 'failed'),
                'recipients as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->latest()->latest('id')
            ->paginate(15);

        return view('bulk.index', ['campaigns' => $campaigns]);
    }

    public function create(Request $request, PlanLimiter $limiter): View
    {
        $user = $request->user();

        return view('bulk.create', [
            'instances' => $user->whatsappSessions()->orderBy('name')->get(),
            'maxRecipients' => self::maxRecipients($user),
            'remaining' => max(0, $limiter->messageLimit($user) - $limiter->messagesSentThisMonth($user)),
            'intervals' => config('bulk.interval_options'),
            'templates' => $user->bulkTemplates()->orderBy('name')->get(['id', 'name', 'body']),
        ]);
    }

    public function store(Request $request, PlanLimiter $limiter, MediaFetcher $fetcher): RedirectResponse
    {
        $user = $request->user();
        $isText = in_array($request->input('type'), [null, '', 'text'], true);
        $fromCsv = $request->input('recipients_source') === 'csv';
        $later = $request->input('when') === 'later';
        // Errors about the list go under whichever box the user filled in.
        $listField = $fromCsv ? 'csv' : 'numbers';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'instance_id' => ['required', 'string'],
            'type' => ['nullable', Rule::in(array_keys(BulkCampaign::TYPES))], // none = text
            // Text: the message. Image/video/document: an optional caption.
            'message' => [Rule::requiredIf($isText), 'nullable', 'string', 'max:4096'],
            'name_fallback' => ['nullable', 'string', 'max:50'],
            // Size and real file type are checked by MediaFetcher below.
            'media' => [Rule::requiredIf(! $isText), 'nullable', 'file'],
            'recipients_source' => ['nullable', Rule::in(['paste', 'csv'])],
            'numbers' => [Rule::requiredIf(! $fromCsv), 'nullable', 'string', 'max:200000'],
            'csv' => [Rule::requiredIf($fromCsv), 'nullable', 'file', 'max:2048', 'extensions:csv,txt'],
            'interval' => ['required', 'integer', Rule::in(config('bulk.interval_options'))],
            'when' => ['nullable', Rule::in(['now', 'later'])],
            'scheduled_at' => [Rule::requiredIf($later), 'nullable', 'date_format:Y-m-d\TH:i'],
            'timezone' => ['nullable', 'string', 'max:64'], // checked in scheduleTime()
            'save_template' => ['nullable', 'boolean'],
            'template_name' => [Rule::requiredIf($request->boolean('save_template')), 'nullable', 'string', 'max:100'],
            'consent' => ['accepted'],
        ], [
            'scheduled_at.required' => 'Choose the date and time to send.',
            'scheduled_at.date_format' => 'Choose a valid date and time.',
            'template_name.required' => 'Give the saved message a name.',
            'consent.accepted' => 'Please confirm that these people agreed to receive messages from you.',
            'media.required' => 'Choose the file to send.',
            'csv.required' => 'Choose the CSV file with your numbers.',
            'csv.extensions' => 'Upload a .csv file (in Excel: File → Save As → CSV).',
            'csv.max' => 'The CSV file is too large (max 2 MB).',
        ]);
        $data['type'] ??= 'text';

        $instance = $user->whatsappSessions()->where('instance_id', $data['instance_id'])->first();

        if (! $instance) {
            throw ValidationException::withMessages(['instance_id' => 'Choose one of your instances.']);
        }

        if ($instance->status !== 'connected') {
            throw ValidationException::withMessages(['instance_id' => 'This instance is not connected. Connect it first.']);
        }

        // A scheduled campaign is checked again when it starts
        // (StartScheduledBulkCampaign), so only "send now" needs this here.
        if (! $later && $this->otherRunning($instance)) {
            throw ValidationException::withMessages(['instance_id' => 'Another campaign is already sending from this instance. Wait for it to finish, pause it, or schedule this one for later.']);
        }

        [$scheduledAt, $timezone] = $later ? $this->scheduleTime($data) : [null, null];

        if ($request->boolean('save_template') && $user->bulkTemplates()->count() >= BulkTemplate::MAX_PER_USER) {
            throw ValidationException::withMessages(['template_name' => 'You already have '.BulkTemplate::MAX_PER_USER.' saved messages. Delete one first.']);
        }

        $listText = $fromCsv ? (string) file_get_contents($request->file('csv')->getRealPath()) : $data['numbers'];
        [$recipients, $invalid] = self::parseRecipients($listText);

        if ($invalid) {
            throw ValidationException::withMessages([$listField => 'These are not valid phone numbers: '
                .implode(', ', array_slice($invalid, 0, 5)).(count($invalid) > 5 ? ' and '.(count($invalid) - 5).' more' : '')
                .'. Use the full number with country code, digits only (e.g. 919876543210).']);
        }

        if (! $recipients) {
            throw ValidationException::withMessages([$listField => 'Add at least one phone number.']);
        }

        $max = self::maxRecipients($user);
        if (count($recipients) > $max) {
            throw ValidationException::withMessages([$listField => 'Your plan allows up to '.number_format($max).' numbers per campaign — you added '.number_format(count($recipients)).'.']);
        }

        $remaining = $limiter->messageLimit($user) - $limiter->messagesSentThisMonth($user);
        if (count($recipients) > $remaining) {
            throw ValidationException::withMessages([$listField => 'You have '.number_format(max(0, $remaining)).' messages left this month, but added '.number_format(count($recipients)).' numbers. Remove some, or upgrade your plan.']);
        }

        // Stored last, once everything else is valid, so a rejected form
        // never leaves an unused file behind. Uploaded once; every message
        // of the campaign uses this same file.
        $media = null;
        if (! $isText) {
            try {
                $media = $fetcher->fromUpload($instance, $request->file('media'), $data['type']);
            } catch (MediaFetchException $e) {
                throw ValidationException::withMessages(['media' => $e->getMessage()]);
            }
        }

        $campaign = DB::transaction(function () use ($user, $instance, $data, $recipients, $media) {
            $campaign = $user->bulkCampaigns()->create([
                'whatsapp_session_id' => $instance->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'body' => (string) ($data['message'] ?? ''),
                'name_fallback' => trim((string) ($data['name_fallback'] ?? '')) ?: null,
                'media_path' => $media['path'] ?? null,
                'media_mime_type' => $media['mime_type'] ?? null,
                'media_file_name' => $media['file_name'] ?? null,
                'media_size' => $media['size'] ?? null,
                'interval_seconds' => (int) $data['interval'],
                'status' => 'paused', // start() below sets it running
            ]);

            $now = now();
            foreach (array_chunk($recipients, 500, preserve_keys: true) as $chunk) {
                $rows = [];
                foreach ($chunk as $phone => $name) {
                    $rows[] = [
                        'bulk_campaign_id' => $campaign->id,
                        'phone' => (string) $phone,
                        'name' => $name,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                $campaign->recipients()->insert($rows);
            }

            return $campaign;
        });

        if ($request->boolean('save_template') && trim((string) $campaign->body) !== '') {
            $user->bulkTemplates()->create(['name' => $data['template_name'], 'body' => $campaign->body]);
        }

        if ($scheduledAt) {
            $campaign->schedule($scheduledAt, $timezone);

            return redirect()->route('bulk.show', $campaign)
                ->with('status', 'Campaign scheduled for '.$campaign->scheduledAtLocal()->format('M j, Y \a\t g:i A').'. Keep the instance connected and the queue worker running.');
        }

        $campaign->start();

        return redirect()->route('bulk.show', $campaign)
            ->with('status', 'Campaign started. Messages are sent one every '.$campaign->interval_seconds.' seconds — you can leave this page.');
    }

    /**
     * The chosen "send at" time, read in the user's own timezone (from the
     * browser; India time if it didn't say), as UTC.
     *
     * @return array{0: Carbon, 1: string}
     */
    private function scheduleTime(array $data): array
    {
        // Browsers may send older names such as "Asia/Calcutta", so allow
        // those too; anything unknown quietly falls back to India time.
        $timezone = $data['timezone'] ?? '';
        if (! in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            $timezone = config('bulk.default_timezone');
        }
        $at = Carbon::createFromFormat('Y-m-d\TH:i', $data['scheduled_at'], $timezone)->utc();

        if ($at->lessThan(now()->addMinute())) {
            throw ValidationException::withMessages(['scheduled_at' => 'Choose a time in the future (at least a minute from now).']);
        }

        if ($at->greaterThan(now()->addDays((int) config('bulk.max_schedule_days')))) {
            throw ValidationException::withMessages(['scheduled_at' => 'You can schedule up to '.config('bulk.max_schedule_days').' days ahead.']);
        }

        return [$at, $timezone];
    }

    public function show(Request $request, string $campaign): View
    {
        $campaign = $this->findOwned($request, $campaign);

        return view('bulk.show', [
            'campaign' => $campaign,
            'counts' => $campaign->counts(),
            'recipients' => $campaign->recipients()->with('message')->orderBy('id')->paginate(25),
            'hasNames' => $campaign->recipients()->whereNotNull('name')->exists(),
        ]);
    }

    /**
     * The campaign's image/video/document, for its owner only. Same safety
     * headers as MessageMediaController: documents always download.
     */
    public function media(Request $request, string $campaign): StreamedResponse
    {
        $campaign = $this->findOwned($request, $campaign);

        abort_unless($campaign->media_path && Storage::disk('whatsapp_media')->exists($campaign->media_path), 404);

        $inline = $campaign->mediaIsInline() && ! $request->boolean('download');

        return Storage::disk('whatsapp_media')->response(
            $campaign->media_path,
            $campaign->media_file_name ?: $campaign->type.'-'.basename($campaign->media_path),
            [
                'Content-Type' => $inline ? $campaign->media_mime_type : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
                'Cache-Control' => 'private, max-age=3600',
            ],
            $inline ? 'inline' : 'attachment'
        );
    }

    /**
     * Polled by the campaign page while it is sending.
     */
    public function status(Request $request, string $campaign): JsonResponse
    {
        $campaign = $this->findOwned($request, $campaign);

        return response()->json([
            'status' => $campaign->status,
            'counts' => $campaign->counts(),
        ]);
    }

    public function pause(Request $request, string $campaign): RedirectResponse
    {
        $campaign = $this->findOwned($request, $campaign);

        if ($campaign->status === 'running') {
            $campaign->pause();
        }

        return redirect()->route('bulk.show', $campaign)->with('status', 'Campaign paused. Resume it anytime.');
    }

    public function resume(Request $request, string $campaign, PlanLimiter $limiter): RedirectResponse
    {
        $campaign = $this->findOwned($request, $campaign);

        // Also "Send now" for a scheduled campaign.
        if (! in_array($campaign->status, ['paused', 'scheduled'], true)) {
            return redirect()->route('bulk.show', $campaign);
        }

        $wasScheduled = $campaign->status === 'scheduled';

        $error = match (true) {
            $campaign->whatsappSession->status !== 'connected' => 'The instance is not connected. Reconnect it first.',
            ! $limiter->canSendMessage($request->user()) => "You've reached your plan's monthly message limit.",
            $campaign->otherRunningOnInstance() => 'Another campaign is sending from this instance. Pause it or wait for it to finish.',
            default => null,
        };

        if ($error) {
            return redirect()->route('bulk.show', $campaign)->with('error', $error);
        }

        $campaign->start();

        return redirect()->route('bulk.show', $campaign)->with('status', $wasScheduled ? 'Sending now instead of at the scheduled time.' : 'Campaign resumed.');
    }

    public function cancel(Request $request, string $campaign): RedirectResponse
    {
        $campaign = $this->findOwned($request, $campaign);

        if ($campaign->isActive()) {
            $campaign->cancel();
        }

        return redirect()->route('bulk.show', $campaign)->with('status', 'Campaign cancelled. Numbers not sent yet were skipped.');
    }

    /**
     * A ready-made CSV to fill in (Bulk → New campaign → Upload CSV).
     */
    public function sampleCsv(): Response
    {
        return response("phone,name\n919876543210,Rahul\n919812345678,Priya\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bulk-numbers-sample.csv"',
        ]);
    }

    /**
     * Numbers (and optional names) from a CSV file or pasted text. Accepts:
     *  - one number per line, or several on a line split by , ; or tab;
     *  - "number, name" lines — a CSV, or columns copied from Excel (tab);
     *  - an optional header row such as "phone,name" (skipped).
     * Spaces, dashes, brackets and a leading + are removed; duplicates are
     * dropped (the first name given for a number is kept).
     *
     * @return array{0: array<string, string|null>, 1: array<int, string>} [phone => name, invalid entries]
     */
    public static function parseRecipients(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text); // Excel's UTF-8 marker
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); // older Excel CSVs
        }

        $valid = [];
        $invalid = [];
        $firstLine = true;

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $delimiter = match (true) {
                str_contains($line, "\t") => "\t",
                str_contains($line, ';') && ! str_contains($line, ',') => ';',
                default => ',',
            };
            $fields = array_values(array_filter(
                array_map(fn ($field) => trim((string) $field, " \t;,\"'"), str_getcsv($line, $delimiter, '"', '')),
                fn (string $field) => $field !== '',
            ));

            $isHeader = $firstLine && isset($fields[0]) && preg_match('/^[a-z _.-]*(phone|number|mobile|whatsapp|contact)[a-z _.-]*$/i', $fields[0]);
            $firstLine = false;
            if ($isHeader || ! $fields) {
                continue;
            }

            // "number, name" when the 2nd field isn't itself a number;
            // otherwise every field on the line is a number.
            $entries = count($fields) >= 2 && ! preg_match('/^\+?[\d\s\-().]{7,}$/', $fields[1])
                ? [[$fields[0], $fields[1]]]
                : array_map(fn (string $field) => [$field, null], $fields);

            foreach ($entries as [$raw, $name]) {
                $number = ltrim((string) preg_replace('/[\s\-().]/', '', $raw), '+');

                if (! preg_match('/^\d{7,15}$/', $number)) {
                    $invalid[] = $raw;

                    continue;
                }

                $valid[$number] ??= self::cleanName($name);
            }
        }

        $result = [];
        foreach ($valid as $number => $name) {
            $result[(string) $number] = $name;
        }

        return [$result, $invalid];
    }

    private static function cleanName(?string $name): ?string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+|\s{2,}/u', ' ', (string) $name));

        return $name === '' ? null : mb_substr($name, 0, 100);
    }

    /**
     * Most numbers allowed in one campaign on this user's plan.
     */
    public static function maxRecipients(User $user): int
    {
        $isPaid = (int) $user->subscription->planDetails()['price'] > 0;

        return (int) config('bulk.max_recipients.'.($isPaid ? 'paid' : 'free'));
    }

    private function findOwned(Request $request, string $campaignId): BulkCampaign
    {
        return $request->user()->bulkCampaigns()->with('whatsappSession')->where('campaign_id', $campaignId)->firstOrFail();
    }

    /**
     * One campaign at a time per instance, so the pacing really holds.
     */
    private function otherRunning(WhatsappSession $instance): bool
    {
        return BulkCampaign::where('whatsapp_session_id', $instance->id)->where('status', 'running')->exists();
    }
}
