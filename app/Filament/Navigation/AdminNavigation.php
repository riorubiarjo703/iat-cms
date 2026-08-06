<?php

namespace App\Filament\Navigation;

use AjayDhakal\FilamentStory\Filament\Resources\BlogPosts\BlogPostResource;
use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Filament\Pages\NavigationMenusPage;
use App\Filament\Pages\Placeholders as P;
use App\Filament\Pages\SiteSettingsPage;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\CodeSnippets\CodeSnippetResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Users\UserResource;
use App\Support\RequestCache;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Slimani\MediaManager\Pages\MediaManager;

/**
 * The single owner of the admin sidebar.
 *
 * A NavigationBuilder makes Filament skip auto-registration entirely
 * (NavigationManager::get() early-returns at line 49), which is how the three
 * plugins' hardcoded placement gets overridden without touching vendor code.
 *
 * Consequence: anything not listed here is reachable only by URL. Eighteen of
 * these destinations are deliberate placeholders — the sidebar shows the
 * product's full intended shape, and each unbuilt screen says so plainly.
 */
final class AdminNavigation
{
    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder->groups([
            // The three group headers are labels, not controls. Only items
            // expand; a collapsible header would give the tree two different
            // kinds of disclosure at two levels.
            self::group('General', [
                self::item('Dashboard', 'heroicon-o-home', Dashboard::getUrl(), 1, Dashboard::getRouteName()),
                self::parent('Content', 'heroicon-o-document-text', 2, [
                    self::resource(BlogPostResource::class, 'Posts', 'heroicon-o-newspaper', 1, self::pendingPostCount()),
                    self::resource(PageResource::class, 'Pages', 'heroicon-o-document-duplicate', 2),
                    self::page(P\ContentBlocksPlaceholder::class, 'Content Blocks', 'heroicon-o-rectangle-stack', 3),
                    self::resource(BlogCategoryResource::class, 'Categories', 'heroicon-o-tag', 4),
                    self::page(P\CommentsPlaceholder::class, 'Comments', 'heroicon-o-chat-bubble-left-right', 5),
                    // Added to the reference structure: it exists and is used, and an
                    // unlisted resource would be reachable only by URL.
                    self::pageUrl('Media Library', 'heroicon-o-photo', MediaManager::getUrl(), 6),
                ]),
                // Was a placeholder until the contact page had somewhere to
                // deliver to. The badge is the unread count.
                self::resource(ContactMessageResource::class, 'Contacts', 'heroicon-o-inbox', 3, ContactMessageResource::getNavigationBadge()),
                self::parent('Marketing', 'heroicon-o-megaphone', 4, [
                    self::page(P\NewsletterPlaceholder::class, 'Newsletter', 'heroicon-o-envelope', 1),
                    self::page(P\AnnouncementsPlaceholder::class, 'Announcements', 'heroicon-o-megaphone', 2),
                    self::page(P\AdvertisementsPlaceholder::class, 'Advertisements', 'heroicon-o-rectangle-group', 3),
                    self::page(P\AdZonesPlaceholder::class, 'Ad Zones', 'heroicon-o-squares-2x2', 4),
                    self::page(P\SocialPostingPlaceholder::class, 'Social Posting', 'heroicon-o-share', 5),
                ]),
                self::parent('Users Management', 'heroicon-o-users', 5, [
                    self::resource(UserResource::class, 'Users', 'heroicon-o-users', 1),
                    self::page(P\RolesPlaceholder::class, 'Roles', 'heroicon-o-shield-check', 2),
                    self::page(P\PermissionsPlaceholder::class, 'Permissions', 'heroicon-o-key', 3),
                ]),
            ]),

            self::group('System', [
                self::page(P\AnalyticsPlaceholder::class, 'Analytics', 'heroicon-o-chart-bar', 1),
                self::page(P\EmailActivityPlaceholder::class, 'Email Activity', 'heroicon-o-at-symbol', 2),
                self::parent('SEO', 'heroicon-o-magnifying-glass', 3, [
                    self::page(P\RedirectsPlaceholder::class, 'Redirects', 'heroicon-o-arrow-uturn-right', 1),
                ]),
                self::parent('System', 'heroicon-o-cog-8-tooth', 4, [
                    self::resource(CodeSnippetResource::class, 'Code Snippets', 'heroicon-o-code-bracket', 1),
                    self::page(P\BackupsPlaceholder::class, 'Backups', 'heroicon-o-archive-box', 2),
                ]),
            ]),

            self::group('Administration', [
                self::parent('Appearance', 'heroicon-o-paint-brush', 1, [
                    self::pageUrl('Navigation Menus', 'heroicon-o-bars-3', NavigationMenusPage::getUrl(), 1, NavigationMenusPage::getRouteName()),
                    // Distinct from Content > Pages: template assignment, not content.
                    self::page(P\TemplatePagesPlaceholder::class, 'Pages', 'heroicon-o-document', 2),
                    self::page(P\TemplateSettingsPlaceholder::class, 'Template Settings', 'heroicon-o-adjustments-horizontal', 3),
                    self::page(P\TranslationsPlaceholder::class, 'Translations', 'heroicon-o-language', 4),
                    self::page(P\ThemeEditorPlaceholder::class, 'Theme Editor', 'heroicon-o-swatch', 5),
                ]),
                self::pageUrl('Settings', 'heroicon-o-cog-6-tooth', SiteSettingsPage::getUrl(), 2, SiteSettingsPage::getRouteName()),
            ]),
        ]);
    }

    /**
     * A section header. Not collapsible: it labels a region of the tree rather
     * than acting as a control, and only items expand.
     *
     * @param  array<int, NavigationItem>  $items
     */
    private static function group(string $label, array $items): NavigationGroup
    {
        return NavigationGroup::make($label)->items($items)->collapsible(false);
    }

    /** A parent item that expands to children and stays active while any child is. */
    private static function parent(string $label, string $icon, int $sort, array $children): NavigationItem
    {
        return NavigationItem::make($label)
            ->icon($icon)
            ->childItems($children)
            ->sort($sort);
    }

    private static function item(string $label, string $icon, string $url, int $sort, ?string $activeRoute = null): NavigationItem
    {
        $item = NavigationItem::make($label)->icon($icon)->url($url)->sort($sort);

        return $activeRoute
            ? $item->isActiveWhen(fn (): bool => request()->routeIs($activeRoute))
            : $item;
    }

    /** @param class-string $resource */
    private static function resource(string $resource, string $label, string $icon, int $sort, ?string $badge = null): NavigationItem
    {
        $item = NavigationItem::make($label)
            ->icon($icon)
            ->url($resource::getUrl('index'))
            ->isActiveWhen(fn (): bool => request()->routeIs($resource::getRouteBaseName().'.*'))
            ->sort($sort);

        return $badge === null ? $item : $item->badge($badge, 'warning');
    }

    /** @param class-string $page */
    private static function page(string $page, string $label, string $icon, int $sort): NavigationItem
    {
        return self::item($label, $icon, $page::getUrl(), $sort, $page::getRouteName());
    }

    private static function pageUrl(string $label, string $icon, string $url, int $sort, ?string $activeRoute = null): NavigationItem
    {
        return self::item($label, $icon, $url, $sort, $activeRoute);
    }

    /** Drafts and scheduled posts still needing attention; null when there are none. */
    private static function pendingPostCount(): ?string
    {
        // Filament builds the navigation more than once per request — sidebar,
        // breadcrumbs, active-state checks — and each build re-ran this count.
        return RequestCache::remember('nav.pending_posts', function (): ?string {
            return self::countPendingPosts();
        });
    }

    private static function countPendingPosts(): ?string
    {
        $count = BlogPost::query()
            ->whereIn('status', [BlogPost::STATUS_DRAFT, BlogPost::STATUS_SCHEDULED])
            ->count();

        return $count > 0 ? (string) $count : null;
    }
}
