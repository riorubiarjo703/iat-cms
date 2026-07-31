<?php

namespace App\Filament\Resources\PublicMenuItems\Pages;

use App\Filament\Resources\PublicMenuItems\PublicMenuItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPublicMenuItem extends EditRecord
{
    protected static string $resource = PublicMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
