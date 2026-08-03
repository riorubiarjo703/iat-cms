<?php

namespace App\Filament\Pages\Placeholders;

class NewsletterPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Newsletter';

    protected static ?string $slug = 'newsletter';

    public static function getNavigationLabel(): string
    {
        return 'Newsletter';
    }

    public static function summary(): string
    {
        return 'Subscriber list and campaign composer for email newsletters.';
    }
}
