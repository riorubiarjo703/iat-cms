<?php

namespace App\Filament\Resources\DistrictPlaces\Pages;

use App\Filament\Resources\DistrictPlaces\DistrictPlaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDistrictPlace extends EditRecord
{
    protected static string $resource = DistrictPlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
