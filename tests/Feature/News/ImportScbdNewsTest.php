<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportScbdNewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Faked network responses for a clean run against the source.
     *
     * Deliberately not registered in setUp(): Http::fake() calls stack rather
     * than replace, and the fake stub registered earliest wins any pattern
     * overlap. Tests that need to fake failure for a specific URL call
     * Http::fake() themselves instead of calling this helper, so their stub is
     * the only — and therefore first-matching — one in play.
     *
     * No test touches the network. Listing pages 2-4 come back empty, so the
     * run stops after the first.
     */
    private function fakeSuccessfulSource(): void
    {
        Http::fake([
            'scbd.com/menu/page/news?page=1' => Http::response(
                file_get_contents(base_path('tests/Fixtures/scbd-news/listing-page-1.html')),
            ),
            'scbd.com/menu/page/news*' => Http::response('<html><body></body></html>'),
            'scbd.com/menu/detail/news/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/scbd-news/detail-earth-hour.html')),
            ),
            'scbd.com/assets/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_it_imports_the_posts_from_the_listing(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame(4, BlogPost::count());
        $this->assertTrue(BlogPost::where('status', BlogPost::STATUS_PUBLISHED)->exists());
    }

    public function test_imported_posts_keep_their_real_dates(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $newest = BlogPost::orderByDesc('published_at')->first();

        $this->assertSame('2026-07-23', $newest->published_at->format('Y-m-d'));
    }

    public function test_it_creates_the_four_categories(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['Community', 'Corporate', 'Environment', 'Events'],
            BlogCategory::pluck('name')->sort()->values()->all(),
        );
    }

    public function test_every_imported_post_has_a_category(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame(0, BlogPost::whereNull('blog_category_id')->count());
    }

    public function test_covers_are_downloaded_and_stored_as_paths(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $post = BlogPost::whereNotNull('featured_image')->firstOrFail();

        $this->assertStringStartsWith('uploads/news/', $post->featured_image);
        Storage::disk('public')->assertExists($post->featured_image);
    }

    public function test_body_images_are_downloaded_and_embedded_in_the_content(): void
    {
        // The detail pages carry three or four photographs each, and the
        // layout has places for them — a two-up row and captioned figures.
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $post = BlogPost::where('content', 'like', '%scbd-prose-pair%')->first();

        $this->assertNotNull($post, 'No imported post embedded an image pair.');
        $this->assertStringContainsString('/storage/uploads/news/', $post->content);
        $this->assertStringContainsString('loading="lazy"', $post->content);
    }

    public function test_the_lead_paragraph_still_comes_first(): void
    {
        // The pair goes after the opening paragraph, not before it.
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $post = BlogPost::where('content', 'like', '%scbd-prose-pair%')->firstOrFail();

        $this->assertLessThan(
            strpos($post->content, 'scbd-prose-pair'),
            strpos($post->content, '<p>'),
            'The image pair should follow the lead paragraph.',
        );
    }

    public function test_running_it_twice_creates_no_duplicates(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();
        $first = BlogPost::count();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame($first, BlogPost::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, BlogPost::count());
        $this->assertSame(0, BlogCategory::count());
    }

    public function test_the_limit_option_is_honoured(): void
    {
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd', ['--limit' => 2])->assertSuccessful();

        $this->assertSame(2, BlogPost::count());
    }

    public function test_a_post_whose_detail_fetch_fails_is_skipped_without_aborting(): void
    {
        Http::fake([
            'scbd.com/menu/page/news?page=1' => Http::response(
                file_get_contents(base_path('tests/Fixtures/scbd-news/listing-page-1.html')),
            ),
            'scbd.com/menu/page/news*' => Http::response('<html><body></body></html>'),
            'scbd.com/menu/detail/news/*' => Http::response('', 500),
            'scbd.com/assets/*' => Http::response('fake-image-bytes'),
        ]);

        $this->artisan('news:import-scbd')->assertSuccessful();

        // The listing carries the title, date and cover, so a post survives a
        // failed detail fetch — it just has no body.
        $this->assertSame(4, BlogPost::count());
    }

    public function test_a_post_whose_image_fails_still_imports(): void
    {
        Http::fake([
            'scbd.com/menu/page/news?page=1' => Http::response(
                file_get_contents(base_path('tests/Fixtures/scbd-news/listing-page-1.html')),
            ),
            'scbd.com/menu/page/news*' => Http::response('<html><body></body></html>'),
            'scbd.com/menu/detail/news/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/scbd-news/detail-earth-hour.html')),
            ),
            'scbd.com/assets/*' => Http::response('', 404),
        ]);

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame(4, BlogPost::count());
        $this->assertSame(4, BlogPost::whereNull('featured_image')->count());
    }
}
