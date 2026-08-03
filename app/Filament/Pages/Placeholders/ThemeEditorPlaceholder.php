<?php

namespace App\Filament\Pages\Placeholders;

class ThemeEditorPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Theme Editor';

    protected static ?string $slug = 'theme-editor';

    public static function getNavigationLabel(): string
    {
        return 'Theme Editor';
    }

    public static function summary(): string
    {
        return 'Colours, typography and spacing for the public site.';
    }
}
