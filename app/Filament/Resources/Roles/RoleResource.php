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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
                ->unique(ignoreRecord: true)
                // Renaming super_admin makes isLastSuperAdmin() and
                // removeRole() (which resolve it by name) find nothing, and
                // the next seeder run then creates a second all-permissions
                // role via updateOrCreate(['name' => 'super_admin']).
                ->disabled(fn (?Model $record): bool => (bool) $record?->is_system)
                // A disabled field is still submittable by a crafted payload
                // — dehydrated(false) is what actually stops a rename
                // reaching the database, not the disabled attribute in the
                // browser.
                ->dehydrated(fn (?Model $record): bool => ! $record?->is_system),

            TextInput::make('description')
                ->label('Description (Optional)')
                ->maxLength(255),

            Section::make('Permissions')
                ->description('Select permissions to assign to this role')
                // A flat list of fifty-seven checkboxes is unusable — the
                // reason this section exists at all. One CheckboxList per
                // group, not one CheckboxList with group descriptions
                // beneath each option (the previous approach): a description
                // line is a caption, not a grouping — every one of the
                // forty-five-plus options still sat in a single undifferentiated
                // list a screen reader or a "select all" had no way to
                // partition. See self::permissionGroupFields() for how each
                // group's field independently saves its own slice of the
                // pivot without disturbing any other group's rows.
                ->schema(fn (): array => [
                    Grid::make(2)->schema(self::permissionGroupFields()),
                ]),
        ]);
    }

    /**
     * One CheckboxList per permission group (Posts, Pages, Admin, ...), each
     * bound to its own slice of ids rather than the whole table — so each
     * keeps its own Select All / Deselect All (bulkToggleable()), the
     * equivalent of the reference's single global toggle, scoped to a list
     * short enough that "select all in this group" is actually meaningful.
     *
     * Not backed by ->relationship(): that binds one field to the *entire*
     * pivot, which is exactly the single-flat-list shape being replaced.
     * Each field here hydrates and saves only the ids in its own group,
     * dehydrated(false) so Filament's own record-fill step (which only knows
     * real columns) never sees these keys — saveRelationshipsUsing runs
     * independently of dehydration, the same way the single CheckboxList's
     * relationship()-bound save already did.
     *
     * @return array<int, CheckboxList>
     */
    private static function permissionGroupFields(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->groupBy(fn (Permission $permission): string => PermissionCatalogue::groupLabel($permission->name))
            ->map(fn (Collection $permissions, string $group): CheckboxList => self::permissionGroupField($group, $permissions))
            ->values()
            ->all();
    }

    /** @param  Collection<int, Permission>  $permissions */
    private static function permissionGroupField(string $group, Collection $permissions): CheckboxList
    {
        $ids = $permissions->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $isGateGroup = $group === PermissionCatalogue::groupLabel(self::GATE_PERMISSION);

        $field = CheckboxList::make('permission_groups.'.Str::slug($group))
            ->label($group)
            ->options($permissions->pluck('name', 'id')->all())
            ->bulkToggleable()
            ->dehydrated(false)
            ->afterStateHydrated(function (CheckboxList $component, mixed $record) use ($ids): void {
                if (! $record instanceof Role || ! $record->exists) {
                    $component->state([]);

                    return;
                }

                $component->state(
                    $record->permissions()
                        ->whereIn('permissions.id', $ids)
                        ->pluck('permissions.id')
                        ->map(fn (mixed $id): string => (string) $id)
                        ->all(),
                );
            });

        if ($isGateGroup) {
            $field = $field
                ->disableOptionWhen(fn (string $label, CheckboxList $component): bool => $label === self::GATE_PERMISSION
                    && (bool) ($component->getRecord()?->is_system))
                // Same reason as before grouping: disabling the option above
                // narrows the automatic `in()` validation to the options left
                // enabled, which would then reject the pre-filled gate
                // permission on an otherwise unchanged save.
                ->in($ids);
        }

        return $field->saveRelationshipsUsing(function (CheckboxList $component) use ($ids, $isGateGroup): void {
            $record = $component->getRecord();

            $selected = collect($component->getState() ?? [])
                ->map(fn (mixed $id): string => (string) $id);

            // The disabled checkbox stops a click in the browser, but not a
            // submitted payload that simply omits the value — which is
            // exactly what an unchecked box produces. This is the actual
            // guarantee; the disabled checkbox is only the visible half.
            if ($isGateGroup && $record->is_system) {
                $gateId = (string) Permission::query()->where('name', self::GATE_PERMISSION)->value('id');
                $selected = $selected->push($gateId)->unique();
            }

            // Scoped to this group's own ids on both sides — detach() and
            // syncWithoutDetaching() only ever touch rows whose id is in
            // $ids, so this cannot clobber another group's field running its
            // own save moments earlier or later in the same request.
            $toDetach = collect($ids)->diff($selected);

            if ($toDetach->isNotEmpty()) {
                $record->permissions()->detach($toDetach->all());
            }

            if ($selected->isNotEmpty()) {
                $record->permissions()->syncWithoutDetaching($selected->all());
            }

            $record->unsetRelation('permissions');
        });
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
