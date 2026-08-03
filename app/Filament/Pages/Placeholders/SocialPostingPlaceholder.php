<?php

namespace App\Filament\Pages\Placeholders;

class SocialPostingPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Social Posting';

    protected static ?string $slug = 'social-posting';

    public static function getNavigationLabel(): string
    {
        return 'Social Posting';
    }

    public static function summary(): string
    {
        return 'Publish and schedule posts to connected social accounts.';
    }
}
