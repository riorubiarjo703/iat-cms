<?php

namespace App\Filament\Widgets;

use AjayDhakal\FilamentStory\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Pages\NavigationMenusPage;
use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Widgets\Widget;
use Slimani\MediaManager\Pages\MediaManager;

class QuickActions extends Widget
{
    protected string $view = 'filament.widgets.quick-actions';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    /**
     * Rendered with the page rather than lazily. These are a few COUNT queries
     * on a company-profile-sized dataset, so the extra round trip buys nothing
     * — and a lazy widget's render failure never reaches the initial response,
     * which is how a broken dashboard passed its tests once already. If
     * coverage ever gets slow, cache it rather than hiding it behind a spinner.
     */
    protected static bool $isLazy = false;

    /**
     * Filament's Page::getVisibleWidgets() filters by canView() before this
     * ever renders (vendor/filament/filament/src/Pages/Page.php), unlike
     * AdminNavigation's NavigationBuilder — so this one check is enough; no
     * per-action physical filtering trick is needed downstream of it.
     */
    public static function canView(): bool
    {
        return static::actions() !== [];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['actions' => static::actions()];
    }

    /**
     * Public so a test can assert every destination resolves, which is the
     * check that would have caught a link pointing at nothing.
     *
     * Each entry is gated on the same permission its sidebar counterpart in
     * AdminNavigation uses — otherwise a content editor sees a working-looking
     * button for a screen they get a 403 from, the same defect the sidebar
     * filter exists to prevent, just one widget over.
     *
     * @return array<int, array{label: string, icon: string, tint: string, url: string}>
     */
    public static function actions(): array
    {
        return array_values(array_filter([
            self::action('New Page', 'heroicon-o-document-plus', 'blue', PageResource::getUrl('create'), 'pages.create'),
            self::action('New Post', 'heroicon-o-pencil-square', 'violet', BlogPostResource::getUrl('create'), 'posts.create'),
            self::action('Media Library', 'heroicon-o-photo', 'emerald', MediaManager::getUrl(), 'media.manage'),
            self::action('Navigation Menus', 'heroicon-o-bars-3', 'amber', NavigationMenusPage::getUrl(), 'menus.manage'),
            self::action('Site Settings', 'heroicon-o-cog-6-tooth', 'slate', SiteSettingsPage::getUrl(), 'settings.manage'),
            self::action('Users', 'heroicon-o-users', 'rose', UserResource::getUrl('index'), 'users.view'),
        ]));
    }

    /** @return array{label: string, icon: string, tint: string, url: string}|null */
    private static function action(string $label, string $icon, string $tint, string $url, string $permission): ?array
    {
        if (! (auth()->user()?->can($permission) ?? false)) {
            return null;
        }

        return ['label' => $label, 'icon' => $icon, 'tint' => $tint, 'url' => $url];
    }
}
