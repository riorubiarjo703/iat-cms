<?php

namespace App\Support;

/**
 * The social networks Site Settings knows about, in display order with their
 * proper casing. Str::headline() renders "linkedin" as "Linkedin" and cannot
 * know the design calls Twitter "X / Twitter", so the labels are declared.
 */
final class SocialNetworks
{
    /** @return array<string, string> key => label, in display order */
    public static function all(): array
    {
        return [
            'facebook' => 'Facebook',
            'twitter' => 'X / Twitter',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
        ];
    }

    /**
     * Configured networks only, in declared order.
     *
     * @param  array<string, string|null>|null  $social
     * @return array<string, array{label: string, url: string}>
     */
    public static function configured(?array $social): array
    {
        $out = [];

        foreach (self::all() as $key => $label) {
            if (filled($social[$key] ?? null)) {
                $out[$key] = ['label' => $label, 'url' => $social[$key]];
            }
        }

        return $out;
    }
}
