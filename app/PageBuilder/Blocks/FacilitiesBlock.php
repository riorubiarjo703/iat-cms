<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class FacilitiesBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_facilities';
    }

    public static function name(): string
    {
        return 'Facilities';
    }

    public static function icon(): string
    {
        return 'heroicon-o-building-office-2';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                Textarea::make("body.{$locale}")->label('Body')->rows(3),
            ]),
        ];
    }

    public static function defaultData(): array
    {
        return ['eyebrow' => [], 'heading' => [], 'body' => []];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'body'];
    }
}
