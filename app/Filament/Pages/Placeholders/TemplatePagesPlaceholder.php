<?php

namespace App\Filament\Pages\Placeholders;

class TemplatePagesPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Pages';

    protected static ?string $slug = 'appearance-pages';

    public static function getNavigationLabel(): string
    {
        return 'Pages';
    }

    public static function summary(): string
    {
        return 'Assign templates to pages and choose which template each page uses.';
    }
}
