<?php

namespace App\Filament\Pages\Placeholders;

class BackupsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Backups';

    protected static ?string $slug = 'backups';

    public static function getNavigationLabel(): string
    {
        return 'Backups';
    }

    public static function summary(): string
    {
        return 'Scheduled database and media backups, with restore points.';
    }
}
