<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * A chronological list of milestones.
 *
 * The year sits outside the translatable set: "1992-1993" reads the same in
 * every language, and duplicating it per locale invites them to drift.
 */
class TimelineBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_timeline';
    }

    public static function name(): string
    {
        return 'Timeline';
    }

    public static function icon(): string
    {
        return 'heroicon-o-clock';
    }

    public static function category(): string
    {
        return self::CATEGORY_CONTENT;
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("heading.{$locale}")->label('Heading')->maxLength(120),
            ]),
            Repeater::make('entries')
                ->label('Milestones')
                ->schema([
                    TextInput::make('year')->label('Year or period')->required()->maxLength(40),
                    TextInput::make('title')->label('Title')->required()->maxLength(160),
                    Textarea::make('body')->label('Description')->rows(3),
                    FileUpload::make('image')->label('Image')->image()->directory('uploads/pages/milestone')->disk('public'),
                ])
                ->addActionLabel('Add a milestone')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => trim(($state['year'] ?? '').' — '.($state['title'] ?? '')) ?: null)
                ->default([]),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => [], 'entries' => []];
    }

    public static function translatableKeys(): array
    {
        return ['heading'];
    }
}
