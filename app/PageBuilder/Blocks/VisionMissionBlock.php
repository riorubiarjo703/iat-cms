<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\Filament\Support\MediaField;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class VisionMissionBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_vision_mission';
    }

    public static function name(): string
    {
        return 'Vision & mission';
    }

    public static function icon(): string
    {
        return 'heroicon-o-eye';
    }

    public static function category(): string
    {
        return self::CATEGORY_CONTENT;
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("vision_label.{$locale}")->label('Vision heading')->maxLength(60),
                Textarea::make("vision.{$locale}")->label('Vision')->rows(3),
                TextInput::make("mission_label.{$locale}")->label('Mission heading')->maxLength(60),
                // A repeater rather than one textarea: the mission is a list of
                // commitments, and the design numbers them.
                Repeater::make("mission.{$locale}")
                    ->label('Mission points')
                    ->simple(Textarea::make('text')->rows(2)->label('Point'))
                    ->addActionLabel('Add a mission point')
                    ->reorderable()
                    ->default([]),
            ]),
            MediaField::image('vision_image', 'Vision image', 'pages'),
            MediaField::image('mission_image', 'Mission image', 'pages'),
        ];
    }

    public static function defaultData(): array
    {
        return [
            'vision_label' => [], 'vision' => [],
            'mission_label' => [], 'mission' => [],
            'vision_image' => null, 'mission_image' => null,
        ];
    }

    /**
     * `mission` is a list per locale, not a string, so it is not published to
     * the switcher — BlockData::t would have nothing sensible to return. The
     * list re-renders on a full page load instead.
     */
    public static function translatableKeys(): array
    {
        return ['vision_label', 'vision', 'mission_label'];
    }
}
