<?php

namespace App\Filament\Pages\Placeholders;

class AnnouncementsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Announcements';

    protected static ?string $slug = 'announcements';

    public static function getNavigationLabel(): string
    {
        return 'Announcements';
    }

    public static function summary(): string
    {
        return 'Site-wide banners and notices with scheduling.';
    }
}
