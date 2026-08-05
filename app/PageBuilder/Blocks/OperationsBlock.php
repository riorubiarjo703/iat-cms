<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;

/**
 * A second presentation of the Facility records the homepage already shows.
 * The homepage stacks them as sticky cards; an interior page wants plain
 * alternating rows, so the layout differs while the content does not.
 */
class OperationsBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_operations';
    }

    public static function name(): string
    {
        return 'District facilities (rows)';
    }

    public static function icon(): string
    {
        return 'heroicon-o-wrench-screwdriver';
    }

    public static function schema(): array
    {
        return [
            Text::make('The facilities come from Facilities under Content — this block sets the section heading and renders them as full-width rows.'),
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
            ]),
        ];
    }

    public static function defaultData(): array
    {
        return ['eyebrow' => [], 'heading' => []];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading'];
    }
}
