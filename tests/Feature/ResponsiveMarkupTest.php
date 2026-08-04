<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\MenuLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

/**
 * The responsive behaviour itself is CSS, which these cannot execute. What they
 * can hold is the contract the CSS depends on: the hooks it targets must be
 * present in the markup, and the drawer must contain everything the desktop row
 * does. A renamed class would otherwise break the phone layout silently.
 */
class ResponsiveMarkupTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    /**
     * Asserts the class appears as a whole token in a class attribute.
     * A plain substring check passes on any superstring — renaming a hook to
     * "scbd-stats-grid-renamed" would have gone unnoticed.
     */
    private function assertHasClass(string $html, string $class): void
    {
        $this->assertMatchesRegularExpression(
            '/class="[^"]*(?<![\w-])'.preg_quote($class, '/').'(?![\w-])[^"]*"/',
            $html,
            "The [{$class}] responsive hook is missing",
        );
    }

    private function home(): string
    {
        $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'location' => MenuLocations::HEADER]);
        $company = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Company'], 'url' => '#about', 'sort' => 1]);
        MenuItem::create([
            'menu_id' => $menu->id, 'parent_id' => $company->id, 'label' => ['en' => 'Profile'],
            'type' => MenuItem::TYPE_PAGE, 'linkable_type' => Page::class,
            'linkable_id' => Page::create(['title' => ['en' => 'Profile'], 'slug' => 'profile', 'status' => Page::STATUS_PUBLISHED])->id,
        ]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Enquire'], 'url' => '#c', 'is_cta' => true, 'sort' => 9]);

        $this->seedHomepage();

        return $this->get('/')->assertSuccessful()->getContent();
    }

    public function test_the_burger_and_backdrop_are_rendered(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('data-nav-toggle', $html);
        $this->assertStringContainsString('data-nav-backdrop', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="scbd-mobile-nav"', $html);
    }

    public function test_the_drawer_holds_the_nav_the_locales_and_the_call_to_action(): void
    {
        // Everything that is in the desktop row has to be reachable in the
        // drawer, or a phone user simply cannot get to it.
        $html = $this->home();
        $start = strpos($html, 'id="scbd-mobile-nav"');
        $drawer = substr($html, $start, strpos($html, '</nav>', $start) - $start);

        $this->assertStringContainsString('Company', $drawer);
        $this->assertStringContainsString('data-lang="en"', $drawer);
        $this->assertStringContainsString('data-lang="cn"', $drawer);
        $this->assertStringContainsString('Enquire', $drawer);
    }

    public function test_the_header_does_not_carry_an_inline_backdrop_filter(): void
    {
        // Inline, it applies at every width and makes the header a containing
        // block for its fixed drawer. It belongs in a desktop-only rule.
        $html = $this->home();
        $header = substr($html, strpos($html, '<header'), 400);

        $this->assertStringNotContainsString('backdrop-filter', $header);
    }

    public function test_the_chrome_hooks_are_present(): void
    {
        $html = $this->home();

        foreach (['scbd-header-bar', 'scbd-header-nav', 'scbd-locales', 'scbd-footer-grid'] as $hook) {
            $this->assertHasClass($html, $hook);
        }
    }

    public function test_the_homepage_block_hooks_are_present(): void
    {
        // Each of these blocks only renders when its content model has rows, so
        // the fixture has to supply them or the assertion passes vacuously.
        \App\Models\Stat::create(['label' => ['en' => 'Hectares'], 'value' => 45, 'sort' => 1]);
        \App\Models\DistrictPlace::create(['title' => ['en' => 'Tower'], 'caption' => ['en' => 'Office'], 'sort' => 1, 'is_active' => true]);
        \App\Models\Facility::create(['title' => ['en' => 'Parking'], 'body' => ['en' => 'B'], 'sort' => 1, 'is_active' => true]);

        Page::create([
            'title' => ['en' => 'Front'], 'slug' => 'front', 'type' => Page::TYPE_BUILDER,
            'is_homepage' => true, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [
                ['id' => 'h', 'type' => 'scbd_hero', 'data' => ['heading' => ['en' => 'Hero']], 'children' => null],
                ['id' => 'a', 'type' => 'scbd_about', 'data' => ['heading' => ['en' => 'About'], 'show_stats' => true], 'children' => null],
                ['id' => 'd', 'type' => 'scbd_district', 'data' => ['heading' => ['en' => 'District']], 'children' => null],
                ['id' => 'f', 'type' => 'scbd_facilities', 'data' => ['heading' => ['en' => 'Facilities']], 'children' => null],
                ['id' => 'n', 'type' => 'scbd_news', 'data' => ['heading' => ['en' => 'News']], 'children' => null],
            ],
        ]);

        $html = $this->get('/')->assertSuccessful()->getContent();

        foreach ([
            'scbd-pad', 'scbd-h1', 'scbd-h2', 'scbd-hero-grid',
            'scbd-split-2', 'scbd-stats-grid', 'scbd-district-track',
            'scbd-card-split', 'scbd-news-row',
        ] as $hook) {
            $this->assertHasClass($html, $hook);
        }
    }

    public function test_the_content_page_hooks_are_present(): void
    {
        Page::create([
            'title' => ['en' => 'P'], 'slug' => 'p', 'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [
                ['id' => 'b1', 'type' => 'scbd_page_hero', 'data' => ['heading' => ['en' => 'H']], 'children' => null],
                ['id' => 'b2', 'type' => 'scbd_timeline', 'data' => ['entries' => [['year' => '1995', 'title' => 'T']]], 'children' => null],
                ['id' => 'b3', 'type' => 'scbd_people', 'data' => ['groups' => [['title' => 'G', 'people' => [['name' => 'N']]]]], 'children' => null],
                ['id' => 'b4', 'type' => 'scbd_awards', 'data' => ['items' => [['title' => 'A']]], 'children' => null],
            ],
        ]);

        $html = $this->get('/p')->assertSuccessful()->getContent();

        foreach (['scbd-pad-top', 'scbd-tl-row', 'scbd-roulette-track', 'scbd-awards-grid'] as $hook) {
            $this->assertHasClass($html, $hook);
        }
    }

    public function test_the_viewport_meta_tag_is_present(): void
    {
        // Without it a phone renders at desktop width and scales down.
        $this->assertStringContainsString('name="viewport"', $this->home());
    }
}
