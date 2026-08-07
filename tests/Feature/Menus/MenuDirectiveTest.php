<?php

namespace Tests\Feature\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\MenuLocations;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The menus index advertises @menu('slug') with a copy button, so whatever it
 * hands out has to be markup. It used to echo an Eloquent collection, which
 * stringifies to JSON: pasting the directive dumped the menu — and the full
 * builder payload of every page it linked to — into the page.
 */
class MenuDirectiveTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        RequestCache::flush('menu.');
        $this->menu = Menu::create(['name' => 'Main Navigation', 'slug' => 'main-navigation', 'location' => MenuLocations::HEADER]);
    }

    private function item(string $label, ?int $parentId = null, array $attributes = []): MenuItem
    {
        return MenuItem::create($attributes + [
            'menu_id' => $this->menu->id, 'parent_id' => $parentId,
            'type' => MenuItem::TYPE_CUSTOM, 'label' => ['en' => $label],
            'url' => '/'.strtolower($label), 'sort' => 1,
        ])->refresh();
    }

    private function render(string $template): string
    {
        RequestCache::flush('menu.');

        return Blade::render($template);
    }

    public function test_the_directive_renders_links(): void
    {
        $this->item('About');

        $html = $this->render("@menu('main-navigation')");

        $this->assertStringContainsString('<a href="/about"', $html);
        $this->assertStringContainsString('About', $html);
    }

    public function test_the_directive_does_not_render_json(): void
    {
        $this->item('About');

        $html = $this->render("@menu('main-navigation')");

        $this->assertStringNotContainsString('"menu_id"', $html);
        $this->assertStringNotContainsString('"linkable_type"', $html);
    }

    public function test_the_directive_does_not_leak_the_content_of_a_linked_page(): void
    {
        // The morphed record was serialised whole, builder payload included.
        $page = Page::create([
            'title' => ['en' => 'Profile'], 'slug' => 'profile',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [['id' => 'b1', 'type' => 'scbd_hero', 'data' => ['heading' => 'UNPUBLISHED-DRAFT-COPY']]],
        ]);
        MenuItem::create([
            'menu_id' => $this->menu->id, 'type' => MenuItem::TYPE_PAGE,
            'label' => [], 'url' => null,
            'linkable_type' => Page::class, 'linkable_id' => $page->id, 'sort' => 1,
        ]);

        $html = $this->render("@menu('main-navigation')");

        $this->assertStringNotContainsString('UNPUBLISHED-DRAFT-COPY', $html);
        $this->assertStringContainsString('Profile', $html);
    }

    public function test_the_directive_renders_nested_children(): void
    {
        $parent = $this->item('Company');
        $this->item('Profile', $parent->id);

        $html = $this->render("@menu('main-navigation')");

        $this->assertStringContainsString('<a href="/company"', $html);
        $this->assertStringContainsString('<a href="/profile"', $html);
    }

    public function test_the_directive_leaves_out_items_the_site_hides(): void
    {
        $this->item('About');
        $this->item('Secret', null, ['is_active' => false]);

        $html = $this->render("@menu('main-navigation')");

        $this->assertStringNotContainsString('Secret', $html);
    }

    public function test_an_unknown_slug_renders_nothing(): void
    {
        $html = $this->render("@menu('no-such-menu')");

        $this->assertSame('', trim($html));
    }

    public function test_the_location_directive_renders_the_assigned_menu(): void
    {
        $this->item('About');

        $html = $this->render("@menuLocation('header')");

        $this->assertStringContainsString('<a href="/about"', $html);
        $this->assertStringNotContainsString('"menu_id"', $html);
    }

    public function test_a_label_cannot_inject_markup(): void
    {
        MenuItem::create([
            'menu_id' => $this->menu->id, 'type' => MenuItem::TYPE_CUSTOM,
            'label' => ['en' => '<script>alert(1)</script>'], 'url' => '/x', 'sort' => 1,
        ]);

        $html = $this->render("@menu('main-navigation')");

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
