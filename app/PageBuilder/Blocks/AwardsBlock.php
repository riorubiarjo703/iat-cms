<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\FileUpload;
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
                    FileUpload::make('image')->label('Certificate')->image()->directory('uploads/pages/awards')->disk('public'),
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
