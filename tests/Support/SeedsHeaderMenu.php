<?php

namespace Tests\Support;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\MenuLocations;

/**
 * The header renders whichever menu is assigned to the header location, so
 * tests that need navigation seed a menu rather than loose items.
 */
trait SeedsHeaderMenu
{
    /**
     * @param  array<int, array{label: array<string, string>, url?: string, target?: string, is_active?: bool}>  $links
     * @param  array{label: array<string, string>, url?: string, target?: string}|null  $cta
     */
    protected function seedHeaderMenu(array $links = [], ?array $cta = null): Menu
    {
        $menu = Menu::create(['name' => 'Main Navigation', 'location' => MenuLocations::HEADER]);

        foreach (array_values($links) as $index => $link) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'type' => MenuItem::TYPE_CUSTOM,
                'label' => $link['label'],
                'url' => $link['url'] ?? '#',
                'target' => $link['target'] ?? '_self',
                'is_cta' => false,
                'is_active' => $link['is_active'] ?? true,
                'sort' => $index + 1,
            ]);
        }

        if ($cta !== null) {
            MenuItem::create([
                'menu_id' => $menu->id,
                'type' => MenuItem::TYPE_CUSTOM,
                'label' => $cta['label'],
                'url' => $cta['url'] ?? '#',
                'target' => $cta['target'] ?? '_self',
                'is_cta' => true,
                'is_active' => true,
                'sort' => 99,
            ]);
        }

        return $menu->refresh();
    }
}
