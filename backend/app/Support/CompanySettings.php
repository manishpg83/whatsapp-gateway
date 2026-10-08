<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The company details (name, email, phone, website, address) shown on the
 * About, Privacy and Terms pages and in the structured data search engines
 * read. The defaults are in config/company.php; Admin → Company settings
 * saves an edited copy in the settings table (key "company").
 *
 * apply() runs on every request (AppServiceProvider) and puts the saved
 * copy into config('company'), so pages keep reading config('company.*').
 */
class CompanySettings
{
    public const KEY = 'company';

    private const CACHE_KEY = 'settings.company';

    public static function apply(): void
    {
        try {
            $saved = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::where('key', self::KEY)->value('value') ?? []);
        } catch (Throwable) {
            // No settings table yet (fresh install, before migrating):
            // the config file's defaults are used.
            return;
        }

        if ($saved) {
            config(['company' => array_replace_recursive(config('company'), $saved)]);
        }
    }

    /**
     * Saves the edited details and returns the names of the fields that
     * changed (for the audit log).
     *
     * @return list<string>
     */
    public static function save(array $company, int $adminId): array
    {
        $before = config('company');

        $setting = Setting::firstOrNew(['key' => self::KEY]);
        $setting->fill(['value' => $company, 'updated_by' => $adminId])->save();

        Cache::forget(self::CACHE_KEY);
        config(['company' => array_replace_recursive($before, $company)]);

        $changed = [];
        foreach (['name', 'email', 'phone', 'website'] as $field) {
            if (($before[$field] ?? null) !== $company[$field]) {
                $changed[] = $field;
            }
        }
        foreach ($company['address'] as $field => $value) {
            if (($before['address'][$field] ?? null) !== $value) {
                $changed[] = "address {$field}";
            }
        }

        return $changed;
    }

    /**
     * "+91 94288 89935" → "+919428889935", for tel: links and schema.org.
     */
    public static function e164(string $phone): string
    {
        return '+'.preg_replace('/\D/', '', $phone);
    }

    /**
     * The address as display lines, leaving out empty parts.
     *
     * @return list<string>
     */
    public static function addressLines(): array
    {
        $address = config('company.address');
        $cityLine = trim(implode(', ', array_filter([$address['city'], $address['region']])).' '.$address['postal_code']);

        return array_values(array_filter([$address['street'], $cityLine, $address['country']]));
    }
}
