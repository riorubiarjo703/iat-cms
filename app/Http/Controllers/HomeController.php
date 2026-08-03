<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\HomepageData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // A page flagged as the homepage takes over "/". While none exists the
        // hand-built homepage still serves, so the switch is reversible by
        // clearing one flag rather than by a deploy.
        $homepage = Page::homepage();

        if ($homepage !== null) {
            App::setLocale($this->resolvedLocale());

            return view('page', ['page' => $homepage]);
        }

        $data = HomepageData::build();

        // HasTranslatableFields::t() falls back to app()->getLocale() whenever
        // it's called without an explicit locale (nav labels, district/facility
        // copy, stat labels, <title>/meta), while the six headings and the
        // <html lang> attribute read $data->settings->default_locale directly.
        // Both paths need to agree, and both need to survive an invalid stored
        // value (SiteSettingsPage's ->in() rule only blocks new writes through
        // the admin form; existing rows can still hold a stale/invalid value)
        // without the six headings blanking out or the page crashing.
        //
        // FALLBACK_LOCALE is read through SiteSetting, not the
        // HasTranslatableFields trait that declares it — PHP 8.4 forbids
        // accessing a trait constant directly (see global-constraints.md,
        // Task 9).
        $locale = array_key_exists($data->settings->default_locale, SiteSetting::LOCALES)
            ? $data->settings->default_locale
            : SiteSetting::FALLBACK_LOCALE;

        App::setLocale($locale);

        // In-memory only — never persisted. Keeps the six i18n-payload-driven
        // headings and the <html lang> attribute (both read
        // $data->settings->default_locale directly) in agreement with the
        // locale just set above.
        $data->settings->default_locale = $locale;

        return view('home', ['data' => $data]);
    }

    /**
     * The stored locale, or English when it is not one we know about. An
     * invalid value in the column would otherwise blank every heading.
     */
    private function resolvedLocale(): string
    {
        $stored = SiteSetting::singleton()->default_locale;

        return array_key_exists($stored, SiteSetting::LOCALES)
            ? $stored
            : SiteSetting::FALLBACK_LOCALE;
    }
}
