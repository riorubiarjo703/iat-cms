<?php

namespace App\Filament\Pages\Placeholders;

class RolesPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Roles';

    protected static ?string $slug = 'roles';

    public static function getNavigationLabel(): string
    {
        return 'Roles';
    }

    public static function summary(): string
    {
        return 'Named permission sets assigned to users.';
    }

    public static function plannedIn(): ?string
    {
        return 'slice E';
    }
}
