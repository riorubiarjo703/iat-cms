<?php

namespace App\Filament\Support;

use Slimani\MediaManager\Form\MediaPicker;

/**
 * The one way an image field is declared in this admin.
 *
 * Every image on the site is picked from the media library rather than
 * uploaded straight to disk, so that one library holds the assets, their alt
 * text and their captions, and the same photograph can be reused across pages
 * instead of being uploaded again per field.
 *
 * The field stores a media-file id. Views never read it directly — they go
 * through App\Support\MediaUrl, which resolves an id or a legacy path alike.
 */
final class MediaField
{
    /**
     * @param  string  $folder  Where files uploaded through this field land in
     *                          the library. Mirrors the old upload directories,
     *                          so the library opens on familiar groupings.
     */
    public static function image(string $name, string $label, string $folder): MediaPicker
    {
        return MediaPicker::make($name)
            ->label($label)
            ->acceptedFileTypes(['image/*'])
            ->directory($folder);
    }
}
