<?php

namespace App\Filament\Support;

use App\Models\SiteSetting;
use Closure;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Locale is the outer axis of every translatable form.
 *
 * Wrapping each individual field in its own three-tab group would produce
 * dozens of nested tab sets; one tab per language instead shows a translator
 * their whole job in one place.
 */
final class LocaleTabs
{
    /**
     * @param  Closure(string): array<int, mixed>  $components
     */
    public static function make(Closure $components, ?string $label = null): Tabs
    {
        $tabs = [];

        foreach (SiteSetting::LOCALES as $locale => $name) {
            $tabs[] = Tab::make($name)->schema($components($locale));
        }

        return Tabs::make($label ?? 'Translations')
            ->tabs($tabs)
            ->columnSpanFull();
    }

    /**
     * English is required everywhere; other locales fall back to it at render
     * time, so a half-finished translation still yields a coherent page.
     *
     * PHP forbids referencing a trait constant directly via the trait name
     * (`HasTranslatableFields::FALLBACK_LOCALE`); it must be read through a
     * class that composes the trait, so `SiteSetting` — which already is the
     * single source of truth for locale data in this codebase — is used here.
     */
    public static function isFallback(string $locale): bool
    {
        return $locale === SiteSetting::FALLBACK_LOCALE;
    }
}
