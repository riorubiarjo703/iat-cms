<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

/**
 * The opening band of an interior page: eyebrow, split heading, intro copy and
 * a full-width image.
 *
 * Deliberately generic rather than profile-specific — Milestone, Organisation
 * Structure and Awards open exactly the same way, and four near-identical
 * blocks would drift apart the first time one was restyled.
 */
class PageHeroBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_page_hero';
    }

    public static function name(): string
    {
        return 'Page hero';
    }

    public static function icon(): string
    {
        return 'heroicon-o-bars-arrow-up';
    }

    public static function category(): string
    {
        return self::CATEGORY_CONTENT;
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")
                    ->label('Heading')
                    ->rows(2)
                    ->helperText('Line breaks split the heading across lines.')
                    ->required(LocaleTabs::isFallback($locale)),
                Textarea::make("body.{$locale}")
                    ->label('Intro')
                    ->rows(8)
                    ->helperText('Blank lines separate paragraphs.'),
            ]),
            FileUpload::make('image')
                ->label('Image')
                ->image()
                ->directory('uploads/pages')
                ->disk('public'),
            TextInput::make('image_caption')->label('Image caption')->maxLength(120),
        ];
    }

    public static function defaultData(): array
    {
        return ['eyebrow' => [], 'heading' => [], 'body' => [], 'image' => null, 'image_caption' => null];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'body'];
    }
}
