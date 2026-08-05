<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;

class PlacesBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_places';
    }

    public static function name(): string
    {
        return 'Places of interest';
    }

    public static function icon(): string
    {
        return 'heroicon-o-map-pin';
    }

    /**
     * Only the section's own heading is edited here. The places themselves are
     * the District places records, so that the homepage and this page cannot
     * drift apart.
     */
    public static function schema(): array
    {
        return [
            Text::make('The places come from District places under Content — this block sets the section heading and renders them as full-width rows.'),
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
