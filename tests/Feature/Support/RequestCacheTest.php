<?php

namespace Tests\Feature\Support;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use App\Support\MenuLocations;
use App\Support\MenuRenderer;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequestCacheTest extends TestCase
{
    use RefreshDatabase;

    private function countQueries(string $like, callable $work): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count, $like) {
            if (str_contains($query->sql, $like)) {
                $count++;
            }
        });

        $work();

        return $count;
    }

    public function test_a_value_is_resolved_once(): void
    {
        $calls = 0;
        $resolve = function () use (&$calls): string {
            $calls++;

            return 'value';
        };

        RequestCache::remember('k', $resolve);
        RequestCache::remember('k', $resolve);

        $this->assertSame(1, $calls);
    }

    public function test_a_null_result_is_still_cached(): void
    {
        // Otherwise "nothing assigned here" is re-queried on every read.
        $calls = 0;
        $resolve = function () use (&$calls) {
            $calls++;

            return null;
        };

        RequestCache::remember('n', $resolve);
        RequestCache::remember('n', $resolve);

        $this->assertSame(1, $calls);
    }

    public function test_flushing_by_prefix_leaves_other_keys_alone(): void
    {
        RequestCache::remember('menu.a', fn () => 'A');
        RequestCache::remember('other', fn () => 'B');

        RequestCache::flush('menu.');

        $this->assertSame('fresh', RequestCache::remember('menu.a', fn () => 'fresh'));
        $this->assertSame('B', RequestCache::remember('other', fn () => 'changed'));
    }

    public function test_site_settings_are_read_once_per_request(): void
    {
        // Warm first: on a fresh database the first call is a SELECT plus the
        // firstOrCreate INSERT, which says nothing about caching.
        SiteSetting::singleton();

        $queries = $this->countQueries('site_settings', function (): void {
            SiteSetting::singleton();
            SiteSetting::singleton();
            SiteSetting::singleton();
        });

        $this->assertSame(0, $queries);
    }

    public function test_saving_settings_makes_the_new_value_visible_immediately(): void
    {
        // The admin saves and re-renders in one request; a stale cache would
        // show the old value back to whoever just changed it.
        SiteSetting::singleton();

        SiteSetting::singleton()->update(['site_name' => 'Renamed']);

        $this->assertSame('Renamed', SiteSetting::singleton()->site_name);
    }

    public function test_a_settings_write_through_a_separate_instance_is_also_seen(): void
    {
        SiteSetting::singleton();

        SiteSetting::query()->first()->update(['site_name' => 'Elsewhere']);

        $this->assertSame('Elsewhere', SiteSetting::singleton()->site_name);
    }

    public function test_the_assigned_menu_is_looked_up_once_per_location(): void
    {
        Menu::create(['name' => 'Main', 'location' => MenuLocations::HEADER]);

        $queries = $this->countQueries('from "menus"', function (): void {
            Menu::assignedTo(MenuLocations::HEADER);
            Menu::assignedTo(MenuLocations::HEADER);
            Menu::assignedTo(MenuLocations::HEADER);
        });

        $this->assertSame(1, $queries);
    }

    public function test_reassigning_a_location_is_visible_immediately(): void
    {
        $first = Menu::create(['name' => 'One', 'location' => MenuLocations::HEADER]);
        $second = Menu::create(['name' => 'Two']);

        Menu::assignedTo(MenuLocations::HEADER);
        $second->assignLocation(MenuLocations::HEADER);

        $this->assertSame($second->id, Menu::assignedTo(MenuLocations::HEADER)?->id);
        $this->assertNotSame($first->id, Menu::assignedTo(MenuLocations::HEADER)?->id);
    }

    public function test_adding_an_item_is_visible_in_the_rendered_tree_immediately(): void
    {
        $menu = Menu::create(['name' => 'Main', 'location' => MenuLocations::HEADER]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'One'], 'url' => '/one', 'sort' => 1]);

        $this->assertCount(1, MenuRenderer::byLocation(MenuLocations::HEADER));

        MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Two'], 'url' => '/two', 'sort' => 2]);

        $this->assertCount(2, MenuRenderer::byLocation(MenuLocations::HEADER));
    }

    public function test_deleting_an_item_is_visible_immediately(): void
    {
        $menu = Menu::create(['name' => 'Main', 'location' => MenuLocations::HEADER]);
        $item = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'One'], 'url' => '/one']);

        MenuRenderer::byLocation(MenuLocations::HEADER);
        $item->delete();

        $this->assertCount(0, MenuRenderer::byLocation(MenuLocations::HEADER));
    }

    public function test_promoting_an_item_to_the_cta_is_visible_immediately(): void
    {
        $menu = Menu::create(['name' => 'Main', 'location' => MenuLocations::HEADER]);
        $item = MenuItem::create(['menu_id' => $menu->id, 'label' => ['en' => 'Enquire'], 'url' => '/c']);

        $this->assertNull(MenuRenderer::cta(MenuLocations::HEADER));

        $item->update(['is_cta' => true]);

        $this->assertSame('Enquire', MenuRenderer::cta(MenuLocations::HEADER)?->t('label'));
    }
}
