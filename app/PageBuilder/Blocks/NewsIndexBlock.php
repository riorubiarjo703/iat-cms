<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * The News landing page's whole body.
 *
 * A block rather than a dedicated route so the page keeps its URL from the
 * ordinary page catch-all and its copy stays editable, like every other page
 * on this site. The posts themselves come from the blog tables and are
 * administered separately — nothing about them is configured here.
 */
class NewsIndexBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_news_index';
    }

    public static function name(): string
    {
        return 'News index';
    }

    public static function icon(): string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                TextInput::make("empty_text.{$locale}")->label('Text when there are no posts')->maxLength(160),
                TextInput::make("sidebar_heading.{$locale}")->label('Sidebar heading')->maxLength(60),
            ]),
            Toggle::make('show_filters')
                ->label('Show category filters')
                ->default(true),
            TextInput::make('sidebar_limit')
                ->label('Posts in the sidebar')
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->default(5),
        ];
    }

    public static function defaultData(): array
    {
        return [
            'eyebrow' => [],
            'heading' => [],
            'empty_text' => [],
            'sidebar_heading' => [],
            'show_filters' => true,
            'sidebar_limit' => 5,
        ];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'empty_text', 'sidebar_heading'];
    }
}
