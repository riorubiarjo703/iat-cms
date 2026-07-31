<?php

namespace App\Filament\Resources\PublicMenuItems\Pages;

use App\Filament\Resources\PublicMenuItems\PublicMenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicMenuItems extends ListRecords
{
    protected static string $resource = PublicMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
