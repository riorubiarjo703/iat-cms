<?php

namespace Tests\Feature\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\MenuLocations;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

/**
 * "#about" names a section of the homepage. Left as a bare fragment it only
 * worked while you were already on the homepage: on every interior page the
 * browser looked for that id in the page you were on, found nothing, and the
 * navigation entry did nothing at all.
 */
class AnchorLinkTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    private function item(string $url): MenuItem
    {
        $menu = Menu::firstOrCreate(
            ['slug' => 'main-navigation'],
            ['name' => 'Main Navigation', 'location' => MenuLocations::HEADER],
        );

        return MenuItem::create([
            'menu_id' => $menu->id, 'type' => MenuItem::TYPE_CUSTOM,
            'label' => ['en' => 'Section'], 'url' => $url, 'sort' => 1,
        ])->refresh();
    }

    private function homeUrl(string $fragment): string
    {
        return rtrim(route('home'), '/').'/'.$fragment;
    }

    public function test_a_bare_fragment_resolves_against_the_homepage(): void
    {
        $this->assertSame($this->homeUrl('#about'), $this->item('#about')->resolveUrl());
    }

    public function test_the_heading_marker_stays_a_link_to_nowhere(): void
    {
        // "#" alone means "this item groups its children"; it is not a section.
        $this->assertSame('#', $this->item('#')->resolveUrl());
    }

    public function test_a_path_is_left_alone(): void
    {
        $this->assertSame('/profile', $this->item('/profile')->resolveUrl());
    }

    public function test_an_external_url_is_left_alone(): void
    {
        $this->assertSame('https://example.com/#top', $this->item('https://example.com/#top')->resolveUrl());
    }

    public function test_the_header_links_an_anchor_to_the_homepage_from_an_interior_page(): void
    {
        $this->item('#about');
        $this->seedHomepage();
        Page::create([
            'title' => ['en' => 'Profile'], 'slug' => 'profile',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED, 'builder_payload' => [],
        ]);
        RequestCache::flush('menu.');

        $html = $this->get('/profile')->getContent();

        $this->assertStringContainsString('href="'.$this->homeUrl('#about').'"', $html);
    }
}
