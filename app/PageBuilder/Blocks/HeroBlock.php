<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\Filament\Support\MediaField;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class HeroBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_hero';
    }

    public static function name(): string
    {
        return 'Hero';
    }

    public static function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                Textarea::make("heading.{$locale}")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Line breaks split the heading across lines.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("subheading.{$locale}")->label('Subheading')->rows(3),
            ]),
            MediaField::image('image', 'Background image', 'blocks'),
            TextInput::make('location_tag')
                ->label('Location tag')
                ->helperText('Small label over the image. Leave blank to use the address from Site Settings.')
                ->maxLength(255),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => [], 'subheading' => [], 'image' => null, 'location_tag' => null];
    }

    public static function translatableKeys(): array
    {
        return ['heading', 'subheading'];
    }
}
