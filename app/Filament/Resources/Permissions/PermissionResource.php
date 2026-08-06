<?php

namespace App\Filament\Resources\Permissions;

use App\Models\Permission;
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

class PermissionResource extends Resource
{
    // Placement is owned by App\Filament\Navigation\AdminNavigation.
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->placeholder('e.g., manage.users'),

            CheckboxList::make('roles')
                ->relationship('roles', 'name')
                ->bulkToggleable(),
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
