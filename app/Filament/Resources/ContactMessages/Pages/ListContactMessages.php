<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    // No create action: enquiries arrive from the contact page. A row typed in
    // here would look identical to one somebody actually sent.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
