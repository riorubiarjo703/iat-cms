<?php

namespace App\Support;

/**
 * Every permission this panel recognises, and which role holds which.
 *
 * Derived from App\Filament\Navigation\AdminNavigation's destinations rather
 * than copied from the reference product, so each sidebar entry has something
 * that gates it. Fifteen of these gate a screen that currently says "not built
 * yet" — the sidebar deliberately shows the product's full intended shape, and
 * a list covering only built features would need editing every time a
 * placeholder became real.
 *
 * Names are exact strings shared by the seeder, the policies, the navigation
 * and the tests. A typo produces a permission that gates nothing and a screen
 * nobody can reach.
 */
final class PermissionCatalogue
{
    /** Built features with real CRUD verbs. */
    private const CRUD = [
        'posts' => ['view', 'create', 'update', 'delete'],
        'pages' => ['view', 'create', 'update', 'delete'],
        'categories' => ['view', 'create', 'update', 'delete'],
        'users' => ['view', 'create', 'update', 'delete'],
        'code-snippets' => ['view', 'create', 'update', 'delete'],

        // No create: messages arrive from the public contact form, never
        // from the panel.
        'contacts' => ['view', 'update', 'delete'],

        // Homepage structure the administrator owns, not the editor: none
        // of these three reach contentEditorPermissions() below. They carry
        // no navigation entry of their own (reachable only by URL, and by
        // the page builder), which is exactly why they had no policy and no
        // catalogue entry at all until this was noticed — every ability on
        // an unpolicied model falls through to Filament's allow-by-default,
        // including bulk delete and reorder.
        'district-places' => ['view', 'create', 'update', 'delete'],
        'facilities' => ['view', 'create', 'update', 'delete'],
        'stats' => ['view', 'create', 'update', 'delete'],
    ];

    /** Built single-screen destinations. */
    private const SINGLE = [
        'dashboard.view',
        'media.manage',
        'menus.manage',
        'settings.manage',
        'roles.manage',
        'permissions.manage',
    ];

    /** The panel gate. Deleting it would lock out everyone, permanently. */
    private const GATE = 'admin.access';

    /** Placeholder screens: view only, because there is nothing to edit yet. */
    private const PLACEHOLDERS = [
        'content-blocks.view',
        'comments.view',
        'newsletter.view',
        'announcements.view',
        'advertisements.view',
        'ad-zones.view',
        'social-posting.view',
        'analytics.view',
        'email-activity.view',
        'redirects.view',
        'backups.view',
        'template-pages.view',
        'template-settings.view',
        'translations.view',
        'theme-editor.view',
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        $crud = [];

        foreach (self::CRUD as $feature => $verbs) {
            foreach ($verbs as $verb) {
                $crud[] = "{$feature}.{$verb}";
            }
        }

        return [...$crud, ...self::SINGLE, self::GATE, ...self::PLACEHOLDERS];
    }

    /** @return array<int, string> */
    public static function systemPermissions(): array
    {
        return [self::GATE];
    }

    /**
     * The content editor: the Content menu, Navigation Menus, the dashboard.
     *
     * Pages are view and update only. Everything else in Content is full CRUD,
     * because an editor who cannot publish a post is not a content editor.
     *
     * @return array<int, string>
     */
    public static function contentEditorPermissions(): array
    {
        return [
            'dashboard.view',
            'posts.view', 'posts.create', 'posts.update', 'posts.delete',
            'pages.view', 'pages.update',
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'content-blocks.view',
            'comments.view',
            'media.manage',
            'menus.manage',
            self::GATE,
        ];
    }

    /**
     * The heading a permission sits under in the Roles modal. A flat list of
     * forty-five checkboxes is unusable.
     */
    public static function groupLabel(string $permission): string
    {
        $feature = str_contains($permission, '.')
            ? strstr($permission, '.', true)
            : $permission;

        return str($feature)->replace('-', ' ')->title()->toString();
    }
}
