<?php

namespace App\Enums;

enum StatFormat: string
{
    /** Render the raw integer — the reference's `data-plain` behaviour, e.g. 45. */
    case Plain = 'plain';

    /** Render with locale thousands separators, e.g. 1,200. */
    case Thousands = 'thousands';

    public function label(): string
    {
        return match ($this) {
            self::Plain => 'Plain (45)',
            self::Thousands => 'Thousands separated (1,200)',
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
}
