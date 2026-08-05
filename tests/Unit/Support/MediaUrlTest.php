<?php

namespace Tests\Unit\Support;

use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Slimani\MediaManager\Models\File;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The media library writes real files. RefreshDatabase rolls the database
     * back but not the disk, so without a fake every run leaves orphaned
     * uploads and their conversions in the project's storage directory.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * A real 1x1 PNG. Arbitrary bytes will not do: the library generates
     * conversions on the way in, and an unreadable image throws there rather
     * than at the point under test.
     */
    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    private function libraryFile(string $name, string $fileName): File
    {
        $file = File::create(['name' => $name]);
        $file->addMediaFromString($this->png())->usingFileName($fileName)->toMediaCollection('default');

        return $file;
    }

    public function test_a_legacy_path_resolves_on_the_public_disk(): void
    {
        $url = MediaUrl::resolve('uploads/district/offices.jpg');

        // Asserted by shape rather than exact string: the host differs between
        // the real disk and the fake, and neither is what this is testing.
        $this->assertNotNull($url);
        $this->assertStringEndsWith('/uploads/district/offices.jpg', $url);
        $this->assertSame(
            Storage::disk('public')->url('uploads/district/offices.jpg'),
            $url,
        );
    }

    public function test_nothing_resolves_to_null(): void
    {
        $this->assertNull(MediaUrl::resolve(null));
        $this->assertNull(MediaUrl::resolve(''));
        $this->assertNull(MediaUrl::resolve('   '));
    }

    public function test_a_media_library_id_resolves_through_the_library(): void
    {
        $file = $this->libraryFile('offices', 'offices.png');

        $url = MediaUrl::resolve($file->id);

        $this->assertNotNull($url);
        $this->assertStringContainsString('offices.png', $url);
        // Resolved through the library, not read as a relative path.
        $this->assertStringNotContainsString('storage/'.$file->id.'?', (string) $url);
    }

    public function test_an_id_given_as_a_string_resolves_the_same_way(): void
    {
        $file = $this->libraryFile('clinic', 'clinic.png');

        $this->assertSame(MediaUrl::resolve($file->id), MediaUrl::resolve((string) $file->id));
    }

    /**
     * A field can point at a record an editor has since deleted. That must read
     * as "no image" so the view falls through to its placeholder, not as an
     * empty src which the browser re-requests against the current page.
     */
    public function test_a_missing_media_record_resolves_to_null(): void
    {
        $this->assertNull(MediaUrl::resolve(999999));
    }

    /**
     * The discriminator is the whole point of the migration working
     * incrementally: no stored value may be readable as both forms.
     */
    public function test_a_path_is_never_mistaken_for_an_id(): void
    {
        foreach (['uploads/pages/01ABC.jpg', '12345.jpg', 'uploads/12345', 'a1'] as $path) {
            $url = MediaUrl::resolve($path);

            $this->assertNotNull($url, "[{$path}] resolved to null");
            $this->assertStringContainsString($path, $url, "[{$path}] was not treated as a path");
        }
    }

    public function test_repeated_lookups_of_one_id_hit_the_database_once(): void
    {
        $file = $this->libraryFile('logo', 'logo.png');

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();

        MediaUrl::resolve($file->id);
        $afterFirst = count(\Illuminate\Support\Facades\DB::getQueryLog());

        MediaUrl::resolve($file->id);
        MediaUrl::resolve($file->id);

        $this->assertSame($afterFirst, count(\Illuminate\Support\Facades\DB::getQueryLog()));
    }
}
