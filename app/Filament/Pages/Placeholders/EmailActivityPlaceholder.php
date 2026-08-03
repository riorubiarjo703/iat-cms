<?php

namespace App\Filament\Pages\Placeholders;

class EmailActivityPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Email Activity';

    protected static ?string $slug = 'email-activity';

    public static function getNavigationLabel(): string
    {
        return 'Email Activity';
    }

    public static function summary(): string
    {
        return 'Delivery, open and bounce history for mail sent by the CMS.';
    }
}
