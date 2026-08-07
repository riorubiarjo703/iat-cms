<?php

namespace Tests\Feature\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuLocations;
use App\Support\MenuRenderer;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The header is on every page, so what matters is not the absolute query count
 * but that it is flat: a menu that grows must not cost a query per row.
 */
class MenuRenderQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendering_the_header_does_not_cost_a_query_per_item(): void
    {
        $small = $this->queriesToRender($this->seedTree(roots: 2, childrenEach: 1));
        $large = $this->queriesToRender($this->seedTree(roots: 10, childrenEach: 4));

        $this->assertSame(
            $small,
            $large,
            "A 50-item menu costs {$large} queries against {$small} for a 4-item one — the tree is not being read from the eager load.",
        );
    }

    /** Renders the header tree once and returns how many queries that took. */
    private function queriesToRender(Menu $menu): int
    {
        RequestCache::flush('menu.');
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Walked in full: the count has to cover what a template touches, not
        // just what the top-level call returns.
        $this->walk(MenuRenderer::withKeys());

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @param array<int, array{item: MenuItem, key: string, children: array<int, mixed>}> $nodes */
    private function walk(array $nodes): void
    {
        foreach ($nodes as $node) {
            $node['item']->resolveUrl();
            $node['item']->t('label');
            $this->walk($node['children']);
        }
    }

    private function seedTree(int $roots, int $childrenEach): Menu
    {
        Menu::query()->delete();

        $menu = Menu::create(['name' => "Main {$roots}", 'location' => MenuLocations::HEADER]);

        for ($r = 1; $r <= $roots; $r++) {
            $parent = MenuItem::create([
                'menu_id' => $menu->id, 'type' => MenuItem::TYPE_CUSTOM,
                'label' => ['en' => "Root {$r}"], 'url' => "/root-{$r}", 'sort' => $r,
            ]);

            for ($c = 1; $c <= $childrenEach; $c++) {
                MenuItem::create([
                    'menu_id' => $menu->id, 'parent_id' => $parent->id,
                    'type' => MenuItem::TYPE_CUSTOM,
                    'label' => ['en' => "Child {$r}.{$c}"], 'url' => "/child-{$r}-{$c}", 'sort' => $c,
                ]);
            }
        }

        return $menu;
    }
}
