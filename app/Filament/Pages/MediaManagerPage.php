<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ChecksPagePermission;
use Slimani\MediaManager\Pages\MediaManager;

/**
 * The vendor page (Slimani\MediaManager\Pages\MediaManager) cannot be edited
 * to add canAccess(), and its own default — Filament's CanAuthorizeAccess
 * trait, unconditional `true` — is exactly the hole C1 exists to close.
 *
 * The plugin ships a first-class extension point for this:
 * MediaManagerPlugin::mediaManagerPage() swaps in a subclass, which is
 * registered in AdminPanelProvider. A middleware mapping page class to
 * permission was considered and rejected — it would duplicate, outside this
 * class hierarchy, knowledge that belongs on the page itself, and would need
 * updating in a second place every time a page's permission changed.
 */
class MediaManagerPage extends MediaManager
{
    use ChecksPagePermission;

    /**
     * Matches the vendor page's own default slug (kebab-cased class
     * basename, "media-manager") rather than the one this subclass would
     * otherwise derive ("media-manager-page"). Filament resolves getUrl() by
     * route name alone (Page::getUrl() -> route(static::getRouteName())), so
     * every existing `MediaManager::getUrl()` call — AdminNavigation, the
     * QuickActions widget — keeps resolving correctly with no change there,
     * because the route registered under this subclass carries the same
     * name the vendor class would have registered for itself.
     */
    protected static ?string $slug = 'media-manager';

    public static function permission(): string
    {
        return 'media.manage';
    }
}
