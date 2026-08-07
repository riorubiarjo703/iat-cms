<?php

namespace Tests\Feature\Menus;

use App\Filament\Pages\EditMenuPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ActsAsSuperAdmin;
use Tests\TestCase;

/**
 * What the menu editor claims about an item has to match what the site does
 * with it. The status dot used to read is_active alone, which reported every
 * draft-blocked item as live.
 */
class MenuTreeStatusTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsSuperAdmin;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        // EditMenuPage now gates on menus.manage (C1 of the roles/permissions
        // final review) — a roleless actor 403s before this test's own
        // assertions ever run.
        $this->actingAsSuperAdmin();
        $this->menu = Menu::create(['name' => 'Main Navigation']);
    }

    private function page(string $slug, string $status): Page
    {
        return Page::create([
            'title' => ['en' => $slug], 'slug' => $slug,
            'type' => Page::TYPE_BUILDER, 'status' => $status, 'builder_payload' => [],
        ]);
    }

    private function linkedItem(string $label, string $status): MenuItem
    {
        return MenuItem::create([
            'menu_id' => $this->menu->id, 'type' => MenuItem::TYPE_PAGE,
            'label' => ['en' => $label], 'url' => null,
            'linkable_type' => Page::class,
            'linkable_id' => $this->page(strtolower($label), $status)->id,
            'sort' => 1,
        ])->refresh();
    }

    private function html(): string
    {
        return Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])->html();
    }

    public function test_an_item_blocked_by_a_draft_page_is_not_shown_as_live(): void
    {
        $this->linkedItem('Profile', Page::STATUS_DRAFT);

        $html = $this->html();

        $this->assertStringContainsString('scbd-tree-dot-blocked', $html);
        $this->assertStringContainsString('Its page is a draft', $html);
    }

    public function test_a_live_item_is_not_marked_blocked(): void
    {
        $this->linkedItem('Profile', Page::STATUS_PUBLISHED);

        $html = $this->html();

        $this->assertStringNotContainsString('scbd-tree-dot-blocked', $html);
        $this->assertStringNotContainsString('scbd-tree-dot-off', $html);
    }

    public function test_a_switched_off_item_says_so_in_the_badge_column(): void
    {
        // Every row that is not live explains itself in the same place. A grey
        // dot beside an empty badge column, next to an amber row carrying a
        // reason, reads as though the two were hidden for unrelated reasons.
        $item = $this->linkedItem('Profile', Page::STATUS_PUBLISHED);
        $item->update(['is_active' => false]);

        // Asserted on the badge class, not the words: "Switched off" is also
        // the dot's tooltip, so the text alone would pass without a badge.
        $this->assertStringContainsString('scbd-type-off', $this->html());
    }

    public function test_a_live_item_carries_no_reason_badge(): void
    {
        $this->linkedItem('Profile', Page::STATUS_PUBLISHED);

        $html = $this->html();

        $this->assertStringNotContainsString('scbd-type-off', $html);
        $this->assertStringNotContainsString('scbd-type-blocked', $html);
    }

    public function test_the_toggle_describes_the_switch_rather_than_the_site(): void
    {
        // "Hide from the site" is a promise this button cannot keep against an
        // item the site is already leaving out.
        $this->linkedItem('Profile', Page::STATUS_DRAFT);

        $html = $this->html();

        $this->assertStringNotContainsString('Hide from the site', $html);
        $this->assertStringContainsString('Switch this item off', $html);
    }

    public function test_a_switched_off_item_stays_distinct_from_a_blocked_one(): void
    {
        // Switched off is a decision someone made; blocked is a consequence.
        // Collapsing them would hide which one you can undo from this screen.
        $item = $this->linkedItem('Profile', Page::STATUS_PUBLISHED);
        $item->update(['is_active' => false]);

        $html = $this->html();

        $this->assertStringContainsString('scbd-tree-dot-off', $html);
        $this->assertStringNotContainsString('scbd-tree-dot-blocked', $html);
    }
}
