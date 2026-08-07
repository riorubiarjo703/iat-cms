<?php

namespace Tests\Feature\Menus;

use App\Filament\Pages\EditMenuPage;
use App\Filament\Pages\NavigationMenusPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

class MenuAdminTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Panel access is gated on the admin.access permission, so a bare
        // factory user is bounced with a 403 before any of this renders.
        $this->actingAsSuperAdmin();
    }

    private function menu(string $name = 'Main Navigation'): Menu
    {
        return Menu::create(['name' => $name]);
    }

    private function item(Menu $menu, string $label, ?int $parentId = null, int $sort = 1): MenuItem
    {
        return MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $parentId,
            'label' => ['en' => $label],
            'url' => '/'.strtolower($label),
            'sort' => $sort,
        ]);
    }

    public function test_the_index_page_renders(): void
    {
        $this->get(NavigationMenusPage::getUrl())->assertSuccessful();
    }

    public function test_the_index_lists_menus_and_their_directives(): void
    {
        $this->menu();

        $this->get(NavigationMenusPage::getUrl())
            ->assertSee('Main Navigation')
            // Escaped, because Blade renders the quotes as &#039;.
            ->assertSee("@menu('main-navigation')");
    }

    public function test_an_unassigned_location_reports_no_item_count(): void
    {
        // "0 items" would describe a menu that is not there.
        $html = $this->get(NavigationMenusPage::getUrl())->getContent();
        $footer = substr($html, strpos($html, 'Footer Navigation'), 600);

        $this->assertStringContainsString('No menu assigned', $html);
        $this->assertStringNotContainsString('0 items', $footer);
    }

    public function test_assigning_a_location_moves_it_from_the_previous_menu(): void
    {
        $first = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);
        $second = $this->menu('Two');

        Livewire::test(NavigationMenusPage::class)
            ->call('assignLocation', MenuLocations::HEADER, (string) $second->id);

        $this->assertNull($first->refresh()->location);
        $this->assertSame(MenuLocations::HEADER, $second->refresh()->location);
    }

    public function test_a_location_can_be_unassigned(): void
    {
        $menu = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);

        Livewire::test(NavigationMenusPage::class)->call('assignLocation', MenuLocations::HEADER, null);

        $this->assertNull($menu->refresh()->location);
    }

    public function test_deleting_a_menu_removes_its_items(): void
    {
        $menu = $this->menu();
        $this->item($menu, 'Home');

        Livewire::test(NavigationMenusPage::class)->call('deleteMenu', (string) $menu->id);

        $this->assertSame(0, Menu::count());
        $this->assertSame(0, MenuItem::count());
    }

    public function test_the_editor_renders_the_tree(): void
    {
        $menu = $this->menu();
        $this->item($menu, 'Home');

        $this->get(EditMenuPage::getUrl(['record' => $menu->id]))
            ->assertSuccessful()
            ->assertSee('Menu Structure')
            ->assertSee('Home');
    }

    public function test_a_custom_link_can_be_added(): void
    {
        $menu = $this->menu();

        Livewire::test(EditMenuPage::class, ['record' => $menu->id])
            ->set('newLabel', 'About Us')
            ->set('newUrl', '/about')
            ->call('addCustomLink');

        $item = $menu->items()->first();
        $this->assertSame('About Us', $item->t('label'));
        $this->assertSame('/about', $item->url);
    }

    public function test_a_custom_link_needs_both_a_label_and_a_url(): void
    {
        $menu = $this->menu();

        Livewire::test(EditMenuPage::class, ['record' => $menu->id])
            ->set('newLabel', 'About Us')
            ->set('newUrl', '')
            ->call('addCustomLink');

        $this->assertSame(0, $menu->items()->count());
    }

    public function test_save_tree_persists_nesting_and_order(): void
    {
        $menu = $this->menu();
        $a = $this->item($menu, 'Alpha', null, 1);
        $b = $this->item($menu, 'Bravo', null, 2);

        Livewire::test(EditMenuPage::class, ['record' => $menu->id])
            ->call('saveTree', [
                ['id' => (string) $a->id, 'parent' => null, 'sort' => 1],
                ['id' => (string) $b->id, 'parent' => (string) $a->id, 'sort' => 1],
            ]);

        $this->assertSame($a->id, $b->refresh()->parent_id);
        $this->assertNull($a->refresh()->parent_id);
    }

    public function test_save_tree_refuses_items_belonging_to_another_menu(): void
    {
        // A crafted payload must not be able to reparent someone else's items.
        $mine = $this->menu('Mine');
        $theirs = $this->menu('Theirs');
        $target = $this->item($mine, 'Target');
        $foreign = $this->item($theirs, 'Foreign');

        Livewire::test(EditMenuPage::class, ['record' => $mine->id])
            ->call('saveTree', [
                ['id' => (string) $foreign->id, 'parent' => (string) $target->id, 'sort' => 1],
            ]);

        $this->assertNull($foreign->refresh()->parent_id);
        $this->assertSame($theirs->id, $foreign->menu_id);
    }

    public function test_save_tree_refuses_a_parent_from_another_menu(): void
    {
        $mine = $this->menu('Mine');
        $theirs = $this->menu('Theirs');
        $target = $this->item($mine, 'Target');
        $foreignParent = $this->item($theirs, 'Foreign');

        Livewire::test(EditMenuPage::class, ['record' => $mine->id])
            ->call('saveTree', [
                ['id' => (string) $target->id, 'parent' => (string) $foreignParent->id, 'sort' => 1],
            ]);

        $this->assertNull($target->refresh()->parent_id);
    }

    public function test_an_item_cannot_become_its_own_parent(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu, 'Loop');

        Livewire::test(EditMenuPage::class, ['record' => $menu->id])
            ->call('saveTree', [['id' => (string) $item->id, 'parent' => (string) $item->id, 'sort' => 1]]);

        $this->assertNull($item->refresh()->parent_id);
    }

    public function test_only_one_item_can_be_the_cta(): void
    {
        $menu = $this->menu();
        $first = $this->item($menu, 'One');
        $second = $this->item($menu, 'Two');
        $first->update(['is_cta' => true]);

        Livewire::test(EditMenuPage::class, ['record' => $menu->id])->call('toggleCta', (string) $second->id);

        $this->assertFalse($first->refresh()->is_cta);
        $this->assertTrue($second->refresh()->is_cta);
    }

    public function test_blog_categories_are_added_as_a_nested_group(): void
    {
        \AjayDhakal\FilamentStory\Models\BlogCategory::create(['name' => 'News', 'slug' => 'news']);
        \AjayDhakal\FilamentStory\Models\BlogCategory::create(['name' => 'Events', 'slug' => 'events']);
        $menu = $this->menu();

        Livewire::test(EditMenuPage::class, ['record' => $menu->id])->call('addBlogCategories');

        $root = $menu->rootItems()->get();
        $this->assertCount(1, $root);
        $this->assertSame('Blog', $root->first()->t('label'));
        $this->assertCount(2, $root->first()->children);
        // Labels are left empty so the category's own name is used.
        $this->assertSame('Events', $root->first()->children->first()->t('label'));
    }

    public function test_deleting_an_item_takes_its_children(): void
    {
        $menu = $this->menu();
        $parent = $this->item($menu, 'Parent');
        $this->item($menu, 'Child', $parent->id);

        Livewire::test(EditMenuPage::class, ['record' => $menu->id])->call('deleteItem', (string) $parent->id);

        $this->assertSame(0, MenuItem::count());
    }

    public function test_a_missing_menu_is_a_404_not_a_crash(): void
    {
        $this->get(EditMenuPage::getUrl(['record' => 99999]))->assertNotFound();
    }
}
