<?php

namespace App\Filament\Pages\Placeholders;

class AnalyticsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Analytics';

    protected static ?string $slug = 'analytics';

    public static function getNavigationLabel(): string
    {
        return 'Analytics';
    }

    public static function summary(): string
    {
        return 'Visitor traffic, page performance and referrers. Requires tracking, which is not yet collected.';
    }
}
