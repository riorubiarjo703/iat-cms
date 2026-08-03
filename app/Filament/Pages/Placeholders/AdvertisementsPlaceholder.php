<?php

namespace App\Filament\Pages\Placeholders;

class AdvertisementsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Advertisements';

    protected static ?string $slug = 'advertisements';

    public static function getNavigationLabel(): string
    {
        return 'Advertisements';
    }

    public static function summary(): string
    {
        return 'Ad creatives, their scheduling and performance.';
    }
}
