<?php

namespace Tests\Feature\Support;

use App\Support\MediaReference;
use App\Support\RequestCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Slimani\MediaManager\Models\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Image fields hold either a media-library id or a legacy "uploads/..." path;
 * MediaUrl reads both. The admin's picker does not: it hands whatever it finds
 * to File::find(), and Postgres rejects a path as a bigint — which took the
 * whole page down with a 500. Everything reaching the picker comes through
 * here first.
 */
class MediaReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RequestCache::flush('media-id.');
    }

    private function imported(string $legacyPath): File
    {
        $file = File::create(['name' => pathinfo($legacyPath, PATHINFO_FILENAME)]);

        Media::create([
            'model_type' => File::class,
            'model_id' => $file->id,
            'collection_name' => 'default',
            'name' => $file->name,
            'file_name' => basename($legacyPath),
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1,
            'manipulations' => [],
            'custom_properties' => ['legacy_path' => $legacyPath],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        return $file;
    }

    public function test_a_numeric_id_passes_through(): void
    {
        $this->assertSame('42', MediaReference::toLibraryId('42'));
    }

    public function test_an_integer_id_passes_through(): void
    {
        $this->assertSame('42', MediaReference::toLibraryId(42));
    }

    public function test_a_legacy_path_resolves_to_the_file_imported_from_it(): void
    {
        $file = $this->imported('uploads/pages/profile-hero.jpg');

        $this->assertSame((string) $file->id, MediaReference::toLibraryId('uploads/pages/profile-hero.jpg'));
    }

    public function test_a_path_that_was_never_imported_resolves_to_nothing(): void
    {
        // Null, not the path: handing the path back is what caused the crash.
        $this->assertNull(MediaReference::toLibraryId('uploads/pages/never-existed.jpg'));
    }

    public function test_a_blank_value_resolves_to_nothing(): void
    {
        $this->assertNull(MediaReference::toLibraryId(null));
        $this->assertNull(MediaReference::toLibraryId(''));
    }

    public function test_nothing_it_returns_is_ever_a_path(): void
    {
        // The one property the picker depends on: whatever comes back is
        // something File::find() can be handed without Postgres objecting.
        $this->imported('uploads/pages/profile-hero.jpg');

        $values = ['42', 42, 'uploads/pages/profile-hero.jpg', 'uploads/pages/never-existed.jpg', null, ''];

        foreach ($values as $value) {
            $resolved = MediaReference::toLibraryId($value);

            $this->assertTrue(
                $resolved === null || ctype_digit($resolved),
                'Resolved '.var_export($value, true).' to '.var_export($resolved, true).', which is not an id',
            );
        }
    }
}
