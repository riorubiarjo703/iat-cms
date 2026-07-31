<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SiteSettingsPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_renders(): void
    {
        $this->get(SiteSettingsPage::getUrl())->assertSuccessful();
    }

    public function test_it_mounts_on_a_fresh_database(): void
    {
        Livewire::test(SiteSettingsPage::class)->assertSuccessful();

        $this->assertSame(1, SiteSetting::query()->count());
    }

    public function test_it_saves_site_name_and_translated_meta(): void
    {
        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'site_name' => 'SCBD',
                'default_locale' => 'en',
                'meta_title' => ['en' => 'SCBD', 'id' => 'SCBD Jakarta'],
                'meta_description' => ['en' => 'Forty-five hectares.'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = SiteSetting::singleton()->fresh();

        $this->assertSame('SCBD', $settings->site_name);
        $this->assertSame('SCBD Jakarta', $settings->t('meta_title', 'id'));
        $this->assertSame('SCBD', $settings->t('meta_title', 'cn'));
    }

    public function test_english_meta_title_is_required(): void
    {
        Livewire::test(SiteSettingsPage::class)
            ->fillForm(['site_name' => 'SCBD', 'meta_title' => ['en' => null]])
            ->call('save')
            ->assertHasFormErrors(['meta_title.en' => 'required']);
    }

    /**
     * The Select's ->options() only constrains the UI dropdown; without a
     * server-side rule an arbitrary string reaches getState()'s validate()
     * call unchecked and would persist, later blanking all six `{!! !!}`
     * homepage headings via HomepageData's `?? ''` fallback.
     */
    public function test_an_invalid_default_locale_is_rejected(): void
    {
        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'site_name' => 'SCBD',
                'default_locale' => 'fr',
                'meta_title' => ['en' => 'SCBD'],
                'meta_description' => ['en' => 'Desc'],
            ])
            ->call('save')
            ->assertHasFormErrors(['default_locale']);

        $this->assertNotSame('fr', SiteSetting::singleton()->fresh()->default_locale);
    }

    public function test_it_saves_social_links(): void
    {
        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'site_name' => 'SCBD',
                'meta_title' => ['en' => 'SCBD'],
                'meta_description' => ['en' => 'Desc'],
                'social' => ['instagram' => 'https://instagram.com/scbd', 'linkedin' => null],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://instagram.com/scbd', SiteSetting::singleton()->fresh()->social['instagram']);
    }
}
