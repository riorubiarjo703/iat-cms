<?php

namespace App\Filament\Resources\CodeSnippets;

use App\Enums\SnippetPosition;
use App\Enums\SnippetType;
use App\Models\CodeSnippet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CodeSnippetResource extends Resource
{
    // Placement is owned by App\Filament\Navigation\AdminNavigation.
    protected static ?string $model = CodeSnippet::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Snippet Details')
                ->description('Configure where and how this code will be injected')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Google Analytics')
                        ->helperText('A descriptive name for this snippet'),

                    Select::make('type')
                        ->options(SnippetType::options())
                        ->default(SnippetType::Script->value)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->helperText('Script, Style, Meta tag, or HTML'),

                    Select::make('position')
                        ->options(SnippetPosition::options())
                        ->default(SnippetPosition::Head->value)
                        ->required()
                        ->selectablePlaceholder(false)
                        ->helperText(SnippetPosition::helperText()),

                    TextInput::make('priority')
                        ->numeric()
                        ->required()
                        ->default(10)
                        ->minValue(0)
                        ->maxValue(100)
                        ->helperText('Lower numbers load first (0-100)'),

                    Textarea::make('code')
                        ->required()
                        ->rows(8)
                        ->columnSpanFull()
                        ->placeholder('<script>...</script>')
                        ->extraInputAttributes(['class' => 'font-mono'])
                        ->helperText('Enter the full code including tags (e.g., <script>...</script>)'),

                    Textarea::make('description')
                        ->label('Description (Optional)')
                        ->rows(3)
                        ->columnSpanFull()
                        ->placeholder('Internal notes about this snippet...'),
                ]),

            Section::make()->schema([
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Enable this snippet immediately'),
            ]),

            Section::make()->schema([
                Toggle::make('skip_for_admins')
                    ->label("Don't load for staff/admins")
                    ->default(true)
                    ->helperText("Skip this snippet when an admin is logged in, so tracking scripts don't pollute analytics with staff sessions."),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (CodeSnippet $record): ?string => $record->description),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (SnippetType $state): string => $state->label()),

                TextColumn::make('position')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (SnippetPosition $state): string => $state->label()),

                TextColumn::make('priority')->sortable(),

                ToggleColumn::make('is_active')->label('Active'),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            // Position then priority is the order snippets actually fire, which
            // is the question this list exists to answer. Newest-first would
            // tell an editor nothing useful. The CASE expression comes from
            // SnippetPosition so document order has one source of truth.
            ->defaultSort(fn ($query) => $query->orderByRaw(
                SnippetPosition::orderByCaseSql()
            )->orderBy('priority'))
            ->emptyStateIcon('heroicon-o-code-bracket')
            ->emptyStateHeading('No snippets yet')
            ->emptyStateDescription('Add tracking codes, analytics, or custom scripts to your site.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label('Add Snippet'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCodeSnippets::route('/'),
            'create' => Pages\CreateCodeSnippet::route('/create'),
            'edit' => Pages\EditCodeSnippet::route('/{record}/edit'),
        ];
    }
}
