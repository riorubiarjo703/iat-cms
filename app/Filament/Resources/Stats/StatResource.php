<?php

namespace App\Filament\Resources\Stats;

use App\Enums\StatFormat;
use App\Filament\Support\LocaleTabs;
use App\Models\Stat;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatResource extends Resource
{
    protected static ?string $model = Stat::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LocaleTabs::make(fn (string $locale) => [
                TextInput::make("label.$locale")
                    ->label('Label')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
            ]),
            Section::make('Value')->schema([
                TextInput::make('value')
                    ->label('Counts up to')
                    ->numeric()
                    ->required()
                    ->default(0),
                TextInput::make('suffix')
                    ->label('Suffix')
                    ->helperText('Appended after the number, e.g. /7 or %.')
                    ->maxLength(16),
                Select::make('format')
                    ->label('Number format')
                    ->options(StatFormat::options())
                    ->default(StatFormat::Thousands->value)
                    ->required()
                    ->helperText('Use Plain for years so 1987 is not rendered as 1,987.'),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->getStateUsing(fn (Stat $record) => $record->t('label', 'en')),
                TextColumn::make('value')->label('Value')->numeric(),
                TextColumn::make('suffix')->label('Suffix'),
                TextColumn::make('format')
                    ->label('Format')
                    ->getStateUsing(fn (Stat $record) => $record->format->label()),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStats::route('/'),
            'create' => Pages\CreateStat::route('/create'),
            'edit' => Pages\EditStat::route('/{record}/edit'),
        ];
    }
}
