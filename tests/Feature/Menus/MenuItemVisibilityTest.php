<?php

namespace Tests\Feature\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * isVisible() answers whether an item renders. hiddenReason() answers why it
 * does not, which is what the admin needs to stop reporting a draft-blocked
 * item as live.
 */
class MenuItemVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = Menu::create(['name' => 'Main Navigation']);
    }

    private function page(string $slug, string $status = Page::STATUS_PUBLISHED): Page
    {
        return Page::create([
            'title' => ['en' => $slug], 'slug' => $slug,
            'type' => Page::TYPE_BUILDER, 'status' => $status, 'builder_payload' => [],
        ]);
    }

    /**
     * Refreshed before it is handed back: is_active defaults in the database,
     * so a model straight out of create() does not carry it and would read as
     * switched off.
     */
    private function item(array $attributes = []): MenuItem
    {
        return MenuItem::create($attributes + [
            'menu_id' => $this->menu->id,
            'type' => MenuItem::TYPE_CUSTOM,
            'label' => ['en' => 'Item'],
            'url' => '/somewhere',
            'sort' => 1,
        ])->refresh();
    }

    public function test_a_live_item_has_no_hidden_reason(): void
    {
        $this->assertNull($this->item()->hiddenReason());
    }

    public function test_a_switched_off_item_reports_that_it_was_switched_off(): void
    {
        $item = $this->item(['is_active' => false]);

        $this->assertSame('Switched off', $item->hiddenReason());
    }

    public function test_an_item_linked_to_a_draft_page_reports_the_draft(): void
    {
        $item = $this->item([
            'type' => MenuItem::TYPE_PAGE, 'url' => null,
            'linkable_type' => Page::class,
            'linkable_id' => $this->page('profile', Page::STATUS_DRAFT)->id,
        ]);

        $this->assertSame('Its page is a draft', $item->hiddenReason());
    }

    public function test_an_item_linked_to_a_published_page_has_no_hidden_reason(): void
    {
        $item = $this->item([
            'type' => MenuItem::TYPE_PAGE, 'url' => null,
            'linkable_type' => Page::class,
            'linkable_id' => $this->page('profile')->id,
        ]);

        $this->assertNull($item->hiddenReason());
    }

    public function test_an_item_whose_linked_record_was_deleted_reports_it(): void
    {
        $page = $this->page('gone');
        $item = $this->item([
            'type' => MenuItem::TYPE_PAGE, 'url' => null,
            'linkable_type' => Page::class, 'linkable_id' => $page->id,
        ]);
        $page->delete();

        $this->assertSame('The record it linked to no longer exists', $item->fresh()->hiddenReason());
    }

    public function test_a_heading_whose_children_are_all_hidden_reports_it(): void
    {
        // "#" is the heading marker: the item exists to group its children.
        $heading = $this->item(['url' => '#']);
        $this->item([
            'parent_id' => $heading->id, 'type' => MenuItem::TYPE_PAGE, 'url' => null,
            'linkable_type' => Page::class,
            'linkable_id' => $this->page('draft-child', Page::STATUS_DRAFT)->id,
        ]);

        $this->assertSame('All of its links are hidden', $heading->fresh()->hiddenReason());
    }

    public function test_a_heading_with_one_live_child_has_no_hidden_reason(): void
    {
        $heading = $this->item(['url' => '#']);
        $this->item([
            'parent_id' => $heading->id, 'type' => MenuItem::TYPE_PAGE, 'url' => null,
            'linkable_type' => Page::class, 'linkable_id' => $this->page('live-child')->id,
        ]);

        $this->assertNull($heading->fresh()->hiddenReason());
    }

    public function test_every_hidden_item_has_a_reason_and_every_visible_one_has_none(): void
    {
        // The two must never disagree: a reason shown against a rendered item
        // is as wrong as a rendered item with no explanation for being absent.
        $items = [
            $this->item(),
            $this->item(['is_active' => false]),
            $this->item(['url' => '#']),
            $this->item([
                'type' => MenuItem::TYPE_PAGE, 'url' => null, 'linkable_type' => Page::class,
                'linkable_id' => $this->page('d', Page::STATUS_DRAFT)->id,
            ]),
        ];

        foreach ($items as $item) {
            $this->assertSame(
                $item->isVisible(),
                $item->hiddenReason() === null,
                "Item #{$item->id} is ".($item->isVisible() ? 'visible' : 'hidden')
                    ." but its reason is ".var_export($item->hiddenReason(), true),
            );
        }
    }
}
