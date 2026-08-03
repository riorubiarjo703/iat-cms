<?php

namespace Tests\Feature\Filament;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Filament\Pages\Placeholders\PagesPlaceholder;
use App\Filament\Pages\Placeholders\PlaceholderPage;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    /** @return array<int, NavigationItem> */
    private function topLevelItems(): array
    {
        $items = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return array<string, array<int, string>> group (or "group > parent") => labels */
    private function tree(): array
    {
        $tree = [];

        foreach (Filament::getNavigation() as $group) {
            $label = $group->getLabel() ?? '(ungrouped)';

            foreach ($group->getItems() as $item) {
                $tree[$label][] = $item->getLabel();

                foreach ($item->getChildItems() as $child) {
                    $tree[$label.' > '.$item->getLabel()][] = $child->getLabel();
                }
            }
        }

        return $tree;
    }

    private function child(string $parent, string $child): NavigationItem
    {
        $parentItem = collect($this->topLevelItems())->firstWhere(fn ($i) => $i->getLabel() === $parent);

        return collect($parentItem->getChildItems())->firstWhere(fn ($i) => $i->getLabel() === $child);
    }

    public function test_the_groups_appear_in_the_declared_order(): void
    {
        $labels = array_values(array_filter(array_map(
            fn ($g) => $g->getLabel(),
            Filament::getNavigation(),
        )));

        // Marketing and Users Management became expandable items under
        // General; only three section headers remain.
        $this->assertSame(['General', 'System', 'Administration'], $labels);
    }

    public function test_section_headers_are_labels_not_controls(): void
    {
        // A collapsible header would give the tree two different kinds of
        // disclosure at two levels. Only items expand.
        foreach (Filament::getNavigation() as $group) {
            $this->assertFalse(
                $group->isCollapsible(),
                "[{$group->getLabel()}] is collapsible, but section headers are labels",
            );
        }
    }

    public function test_exactly_the_six_named_items_expand(): void
    {
        $expandable = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                if ($item->getChildItems() !== []) {
                    $expandable[] = $item->getLabel();
                }
            }
        }

        sort($expandable);

        $this->assertSame(
            ['Appearance', 'Content', 'Marketing', 'SEO', 'System', 'Users Management'],
            $expandable,
        );
    }

    public function test_the_content_parent_lists_its_children_in_order(): void
    {
        $this->assertSame(
            ['Posts', 'Pages', 'Content Blocks', 'Categories', 'Comments', 'Media Library'],
            $this->tree()['General > Content'],
        );
    }

    public function test_the_appearance_parent_lists_its_children_in_order(): void
    {
        $this->assertSame(
            ['Navigation Menus', 'Pages', 'Template Settings', 'Translations', 'Theme Editor'],
            $this->tree()['Administration > Appearance'],
        );
    }

    public function test_the_marketing_parent_lists_all_five_entries(): void
    {
        $this->assertSame(
            ['Newsletter', 'Announcements', 'Advertisements', 'Ad Zones', 'Social Posting'],
            $this->tree()['General > Marketing'],
        );
    }

    public function test_users_management_lists_users_roles_permissions(): void
    {
        $this->assertSame(['Users', 'Roles', 'Permissions'], $this->tree()['General > Users Management']);
    }

    public function test_the_system_group_nests_seo_and_system(): void
    {
        $tree = $this->tree();

        $this->assertSame(['Analytics', 'Email Activity', 'SEO', 'System'], $tree['System']);
        $this->assertSame(['Redirects'], $tree['System > SEO']);
        $this->assertSame(['Code Snippets', 'Backups'], $tree['System > System']);
    }

    public function test_every_destination_resolves_to_a_reachable_page(): void
    {
        // Catches an item pointing at nothing, or at the wrong resource — both of
        // which look perfectly fine in the rendered sidebar.
        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                foreach ([$item, ...$item->getChildItems()] as $node) {
                    if ($node->getChildItems() !== []) {
                        continue; // parents expand, they do not link
                    }

                    $url = $node->getUrl();
                    $this->assertNotEmpty($url, "[{$node->getLabel()}] has no URL");
                    $this->get($url)->assertSuccessful();
                }
            }
        }
    }

    public function test_no_two_entries_lead_to_the_same_page(): void
    {
        $urls = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                foreach ([$item, ...$item->getChildItems()] as $node) {
                    if ($node->getChildItems() === [] && $node->getUrl()) {
                        $urls[] = $node->getUrl();
                    }
                }
            }
        }

        $this->assertSame(count($urls), count(array_unique($urls)), 'Two menu entries lead to the same page');
    }

    public function test_content_pages_points_at_the_placeholder_until_the_builder_lands(): void
    {
        // Graper is removed; the page builder slice replaces this.
        $this->assertSame(PagesPlaceholder::getUrl(), $this->child('Content', 'Pages')->getUrl());
    }

    public function test_appearance_pages_is_a_separate_placeholder(): void
    {
        // "Pages" appears under both Content and Appearance; they are different things.
        $url = $this->child('Appearance', 'Pages')->getUrl();

        $this->assertNotSame(PagesPlaceholder::getUrl(), $url);
        $this->assertStringContainsString('appearance-pages', $url);
    }

    public function test_the_posts_badge_counts_drafts_and_scheduled(): void
    {
        BlogPost::create(['title' => 'A', 'slug' => 'a', 'content' => 'x', 'status' => BlogPost::STATUS_DRAFT]);
        BlogPost::create(['title' => 'B', 'slug' => 'b', 'content' => 'x', 'status' => BlogPost::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);
        BlogPost::create(['title' => 'C', 'slug' => 'c', 'content' => 'x', 'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->assertSame('2', (string) $this->child('Content', 'Posts')->getBadge());
    }

    public function test_the_posts_badge_is_absent_when_nothing_is_pending(): void
    {
        // A hardcoded count beside an empty feature would be inventing data.
        $this->assertNull($this->child('Content', 'Posts')->getBadge());
    }

    /**
     * PHPUnit resolves data providers before the application boots, so this
     * cannot use app_path() or the File facade — both need the container.
     *
     * @return array<int, array{0: string}>
     */
    public static function placeholderClasses(): array
    {
        $dir = dirname(__DIR__, 3).'/app/Filament/Pages/Placeholders';

        return collect(glob($dir.'/*.php') ?: [])
            ->map(fn (string $f) => 'App\\Filament\\Pages\\Placeholders\\'.basename($f, '.php'))
            ->reject(fn (string $c) => $c === PlaceholderPage::class)
            ->map(fn (string $c) => [$c])
            ->values()
            ->all();
    }

    #[DataProvider('placeholderClasses')]
    public function test_each_placeholder_says_plainly_that_it_is_not_built(string $class): void
    {
        $this->get($class::getUrl())
            ->assertSuccessful()
            ->assertSee('isn’t built yet', false)
            ->assertSee($class::summary(), false);
    }

    #[DataProvider('placeholderClasses')]
    public function test_each_placeholder_is_excluded_from_global_search(string $class): void
    {
        $this->assertFalse($class::canGloballySearch());
    }

    public function test_twenty_placeholders_are_registered(): void
    {
        // Nineteen, plus Pages once Graper was removed.
        $this->assertCount(20, static::placeholderClasses());
    }
}
