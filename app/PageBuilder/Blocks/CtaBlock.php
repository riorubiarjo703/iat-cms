<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class CtaBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_cta';
    }

    public static function name(): string
    {
        return 'Call to action';
    }

    public static function icon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                Textarea::make("body.{$locale}")->label('Body')->rows(3),
                TextInput::make("button_label.{$locale}")->label('Button label')->maxLength(60),
            ]),
            // Relative paths are the common case here ("/contact-us"), so this
            // is not url()-validated — that rule would reject them.
            TextInput::make('button_url')
                ->label('Button link')
                ->helperText('A path such as /contact-us, or a full URL.')
                ->maxLength(2000),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => [], 'body' => [], 'button_label' => [], 'button_url' => '/contact-us'];
    }

    public static function translatableKeys(): array
    {
        return ['heading', 'body', 'button_label'];
    }
}
