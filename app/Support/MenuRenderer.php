<?php

namespace App\Support;

use App\Models\Menu;
use Illuminate\Support\Collection;
use App\Support\RequestCache;

/**
 * Resolves a menu to its renderable tree. Kept out of the models so a template
 * cannot accidentally trigger a query per item: the whole tree loads eagerly.
 */
final class MenuRenderer
{
    /** @return Collection<int, \App\Models\MenuItem> */
    public static function bySlug(string $slug): Collection
    {
        $menu = Menu::query()->where('slug', $slug)->first();

        return $menu ? self::tree($menu) : collect();
    }

    /** @return Collection<int, \App\Models\MenuItem> */
    public static function byLocation(string $location): Collection
    {
        return RequestCache::remember("menu.tree.{$location}", function () use ($location): Collection {
            $menu = Menu::assignedTo($location);

            return $menu ? self::tree($menu) : collect();
        });
    }

    /**
     * Ordinary links, CTA excluded — a call-to-action is styled as a button
     * rather than a nav link, so templates ask for it separately.
     *
     * @return Collection<int, \App\Models\MenuItem>
     */
    public static function tree(Menu $menu): Collection
    {
        return $menu->rootItems()
            ->with(['childrenRecursive', 'linkable'])
            ->get()
            ->filter(fn ($item) => $item->is_active && ! $item->is_cta)
            ->values();
    }

    public static function cta(string $location): ?\App\Models\MenuItem
    {
        return RequestCache::remember(
            "menu.cta.{$location}",
            fn (): ?\App\Models\MenuItem => Menu::assignedTo($location)?->items()->cta()->first(),
        );
    }
}
