<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\MenuLocations;
use Illuminate\Database\Seeder;

/**
 * Builds the header navigation and the pages behind it, from the structure the
 * live scbd.com site uses.
 *
 * Pages are created as drafts with no content: the structure can exist before
 * the copy does, and MenuItem::isVisible() hides an item whose page is not
 * published, so nothing appears on the site until someone writes the page.
 *
 * Idempotent and non-destructive. Items are matched by their English label, so
 * running it again updates what is there rather than duplicating it, and any
 * item not named here — the call-to-action, for instance — is left alone.
 */
class NavigationTreeSeeder extends Seeder
{
    /**
     * label, destination, children.
     *
     * The destination is a page slug, or a URL when it starts with "#" or "/",
     * or null for a heading with no destination of its own.
     *
     * The top-level entries keep the homepage anchors they already had, so the
     * navigation keeps working while the pages beneath them are still drafts.
     * They gain their dropdown entries automatically as those pages publish.
     * CSSR is null because the homepage has no such section — it stays hidden
     * until one of its pages is published, which is the honest behaviour.
     *
     * @var array<int, array{0: string, 1: string|null, 2?: array<int, mixed>}>
     */
    private const TREE = [
        ['Company', '#about', [
            ['Profile', 'profile'],
            ['Our Milestone', 'milestone'],
            ['Organisation Structure', 'organisation-structure'],
            ['Awards & Certification', 'awards-certification'],
        ]],
        ['District', '#district', [
            ['Place of Interest', 'place-of-interest'],
            ['Location', 'location'],
            ['District Facilities', 'district-facilities'],
        ]],
        // Not in the scbd.com tree, which has District Facilities beneath
        // District. Kept because the homepage still has a #facilities section
        // and removing this left it unreachable from the navigation. Delete it
        // once the District Facilities page has content.
        ['Facilities', '#facilities'],
        ['CSSR', null, [
            ['Social Responsibility', 'social-responsibility'],
            ['Policy', 'policy'],
            ['CSSR Activities', null, [
                ['Environmental Responsibility', 'environmental-responsibility'],
                ['Responsibility on Social & Community Development', 'social-community-development'],
            ]],
        ]],
        ['News', '#news', [
            ['News', 'news'],
            ['Gallery', 'gallery'],
            ['Events', 'events'],
        ]],
        ['Contact Us', '#contact'],
    ];

    /**
     * Rows already placed in this run.
     *
     * A label can appear twice in the tree — "News" is both a group and a page
     * beneath it — and matching purely on label made the second occurrence
     * find the row the first had just created, parenting it to itself.
     *
     * @var array<int, int>
     */
    private array $placed = [];

    public function run(): void
    {
        $this->placed = [];

        $menu = Menu::assignedTo(MenuLocations::HEADER)
            ?? Menu::create(['name' => 'Main Navigation', 'slug' => 'main-navigation', 'location' => MenuLocations::HEADER]);

        foreach (self::TREE as $index => $node) {
            $this->place($menu, $node, null, $index + 1);
        }

        // The call-to-action is not part of the tree; it keeps the last slot.
        MenuItem::query()
            ->where('menu_id', $menu->getKey())
            ->where('is_cta', true)
            ->update(['parent_id' => null, 'sort' => 99]);
    }

    /** @param array{0: string, 1: string|null, 2?: array<int, mixed>} $node */
    private function place(Menu $menu, array $node, ?int $parentId, int $sort): void
    {
        [$label, $destination] = [$node[0], $node[1] ?? null];
        $children = $node[2] ?? [];

        // A destination starting with "#" or "/" is a plain URL; anything else
        // names a page this seeder creates.
        $isUrl = is_string($destination) && (str_starts_with($destination, '#') || str_starts_with($destination, '/'));
        $page = ($destination !== null && ! $isUrl) ? $this->page($label, $destination) : null;

        $attributes = [
            'menu_id' => $menu->getKey(),
            'parent_id' => $parentId,
            'sort' => $sort,
            'label' => ['en' => $label],
            'type' => $page ? MenuItem::TYPE_PAGE : MenuItem::TYPE_CUSTOM,
            'linkable_type' => $page ? Page::class : null,
            'linkable_id' => $page?->getKey(),
            'url' => $page ? null : ($isUrl ? $destination : '#'),
            'is_active' => true,
        ];

        $existing = MenuItem::query()
            ->where('menu_id', $menu->getKey())
            ->whereKeyNot($this->placed ?: [0])
            ->get()
            ->first(fn (MenuItem $item): bool => $item->t('label', 'en') === $label);

        $item = $existing
            ? tap($existing)->update($attributes)
            : MenuItem::create($attributes);

        $this->placed[] = $item->getKey();

        foreach ($children as $index => $child) {
            $this->place($menu, $child, $item->getKey(), $index + 1);
        }
    }

    private function page(string $title, string $slug): Page
    {
        return Page::firstOrCreate(['slug' => $slug], [
            'title' => ['en' => $title],
            'type' => Page::TYPE_BUILDER,
            // Draft: the page exists so the menu can point at it, but it stays
            // off the site until it has content.
            'status' => Page::STATUS_DRAFT,
            'builder_payload' => [],
        ]);
    }
}
