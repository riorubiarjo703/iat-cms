<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\Filament\Support\MediaField;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class AwardsBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_awards';
    }

    public static function name(): string
    {
        return 'Awards & certifications';
    }

    public static function icon(): string
    {
        return 'heroicon-o-trophy';
    }

    public static function category(): string
    {
        return self::CATEGORY_CONTENT;
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("heading.{$locale}")->label('Heading')->maxLength(120),
            ]),
            Repeater::make('items')
                ->label('Awards and certifications')
                ->schema([
                    TextInput::make('title')->label('Title')->required()->maxLength(160),
                    TextInput::make('year')->label('Year')->maxLength(20),
                    // The index lists who awarded or certified each item, which
                    // is what distinguishes five ISO certificates from one
                    // another as text rather than as five near-identical scans.
                    // Optional: the row reads correctly without it, and no
                    // issuer is invented for existing entries.
                    TextInput::make('issuer')->label('Issued by')->maxLength(120)
                        ->helperText('Certification body or awarding organisation. Shown beside the title.'),
                    MediaField::image('image', 'Certificate', 'pages/awards'),
                ])
                ->addActionLabel('Add an award')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->default([]),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => [], 'items' => []];
    }

    public static function translatableKeys(): array
    {
        return ['heading'];
    }
}
