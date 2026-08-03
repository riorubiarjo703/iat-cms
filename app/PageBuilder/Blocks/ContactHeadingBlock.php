<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;

/**
 * The oversized headline above the footer band. The band itself is page chrome
 * — it appears on every page — so this block owns only the headline that sits
 * above it on the homepage.
 */
class ContactHeadingBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_contact_heading';
    }

    public static function name(): string
    {
        return 'Contact headline';
    }

    public static function icon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                Textarea::make("heading.{$locale}")
                    ->label('Headline')
                    ->rows(2)
                    ->required(LocaleTabs::isFallback($locale)),
            ]),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => []];
    }

    public static function translatableKeys(): array
    {
        return ['heading'];
    }
}
