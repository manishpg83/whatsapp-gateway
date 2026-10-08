<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AdminAudit;
use App\Support\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Company settings: the company name, email, phone, website and
 * address shown on the About, Privacy and Terms pages. See CompanySettings.
 */
class CompanySettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.company.edit', [
            'company' => config('company'),
            'saved' => Setting::with('editor')->where('key', CompanySettings::KEY)->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+\d[\d ()-]{6,}$/'],
            'website' => ['required', 'url:http,https', 'max:190'],
            'street' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'alpha', 'size:2'],
        ], [
            'phone.regex' => 'Start with + and the country code, e.g. +91 94288 89935.',
            'website.url' => 'Enter the full address, starting with https://',
            'country_code.*' => 'Enter the 2-letter country code, e.g. IN.',
        ]);

        $changed = CompanySettings::save([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'phone_e164' => CompanySettings::e164($data['phone']),
            'website' => $data['website'],
            'address' => [
                'street' => $data['street'],
                'city' => $data['city'],
                'region' => $data['region'] ?? '',
                'postal_code' => $data['postal_code'] ?? '',
                'country' => $data['country'],
                'country_code' => strtoupper($data['country_code']),
            ],
        ], $request->user()->id);

        if ($changed) {
            AdminAudit::record($request, 'company_settings.updated', Setting::where('key', CompanySettings::KEY)->first(), [
                'changed' => implode(', ', $changed),
            ]);
        }

        return redirect()->route('admin.company.edit')
            ->with('status', $changed ? 'Company details saved. The About, Privacy and Terms pages now show them.' : 'No changes to save.');
    }
}
