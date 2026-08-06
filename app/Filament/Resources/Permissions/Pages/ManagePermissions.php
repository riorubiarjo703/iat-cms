<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Filament\Resources\Permissions\PermissionResource;
use App\Models\Permission;
use App\Models\Role;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;

    public function getHeading(): string
    {
        return 'Permissions Management';
    }

    public function getSubheading(): ?string
    {
        return 'Manage user permissions and their assignments';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Permission')
                // There is deliberately no Gate::before wildcard for
                // super_admin (see RolesAndPermissionsSeeder) — a wildcard
                // would make the Roles screen show a permission count with
                // no relationship to what the role can actually do. Without
                // this, a permission created here is one super_admin (and
                // any other is_system role) does not hold: an ability
                // nobody can exercise.
                ->after(function (Permission $record): void {
                    Role::query()->where('is_system', true)->get()
                        ->each(fn (Role $role): mixed => $role->givePermissionTo($record));
                }),
        ];
    }
}
