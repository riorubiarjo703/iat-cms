<?php

namespace App\Support;

use Slimani\MediaManager\Models\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolves a stored image reference to a media-library id.
 *
 * The counterpart to MediaUrl, which answers the same question for a view.
 * Image fields here hold either a media-file id or a legacy "uploads/..." path,
 * and both have to keep working while the migration is in progress.
 *
 * A view can be relaxed about that, because it only needs a URL. The admin's
 * picker cannot: it hands the stored value straight to File::find(), and
 * Postgres rejects a path as a bigint rather than quietly returning nothing the
 * way SQLite does — which took the whole edit screen down with a 500. So
 * nothing reaches the picker without coming through here.
 *
 * Once media:migrate-uploads has run and no legacy paths remain, this becomes a
 * pass-through, and can go with MediaUrl's path branch.
 */
final class MediaReference
{
    /**
     * The media-library id for a stored reference, or null when there is none.
     *
     * Never the input: returning a path unchanged is exactly the crash this
     * exists to prevent, so an unrecognised value resolves to nothing and the
     * field simply shows empty.
     */
    public static function toLibraryId(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return self::importedFrom($value);
    }

    /**
     * The file that media:migrate-uploads created from this path, if it ran.
     *
     * Matched on the legacy_path it records, which is the same handle the
     * migration itself uses to recognise a file it has already imported.
     *
     * Memoised per request: a page of twelve portraits asks this twelve times.
     */
    private static function importedFrom(string $path): ?string
    {
        return RequestCache::remember("media-id.{$path}", static function () use ($path): ?string {
            $media = Media::query()
                ->where('model_type', File::class)
                ->whereJsonContains('custom_properties->legacy_path', $path)
                ->first();

            return $media ? (string) $media->model_id : null;
        });
    }
}
