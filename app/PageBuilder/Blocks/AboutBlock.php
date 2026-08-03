<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class AboutBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_about';
    }

    public static function name(): string
    {
        return 'About + stats';
    }

    public static function icon(): string
    {
        return 'heroicon-o-identification';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                Textarea::make("body.{$locale}")->label('Body')->rows(4),
                TextInput::make("cta_label.{$locale}")->label('Button label')->maxLength(60),
                TextInput::make("badge_label.{$locale}")->label('Badge label')->maxLength(60),
                Textarea::make("badge_text.{$locale}")->label('Badge text')->rows(2),
            ]),
            TextInput::make('cta_url')->label('Button URL')->maxLength(255),
            FileUpload::make('image')->label('Image')->image()->directory('uploads/blocks')->disk('public'),
            Toggle::make('show_stats')
                ->label('Show the stats grid')
                ->default(true)
                ->helperText('Stats are managed under Content — this only controls whether they appear here.'),
        ];
    }

    public static function defaultData(): array
    {
        return [
            'eyebrow' => [], 'heading' => [], 'body' => [], 'cta_label' => [],
            'badge_label' => [], 'badge_text' => [],
            'cta_url' => null, 'image' => null, 'show_stats' => true,
        ];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'body', 'cta_label', 'badge_label', 'badge_text'];
    }
}
