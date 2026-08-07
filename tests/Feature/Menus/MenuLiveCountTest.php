<?php

namespace Tests\Feature\Menus;

use App\Filament\Pages\NavigationMenusPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\MenuLocations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

/**
 * The menus index counted rows in the database, which is not the same number
 * as the links a visitor sees. Where the two differ, the screen has to say so.
 */
class MenuLiveCountTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        // Panel access is gated on the admin.access permission, so a bare
        // factory user is bounced with a 403 before any of this renders.
        $this->actingAsSuperAdmin();
        $this->menu = Menu::create(['name' => 'Main Navigation', 'location' => MenuLocations::HEADER]);
    }

    private function page(string $slug, string $status): Page
    {
        return Page::create([
            'title' => ['en' => $slug], 'slug' => $slug,
            'type' => Page::TYPE_BUILDER, 'status' => $status, 'builder_payload' => [],
        ]);
    }

    private function link(string $label, ?int $parentId = null, array $attributes = []): MenuItem
    {
        return MenuItem::create($attributes + [
            'menu_id' => $this->menu->id, 'parent_id' => $parentId,
            'type' => MenuItem::TYPE_CUSTOM, 'label' => ['en' => $label],
            'url' => '/'.strtolower($label), 'sort' => 1,
        ])->refresh();
    }

    private function linkedTo(string $label, string $status, ?int $parentId = null): MenuItem
    {
        return MenuItem::create([
            'menu_id' => $this->menu->id, 'parent_id' => $parentId,
            'type' => MenuItem::TYPE_PAGE, 'label' => ['en' => $label], 'url' => null,
            'linkable_type' => Page::class,
            'linkable_id' => $this->page(strtolower($label), $status)->id,
            'sort' => 1,
        ])->refresh();
    }

    public function test_every_visible_item_counts(): void
    {
        $this->link('One');
        $this->link('Two');

        $this->assertSame(2, $this->menu->liveItemCount());
    }

    public function test_a_switched_off_item_does_not_count(): void
    {
        $this->link('One');
        $this->link('Two', attributes: ['is_active' => false]);

        $this->assertSame(1, $this->menu->liveItemCount());
    }

    public function test_an_item_blocked_by_a_draft_page_does_not_count(): void
    {
        $this->link('One');
        $this->linkedTo('Two', Page::STATUS_DRAFT);

        $this->assertSame(1, $this->menu->liveItemCount());
    }

    public function test_children_count_towards_the_total(): void
    {
        $parent = $this->link('Parent');
        $this->linkedTo('Live', Page::STATUS_PUBLISHED, $parent->id);
        $this->linkedTo('Draft', Page::STATUS_DRAFT, $parent->id);

        $this->assertSame(2, $this->menu->liveItemCount());
    }

    public function test_a_hidden_parent_takes_its_children_out_of_the_count(): void
    {
        // The site never reaches them, so counting them would overstate it.
        $parent = $this->link('Parent', attributes: ['is_active' => false]);
        $this->linkedTo('Child', Page::STATUS_PUBLISHED, $parent->id);

        $this->assertSame(0, $this->menu->liveItemCount());
    }

    public function test_the_index_reports_how_many_items_are_hidden(): void
    {
        $this->link('One');
        $this->linkedTo('Two', Page::STATUS_DRAFT);
        $this->linkedTo('Three', Page::STATUS_DRAFT);

        $this->get(NavigationMenusPage::getUrl())
            ->assertSee('3 items')
            ->assertSee('2 hidden');
    }

    public function test_the_index_says_nothing_about_hidden_items_when_none_are(): void
    {
        $this->link('One');

        // Asserted on the pill's class, not the word: "hidden" is a bare HTML
        // attribute all over Filament's own markup.
        $this->get(NavigationMenusPage::getUrl())
            ->assertDontSee('scbd-count-pill-hidden');
    }
}
