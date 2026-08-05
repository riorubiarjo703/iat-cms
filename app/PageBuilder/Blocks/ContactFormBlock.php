<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class ContactFormBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_contact_form';
    }

    public static function name(): string
    {
        return 'Enquiry form';
    }

    public static function icon(): string
    {
        return 'heroicon-o-envelope';
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
                TextInput::make("intro.{$locale}")->label('Intro')->maxLength(400),
                TextInput::make("submit.{$locale}")->label('Button label')->maxLength(60),
                TextInput::make("success.{$locale}")->label('Confirmation message')->maxLength(300),
            ]),
            // Editor-configurable, which is why the stored subject is free text
            // rather than a database enum that would go stale when this changes.
            Repeater::make('subjects')
                ->label('Enquiry types')
                ->schema([TextInput::make('label')->label('Option')->required()->maxLength(120)])
                ->addActionLabel('Add an enquiry type')
                ->reorderable()
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                ->default([]),
        ];
    }

    public static function defaultData(): array
    {
        return ['heading' => [], 'intro' => [], 'submit' => [], 'success' => [], 'subjects' => []];
    }

    public static function translatableKeys(): array
    {
        return ['heading', 'intro', 'submit', 'success'];
    }
}
