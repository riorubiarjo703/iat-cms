<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

/**
 * Named people in groups — commissioners, directors, and so on.
 *
 * Groups are part of the structure rather than separate blocks, so the whole
 * board reorders as one and a new group needs no code.
 */
class PeopleBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_people';
    }

    public static function name(): string
    {
        return 'People';
    }

    public static function icon(): string
    {
        return 'heroicon-o-user-group';
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
            Repeater::make('groups')
                ->label('Groups')
                ->schema([
                    TextInput::make('title')->label('Group title')->required()->maxLength(80),
                    Repeater::make('people')
                        ->label('People')
                        ->schema([
                            TextInput::make('name')->label('Name')->required()->maxLength(120),
                            TextInput::make('role')->label('Role')->maxLength(120),
                            FileUpload::make('photo')->label('Photo')->image()->directory('uploads/pages/people')->disk('public'),
                        ])
                        ->addActionLabel('Add a person')
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->collapsed()
                        ->default([]),
                ])
                ->addActionLabel('Add a group')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->default([]),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => [], 'groups' => []];
    }

    public static function translatableKeys(): array
    {
        return ['heading'];
    }
}
