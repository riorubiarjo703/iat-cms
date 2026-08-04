<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * The corporate-culture panel: a heading and a grid of named values.
 *
 * SCBD's values spell SUSTAIN, so the order matters and the repeater is
 * reorderable rather than sorted.
 */
class ValuesBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_values';
    }

    public static function name(): string
    {
        return 'Values panel';
    }

    public static function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public static function category(): string
    {
        return self::CATEGORY_CONTENT;
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("heading.{$locale}")->label('Heading')->maxLength(80),
                TextInput::make("acronym.{$locale}")->label('Acronym')->maxLength(40)
                    ->helperText('Optional. Shown large beside the values, e.g. SUSTAIN.'),
                Repeater::make("values.{$locale}")
                    ->label('Values')
                    ->schema([
                        TextInput::make('name')->label('Name')->required()->maxLength(60),
                        Textarea::make('description')->label('Description')->rows(2),
                    ])
                    ->addActionLabel('Add a value')
                    ->reorderable()
                    ->columns(1)
                    ->default([]),
            ]),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => [], 'acronym' => [], 'values' => []];
    }

    public static function translatableKeys(): array
    {
        return ['heading', 'acronym'];
    }
}
