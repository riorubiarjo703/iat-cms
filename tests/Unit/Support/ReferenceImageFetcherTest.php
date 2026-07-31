<?php

namespace Tests\Unit\Support;

use App\Support\ReferenceImageFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReferenceImageFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_it_declares_all_nine_reference_slots(): void
    {
        $this->assertCount(9, ReferenceImageFetcher::SOURCES);
        $this->assertArrayHasKey('hero1', ReferenceImageFetcher::SOURCES);
        $this->assertArrayHasKey('transport', ReferenceImageFetcher::SOURCES);
    }

    public function test_it_stores_a_downloaded_image_and_returns_its_path(): void
    {
        Http::fake([
            'scbd.com/*' => Http::response('binary-image-bytes', 200),
        ]);

        $path = (new ReferenceImageFetcher)->fetch('hero1', 'uploads/homepage');

        $this->assertSame('uploads/homepage/hero1.jpg', $path);
        Storage::disk('public')->assertExists('uploads/homepage/hero1.jpg');
        $this->assertSame('binary-image-bytes', Storage::disk('public')->get($path));
    }

    public function test_it_preserves_the_png_extension_from_the_source_url(): void
    {
        Http::fake(['scbd.com/*' => Http::response('png-bytes', 200)]);

        $path = (new ReferenceImageFetcher)->fetch('publicrealm', 'uploads/district');

        $this->assertSame('uploads/district/publicrealm.png', $path);
    }

    public function test_it_returns_null_for_an_unknown_slot(): void
    {
        Http::fake();

        $this->assertNull((new ReferenceImageFetcher)->fetch('nope', 'uploads/x'));
        Http::assertNothingSent();
    }

    public function test_it_returns_null_when_the_request_fails(): void
    {
        Http::fake(['scbd.com/*' => Http::response('<html><body>404 Not Found</body></html>', 404)]);

        $this->assertNull((new ReferenceImageFetcher)->fetch('clinic', 'uploads/facilities'));
        Storage::disk('public')->assertMissing('uploads/facilities/clinic.jpg');
    }

    public function test_it_returns_null_when_the_server_errors(): void
    {
        Http::fake(['scbd.com/*' => Http::response('<html><body>500 Server Error</body></html>', 500)]);

        $this->assertNull((new ReferenceImageFetcher)->fetch('hospitality', 'uploads/facilities'));
        Storage::disk('public')->assertMissing('uploads/facilities/hospitality.jpg');
    }

    public function test_it_returns_null_when_the_connection_throws(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->assertNull((new ReferenceImageFetcher)->fetch('clinic', 'uploads/facilities'));
    }

    public function test_it_returns_null_on_an_empty_body(): void
    {
        Http::fake(['scbd.com/*' => Http::response('', 200)]);

        $this->assertNull((new ReferenceImageFetcher)->fetch('security', 'uploads/facilities'));
    }

    public function test_it_does_not_redownload_an_existing_file(): void
    {
        Storage::disk('public')->put('uploads/homepage/hero1.jpg', 'already-here');
        Http::fake();

        $path = (new ReferenceImageFetcher)->fetch('hero1', 'uploads/homepage');

        $this->assertSame('uploads/homepage/hero1.jpg', $path);
        $this->assertSame('already-here', Storage::disk('public')->get($path));
        Http::assertNothingSent();
    }
}
