<?php

namespace App\Filament\Pages\Placeholders;

class CodeSnippetsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Code Snippets';

    protected static ?string $slug = 'code-snippets';

    public static function getNavigationLabel(): string
    {
        return 'Code Snippets';
    }

    public static function summary(): string
    {
        return 'Custom scripts and markup injected into the site’s head or body.';
    }
}
