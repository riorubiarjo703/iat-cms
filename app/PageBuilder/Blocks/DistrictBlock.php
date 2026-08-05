<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class DistrictBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_district';
    }

    public static function name(): string
    {
        return 'District';
    }

    public static function icon(): string
    {
        return 'heroicon-o-map';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                Textarea::make("body.{$locale}")->label('Body')->rows(3),
                TextInput::make("location_label.{$locale}")->label('Location panel label')->maxLength(60),
                TextInput::make("directions_label.{$locale}")->label('Directions button label')->maxLength(60),
            ]),
            TextInput::make('directions_url')->label('Directions URL')->maxLength(255),
        ];
    }

    public static function defaultData(): array
    {
        return ['eyebrow' => [], 'heading' => [], 'body' => [], 'location_label' => [], 'directions_label' => [], 'directions_url' => null];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'body', 'location_label', 'directions_label'];
    }
}
