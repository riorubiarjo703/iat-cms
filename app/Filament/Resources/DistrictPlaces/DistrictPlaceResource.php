<?php

namespace App\Filament\Resources\DistrictPlaces;

use App\Filament\Support\LocaleTabs;
use App\Models\DistrictPlace;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DistrictPlaceResource extends Resource
{
    protected static ?string $model = DistrictPlace::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LocaleTabs::make(fn (string $locale) => [
                TextInput::make("title.$locale")
                    ->label('Title')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
                Textarea::make("caption.$locale")
                    ->label('Caption')
                    ->rows(2)
                    ->maxLength(255),
            ]),
            Section::make('Image & Position')->schema([
                FileUpload::make('image')
                    ->image()->disk('public')->directory('uploads/district')
                    ->visibility('public')->maxSize(5120),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
                Toggle::make('is_active')->label('Visible on the homepage')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')->disk('public')->imageHeight(56)->label('Image'),
                TextColumn::make('title')
                    ->label('Title')
                    ->getStateUsing(fn (DistrictPlace $record) => $record->t('title', 'en')),
                TextColumn::make('caption')
                    ->label('Caption')
                    ->getStateUsing(fn (DistrictPlace $record) => $record->t('caption', 'en')),
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
            'index' => Pages\ListDistrictPlaces::route('/'),
            'create' => Pages\CreateDistrictPlace::route('/create'),
            'edit' => Pages\EditDistrictPlace::route('/{record}/edit'),
        ];
    }
}
