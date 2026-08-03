<?php

namespace App\PageBuilder;

use App\Models\SiteSetting;
use Illuminate\Support\Str;

/**
 * Reading values out of a block's `data` bag.
 *
 * Every accessor defaults rather than throwing: a page persisted under an older
 * schema is read by newer code, so a missing key is expected, not exceptional.
 */
final class BlockData
{
    /**
     * A translatable leaf, with the same per-key English fallback the models
     * use — so a half-finished translation still renders coherent copy.
     *
     * @param  array<string, mixed>  $data
     */
    public static function t(array $data, string $key, ?string $locale = null): ?string
    {
        $value = $data[$key] ?? null;

        // Tolerates a plain string, which is what a block written before the
        // field became translatable would have stored.
        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $locale ??= app()->getLocale();

        if (filled($value[$locale] ?? null)) {
            return $value[$locale];
        }

        return filled($value[SiteSetting::FALLBACK_LOCALE] ?? null)
            ? $value[SiteSetting::FALLBACK_LOCALE]
            : null;
    }

    /** @param array<string, mixed> $data */
    public static function get(array $data, string $key, mixed $default = null): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * The i18n key a block's field is published under, so the client-side
     * language switcher can find it. Namespaced by block id, because two blocks
     * of the same type on one page would otherwise collide.
     */
    public static function i18nKey(string $blockId, string $field): string
    {
        return 'b'.Str::of($blockId)->replace(['block_', '-'], ['', '_'])->toString().'_'.$field;
    }
}
