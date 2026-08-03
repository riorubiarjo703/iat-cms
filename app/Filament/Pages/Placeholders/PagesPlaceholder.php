<?php

namespace App\Filament\Pages\Placeholders;

class PagesPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Pages';

    protected static ?string $slug = 'pages';

    public static function getNavigationLabel(): string
    {
        return 'Pages';
    }

    public static function summary(): string
    {
        return 'Site pages built from drag-and-drop blocks, each with its own layout, SEO and per-language content.';
    }

    public static function plannedIn(): ?string
    {
        return 'the page builder slice';
    }
}
