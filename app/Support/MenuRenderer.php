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
            ->filter(fn ($item) => $item->isVisible() && ! $item->is_cta)
            ->values();
    }

    /**
     * The visible tree, each node paired with the i18n key its markup carries.
     *
     * Keys are positional and derived here rather than in the template, so the
     * header and the translation payload cannot drift apart: a child rendered
     * with a key the dictionary does not contain simply never translates.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\MenuItem>|null  $items
     * @return array<int, array{item: \App\Models\MenuItem, key: string, children: array<int, mixed>}>
     */
    public static function withKeys(?Collection $items = null, string $prefix = 'nav'): array
    {
        $items ??= self::byLocation(\App\Support\MenuLocations::HEADER);
        $keyed = [];

        foreach ($items->values() as $index => $item) {
            $key = $prefix.($index + 1);

            $children = $item->children
                ->filter(fn (\App\Models\MenuItem $child): bool => $child->isVisible())
                ->values();

            $keyed[] = [
                'item' => $item,
                'key' => $key,
                'children' => self::withKeys($children, $key.'_'),
            ];
        }

        return $keyed;
    }

    public static function cta(string $location): ?\App\Models\MenuItem
    {
        return RequestCache::remember(
            "menu.cta.{$location}",
            fn (): ?\App\Models\MenuItem => Menu::assignedTo($location)?->items()->cta()->get()->first(fn ($item) => $item->isVisible()),
        );
    }
}
