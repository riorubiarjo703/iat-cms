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
use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Roles\RoleResource;
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
 * Consequence: anything not listed here is reachable only by URL. Sixteen of
 * these destinations are deliberate placeholders — the sidebar shows the
 * product's full intended shape, and each unbuilt screen says so plainly.
 *
 * The same bypass also skips Filament's policy-based nav hiding (it only
 * fires during auto-registration). Two different things happen to the two
 * levels of this tree as a result, confirmed by reading
 * vendor/filament/filament/src/Navigation/NavigationBuilder.php and tracing
 * Panel::buildNavigation() -> NavigationManager::get():
 *
 * - A GROUP's direct items ARE filtered by isVisible() by Filament itself
 *   (NavigationBuilder::getNavigation() does it, and drops any group left
 *   with none), before Blade ever sees them. self::group() only needs to
 *   strip the nulls self::parent() can return — Filament's own filtering
 *   still runs after and does the rest, which is why the top-level pruning
 *   here does not need to duplicate it.
 * - A NavigationItem's own childItems() are NOT touched by that pass, and
 *   the Blade sidebar template renders whatever is in that array without
 *   consulting isVisible() either. Nothing downstream ever looks at a child
 *   item's visibility or asks whether a parent's children are all hidden.
 *   So every leaf entry below still carries a permission, and self::parent()
 *   still physically drops hidden children (and prunes itself to null when
 *   none remain) rather than trusting anything downstream to check.
 */
final class AdminNavigation
{
    public static function build(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder->groups(self::groups());
    }

    /** @return array<int, NavigationGroup> */
    private static function groups(): array
    {
        return [
            // The three group headers are labels, not controls. Only items
            // expand; a collapsible header would give the tree two different
            // kinds of disclosure at two levels.
            self::group('General', [
                self::item('Dashboard', 'heroicon-o-home', Dashboard::getUrl(), 1, 'dashboard.view', Dashboard::getRouteName()),
                self::parent('Content', 'heroicon-o-document-text', 2, [
                    self::resource(BlogPostResource::class, 'Posts', 'heroicon-o-newspaper', 1, 'posts.view', self::pendingPostCount()),
                    self::resource(PageResource::class, 'Pages', 'heroicon-o-document-duplicate', 2, 'pages.view'),
                    self::page(P\ContentBlocksPlaceholder::class, 'Content Blocks', 'heroicon-o-rectangle-stack', 3, 'content-blocks.view'),
                    self::resource(BlogCategoryResource::class, 'Categories', 'heroicon-o-tag', 4, 'categories.view'),
                    self::page(P\CommentsPlaceholder::class, 'Comments', 'heroicon-o-chat-bubble-left-right', 5, 'comments.view'),
                    // Added to the reference structure: it exists and is used, and an
                    // unlisted resource would be reachable only by URL.
                    self::pageUrl('Media Library', 'heroicon-o-photo', MediaManager::getUrl(), 6, 'media.manage'),
                ]),
                // Was a placeholder until the contact page had somewhere to
                // deliver to. The badge is the unread count.
                self::resource(ContactMessageResource::class, 'Contacts', 'heroicon-o-inbox', 3, 'contacts.view', ContactMessageResource::getNavigationBadge()),
                self::parent('Marketing', 'heroicon-o-megaphone', 4, [
                    self::page(P\NewsletterPlaceholder::class, 'Newsletter', 'heroicon-o-envelope', 1, 'newsletter.view'),
                    self::page(P\AnnouncementsPlaceholder::class, 'Announcements', 'heroicon-o-megaphone', 2, 'announcements.view'),
                    self::page(P\AdvertisementsPlaceholder::class, 'Advertisements', 'heroicon-o-rectangle-group', 3, 'advertisements.view'),
                    self::page(P\AdZonesPlaceholder::class, 'Ad Zones', 'heroicon-o-squares-2x2', 4, 'ad-zones.view'),
                    self::page(P\SocialPostingPlaceholder::class, 'Social Posting', 'heroicon-o-share', 5, 'social-posting.view'),
                ]),
                self::parent('Users Management', 'heroicon-o-users', 5, [
                    self::resource(UserResource::class, 'Users', 'heroicon-o-users', 1, 'users.view'),
                    self::resource(RoleResource::class, 'Roles', 'heroicon-o-shield-check', 2, 'roles.manage'),
                    self::resource(PermissionResource::class, 'Permissions', 'heroicon-o-key', 3, 'permissions.manage'),
                ]),
            ]),

            self::group('System', [
                self::page(P\AnalyticsPlaceholder::class, 'Analytics', 'heroicon-o-chart-bar', 1, 'analytics.view'),
                self::page(P\EmailActivityPlaceholder::class, 'Email Activity', 'heroicon-o-at-symbol', 2, 'email-activity.view'),
                self::parent('SEO', 'heroicon-o-magnifying-glass', 3, [
                    self::page(P\RedirectsPlaceholder::class, 'Redirects', 'heroicon-o-arrow-uturn-right', 1, 'redirects.view'),
                ]),
                self::parent('System', 'heroicon-o-cog-8-tooth', 4, [
                    self::resource(CodeSnippetResource::class, 'Code Snippets', 'heroicon-o-code-bracket', 1, 'code-snippets.view'),
                    self::page(P\BackupsPlaceholder::class, 'Backups', 'heroicon-o-archive-box', 2, 'backups.view'),
                ]),
            ]),

            self::group('Administration', [
                self::parent('Appearance', 'heroicon-o-paint-brush', 1, [
                    self::pageUrl('Navigation Menus', 'heroicon-o-bars-3', NavigationMenusPage::getUrl(), 1, 'menus.manage', NavigationMenusPage::getRouteName()),
                    // Distinct from Content > Pages: template assignment, not content.
                    self::page(P\TemplatePagesPlaceholder::class, 'Pages', 'heroicon-o-document', 2, 'template-pages.view'),
                    self::page(P\TemplateSettingsPlaceholder::class, 'Template Settings', 'heroicon-o-adjustments-horizontal', 3, 'template-settings.view'),
                    self::page(P\TranslationsPlaceholder::class, 'Translations', 'heroicon-o-language', 4, 'translations.view'),
                    self::page(P\ThemeEditorPlaceholder::class, 'Theme Editor', 'heroicon-o-swatch', 5, 'theme-editor.view'),
                ]),
                self::pageUrl('Settings', 'heroicon-o-cog-6-tooth', SiteSettingsPage::getUrl(), 2, 'settings.manage', SiteSettingsPage::getRouteName()),
            ]),
        ];
    }

