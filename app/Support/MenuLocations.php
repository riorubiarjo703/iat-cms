<?php

namespace App\Support;

/**
 * The places a menu can be shown. Declared in code rather than the database:
 * a location only means something if a template renders it, so adding a row
 * could never make a new location appear on the site.
 */
final class MenuLocations
{
    public const HEADER = 'header';

    public const FOOTER = 'footer';

    /**
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    public static function all(): array
    {
        return [
            self::HEADER => [
                'label' => 'Header Navigation',
                'description' => 'Main navigation links in the site header',
                'icon' => 'heroicon-o-window',
            ],
            self::FOOTER => [
                'label' => 'Footer Navigation',
                'description' => 'Footer columns — top-level items become column headers, children become links',
                'icon' => 'heroicon-o-bars-3-bottom-left',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }
}
