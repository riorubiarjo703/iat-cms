<?php

namespace App\Filament\Resources\Permissions;

use App\Models\Permission;
use App\Models\Role;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PermissionResource extends Resource
{
    // Placement is owned by App\Filament\Navigation\AdminNavigation.
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    /**
     * The only role that guarantees a way back into the panel. This form
     * cannot be used to give up super_admin's hold on a system permission —
     * mirrors RoleResource's protection of admin.access, in the other
     * direction: that form guards a system ROLE's hold on the gate
     * permission, this one guards the gate PERMISSION's hold on the system
     * role.
     */
    private const PROTECTED_ROLE = 'super_admin';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->placeholder('e.g., manage.users')
                ->disabled(fn (?Model $record): bool => (bool) $record?->is_system)
                // A disabled field is still submittable by a crafted payload —
                // dehydrated(false) is what actually stops a rename reaching
                // the database, not the disabled attribute in the browser.
                ->dehydrated(fn (?Model $record): bool => ! $record?->is_system),

            CheckboxList::make('roles')
                ->relationship('roles', 'name')
                ->bulkToggleable()
                ->disableOptionWhen(fn (string $label, CheckboxList $component): bool => $label === self::PROTECTED_ROLE
                    && (bool) ($component->getRecord()?->is_system))
                // Same reason as RoleResource's matching ->in() override:
                // disabling the option above narrows the automatic `in()`
                // validation to the options left enabled, which would then
                // reject the pre-filled super_admin value on an otherwise
                // unchanged save.
                ->in(fn (): array => Role::query()->pluck('id')->map(fn (int $id): string => (string) $id)->all())
                ->saveRelationshipsUsing(function (CheckboxList $component): void {
                    $record = $component->getRecord();
                    $relationship = $component->getRelationship();

                    $state = collect($component->getState() ?? [])
                        ->map(fn (mixed $id): string => (string) $id);

                    // The disabled checkbox stops a click in the browser, but
                    // not a submitted payload that simply omits the value —
                    // exactly what an unchecked box produces. This is the
                    // actual guarantee; the disabled checkbox is only the
                    // visible half.
                    // Resolved by is_system, not by name. Looking the role up by
                    // literal would return nothing once it had been renamed —
                    // renaming is allowed, and is why the column exists — which
                    // would both drop this protection silently and push an empty
                    // id into sync(), failing the foreign key.
                    if ($record->is_system) {
                        $state = $state
                            ->concat(Role::query()->where('is_system', true)->pluck('id')->map(strval(...)))
                            ->unique();
                    }

                    $recordsToDetach = $relationship->getResults()
                        ->pluck($relationship->getRelatedKeyName())
                        ->map(fn (mixed $id): string => (string) $id)
                        ->diff($state);

                    if ($recordsToDetach->isNotEmpty()) {
                        $relationship->detach($recordsToDetach->all());
                    }

                    $relationship->sync($state->all(), detaching: false);
                    $record->unsetRelation('roles');
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // The role chips need data beyond the permission row itself; doing
            // it here means one query per page load rather than one per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount('roles')
                ->with('roles'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('is_system')
                    ->label('System')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (bool $state): ?string => $state ? 'System' : null),

                TextColumn::make('roles_count')
                    ->label('Roles')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('roles.name')
                    ->label('')
                    ->badge()
                    ->limitList(3),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date(),
            ])
            ->defaultSort('name')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    // admin.access is the panel gate; deleting it here locks
                    // every account out for good. is_system marks that (and
                    // any future) permission no admin should be able to remove.
                    DeleteAction::make()->hidden(fn (Permission $record): bool => $record->is_system),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePermissions::route('/'),
        ];
    }
}
