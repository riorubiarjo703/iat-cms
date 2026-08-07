<?php

namespace App\Filament\Pages\Concerns;

/**
 * Filament's own default for a custom Page's canAccess() is an unconditional
 * `true` (vendor/filament/filament/src/Pages/Concerns/CanAuthorizeAccess.php) —
 * resource policies never come into it, because a Page is not backed by a
 * model. Without this, a content_editor who cleared the panel gate could open
 * any app Page by URL — Site Settings, the menu builder, every placeholder —
 * regardless of what the sidebar hid, because AdminNavigation's own
 * permission check only ever ran during the sidebar's own render.
 *
 * One line per page: implement `permission()` with the exact string
 * AdminNavigation already passes for that entry.
 */
trait ChecksPagePermission
{
    abstract public static function permission(): string;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(static::permission()) ?? false;
    }
}
