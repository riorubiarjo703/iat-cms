<?php

namespace Tests\Feature\Pages;

use App\Models\HomepageContent;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks;
use App\PageBuilder\SiteTranslations;
use App\Support\MenuLocations;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        HomepageContent::singleton()->update(['hero_line' => ['en' => 'Legacy hero']]);
    }

    private function homepage(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => 'Home'],
            'slug' => 'home',
            'type' => Page::TYPE_BUILDER,
            'is_homepage' => true,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [
                ['id' => 'block_hero', 'type' => Blocks\HeroBlock::type(), 'data' => ['heading' => ['en' => 'Built hero']], 'children' => null],
            ],
        ], $attributes));
    }

    public function test_a_flagged_page_takes_over_the_root_url(): void
    {
        $this->homepage();

        $this->get('/')->assertSuccessful()->assertSee('Built hero', false);
    }

    public function test_the_hand_built_homepage_still_serves_while_none_is_flagged(): void
    {
        // The switch has to be reversible by clearing a flag, not by a deploy.
        $this->get('/')->assertSuccessful()->assertSee('Legacy hero', false);
    }

    public function test_an_unpublished_homepage_falls_back_rather_than_taking_the_site_down(): void
    {
        $this->homepage(['status' => Page::STATUS_DRAFT]);

        $this->get('/')->assertSuccessful()->assertSee('Legacy hero', false);
    }

    public function test_the_database_refuses_a_second_homepage(): void
    {
        // Two would make which page "/" serves non-deterministic.
        $this->homepage();

        $this->expectException(QueryException::class);

        Page::create([
            'title' => ['en' => 'Other'],
            'slug' => 'other',
            'is_homepage' => true,
            'status' => Page::STATUS_PUBLISHED,
        ]);
    }

    public function test_many_pages_may_be_not_the_homepage(): void
    {
        Page::create(['title' => ['en' => 'A'], 'slug' => 'a', 'status' => Page::STATUS_PUBLISHED]);
        Page::create(['title' => ['en' => 'B'], 'slug' => 'b', 'status' => Page::STATUS_PUBLISHED]);

        $this->assertSame(2, Page::query()->where('is_homepage', false)->count());
    }

    public function test_the_homepage_url_is_the_root_not_its_slug(): void
    {
        $this->assertSame(url('/'), $this->homepage()->getPublicUrl());
    }

    public function test_the_homepage_title_uses_the_site_meta_title_with_no_suffix(): void
    {
        // "Home — SCBD" would be worse than what it replaced.
        SiteSetting::singleton()->update(['site_name' => 'SCBD', 'meta_title' => ['en' => 'SCBD — Sudirman Central Business District']]);
        $this->homepage();

        $this->get('/')->assertSee('<title>SCBD — Sudirman Central Business District</title>', false);
    }

    public function test_an_ordinary_page_still_takes_the_site_name_suffix(): void
    {
        SiteSetting::singleton()->update(['site_name' => 'SCBD']);
        Page::create(['title' => ['en' => 'About'], 'slug' => 'about', 'status' => Page::STATUS_PUBLISHED]);

        $this->get('/about')->assertSee('<title>About — SCBD</title>', false);
    }

    public function test_the_intro_loader_renders_on_the_homepage_only(): void
    {
        $this->homepage();
        Page::create(['title' => ['en' => 'About'], 'slug' => 'about', 'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED]);

        $this->assertStringContainsString('data-loader', $this->get('/')->getContent());
        $this->assertStringNotContainsString('data-loader', $this->get('/about')->getContent());
    }

    public function test_the_translation_payload_carries_the_header_chrome_as_well_as_the_blocks(): void
    {
        // Chrome outside the payload simply would not translate.
        $menu = Menu::create(['name' => 'Main', 'location' => MenuLocations::HEADER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Company', 'id' => 'Perusahaan'], 'url' => '#about', 'sort' => 1]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Enquire', 'id' => 'Tanya'], 'url' => '#c', 'is_cta' => true, 'sort' => 2]);
        SiteSetting::singleton()->update(['brand_subtitle' => ['en' => 'Sub']]);

        $payload = SiteTranslations::forPage($this->homepage(), app(BlockRegistry::class));

        $this->assertSame('Company', $payload['en']['nav1']);
        $this->assertSame('Perusahaan', $payload['id']['nav1']);
        $this->assertSame('Enquire', $payload['en']['cta']);
        $this->assertSame('Sub', $payload['en']['brandsub']);
        $this->assertSame('Built hero', $payload['en']['bhero_heading']);
    }

    public function test_every_data_i18n_key_in_the_markup_has_a_payload_entry(): void
    {
        // A key with no entry is an element the switcher cannot translate.
        $menu = Menu::create(['name' => 'Main', 'location' => MenuLocations::HEADER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        $this->homepage();

        $html = $this->get('/')->getContent();

        preg_match('/id="scbd-i18n">(.*?)<\/script>/s', $html, $matches);
        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        preg_match_all('/data-i18n="([^"]+)"/', $html, $keys);

        foreach (array_keys($payload) as $locale) {
            $missing = array_diff(array_unique($keys[1]), array_keys($payload[$locale]));
            $this->assertSame([], array_values($missing), "[{$locale}] cannot translate: ".implode(', ', $missing));
        }
    }
}
