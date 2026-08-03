<?php

namespace App\Filament\Pages\Placeholders;

class PermissionsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Permissions';

    protected static ?string $slug = 'permissions';

    public static function getNavigationLabel(): string
    {
        return 'Permissions';
    }

    public static function summary(): string
    {
        return 'Individual capabilities that roles are composed from.';
    }

    public static function plannedIn(): ?string
    {
        return 'slice E';
    }
}
