<?php

namespace App\Filament\Pages\Placeholders;

class AdZonesPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Ad Zones';

    protected static ?string $slug = 'ad-zones';

    public static function getNavigationLabel(): string
    {
        return 'Ad Zones';
    }

    public static function summary(): string
    {
        return 'Named placements on the site that advertisements can be assigned to.';
    }
}