    /**
     * A section header. Not collapsible: it labels a region of the tree rather
     * than acting as a control, and only items expand.
     *
     * Only strips the nulls self::parent() can return below — passing one
     * into NavigationGroup::items() would crash Filament's own isVisible()
     * pass over this same array (see this class's docblock). Filtering by
     * permission again here would be redundant: Filament already does it for
     * a group's direct items and drops the group entirely if none survive.
     *
     * @param  array<int, NavigationItem|null>  $items
     */
    private static function group(string $label, array $items): NavigationGroup
    {
        return NavigationGroup::make($label)
            ->items(array_values(array_filter($items)))
            ->collapsible(false);
    }

    /**
     * A parent item that expands to children and stays active while any child is.
     * Null when every child is hidden — the caller (self::group(), or another
     * self::parent() one level up) drops a null rather than render an empty
     * disclosure that invites a click revealing nothing.
     *
     * @param  array<int, NavigationItem>  $children
     */
    private static function parent(string $label, string $icon, int $sort, array $children): ?NavigationItem
    {
        $children = self::visible($children);

        if ($children === []) {
            return null;
        }

        return NavigationItem::make($label)
            ->icon($icon)
            ->childItems($children)
            ->sort($sort);
    }

    private static function item(string $label, string $icon, string $url, int $sort, string $permission, ?string $activeRoute = null): NavigationItem
    {
        $item = NavigationItem::make($label)
            ->icon($icon)
            ->url($url)
            ->sort($sort)
            ->visible(fn (): bool => auth()->user()?->can($permission) ?? false);

        return $activeRoute
            ? $item->isActiveWhen(fn (): bool => request()->routeIs($activeRoute))
            : $item;
    }

    /** @param class-string $resource */
    private static function resource(string $resource, string $label, string $icon, int $sort, string $permission, ?string $badge = null): NavigationItem
    {
        $item = NavigationItem::make($label)
            ->icon($icon)
            ->url($resource::getUrl('index'))
            ->isActiveWhen(fn (): bool => request()->routeIs($resource::getRouteBaseName().'.*'))
            ->sort($sort)
            ->visible(fn (): bool => auth()->user()?->can($permission) ?? false);

        return $badge === null ? $item : $item->badge($badge, 'warning');
    }

    /** @param class-string $page */
    private static function page(string $page, string $label, string $icon, int $sort, string $permission): NavigationItem
    {
        return self::item($label, $icon, $page::getUrl(), $sort, $permission, $page::getRouteName());
    }

    private static function pageUrl(string $label, string $icon, string $url, int $sort, string $permission, ?string $activeRoute = null): NavigationItem
    {
        return self::item($label, $icon, $url, $sort, $permission, $activeRoute);
    }

    /**
     * Filament skips its own policy-based nav hiding when a NavigationBuilder
     * is used (see this class's docblock), so visibility is explicit here.
     * Without it an editor sees every link and learns the restriction by
     * hitting a 403.
     *
     * @param  array<int, NavigationItem>  $items
     * @return array<int, NavigationItem>
     */
    private static function visible(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (NavigationItem $item): bool => $item->isVisible(),
        ));
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
