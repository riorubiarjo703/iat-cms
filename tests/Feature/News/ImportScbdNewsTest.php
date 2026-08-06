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
     * A real 1x1 JPEG.
     *
     * The importer sniffs what it downloads before storing it, so the fakes
     * have to serve bytes that are actually an image — a placeholder string
     * would be refused, exactly as an attacker's would be.
     */
    private function imageBytes(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0'
            .'Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy'
            .'MjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQID'
            .'AAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlq'
            .'c3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3'
            .'+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEI'
            .'FEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImK'
            .'kpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3'
            .'+iiigD//2Q=='
        );
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
            'scbd.com/assets/*' => Http::response($this->imageBytes(), 200, ['Content-Type' => 'image/jpeg']),
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
            'scbd.com/assets/*' => Http::response($this->imageBytes(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->artisan('news:import-scbd')->assertSuccessful();

        // The listing carries the title, date and cover, so a post survives a
        // failed detail fetch — it just has no body. A count alone would not
        // show that: it stays at 4 whether the post imported bodyless or the
        // command wrote four rows from some other path entirely.
        $this->assertSame(4, BlogPost::count());

        $post = BlogPost::where('title', 'like', "%Children%Day%")->first();

        $this->assertNotNull($post, 'The post whose detail fetch failed was dropped rather than imported.');
        $this->assertSame('<p></p>', $post->content, 'A failed detail fetch should leave an empty body, not partial markup.');
        $this->assertSame(BlogPost::STATUS_PUBLISHED, $post->status);
        $this->assertSame('2026-07-23', $post->published_at->format('Y-m-d'));
        // The cover comes from the listing, which fetched fine.
        $this->assertNotNull($post->featured_image);
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

    // ---------------------------------------------------------------------
    // What the downloader is allowed to write
    //
    // Everything under uploads/ is served from this site's own origin, so a
    // file the importer stores is a file an attacker got onto our origin. The
    // URLs come out of somebody else's markup, which makes every one of them
    // attacker-controlled the moment the source is compromised or accepts a
    // user submission. Each test below removes exactly one guard's excuse.
    // ---------------------------------------------------------------------

    /**
     * A listing page carrying one post with the given cover URL.
     *
     * Built here rather than saved as a fixture because the whole point is to
     * hand the importer a URL the real source would never emit.
     */
    private function listingWithCover(string $cover, string $title = 'Hostile Cover Story'): string
    {
        return '<html><body><div class="niche-box-post">'
            .'<div style="background-image: url(\''.$cover.'\')"></div>'
            .'<p class="bd-day">23</p><p class="bd-month">Jul 2026</p>'
            .'<h2><a href="https://scbd.com/menu/detail/news/hostile">'.$title.'</a></h2>'
            .'</div></body></html>';
    }

    /**
     * @param  array<string, \Closure|\Illuminate\Http\Client\Response>  $extra  Stubs matched before the catch-all.
     */
    private function fakeSourceWithCover(string $cover, array $extra = [], string $title = 'Hostile Cover Story'): void
    {
        Http::fake($extra + [
            'scbd.com/menu/page/news?page=1' => Http::response($this->listingWithCover($cover, $title)),
            'scbd.com/menu/page/news*' => Http::response('<html><body></body></html>'),
            'scbd.com/menu/detail/news/*' => Http::response('<html><body><div class="col-md-9"><p>Story.</p></div></body></html>'),
            // Deliberately permissive: any image request that is NOT refused
            // outright succeeds, so a missing guard shows up as a stored file
            // rather than as a lucky 404.
            '*' => Http::response($this->imageBytes(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function storedFiles(): array
    {
        return Storage::disk('public')->allFiles();
    }

    public function test_an_image_on_another_host_is_never_fetched_or_stored(): void
    {
        // Not merely "not stored": fetching it at all is an SSRF whose response
        // this command would persist at a guessable public URL.
        $this->fakeSourceWithCover('https://evil.example/news/images/x.jpg');

        $this->artisan('news:import-scbd')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));

        $post = BlogPost::firstOrFail();

        $this->assertNull($post->featured_image);
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_lookalike_host_is_rejected(): void
    {
        // scbd.com.evil.example ends with the trusted name but is not it.
        $this->fakeSourceWithCover('https://scbd.com.evil.example/news/images/x.jpg');

        $this->artisan('news:import-scbd')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'evil.example'));
        $this->assertNull(BlogPost::firstOrFail()->featured_image);
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_php_extension_is_rejected(): void
    {
        // A .php file under the public root is remote code execution anywhere
        // the deploy maps .php to FPM.
        $this->fakeSourceWithCover('https://scbd.com/news/images/../../../evil.php');

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertNull(BlogPost::firstOrFail()->featured_image);
        $this->assertSame([], $this->storedFiles());
    }

    public function test_an_svg_extension_is_rejected(): void
    {
        // An SVG is a script host, served from our own origin.
        $this->fakeSourceWithCover('https://scbd.com/news/images/x.svg');

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertNull(BlogPost::firstOrFail()->featured_image);
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_response_that_is_html_despite_a_jpg_url_is_rejected(): void
    {
        // The URL says .jpg and the header says image/jpeg; the bytes say
        // otherwise, and the bytes are what the browser will act on.
        $this->fakeSourceWithCover(
            'https://scbd.com/news/images/x.jpg',
            ['scbd.com/news/images/*' => Http::response(
                '<html><script>alert(1)</script></html>',
                200,
                ['Content-Type' => 'image/jpeg'],
            )],
        );

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertNull(BlogPost::firstOrFail()->featured_image);
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_non_image_content_type_is_rejected(): void
    {
        // Real image bytes behind a text/html Content-Type, so only the header
        // check can catch this one.
        $this->fakeSourceWithCover(
            'https://scbd.com/news/images/x.jpg',
            ['scbd.com/news/images/*' => Http::response($this->imageBytes(), 200, ['Content-Type' => 'text/html'])],
        );

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertNull(BlogPost::firstOrFail()->featured_image);
        $this->assertSame([], $this->storedFiles());
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        // A valid JPEG header with megabytes of padding behind it: the sniff
        // passes, so only the size cap refuses this.
        $this->fakeSourceWithCover(
            'https://scbd.com/news/images/x.jpg',
            ['scbd.com/news/images/*' => Http::response(
                $this->imageBytes().str_repeat('A', 6 * 1024 * 1024),
                200,
                ['Content-Type' => 'image/jpeg'],
            )],
        );

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertNull(BlogPost::firstOrFail()->featured_image);
        $this->assertSame([], $this->storedFiles());
    }

    public function test_a_traversal_path_cannot_escape_the_uploads_directory(): void
    {
        $this->fakeSourceWithCover('https://scbd.com/news/images/../../../evil.jpg');

        $this->artisan('news:import-scbd')->assertSuccessful();

        $stored = BlogPost::firstOrFail()->featured_image;

        $this->assertNotNull($stored, 'A legitimate JPEG on the source host should still import.');
        $this->assertStringStartsWith('uploads/news/', $stored);
        $this->assertStringNotContainsString('..', $stored);

        foreach ($this->storedFiles() as $file) {
            $this->assertStringStartsWith('uploads/news/', $file);
        }
    }

    public function test_a_rejected_image_does_not_abort_the_run(): void
    {
        // The post still imports; it just has no picture.
        $this->fakeSourceWithCover('https://evil.example/news/images/x.jpg', title: 'Still Imported Story');

        $this->artisan('news:import-scbd')->assertSuccessful();

        $post = BlogPost::firstOrFail();

        $this->assertSame('Still Imported Story', $post->title);
        $this->assertStringContainsString('Story.', $post->content);
    }

    // ---------------------------------------------------------------------
    // Slugs: unique across genuinely different posts, stable across re-runs
    // ---------------------------------------------------------------------

    /**
     * Two posts whose titles differ only past the 90-character truncation, so
     * both want the same slug.
     */
    private function fakeTwinTitledSource(): void
    {
        $prefix = 'A Very Long Headline About The District That Keeps Going And Going And Going Onward Until';
        $listing = '<html><body>';

        foreach (['Alpha', 'Beta'] as $i => $suffix) {
            $listing .= '<div class="niche-box-post">'
                .'<p class="bd-day">'.(23 - $i).'</p><p class="bd-month">Jul 2026</p>'
                .'<h2><a href="https://scbd.com/menu/detail/news/'.strtolower($suffix).'">'.$prefix.' '.$suffix.'</a></h2>'
                .'</div>';
        }

        $listing .= '</body></html>';

        Http::fake([
            'scbd.com/menu/page/news?page=1' => Http::response($listing),
            'scbd.com/menu/page/news*' => Http::response('<html><body></body></html>'),
            'scbd.com/menu/detail/news/*' => Http::response('<html><body><div class="col-md-9"><p>Story.</p></div></body></html>'),
        ]);
    }

    public function test_two_posts_whose_titles_slugify_alike_both_survive(): void
    {
        // Keying on the slug alone made the second post update() the first,
        // replacing its title, date, body and cover while reporting "updated".
        $this->fakeTwinTitledSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame(2, BlogPost::count());

        $slugs = BlogPost::pluck('slug')->all();

        $this->assertCount(2, array_unique($slugs));
        $this->assertStringEndsWith('-2', BlogPost::orderBy('id')->skip(1)->first()->slug);

        // Both titles are intact — neither overwrote the other.
        $this->assertSame(1, BlogPost::where('title', 'like', '%Alpha')->count());
        $this->assertSame(1, BlogPost::where('title', 'like', '%Beta')->count());
    }

    public function test_a_re_run_keeps_the_slugs_it_already_assigned(): void
    {
        // Uniqueness must not cost idempotency: a second run has to match the
        // post it created, not append -2 and then -3 for ever.
        $this->fakeTwinTitledSource();

        $this->artisan('news:import-scbd')->assertSuccessful();
        $before = BlogPost::orderBy('id')->pluck('slug', 'title')->all();

        $this->artisan('news:import-scbd')->assertSuccessful();
        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame(2, BlogPost::count());
        $this->assertSame($before, BlogPost::orderBy('id')->pluck('slug', 'title')->all());
    }

    public function test_it_does_not_overwrite_a_hand_authored_post_on_the_same_slug(): void
    {
        $this->fakeSuccessfulSource();

        $hand = BlogPost::create([
            'title' => 'Editorial piece written by hand',
            // The slug the newest source post wants.
            'slug' => \Illuminate\Support\Str::limit(
                \Illuminate\Support\Str::slug(
                    "Artha Graha Peduli Holds Community Service Program To Celebrate National Children's Day"
                ),
                90,
                '',
            ),
            'content' => '<p>Hand-written body.</p>',
            'excerpt' => 'Hand-written.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => '2026-01-01',
        ]);

        $this->artisan('news:import-scbd')->assertSuccessful();

        $hand->refresh();

        $this->assertSame('Editorial piece written by hand', $hand->title);
        $this->assertSame('<p>Hand-written body.</p>', $hand->content);
    }

    // ---------------------------------------------------------------------
    // Excerpts
    // ---------------------------------------------------------------------

    public function test_excerpts_are_plain_text_with_real_characters(): void
    {
        // The parser escapes the body, so stripping tags alone leaves entities
        // behind — and Blade escapes those again, so the reader sees
        // "Children&#039;s Day" on the homepage and in a meta description.
        $this->fakeSuccessfulSource();

        $this->artisan('news:import-scbd')->assertSuccessful();

        foreach (BlogPost::pluck('excerpt') as $excerpt) {
            $this->assertStringNotContainsString('&#', (string) $excerpt);
            $this->assertStringNotContainsString('&amp;', (string) $excerpt);
            $this->assertStringNotContainsString('<', (string) $excerpt);
        }

        $this->assertTrue(
            BlogPost::where('excerpt', 'like', "%'%")->exists(),
            'The source bodies carry apostrophes; none survived as a real character.',
        );
    }
}
