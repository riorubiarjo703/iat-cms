<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Slimani\MediaManager\Models\File;
use Throwable;

/**
 * Resolves a stored image reference to a URL, whichever form it is in.
 *
 * Image fields on this site hold a path on the public disk — "uploads/district/
 * offices.jpg" — written there by Filament's plain FileUpload. The media
 * manager stores something different: a media-file id, with the file itself
 * held by spatie/laravel-medialibrary.
 *
 * Migrating every field at once would mean changing a dozen forms, forty-odd
 * stored values and every view in one step, with no working state in between.
 * Reading through here instead means a field can move to the media library on
 * its own, while every other field keeps its path — the view no longer knows
 * or cares which it was handed.
 *
 * Once no legacy paths remain, the path branch can go.
 */
final class MediaUrl
{
    /**
     * A URL for the stored reference, or null when there is nothing to show.
     *
     * Null rather than an empty string, so a caller can guard on it: a missing
     * media record must not render as `<img src="">`, which browsers resolve
     * against the current page and re-request.
     */
    public static function resolve(mixed $value, string $conversion = ''): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (self::isLibraryId($value)) {
            return self::fromLibrary((int) $value, $conversion);
        }

        return Storage::disk('public')->url((string) $value);
    }

    /**
     * Media-library ids are integers; legacy values are paths.
     *
     * A path always carries a directory separator or an extension, so it can
     * never be read as an id — there is no value that could be either.
     */
    private static function isLibraryId(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        return is_string($value) && $value !== '' && ctype_digit($value);
    }

    /**
     * Memoised per request: one page renders the same logo in the header and
     * the footer, and a listing resolves a reference per row. Without this each
     * of those is its own query.
     */
    private static function fromLibrary(int $id, string $conversion): ?string
    {
        return RequestCache::remember("media-url.{$id}.{$conversion}", static function () use ($id, $conversion): ?string {
            $file = File::query()->find($id);

            if ($file === null) {
                return null;
            }

            try {
                return $file->getUrl($conversion) ?: null;
            } catch (Throwable) {
                // A record whose conversion has not been generated yet, or
                // whose underlying file is gone, must not take the page down —
                // conversions are queued, so "not ready" is a normal state.
                return null;
            }
        });
    }
}
