<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\TextInput;

class MarqueeBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_marquee';
    }

    public static function name(): string
    {
        return 'Marquee';
    }

    public static function icon(): string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("text.{$locale}")
                    ->label('Scrolling text')
                    ->required(LocaleTabs::isFallback($locale))
                    ->maxLength(255),
            ]),
        ];
    }

    public static function defaultData(): array
    {
        return ['text' => []];
    }

    public static function translatableKeys(): array
    {
        return ['text'];
    }
}
