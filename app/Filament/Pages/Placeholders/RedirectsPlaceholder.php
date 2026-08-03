<?php

namespace App\Filament\Pages\Placeholders;

class RedirectsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Redirects';

    protected static ?string $slug = 'redirects';

    public static function getNavigationLabel(): string
    {
        return 'Redirects';
    }

    public static function summary(): string
    {
        return 'Managed URL redirects, so moved or renamed pages keep their inbound links.';
    }
}
