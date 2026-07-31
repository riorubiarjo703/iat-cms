<?php

namespace App\Filament\Resources\Facilities;

use App\Filament\Support\LocaleTabs;
use App\Models\Facility;
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

class FacilityResource extends Resource
{
    protected static ?string $model = Facility::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LocaleTabs::make(fn (string $locale) => [
                TextInput::make("title.$locale")
                    ->label('Title')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
                Textarea::make("body.$locale")
                    ->label('Body')
                    ->rows(4)
                    ->maxLength(1000),
            ]),
            Section::make('Image & Position')->schema([
                FileUpload::make('image')
                    ->image()->disk('public')->directory('uploads/facilities')
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
                    ->getStateUsing(fn (Facility $record) => $record->t('title', 'en')),
                TextColumn::make('body')
                    ->label('Body')
                    ->limit(60)
                    ->getStateUsing(fn (Facility $record) => $record->t('body', 'en')),
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
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }
}
