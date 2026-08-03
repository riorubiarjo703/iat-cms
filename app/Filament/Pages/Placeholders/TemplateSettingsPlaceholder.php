<?php

namespace App\Filament\Pages\Placeholders;

class TemplateSettingsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Template Settings';

    protected static ?string $slug = 'template-settings';

    public static function getNavigationLabel(): string
    {
        return 'Template Settings';
    }

    public static function summary(): string
    {
        return 'Layout options that apply across the whole theme.';
    }
}
