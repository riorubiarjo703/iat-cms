<?php

namespace App\Filament\Resources\ContactMessages;

use App\Models\ContactMessage;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The enquiry inbox.
 *
 * Read-only by design: an enquiry is a record of what somebody sent, and an
 * editable one is no longer evidence of that. The only state anyone can change
 * is whether it has been read.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $modelLabel = 'enquiry';

    protected static ?string $pluralModelLabel = 'enquiries';

    // Placement is owned by App\Filament\Navigation\AdminNavigation.

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::query()->unread()->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Enquiry')->schema([
                TextEntry::make('name')->label('From'),
                TextEntry::make('email')->label('Email')->copyable()
                    ->url(fn (ContactMessage $record): string => "mailto:{$record->email}"),
                TextEntry::make('phone')->label('Phone')->placeholder('Not given'),
                TextEntry::make('subject')->label('Enquiry type')->placeholder('Not specified'),
                TextEntry::make('locale')->label('Written in')
                    ->formatStateUsing(fn (?string $state): string => SiteSetting::LOCALES[$state] ?? (string) $state)
                    ->helperText('Reply in this language where you can.'),
                TextEntry::make('created_at')->label('Received')->dateTime('j F Y, H:i'),
            ])->columns(2),

            Section::make('Message')->schema([
                TextEntry::make('message')->hiddenLabel()->prose(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Unread first, then newest. An inbox ordered purely by date buries
            // an unanswered enquiry as soon as newer ones arrive.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('read_at is null desc')
                ->orderByDesc('created_at'))
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-s-envelope')
                    ->trueColor('gray')
                    ->falseColor('danger')
                    ->tooltip(fn (ContactMessage $record): string => $record->isUnread() ? 'Unread' : 'Read'),
                TextColumn::make('name')->label('From')->searchable()
                    ->weight(fn (ContactMessage $record) => $record->isUnread() ? 'bold' : null),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('subject')->label('Type')->placeholder('—')->toggleable(),
                TextColumn::make('message')->limit(60)->wrap()->toggleable(),
                TextColumn::make('created_at')->label('Received')->since()->sortable()
                    ->tooltip(fn (ContactMessage $record): string => $record->created_at->format('j F Y, H:i')),
            ])
            ->filters([
                Filter::make('unread')
                    ->label('Unread only')
                    ->query(fn (Builder $query) => $query->unread()),
            ])
            ->recordActions([
                // Opening an enquiry marks it read, which is what "read" means.
                ViewAction::make()->after(fn (ContactMessage $record) => $record->markAsRead()),
                Action::make('toggleRead')
                    ->label(fn (ContactMessage $record): string => $record->isUnread() ? 'Mark read' : 'Mark unread')
                    ->icon('heroicon-o-envelope')
                    ->action(fn (ContactMessage $record) => $record->isUnread() ? $record->markAsRead() : $record->markAsUnread()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->emptyStateHeading('No enquiries yet')
            ->emptyStateDescription('Messages sent from the contact page arrive here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
        ];
    }
}
