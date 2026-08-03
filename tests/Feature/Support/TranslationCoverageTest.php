<?php

namespace Tests\Feature\Support;

use App\Models\PublicMenuItem;
use App\Models\SiteSetting;
use App\Support\TranslationCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationCoverageTest extends TestCase
{
    use RefreshDatabase;

    private TranslationCoverage $coverage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coverage = new TranslationCoverage;
    }

    public function test_it_discovers_translatable_models_without_a_hardcoded_list(): void
    {
        $models = $this->coverage->translatableModels();

        $this->assertContains(PublicMenuItem::class, $models);
        $this->assertContains(SiteSetting::class, $models);
    }

    public function test_a_model_with_no_translatable_fields_is_skipped(): void
    {
        // User composes no translation concern, so it must not be counted.
        $this->assertNotContains(\App\Models\User::class, $this->coverage->translatableModels());
    }

    public function test_a_fully_translated_field_reads_one_hundred_percent(): void
    {
        PublicMenuItem::create([
            'label' => ['en' => 'Home', 'id' => 'Beranda', 'cn' => '首页'],
            'url' => '/', 'sort' => 1, 'is_active' => true,
        ]);

        $coverage = $this->coverage->perLocale();

        foreach (['en', 'id', 'cn'] as $locale) {
            $this->assertSame(100, $coverage[$locale]['percent'], "[{$locale}] should be fully covered");
        }
    }

    public function test_a_partially_translated_field_reads_proportionally(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Home', 'id' => 'Beranda'], 'url' => '/', 'sort' => 1, 'is_active' => true]);
        PublicMenuItem::create(['label' => ['en' => 'About'], 'url' => '/about', 'sort' => 2, 'is_active' => true]);

        $coverage = $this->coverage->perLocale();

        $this->assertSame(100, $coverage['en']['percent']);
        $this->assertSame(50, $coverage['id']['percent']);
        $this->assertSame(0, $coverage['cn']['percent']);
    }

    public function test_an_empty_string_does_not_count_as_translated(): void
    {
        PublicMenuItem::create(['label' => ['en' => 'Home', 'id' => ''], 'url' => '/', 'sort' => 1, 'is_active' => true]);

        $this->assertSame(0, $this->coverage->perLocale()['id']['percent']);
    }

    public function test_nothing_to_translate_reads_as_a_dash_not_zero_percent(): void
    {
        // No translatable records exist at all. 0% would claim total failure;
        // null renders as an em dash, meaning "nothing to measure".
        foreach ($this->coverage->perLocale() as $locale) {
            $this->assertNull($locale['percent']);
            $this->assertSame(0, $locale['total']);
        }
    }

    public function test_adding_a_locale_changes_the_output_without_a_code_change(): void
    {
        $this->assertCount(count(SiteSetting::LOCALES), $this->coverage->perLocale());
        $this->assertSame(array_keys(SiteSetting::LOCALES), array_keys($this->coverage->perLocale()));
    }
}
