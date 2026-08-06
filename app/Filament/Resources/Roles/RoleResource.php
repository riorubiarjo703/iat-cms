<?php

namespace App\Filament\Resources\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalogue;
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

class RoleResource extends Resource
{
    // Placement is owned by App\Filament\Navigation\AdminNavigation.
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    /**
     * The panel gate. super_admin is the only role that guarantees a way
     * back into the panel, so its hold on this permission cannot be given up
     * from this form — see the CheckboxList below.
     */
    private const GATE_PERMISSION = 'admin.access';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('description')
                ->label('Description (Optional)')
                ->maxLength(255),

            CheckboxList::make('permissions')
                ->relationship('permissions', 'name')
                ->bulkToggleable()
                ->searchable()
                ->descriptions(fn (): array => Permission::query()
                    ->pluck('name', 'id')
                    ->mapWithKeys(fn (string $name, int $id): array => [$id => PermissionCatalogue::groupLabel($name)])
                    ->all())
                ->disableOptionWhen(fn (string $label, CheckboxList $component): bool => $label === self::GATE_PERMISSION
                    && (bool) ($component->getRecord()?->is_system))
                // Disabling the option above narrows CheckboxList's automatic
                // `in()` validation to the options left enabled — without this,
                // saving a system role unchanged would fail validation, because
                // its pre-filled state still carries the now-"invalid" gate
                // permission. This restores the full option set as valid.
                ->in(fn (): array => Permission::query()->pluck('id')->map(fn (int $id): string => (string) $id)->all())
                ->saveRelationshipsUsing(function (CheckboxList $component): void {
                    $record = $component->getRecord();
                    $relationship = $component->getRelationship();

                    $state = collect($component->getState() ?? [])
                        ->map(fn (mixed $id): string => (string) $id);

                    // The disabled checkbox stops a click in the browser, but not
                    // a submitted payload that simply omits the value — which is
                    // exactly what an unchecked box produces. This is the actual
                    // guarantee; the disabled checkbox is only the visible half.
                    if ($record->is_system) {
                        $gateId = (string) Permission::query()->where('name', self::GATE_PERMISSION)->value('id');
                        $state = $state->push($gateId)->unique();
                    }

                    $recordsToDetach = $relationship->getResults()
                        ->pluck($relationship->getRelatedKeyName())
                        ->map(fn (mixed $id): string => (string) $id)
                        ->diff($state);

                    if ($recordsToDetach->isNotEmpty()) {
                        $relationship->detach($recordsToDetach->all());
                    }

                    $relationship->sync($state->all(), detaching: false);
                    $record->unsetRelation('permissions');
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Counts and the permission chips both need data beyond the role
            // row itself; doing it here means one query per page load rather
            // than two extra queries per row.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['permissions', 'users'])
                ->with('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('is_system')
                    ->label('System')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (bool $state): ?string => $state ? 'System' : null),

                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('permissions.name')
                    ->label('')
                    ->badge()
                    ->limitList(3),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->icon('heroicon-o-users'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date(),
            ])
            ->defaultSort('name')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    // A system role losing its own row here would strip the
                    // only route back into the panel for whoever holds it.
                    DeleteAction::make()->hidden(fn (Role $record): bool => $record->is_system),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRoles::route('/'),
        ];
    }
}
