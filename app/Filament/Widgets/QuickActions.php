<?php

namespace App\Filament\Widgets;

use AjayDhakal\FilamentStory\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\PublicMenuItems\PublicMenuItemResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Pages\Placeholders\PagesPlaceholder;
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

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['actions' => static::actions()];
    }

    /**
     * Public so a test can assert every destination resolves, which is the
     * check that would have caught a link pointing at nothing.
     *
     * @return array<int, array{label: string, icon: string, tint: string, url: string}>
     */
    public static function actions(): array
    {
        return [
            ['label' => 'New Page', 'icon' => 'heroicon-o-document-plus', 'tint' => 'blue', 'url' => PagesPlaceholder::getUrl()],
            ['label' => 'New Post', 'icon' => 'heroicon-o-pencil-square', 'tint' => 'violet', 'url' => BlogPostResource::getUrl('create')],
            ['label' => 'Media Library', 'icon' => 'heroicon-o-photo', 'tint' => 'emerald', 'url' => MediaManager::getUrl()],
            ['label' => 'Navigation Menus', 'icon' => 'heroicon-o-bars-3', 'tint' => 'amber', 'url' => PublicMenuItemResource::getUrl('index')],
            ['label' => 'Site Settings', 'icon' => 'heroicon-o-cog-6-tooth', 'tint' => 'slate', 'url' => SiteSettingsPage::getUrl()],
            ['label' => 'Users', 'icon' => 'heroicon-o-users', 'tint' => 'rose', 'url' => UserResource::getUrl('index')],
        ];
    }
}
