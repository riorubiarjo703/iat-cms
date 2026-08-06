<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    public function getHeading(): string
    {
        return 'Roles Management';
    }

    public function getSubheading(): ?string
    {
        return 'Manage user roles and their permissions';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Role'),
        ];
    }
}
