<?php

namespace Tests\Feature\Pages;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\MenuLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => 'About Us'],
            'slug' => 'about-us',
            'content' => ['en' => '<p>Body copy.</p>'],
            'status' => Page::STATUS_PUBLISHED,
        ], $attributes));
    }

    public function test_a_published_page_renders_at_its_slug(): void
    {
        $this->page();

        $this->get('/about-us')->assertSuccessful()->assertSee('Body copy.', false);
    }

    public function test_a_draft_page_is_not_reachable(): void
    {
        $this->page(['status' => Page::STATUS_DRAFT]);

        $this->get('/about-us')->assertNotFound();
    }

    public function test_a_scheduled_page_is_not_reachable_before_its_time(): void
    {
        // Published-in-the-future must not be live, or scheduling means nothing.
        $this->page(['published_at' => now()->addDay()]);

        $this->get('/about-us')->assertNotFound();
    }

    public function test_a_page_scheduled_in_the_past_is_reachable(): void
    {
        $this->page(['published_at' => now()->subDay()]);

        $this->get('/about-us')->assertSuccessful();
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_the_catch_all_does_not_shadow_the_homepage(): void
    {
        // A page whose slug happens to be "home" is still reached at /home;
        // "/" belongs to whichever page carries the flag.
        $this->page(['slug' => 'home']);
        Page::create([
            'title' => ['en' => 'Front'], 'slug' => 'front', 'type' => Page::TYPE_BUILDER,
            'is_homepage' => true, 'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->get('/')->assertSuccessful()->assertDontSee('Body copy.', false);
        $this->get('/home')->assertSuccessful()->assertSee('Body copy.', false);
    }

    public function test_the_header_is_added_automatically_from_the_assigned_menu(): void
    {
        // Pages never store their own navigation; changing the menu changes
        // every page at once.
        $menu = Menu::create(['name' => 'Main', 'location' => MenuLocations::HEADER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'District'], 'url' => '/district']);
        $this->page();

        $this->get('/about-us')->assertSee('District');
    }

    public function test_the_footer_is_added_automatically(): void
    {
        $menu = Menu::create(['name' => 'Footer', 'location' => MenuLocations::FOOTER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Careers'], 'url' => '/careers']);
        SiteSetting::singleton()->update(['contact_phone' => '+62 999']);
        $this->page();

        $html = $this->get('/about-us')->getContent();

        $this->assertStringContainsString('Careers', $html);
        $this->assertStringContainsString('+62 999', $html);
    }

    public function test_seo_title_falls_back_to_the_page_title(): void
    {
        $this->page();

        $this->get('/about-us')->assertSee('<title>About Us', false);
    }

    public function test_seo_title_wins_when_set(): void
    {
        $this->page(['seo_title' => ['en' => 'Custom SEO Title']]);

        $this->get('/about-us')->assertSee('<title>Custom SEO Title', false);
    }

    public function test_a_builder_page_with_no_blocks_renders_rather_than_erroring(): void
    {
        $this->page(['type' => Page::TYPE_BUILDER, 'builder_payload' => null]);

        $this->get('/about-us')->assertSuccessful();
    }

    public function test_a_malformed_payload_does_not_throw(): void
    {
        // A page saved by an older version must still load.
        $page = $this->page(['type' => Page::TYPE_BUILDER]);
        $page->forceFill(['builder_payload' => ['not-an-array', 42]])->save();

        $this->get('/about-us')->assertSuccessful();
        $this->assertSame([], $page->refresh()->blocks());
    }

    public function test_a_builder_page_does_not_render_the_simple_body(): void
    {
        // Two visible bodies would be a page with duplicated content.
        $this->page(['type' => Page::TYPE_BUILDER]);

        $this->get('/about-us')->assertDontSee('Body copy.', false);
    }

    public function test_slugs_are_generated_and_do_not_collide(): void
    {
        Page::create(['title' => ['en' => 'About Us'], 'status' => Page::STATUS_DRAFT]);
        $second = Page::create(['title' => ['en' => 'About Us'], 'status' => Page::STATUS_DRAFT]);

        $this->assertSame('about-us-2', $second->slug);
    }

    public function test_page_titles_join_the_translation_coverage_panel(): void
    {
        $this->assertContains(Page::class, (new \App\Support\TranslationCoverage)->translatableModels());
    }
}
