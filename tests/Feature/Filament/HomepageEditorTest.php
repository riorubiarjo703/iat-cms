<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\HomepageEditor;
use App\Models\HomepageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_renders(): void
    {
        $this->get(HomepageEditor::getUrl())->assertSuccessful();
    }

    public function test_it_mounts_with_the_existing_content(): void
    {
        HomepageContent::singleton()->update(['hero_line' => ['en' => 'Existing headline']]);

        // The form materialises every locale key for each translatable field,
        // because the schema declares a field per locale. assertFormSet compares
        // the whole array, so the untranslated locales must be listed as null.
        Livewire::test(HomepageEditor::class)
            ->assertFormSet(['hero_line' => ['en' => 'Existing headline', 'id' => null, 'cn' => null]]);
    }

    public function test_it_mounts_on_a_fresh_database(): void
    {
        $this->assertSame(0, HomepageContent::query()->count());

        Livewire::test(HomepageEditor::class)->assertSuccessful();

        $this->assertSame(1, HomepageContent::query()->count());
    }

    public function test_it_saves_all_three_locales(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm([
                'hero_line' => ['en' => 'English line', 'id' => 'Baris Indonesia', 'cn' => '中文标题'],
                'hero_sub' => ['en' => 'Sub'],
                'brand_sub' => ['en' => 'Danayasa'],
                'about_heading' => ['en' => 'About'],
                'about_body' => ['en' => 'Body'],
                'about_cta_label' => ['en' => 'Read more'],
                'district_heading' => ['en' => 'District'],
                'district_body' => ['en' => 'Body'],
                'facilities_heading' => ['en' => 'Facilities'],
                'facilities_body' => ['en' => 'Body'],
                'news_heading' => ['en' => 'News'],
                'news_cta_label' => ['en' => 'All news'],
                'contact_heading' => ['en' => 'Contact'],
                'marquee_text' => ['en' => 'Offices — Hotels'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $content = HomepageContent::singleton()->fresh();

        $this->assertSame('English line', $content->t('hero_line', 'en'));
        $this->assertSame('Baris Indonesia', $content->t('hero_line', 'id'));
        $this->assertSame('中文标题', $content->t('hero_line', 'cn'));
    }

    public function test_english_is_required(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm(['hero_line' => ['en' => null, 'id' => 'Ada']])
            ->call('save')
            ->assertHasFormErrors(['hero_line.en' => 'required']);
    }

    public function test_other_locales_are_optional(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm([
                'hero_line' => ['en' => 'Only English'],
                'hero_sub' => ['en' => 'Sub'],
                'brand_sub' => ['en' => 'Danayasa'],
                'about_heading' => ['en' => 'About'],
                'about_body' => ['en' => 'Body'],
                'about_cta_label' => ['en' => 'Read more'],
                'district_heading' => ['en' => 'District'],
                'district_body' => ['en' => 'Body'],
                'facilities_heading' => ['en' => 'Facilities'],
                'facilities_body' => ['en' => 'Body'],
                'news_heading' => ['en' => 'News'],
                'news_cta_label' => ['en' => 'All news'],
                'contact_heading' => ['en' => 'Contact'],
                'marquee_text' => ['en' => 'Offices'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Only English', HomepageContent::singleton()->fresh()->t('hero_line', 'cn'));
    }

    public function test_it_saves_contact_details(): void
    {
        Livewire::test(HomepageEditor::class)
            ->fillForm([
                'hero_line' => ['en' => 'Line'],
                'hero_sub' => ['en' => 'Sub'],
                'brand_sub' => ['en' => 'Danayasa'],
                'about_heading' => ['en' => 'About'],
                'about_body' => ['en' => 'Body'],
                'about_cta_label' => ['en' => 'Read more'],
                'district_heading' => ['en' => 'District'],
                'district_body' => ['en' => 'Body'],
                'facilities_heading' => ['en' => 'Facilities'],
                'facilities_body' => ['en' => 'Body'],
                'news_heading' => ['en' => 'News'],
                'news_cta_label' => ['en' => 'All news'],
                'contact_heading' => ['en' => 'Contact'],
                'marquee_text' => ['en' => 'Offices'],
                'contact_email' => 'test@example.com',
                'contact_phone' => '+62 (21) 000-0000',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('test@example.com', HomepageContent::singleton()->fresh()->contact_email);
    }
}
