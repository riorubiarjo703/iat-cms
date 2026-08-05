<?php

namespace App\PageBuilder;

/**
 * A page-builder block: a type string, a Filament form, default data and a
 * Blade view.
 *
 * Type strings are persistent identifiers stored in every page's payload.
 * Renaming one orphans the blocks already saved under the old name, so treat
 * them as permanent.
 */
interface BlockContract
{
    public static function type(): string;

    public static function name(): string;

    public static function icon(): string;

    public static function category(): string;

    /** @return array<int, mixed> Filament form components */
    public static function schema(): array;

    /** @return array<string, mixed> */
    public static function defaultData(): array;

    public static function renderView(): string;

    /**
     * Keys inside `data` whose values are `{locale: text}` maps. The structure
     * of a block is shared across languages; only these leaves vary, which is
     * what stops the three locales drifting into different layouts.
     *
     * @return array<int, string>
     */
    public static function translatableKeys(): array;
}
