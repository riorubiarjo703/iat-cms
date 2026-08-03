<?php

namespace App\Filament\Pages\Placeholders;

class ContentBlocksPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Content Blocks';

    protected static ?string $slug = 'content-blocks';

    public static function getNavigationLabel(): string
    {
        return 'Content Blocks';
    }

    public static function summary(): string
    {
        return 'Reusable content sections that can be dropped onto any page and edited in one place.';
    }

    public static function plannedIn(): ?string
    {
        return 'the Graper page builder slice';
    }
}
