<?php

namespace Tests\Feature\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuLocations;
use App\Support\MenuRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_menu_slugs_itself_from_its_name(): void
    {
        $this->assertSame('main-navigation', Menu::create(['name' => 'Main Navigation'])->slug);
    }

    public function test_slugs_do_not_collide(): void
    {
        Menu::create(['name' => 'Main Navigation']);

        $this->assertSame('main-navigation-2', Menu::create(['name' => 'Main Navigation'])->slug);
    }

    public function test_assigning_a_location_takes_it_from_the_previous_holder(): void
    {
        // The unique index would otherwise reject the save, which reads as a
        // bug to anyone using the dropdown.
        $first = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);
        $second = Menu::create(['name' => 'Two']);

        $second->assignLocation(MenuLocations::HEADER);

        $this->assertNull($first->refresh()->location);
        $this->assertSame(MenuLocations::HEADER, $second->refresh()->location);
    }

    public function test_a_location_can_be_cleared(): void
    {
        $menu = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);

        $menu->assignLocation(null);

        $this->assertNull($menu->refresh()->location);
        $this->assertNull(Menu::assignedTo(MenuLocations::HEADER));
    }

    public function test_an_unknown_location_is_refused(): void
    {
        $menu = Menu::create(['name' => 'One']);

        $menu->assignLocation('nowhere');

        $this->assertNull($menu->refresh()->location);
    }

    public function test_the_directive_uses_the_slug(): void
    {
        $this->assertSame("@menu('main-navigation')", Menu::create(['name' => 'Main Navigation'])->directive());
    }

    public function test_deleting_a_menu_takes_its_items(): void
    {
        $menu = Menu::create(['name' => 'One']);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Home'], 'url' => '/']);

        $menu->delete();

        $this->assertSame(0, MenuItem::count());
    }

    public function test_deleting_a_parent_takes_its_children(): void
    {
        // An orphan would point at a row that is gone.
        $menu = Menu::create(['name' => 'One']);
        $parent = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Quick Links'], 'url' => '#']);
        MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => ['en' => 'Legal'], 'url' => '#']);

        $parent->delete();

        $this->assertSame(0, MenuItem::count());
    }

    public function test_the_renderer_excludes_the_cta_from_nav_links(): void
    {
        $menu = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Home'], 'url' => '/', 'sort' => 1]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Enquire'], 'url' => '#c', 'is_cta' => true, 'sort' => 2]);

        $this->assertSame(['Home'], MenuRenderer::byLocation(MenuLocations::HEADER)->map->t('label')->all());
        $this->assertSame('Enquire', MenuRenderer::cta(MenuLocations::HEADER)?->t('label'));
    }

    public function test_the_renderer_excludes_inactive_items(): void
    {
        $menu = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Hidden'], 'url' => '/', 'is_active' => false]);

        $this->assertCount(0, MenuRenderer::byLocation(MenuLocations::HEADER));
    }

    public function test_an_unassigned_location_renders_nothing_rather_than_failing(): void
    {
        $this->assertCount(0, MenuRenderer::byLocation(MenuLocations::FOOTER));
        $this->assertNull(MenuRenderer::cta(MenuLocations::FOOTER));
    }

    public function test_only_root_items_are_returned_children_hang_off_them(): void
    {
        $menu = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);
        $parent = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Quick Links'], 'url' => '#', 'sort' => 1]);
        MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => ['en' => 'Legal'], 'url' => '#', 'sort' => 1]);

        $tree = MenuRenderer::byLocation(MenuLocations::HEADER);

        $this->assertCount(1, $tree);
        $this->assertSame('Legal', $tree->first()->childrenRecursive->first()->t('label'));
    }
}
