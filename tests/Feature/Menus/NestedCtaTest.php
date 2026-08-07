<?php

namespace Tests\Feature\Menus;

use App\Filament\Pages\EditMenuPage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuLocations;
use App\Support\MenuRenderer;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\ActsAsSuperAdmin;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

/**
 * The call-to-action is the header's button, which is a top-level slot. A
 * nested item flagged as the CTA used to be drawn twice: once inside its
 * dropdown, and again as the button.
 */
class NestedCtaTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;
    use ActsAsSuperAdmin;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        // EditMenuPage now gates on menus.manage (C1 of the roles/permissions
        // final review) — a roleless actor 403s before this test's own
        // assertions ever run.
        $this->actingAsSuperAdmin();
        $this->menu = Menu::create(['name' => 'Main Navigation', 'location' => MenuLocations::HEADER]);
    }

    private function item(string $label, ?int $parentId = null, array $attributes = []): MenuItem
    {
        RequestCache::flush('menu.');

        return MenuItem::create($attributes + [
            'menu_id' => $this->menu->id, 'parent_id' => $parentId,
            'type' => MenuItem::TYPE_CUSTOM, 'label' => ['en' => $label],
            'url' => '/'.strtolower($label), 'sort' => 1,
        ])->refresh();
    }

    public function test_the_button_is_taken_from_the_top_level_only(): void
    {
        $parent = $this->item('Company');
        $this->item('Enquire', $parent->id, ['is_cta' => true]);

        RequestCache::flush('menu.');

        $this->assertNull(MenuRenderer::cta(MenuLocations::HEADER));
    }

    public function test_a_top_level_cta_is_still_the_button(): void
    {
        $this->item('Company');
        $this->item('Enquire', null, ['is_cta' => true, 'sort' => 9]);

        RequestCache::flush('menu.');

        $this->assertSame('Enquire', MenuRenderer::cta(MenuLocations::HEADER)?->t('label', 'en'));
    }

    public function test_a_nested_cta_still_renders_as_an_ordinary_link(): void
    {
        // Dropping it from the tree as well would make the item vanish from
        // the site with nothing on the admin screen to explain where it went.
        $parent = $this->item('Company');
        $this->item('Enquire', $parent->id, ['is_cta' => true]);

        RequestCache::flush('menu.');
        $tree = MenuRenderer::withKeys();

        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Enquire', $tree[0]['children'][0]['item']->t('label', 'en'));
    }

    public function test_the_header_never_draws_the_same_item_twice(): void
    {
        $parent = $this->item('Company');
        $this->item('Enquire', $parent->id, ['is_cta' => true]);
        $this->seedHomepage();

        $html = $this->get('/')->getContent();

        $this->assertSame(0, substr_count($html, 'data-i18n="cta"'));
        $this->assertSame(1, substr_count($html, 'data-i18n="nav1_1"'));
    }

    public function test_the_editor_refuses_to_make_a_nested_item_the_button(): void
    {
        $parent = $this->item('Company');
        $child = $this->item('Enquire', $parent->id);

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('toggleCta', (string) $child->id);

        $this->assertFalse($child->refresh()->is_cta);
    }

    public function test_dragging_the_button_under_a_parent_stops_it_being_the_button(): void
    {
        // Otherwise the row keeps a CTA badge that no longer does anything.
        $parent = $this->item('Company');
        $cta = $this->item('Enquire', null, ['is_cta' => true, 'sort' => 9]);

        Livewire::test(EditMenuPage::class, ['record' => $this->menu->id])
            ->call('saveTree', [
                ['id' => (string) $parent->id, 'parent' => null, 'sort' => 1],
                ['id' => (string) $cta->id, 'parent' => (string) $parent->id, 'sort' => 1],
            ]);

        $cta->refresh();

        $this->assertSame($parent->id, $cta->parent_id);
        $this->assertFalse($cta->is_cta);
    }
}
