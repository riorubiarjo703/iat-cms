<?php

namespace App\PageBuilder;

abstract class BaseBlock implements BlockContract
{
    public const CATEGORY_SECTIONS = 'Sections';

    public const CATEGORY_CONTENT = 'Content';

    public const CATEGORY_MEDIA = 'Media';

    public static function icon(): string
    {
        return 'heroicon-o-square-3-stack-3d';
    }

    public static function category(): string
    {
        return self::CATEGORY_SECTIONS;
    }

    public static function defaultData(): array
    {
        return [];
    }

    /** Convention: app.page-builder.blocks.{type-with-dashes} */
    public static function renderView(): string
    {
        return 'partials.blocks.'.str_replace('_', '-', static::type());
    }

    public static function translatableKeys(): array
    {
        return [];
    }
}
