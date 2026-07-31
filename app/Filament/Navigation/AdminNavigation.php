<?php

namespace App\Filament\Navigation;

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
use CybertronianKelvin\Graper\Resources\GraperPageResource;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Slimani\MediaManager\Pages\MediaManager;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

/**
 * The single owner of the admin sidebar.
 *
 * Using a NavigationBuilder makes Filament skip auto-registration entirely
 * (NavigationManager::get() early-returns at line 49), which is how the three
 * plugins' hardcoded navigation placement gets overridden without touching
 * vendor code.
 *
 * Consequence: any resource or page added later must be listed here or it will
 * not appear in the sidebar.
 *
 * Groups deliberately carry no ->icon(): Filament's sidebar group Blade
 * partial throws ("Either the group or its items can have icons, but not
 * both") when a non-dropdown group icon is combined with per-item icons.
 * Per-item icons are kept because they distinguish resources at a glance;
 * the group icon is the one dropped.
 */
final class AdminNavigation
{
    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder
            ->items([
                NavigationItem::make('Dashboard')
                    ->icon('heroicon-o-home')
                    ->url(Dashboard::getUrl())
                    ->isActiveWhen(fn () => request()->routeIs(Dashboard::getRouteName()))
                    ->sort(0),
            ])
            ->groups([
                NavigationGroup::make('Content')
                    ->items([
                        NavigationItem::make('Homepage')
                            ->icon('heroicon-o-home-modern')
                            ->url(HomepageEditor::getUrl())
                            ->isActiveWhen(fn () => request()->routeIs(HomepageEditor::getRouteName()))
                            ->sort(1),
                        ...self::resourceItems(GraperPageResource::class, 'Pages', 'heroicon-o-document-duplicate', 2),
                        ...self::resourceItems(BlogPostResource::class, 'Blog Posts', 'heroicon-o-newspaper', 3, self::pendingPostCount()),
                        ...self::resourceItems(BlogCategoryResource::class, 'Blog Categories', 'heroicon-o-tag', 4),
                        NavigationItem::make('Media Library')
                            ->icon('heroicon-o-photo')
                            ->url(MediaManager::getUrl())
                            ->isActiveWhen(fn () => request()->routeIs(MediaManager::getRouteName()))
                            ->sort(5),
                    ]),

                NavigationGroup::make('Homepage Data')
                    ->items([
                        ...self::resourceItems(DistrictPlaceResource::class, 'District Places', 'heroicon-o-building-office-2', 1),
                        ...self::resourceItems(FacilityResource::class, 'Facilities', 'heroicon-o-wrench-screwdriver', 2),
                        ...self::resourceItems(StatResource::class, 'Stats', 'heroicon-o-chart-bar', 3),
                    ]),

                NavigationGroup::make('Appearance')
                    ->items([
                        ...self::resourceItems(PublicMenuItemResource::class, 'Public Menu', 'heroicon-o-link', 1),
                        // Deliberately NOT "Topbar Menu": this renders inside the
                        // admin panel's topbar, not on the public site. Sitting
                        // next to "Public Menu" the shorter label misleads.
                        ...self::resourceItems(TopbarMenuItemResource::class, 'Admin Topbar Menu', 'heroicon-o-bars-3-bottom-left', 2),
                    ]),

                NavigationGroup::make('Settings')
                    ->items([
                        NavigationItem::make('Site Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->url(SiteSettingsPage::getUrl())
                            ->isActiveWhen(fn () => request()->routeIs(SiteSettingsPage::getRouteName()))
                            ->sort(1),
                    ]),

                NavigationGroup::make('System')
                    ->items([
                        ...self::resourceItems(UserResource::class, 'Users', 'heroicon-o-users', 1),
                    ]),
            ]);
    }

    /**
     * Builds one navigation item for a resource, labelled and sorted by us
     * rather than by whatever the resource class hardcodes.
     *
     * @param  class-string  $resource
     * @return array<int, NavigationItem>
     */
    private static function resourceItems(
        string $resource,
        string $label,
        string $icon,
        int $sort,
        ?string $badge = null,
    ): array {
        $item = NavigationItem::make($label)
            ->icon($icon)
            ->url($resource::getUrl('index'))
            ->isActiveWhen(fn () => request()->routeIs($resource::getRouteBaseName().'.*'))
            ->sort($sort);

        if ($badge !== null) {
            $item->badge($badge, 'warning');
        }

        return [$item];
    }

    /**
     * Posts still needing attention: drafts plus anything scheduled but not yet
     * published. Returns null when there are none, so no badge renders.
     */
    private static function pendingPostCount(): ?string
    {
        $count = BlogPost::query()
            ->whereIn('status', [BlogPost::STATUS_DRAFT, BlogPost::STATUS_SCHEDULED])
            ->count();

        return $count > 0 ? (string) $count : null;
    }
}
