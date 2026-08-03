<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Support\LocaleTabs;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')
                ->schema([
                    Select::make('type')
                        ->label('Page type')
                        ->options(Page::types())
                        ->default(Page::TYPE_SIMPLE)
                        ->required()
                        ->live()
                        ->helperText(fn ($state): string => $state === Page::TYPE_BUILDER
                            ? 'Composed from blocks on the builder screen.'
                            : 'A single rich-text body — good for policies, about pages and the like.'),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('The page address, e.g. /about-us')
                        // Generated from the English title on create only:
                        // changing it later would break links already published.
                        ->dehydrateStateUsing(fn (?string $state, $get): string => filled($state)
                            ? Str::slug($state)
                            : Page::uniqueSlug((string) ($get('title.en') ?: 'page'))),

                    Select::make('status')
                        ->options([
                            Page::STATUS_DRAFT => 'Draft',
                            Page::STATUS_PUBLISHED => 'Published',
                        ])
                        ->default(Page::STATUS_DRAFT)
                        ->required(),

                    DateTimePicker::make('published_at')
                        ->label('Publish at')
                        ->helperText('Leave empty to publish immediately once status is Published.'),
                ])
                ->columns(2),

            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("title.{$locale}")
                    ->label('Title')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),

                RichEditor::make("content.{$locale}")
                    ->label('Body')
                    // Only shown for standard pages; a builder page's text lives
                    // in its blocks, and two editable bodies would compete.
                    ->visible(fn ($get): bool => $get('type') !== Page::TYPE_BUILDER)
                    ->columnSpanFull(),

                TextInput::make("seo_title.{$locale}")
                    ->label('SEO title')
                    ->maxLength(255)
                    ->helperText('Falls back to the page title.'),

                Textarea::make("seo_description.{$locale}")
                    ->label('SEO description')
                    ->rows(2)
                    ->maxLength(320),
            ], 'Content'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->state(fn (Page $record): string => $record->t('title') ?: '(untitled)')
                    ->searchable(query: fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
                    ->sortable(),

                TextColumn::make('slug')->searchable()->color('gray'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Page::types()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === Page::TYPE_BUILDER ? 'info' : 'gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === Page::STATUS_PUBLISHED ? 'success' : 'warning'),

                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(Page::types()),
                SelectFilter::make('status')->options([
                    Page::STATUS_DRAFT => 'Draft',
                    Page::STATUS_PUBLISHED => 'Published',
                ]),
            ])
            ->recordActions([
                // Only builder pages have blocks to arrange, so the action is
                // absent rather than disabled on a standard page.
                Action::make('build')
                    ->label('Build')
                    ->icon('heroicon-o-squares-2x2')
                    ->visible(fn (Page $record): bool => $record->usesBuilder())
                    ->url(fn (Page $record): string => \App\Filament\Pages\BuildPage::getUrl(['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('updated_at', 'desc');
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['slug'];
    }
}
