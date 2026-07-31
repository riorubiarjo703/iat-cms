<?php

namespace Tests\Unit\Filament;

use App\Filament\Support\LocaleTabs;
use App\Models\SiteSetting;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Tests\TestCase;

/**
 * Division of labour with SiteSettingTest: that suite's
 * test_locales_constant_lists_the_three_supported_languages() pins the literal
 * content of SiteSetting::LOCALES. This suite pins that LocaleTabs *follows*
 * whatever that constant says, rather than restating its current values —
 * so a fourth locale added there, or a helper reimplemented with a hardcoded
 * array, is caught here instead of silently drifting.
 */
class LocaleTabsTest extends TestCase
{
    public function test_it_builds_one_tab_per_supported_locale(): void
    {
        $tabs = LocaleTabs::make(fn (string $locale) => [TextInput::make("title.$locale")]);

        $this->assertInstanceOf(Tabs::class, $tabs);
        $this->assertCount(count(SiteSetting::LOCALES), $tabs->getDefaultChildComponents());
    }

    public function test_tab_labels_are_the_locale_display_names(): void
    {
        $tabs = LocaleTabs::make(fn (string $locale) => [TextInput::make("title.$locale")]);

        $labels = array_map(
            fn ($tab) => $tab->getLabel(),
            $tabs->getDefaultChildComponents(),
        );

        $this->assertSame(array_values(SiteSetting::LOCALES), $labels);
    }

    public function test_the_closure_receives_each_locale_code(): void
    {
        $seen = [];
        LocaleTabs::make(function (string $locale) use (&$seen) {
            $seen[] = $locale;

            return [TextInput::make("title.$locale")];
        })->getDefaultChildComponents();

        $this->assertSame(array_keys(SiteSetting::LOCALES), $seen);
    }

    public function test_english_is_the_fallback_locale(): void
    {
        $this->assertTrue(LocaleTabs::isFallback('en'));
        $this->assertFalse(LocaleTabs::isFallback('id'));
        $this->assertFalse(LocaleTabs::isFallback('cn'));
    }
}
