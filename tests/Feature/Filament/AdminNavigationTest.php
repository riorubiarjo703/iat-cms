<?php

namespace Tests\Feature\Filament;

use AjayDhakal\FilamentStory\Filament\Resources\BlogPosts\BlogPostResource;
use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Filament\Pages\HomepageEditor;
use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\DistrictPlaces\DistrictPlaceResource;
use App\Filament\Resources\Facilities\FacilityResource;
use App\Filament\Resources\PublicMenuItems\PublicMenuItemResource;
use App\Filament\Resources\Stats\StatResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use CybertronianKelvin\Graper\Resources\GraperPageResource;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    }

    /**
     * @return array<int, string>
     */
    private function groupLabels(): array
    {
        return array_values(array_filter(array_map(
            fn ($group) => $group->getLabel(),
            Filament::getNavigation(),
        )));
    }

    /**
     * @return array<int, string>
     */
    private function itemLabels(): array
    {
        $labels = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $labels[] = $item->getLabel();
            }
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function itemUrls(): array
    {
        $urls = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $urls[$item->getLabel()] = $item->getUrl();
            }
        }

        return $urls;
    }

    public function test_it_registers_the_five_content_groups_in_order(): void
    {
        // Deliberately assertSame against the raw, un-intersected group label
        // list: array_intersect() cannot detect misordering (it always
        // returns elements in the order of its first argument) and cannot
        // detect a stray extra group, so it was replaced with a direct
        // ordered comparison.
        $this->assertSame(
            ['Content', 'Homepage Data', 'Appearance', 'Settings', 'System'],
            $this->groupLabels(),
        );
    }

    public function test_it_lists_every_expected_item(): void
    {
        $labels = $this->itemLabels();

        foreach ([
            'Dashboard', 'Homepage', 'Pages', 'Blog Posts', 'Blog Categories',
            'District Places', 'Facilities', 'Stats',
            'Public Menu', 'Admin Topbar Menu',
            'Site Settings', 'Users',
        ] as $expected) {
            $this->assertContains($expected, $labels, "Missing sidebar item: {$expected}");
        }
    }

    public function test_no_item_appears_twice(): void
    {
        $labels = $this->itemLabels();

        $this->assertSame(
            count($labels),
            count(array_unique($labels)),
            'A duplicate sidebar item means AdminNavigation registered the same destination under two labels.',
        );
    }

    public function test_the_topbar_menu_is_relabelled_to_avoid_confusion_with_the_public_menu(): void
    {
        $labels = $this->itemLabels();

        $this->assertContains('Admin Topbar Menu', $labels);
        $this->assertNotContains('Topbar Menu', $labels);
    }

    /**
     * Every item's destination, asserted in one pass. A label existing
     * (test above) proves nothing about where it actually sends the admin;
     * this is what catches a "Users" entry that silently points at the
     * wrong resource.
     */
    public function test_every_item_points_at_its_expected_destination(): void
    {
        $this->assertSame(
            [
                'Dashboard' => Dashboard::getUrl(),
                'Homepage' => HomepageEditor::getUrl(),
                'Pages' => GraperPageResource::getUrl('index'),
                'Blog Posts' => BlogPostResource::getUrl('index'),
                'Blog Categories' => BlogCategoryResource::getUrl('index'),
                'District Places' => DistrictPlaceResource::getUrl('index'),
                'Facilities' => FacilityResource::getUrl('index'),
                'Stats' => StatResource::getUrl('index'),
                'Public Menu' => PublicMenuItemResource::getUrl('index'),
                'Admin Topbar Menu' => TopbarMenuItemResource::getUrl('index'),
                'Site Settings' => SiteSettingsPage::getUrl(),
                'Users' => UserResource::getUrl('index'),
            ],
            $this->itemUrls(),
        );
    }

    public function test_the_pages_item_points_at_the_graper_resource(): void
    {
        $this->assertSame(GraperPageResource::getUrl('index'), $this->itemUrls()['Pages']);
    }

    public function test_the_blog_posts_badge_counts_drafts_and_scheduled_posts(): void
    {
        BlogPost::create([
            'title' => 'Draft One',
            'slug' => 'draft-one',
            'content' => 'Content for draft one.',
            'status' => BlogPost::STATUS_DRAFT,
        ]);
        BlogPost::create([
            'title' => 'Draft Two',
            'slug' => 'draft-two',
            'content' => 'Content for draft two.',
            'status' => BlogPost::STATUS_DRAFT,
        ]);
        BlogPost::create([
            'title' => 'Published One',
            'slug' => 'published-one',
            'content' => 'Content for published one.',
            'status' => BlogPost::STATUS_PUBLISHED,
        ]);

        $badge = null;

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                if ($item->getLabel() === 'Blog Posts') {
                    $badge = $item->getBadge();
                }
            }
        }

        $this->assertSame('2', (string) $badge);
    }

    public function test_the_blog_posts_badge_is_absent_when_nothing_is_pending(): void
    {
        BlogPost::create([
            'title' => 'Published Only',
            'slug' => 'published-only',
            'content' => 'Content for the only post.',
            'status' => BlogPost::STATUS_PUBLISHED,
        ]);

        $badge = 'not-checked';

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                if ($item->getLabel() === 'Blog Posts') {
                    $badge = $item->getBadge();
                }
            }
        }

        $this->assertNull($badge, 'A permanent "0" (or any) badge would pass unless zero pending posts is asserted explicitly.');
    }

    public function test_the_district_places_item_is_active_while_visiting_its_own_pages(): void
    {
        $this->get(DistrictPlaceResource::getUrl('index'));

        $item = null;

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $candidate) {
                if ($candidate->getLabel() === 'District Places') {
                    $item = $candidate;
                }
            }
        }

        $this->assertNotNull($item, 'District Places item not found in the rebuilt navigation.');
        $this->assertTrue($item->isActive(), 'District Places should be active while its own index page is the current request.');
    }

    public function test_the_district_places_item_is_not_active_on_an_unrelated_page(): void
    {
        $this->get(StatResource::getUrl('index'));

        $item = null;

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $candidate) {
                if ($candidate->getLabel() === 'District Places') {
                    $item = $candidate;
                }
            }
        }

        $this->assertNotNull($item, 'District Places item not found in the rebuilt navigation.');
        $this->assertFalse($item->isActive(), 'District Places should not be active while viewing an unrelated resource.');
    }
}
