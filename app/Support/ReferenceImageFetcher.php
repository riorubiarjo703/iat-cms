<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Downloads the nine photographs used by the SCBD reference design.
 *
 * Seeding must never hard-fail because a third-party host is unreachable, so
 * every failure path returns null and the caller leaves that image unset.
 */
class ReferenceImageFetcher
{
    /**
     * Slot name => source URL. Slot names match the `data-src` values in the
     * reference markup.
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        'hero1' => 'https://scbd.com/assets/images/slideshow/slider_1_700.jpg-1707185523.jpg',
        'towers' => 'https://scbd.com/assets/images/slideshow/slider_2_700.jpg-1707185536.jpg',
        'offices' => 'https://scbd.com/assets/images/slideshow/slider_3_700.jpg-1707185550.jpg',
        'hospitality' => 'https://scbd.com/assets/images/facilities/fasilitas6.jpg-1707157253.jpg',
        'publicrealm' => 'https://scbd.com/assets/images/facilities/fasilitas1.png-1707156296.png',
        'fireservice' => 'https://scbd.com/assets/images/facilities/fasilitas_damkar.jpg-1707156741.jpg',
        'clinic' => 'https://scbd.com/assets/images/facilities/fasilitas_klinik.jpg-1707156741.jpg',
        'security' => 'https://scbd.com/assets/images/facilities/fasilitas3.png-1707156925.png',
        'transport' => 'https://scbd.com/assets/images/facilities/fasilitas4.png-1707156055.png',
    ];

    public function fetch(string $slot, string $directory): ?string
    {
        $source = self::SOURCES[$slot] ?? null;

        if ($source === null) {
            Log::warning('Unknown reference image slot.', ['slot' => $slot]);

            return null;
        }

        $path = rtrim($directory, '/').'/'.$slot.'.'.$this->extensionFor($source);
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        try {
            $response = Http::timeout(20)->get($source);
        } catch (ConnectionException|Throwable $exception) {
            Log::warning('Reference image download failed.', [
                'slot' => $slot,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed() || $response->body() === '') {
            Log::warning('Reference image download returned no usable body.', [
                'slot' => $slot,
                'status' => $response->status(),
            ]);

            return null;
        }

        $disk->put($path, $response->body());

        return $path;
    }

    /**
     * The source filenames end in a real extension after a cache-busting
     * suffix, e.g. `fasilitas1.png-1707156296.png`, so the trailing extension
     * is authoritative.
     */
    private function extensionFor(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
    }
}
