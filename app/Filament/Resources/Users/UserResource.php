<?php

namespace App\Filament\Resources\Users;

use App\Exceptions\LastSuperAdminException;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    // Placement is owned by App\Filament\Navigation\AdminNavigation.
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                // Leaving the field blank on edit must not blank the password.
                ->dehydrated(fn (?string $state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(8)
                ->helperText('Leave blank when editing to keep the current password.'),

            self::rolesField(),
        ]);
    }

    /**
     * Editing a user only requires users.update; handing out roles is
     * granting permissions, a higher bar, gated on roles.manage.
     *
     * Not ->relationship('roles', 'name'): that saves by calling sync() on
     * the raw pivot, which never reaches User::assignRole()/removeRole()
     * below — the only paths that guard against demoting the installation's
     * last super_admin (see the LastSuperAdminException catch below and
     * App\Models\User).
     */
    private static function rolesField(): CheckboxList
    {
        return CheckboxList::make('roles')
            ->label('Roles')
            ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())
            ->visible(fn (): bool => auth()->user()?->can('roles.manage') ?? false)
            // Saved through saveRelationshipsUsing below, not through the
            // record's own mass-assignment — dehydrated(false) keeps this
            // key out of $data so it never reaches $record->fill()/update().
            ->dehydrated(false)
            ->afterStateHydrated(function (CheckboxList $component, mixed $record): void {
                if (! $record instanceof User || ! $record->exists) {
                    $component->state([]);

                    return;
                }

                $component->state(
                    $record->roles()
                        ->pluck('roles.id')
                        ->map(fn (mixed $id): string => (string) $id)
                        ->all(),
                );
            })
            ->saveRelationshipsUsing(function (CheckboxList $component): void {
                $record = $component->getRecord();

                // Filament already skips a hidden field's saveRelationships()
                // call (BelongsToModel::saveRelationships() checks
                // isHidden()), but that is the field's own implementation
                // detail, not a guarantee this application controls — the
                // same reason RoleResource's saveRelationshipsUsing does not
                // trust a disabled/hidden control alone to stop a crafted
                // payload. Checked again here.
                if (! (auth()->user()?->can('roles.manage') ?? false)) {
                    return;
                }

                $submitted = collect($component->getState() ?? [])
                    ->map(fn (mixed $id): string => (string) $id);

                $current = $record->roles()
                    ->pluck('roles.id')
                    ->map(fn (mixed $id): string => (string) $id);

                $toAssign = Role::query()
                    ->whereIn('id', $submitted->diff($current)->all())
                    ->pluck('name')
                    ->all();

                $toRemove = Role::query()
                    ->whereIn('id', $current->diff($submitted)->all())
                    ->pluck('name')
                    ->all();

                try {
                    // Guarded methods, not sync() — see the class docblock
                    // above self::rolesField(). assignRole() is unguarded
                    // (adding a role never strips the last super_admin of
                    // anything) but removeRole() throws
                    // LastSuperAdminException when this call would leave the
                    // installation with none.
                    if ($toAssign !== []) {
                        $record->assignRole($toAssign);
                    }

                    if ($toRemove !== []) {
                        $record->removeRole($toRemove);
                    }
                } catch (LastSuperAdminException $exception) {
                    // The exception's message is already written for a human
                    // reading a form error; reused verbatim rather than
                    // inventing a second wording.
                    $component->getLivewire()->addError($component->getStatePath(), $exception->getMessage());

                    // Halt (not a bare return) rolls back the transaction
                    // this save runs inside — undoing the assignRole() call
                    // above too, if one happened to run before the refused
                    // removeRole() — so a partial swap never lands.
                    throw (new Halt)->rollBackDatabaseTransaction();
                }

                $record->unsetRelation('roles');
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            // Roles badges need the relationship loaded; doing it here means
            // one query per page load rather than one per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Joined'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->name;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
