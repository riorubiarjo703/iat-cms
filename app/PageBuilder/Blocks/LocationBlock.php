<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class LocationBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_location';
    }

    public static function name(): string
    {
        return 'Location & access';
    }

    public static function icon(): string
    {
        return 'heroicon-o-map';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                TextInput::make("address_heading.{$locale}")->label('Address heading')->maxLength(60),
                Textarea::make("address.{$locale}")
                    ->label('Address')
                    ->helperText('Leave blank to use the address from Site settings.')
                    ->rows(4),
                TextInput::make("contact_heading.{$locale}")->label('Contact heading')->maxLength(60),
                Textarea::make("contact.{$locale}")
                    ->label('Contact')
                    ->helperText('Leave blank to use the phone number from Site settings.')
                    ->rows(3),
                TextInput::make("access_heading.{$locale}")->label('Getting here heading')->maxLength(60),
            ]),

            // Not translatable: each row is a label plus a place name, and the
            // whole list would otherwise be repeated three times over.
            Repeater::make('access')
                ->label('Getting here')
                ->schema([
                    TextInput::make('label')->label('Label')->required()->maxLength(40),
                    TextInput::make('text')->label('Detail')->required()->maxLength(200),
                ])
                ->addActionLabel('Add a way in')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->columns(2)
                ->default([]),

            Section::make('Map')->schema([
                TextInput::make('map_embed_url')
                    ->label('Google Maps embed URL')
                    ->url()
                    ->helperText('From Google Maps → Share → Embed a map → copy the src of the iframe. Leave blank to hide the map.')
                    ->maxLength(2000),
                Repeater::make('facts')
                    ->label('Facts beneath the map')
                    ->schema([
                        TextInput::make('label')->label('Label')->required()->maxLength(60),
                        TextInput::make('value')->label('Value')->required()->maxLength(40),
                    ])
                    ->addActionLabel('Add a fact')
                    ->reorderable()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->columns(2)
                    ->default([]),
            ]),
        ];
    }

    public static function defaultData(): array
    {
        return [
            'eyebrow' => [],
            'heading' => [],
            'address_heading' => [],
            'address' => [],
            'contact_heading' => [],
            'contact' => [],
            'access_heading' => [],
            'access' => [],
            'map_embed_url' => null,
            'facts' => [],
        ];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'address_heading', 'address', 'contact_heading', 'contact', 'access_heading'];
    }
}
