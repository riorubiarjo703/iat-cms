<?php

namespace App\Filament\Pages\Placeholders;

class ContactsPlaceholder extends PlaceholderPage
{
    protected static ?string $title = 'Contacts';

    protected static ?string $slug = 'contacts';

    public static function getNavigationLabel(): string
    {
        return 'Contacts';
    }

    public static function summary(): string
    {
        return 'Enquiries submitted through the site’s contact form, with read, reply and archive states.';
    }

    public static function plannedIn(): ?string
    {
        return 'slice D';
    }
}
