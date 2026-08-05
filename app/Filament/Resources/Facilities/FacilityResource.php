<?php

namespace App\Filament\Resources\Facilities;

use App\Filament\Support\LocaleTabs;
use App\Filament\Support\MediaField;
use App\Models\Facility;
use App\Support\MediaUrl;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                TextInput::make("eyebrow.$locale")
                    ->label('Eyebrow')
                    ->helperText('The kicker above the title, e.g. “24/7 Operations”.')
                    ->maxLength(60),
                Textarea::make("body.$locale")
                    ->label('Body')
                    ->rows(4)
                    ->maxLength(1000),
                TextInput::make("stat_label.$locale")
                    ->label('Statistic label')
                    ->helperText('e.g. “Team strength”.')
                    ->maxLength(120),
            ]),
            Section::make('Statistic')->schema([
                // Outside the locale tabs: see DistrictPlaceResource.
                TextInput::make('stat_value')
                    ->label('Statistic value')
                    ->helperText('e.g. “32 personnel”. Shown with the label above it.')
                    ->maxLength(60),
            ]),
            Section::make('Image & Position')->schema([
                MediaField::image('image', 'Image', 'facilities'),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
                Toggle::make('is_active')->label('Visible on the homepage')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->imageHeight(56)
                    // The column holds a media id, not a path, so the
                    // thumbnail is resolved rather than read off a disk.
                    // Forced absolute: ImageColumn only passes a state straight
                    // through when it validates as a URL, and treats a relative
                    // one as a disk path it then fails to find.
                    ->getStateUsing(function (Facility $record): ?string {
                        $url = MediaUrl::resolve($record->image);

                        return $url === null ? null : url($url);
                    }),
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
