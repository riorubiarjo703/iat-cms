<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class NewsBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_news';
    }

    public static function name(): string
    {
        return 'News list';
    }

    public static function icon(): string
    {
        return 'heroicon-o-newspaper';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                TextInput::make("cta_label.{$locale}")->label('Button label')->maxLength(60),
                TextInput::make("empty_text.{$locale}")->label('Text when there are no posts')->maxLength(160),
            ]),
            TextInput::make('limit')
                ->label('Posts to show')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->default(3),
        ];
    }

    public static function defaultData(): array
    {
        return ['eyebrow' => [], 'heading' => [], 'cta_label' => [], 'empty_text' => [], 'limit' => 3];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'cta_label', 'empty_text'];
    }
}
