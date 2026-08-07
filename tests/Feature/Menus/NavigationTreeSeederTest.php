<?php

namespace Tests\Feature\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\MenuLocations;
use App\Support\MenuRenderer;
use Database\Seeders\NavigationTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTreeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_tree_three_levels_deep(): void
    {
        $this->seed(NavigationTreeSeeder::class);

        $cssr = MenuItem::query()->get()->first(fn ($i) => $i->t('label', 'en') === 'CSSR');
        $activities = $cssr->children->first(fn ($i) => $i->t('label', 'en') === 'CSSR Activities');

        $this->assertNotNull($activities);
        $this->assertCount(2, $activities->children);
    }

    public function test_a_label_appearing_twice_does_not_parent_a_row_to_itself(): void
    {
        // "News" is both a group and a page beneath it. Matching purely on
        // label made the second occurrence find the row the first had just
        // created — a cycle that vanishes from the tree.
        $this->seed(NavigationTreeSeeder::class);

        $this->assertSame(0, MenuItem::query()->whereColumn('parent_id', 'id')->count());

        $news = MenuItem::query()->whereNull('parent_id')->get()
            ->first(fn ($i) => $i->t('label', 'en') === 'News');

        $this->assertNotNull($news, 'The News group is missing from the root');
        $this->assertCount(3, $news->children);
    }

    public function test_the_model_refuses_to_be_its_own_parent(): void
    {
        $menu = Menu::create(['name' => 'M', 'slug' => 'm']);
        $item = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'X'], 'url' => '/x']);

        $item->update(['parent_id' => $item->id]);

        $this->assertNull($item->refresh()->parent_id);
    }

    public function test_every_page_it_creates_starts_as_a_draft(): void
    {
        // The structure exists before the copy does.
        $this->seed(NavigationTreeSeeder::class);

        $this->assertSame(0, Page::query()->where('status', Page::STATUS_PUBLISHED)->count());
        $this->assertGreaterThan(10, Page::query()->count());
    }

    public function test_items_pointing_at_a_draft_page_do_not_render(): void
    {
        // Otherwise the nav is full of links to 404s.
        $this->seed(NavigationTreeSeeder::class);

        $this->assertCount(0, MenuRenderer::byLocation(MenuLocations::HEADER)
            ->flatMap(fn ($i) => $i->children)
            ->filter(fn ($i) => $i->isVisible()));
    }

    public function test_an_item_appears_as_soon_as_its_page_is_published(): void
    {
        $this->seed(NavigationTreeSeeder::class);

        Page::query()->where('slug', 'profile')->first()->update(['status' => Page::STATUS_PUBLISHED]);

        $company = MenuRenderer::byLocation(MenuLocations::HEADER)
            ->first(fn ($i) => $i->t('label', 'en') === 'Company');

        $visible = $company->children->filter(fn ($i) => $i->isVisible());

        $this->assertCount(1, $visible);
        $this->assertSame('Profile', $visible->first()->t('label', 'en'));
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seed(NavigationTreeSeeder::class);
        $before = MenuItem::query()->count();

        $this->seed(NavigationTreeSeeder::class);

        $this->assertSame($before, MenuItem::query()->count());
    }

    public function test_re_seeding_does_not_switch_an_item_back_on(): void
    {
        // Switching an item off is a decision someone made in the admin. The
        // seeder defines the structure; it has no business overruling that,
        // and doing so silently made "non-destructive" untrue.
        $this->seed(NavigationTreeSeeder::class);
        $facilities = MenuItem::query()->get()->first(fn ($i) => $i->t('label', 'en') === 'Facilities');
        $facilities->update(['is_active' => false]);

        $this->seed(NavigationTreeSeeder::class);

        $this->assertFalse($facilities->refresh()->is_active);
    }

    public function test_an_item_it_creates_starts_switched_on(): void
    {
        // The other half of the same rule: leaving an existing row alone must
        // not leave a brand-new one off the site.
        $this->seed(NavigationTreeSeeder::class);

        $facilities = MenuItem::query()->get()->first(fn ($i) => $i->t('label', 'en') === 'Facilities');

        $this->assertTrue($facilities->is_active);
    }

    public function test_it_leaves_the_call_to_action_alone(): void
    {
        $menu = Menu::create(['name' => 'Main', 'slug' => 'main', 'location' => MenuLocations::HEADER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Leasing enquiry'], 'url' => '#contact', 'is_cta' => true]);

        $this->seed(NavigationTreeSeeder::class);

        $cta = MenuItem::query()->where('is_cta', true)->first();

        $this->assertNotNull($cta);
        $this->assertNull($cta->parent_id);
        $this->assertSame('#contact', $cta->url);
    }
}
