<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Resolves a menu to its renderable tree. Kept out of the models so a template
 * cannot accidentally trigger a query per item: the whole tree loads eagerly.
 */
final class MenuRenderer
{
    /** @return Collection<int, MenuItem> */
    public static function bySlug(string $slug): Collection
    {
        $menu = Menu::query()->where('slug', $slug)->first();

        return $menu ? self::tree($menu) : collect();
    }

    /** @return Collection<int, MenuItem> */
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
     * @return Collection<int, MenuItem>
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
     * @param  Collection<int, MenuItem>|null  $items
     * @return array<int, array{item: MenuItem, key: string, children: array<int, mixed>}>
     */
    public static function withKeys(?Collection $items = null, string $prefix = 'nav'): array
    {
        $items ??= self::byLocation(MenuLocations::HEADER);
        $keyed = [];

        foreach ($items->values() as $index => $item) {
            $key = $prefix.($index + 1);

            $children = $item->loadedChildren()
                ->filter(fn (MenuItem $child): bool => $child->isVisible())
                ->values();

            $keyed[] = [
                'item' => $item,
                'key' => $key,
                'children' => self::withKeys($children, $key.'_'),
            ];
        }

        return $keyed;
    }

    /**
     * A menu as markup, for @menu('slug').
     *
     * The directives echo whatever this returns. They used to echo the
     * collection itself, which stringifies to JSON: the advertised directive
     * dumped the menu and the whole builder payload of every page it linked to
     * straight into the page.
     */
    public static function render(string $slug): HtmlString
    {
        return self::markup(self::bySlug($slug));
    }

    /** A location's assigned menu as markup, for @menuLocation('header'). */
    public static function renderLocation(string $location): HtmlString
    {
        return self::markup(self::byLocation($location));
    }

    /** @param Collection<int, MenuItem> $items */
    private static function markup(Collection $items): HtmlString
    {
        return new HtmlString(
            view('partials.site.menu', ['items' => $items, 'depth' => 0])->render(),
        );
    }

    /**
     * The header's button, which is a top-level slot.
     *
     * Root items only. Searching the whole menu meant a nested item flagged as
     * the CTA was drawn twice — once inside its dropdown, because tree() only
     * excludes the flag at the top level, and again as the button. A nested
     * flag is ignored here and the item renders as the ordinary link it is.
     */
    public static function cta(string $location): ?MenuItem
    {
        return RequestCache::remember(
            "menu.cta.{$location}",
            fn (): ?\App\Models\MenuItem => Menu::assignedTo($location)
                ?->rootItems()->cta()->get()->first(fn ($item) => $item->isVisible()),
        );
    }
}
