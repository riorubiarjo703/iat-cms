<?php

namespace App\Filament\Pages\Placeholders;

class TranslationsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Translations';

    protected static ?string $slug = 'translations';

    public static function getNavigationLabel(): string
    {
        return 'Translations';
    }

    public static function summary(): string
    {
        return 'Per-locale copy for every translatable string on the site.';
    }

    public static function plannedIn(): ?string
    {
        return 'the Graper page builder slice';
    }
}
