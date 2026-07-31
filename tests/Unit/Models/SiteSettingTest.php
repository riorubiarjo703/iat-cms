<?php

namespace Tests\Unit\Models;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_singleton_creates_the_row_on_a_fresh_database(): void
    {
        $settings = SiteSetting::singleton();

        $this->assertSame(1, $settings->id);
        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_it_defaults_to_english_with_all_three_locales_available(): void
    {
        $settings = SiteSetting::singleton()->fresh();

        $this->assertSame('en', $settings->default_locale);
        $this->assertSame(['en', 'id', 'cn'], $settings->available_locales);
    }

    public function test_locales_constant_lists_the_three_supported_languages(): void
    {
        $this->assertSame(['en' => 'English', 'id' => 'Indonesian', 'cn' => '中文'], SiteSetting::LOCALES);
    }

    public function test_social_links_round_trip_as_an_array(): void
    {
        $settings = SiteSetting::singleton();
        $settings->update(['social' => ['instagram' => 'https://instagram.com/scbd']]);

        $this->assertSame(['instagram' => 'https://instagram.com/scbd'], $settings->fresh()->social);
    }

    public function test_meta_fields_are_translatable(): void
    {
        $settings = SiteSetting::singleton();
        $settings->update(['meta_title' => ['en' => 'SCBD', 'id' => 'SCBD Jakarta']]);

        $this->assertSame('SCBD Jakarta', $settings->fresh()->t('meta_title', 'id'));
        $this->assertSame('SCBD', $settings->fresh()->t('meta_title', 'cn'));
    }
}
