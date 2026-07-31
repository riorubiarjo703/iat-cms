<?php

namespace App\Filament\Resources\PublicMenuItems;

use App\Filament\Support\LocaleTabs;
use App\Models\PublicMenuItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicMenuItemResource extends Resource
{
    protected static ?string $model = PublicMenuItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

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
            Section::make('Destination & Position')->schema([
                TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->default('#')
                    ->helperText('An anchor such as #about scrolls smoothly; a path such as /blogs navigates.')
                    ->maxLength(255),
                Select::make('target')
                    ->label('Opens in')
                    ->options(['_self' => 'Same tab', '_blank' => 'New tab'])
                    ->default('_self')
                    ->required(),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
                Toggle::make('is_active')->label('Visible')->default(true),
                Toggle::make('is_cta')
                    ->label('Render as the header call-to-action button')
                    ->helperText('Only one item should carry this. The first one wins.')
                    ->default(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->getStateUsing(fn (PublicMenuItem $record) => $record->t('label', 'en')),
                TextColumn::make('url')->label('URL'),
                IconColumn::make('is_cta')->boolean()->label('CTA'),
                IconColumn::make('is_active')->boolean()->label('Visible'),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublicMenuItems::route('/'),
            'create' => Pages\CreatePublicMenuItem::route('/create'),
            'edit' => Pages\EditPublicMenuItem::route('/{record}/edit'),
        ];
    }
}
