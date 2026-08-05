<?php

namespace App\Enums;

/**
 * Where a snippet is injected.
 *
 * Cases are declared in document order, so `cases()` yields the order the
 * snippet list sorts by and no separate sort map has to be kept in step.
 */
enum SnippetPosition: string
{
    case Head = 'head';
    case BodyStart = 'body_start';
    case BodyEnd = 'body_end';

    public function label(): string
    {
        return match ($this) {
            self::Head => 'Head',
            self::BodyStart => 'Body Start',
            self::BodyEnd => 'Body End',
        };
    }

    /**
     * Options map for Filament `Select::options()`.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** Shown under the Position field; also the gist of the Help modal. */
    public static function helperText(): string
    {
        return 'Head: analytics, meta, CSS. Body Start: tracking pixels. Body End: chat widgets.';
    }
}
