<?php

namespace App\Console\Commands;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Support\ScbdNewsParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Brings the posts from the current scbd.com news section into this CMS.
 *
 * Kept in the repo rather than run once and deleted, because the source keeps
 * publishing: re-running picks up what is new and refreshes what changed.
 * Matching is by title, so a second run updates rather than duplicates.
 */
class ImportScbdNews extends Command
{
    protected $signature = 'news:import-scbd {--limit= : Stop after this many posts} {--dry-run : Report what would happen and write nothing}';

    protected $description = 'Import news posts from scbd.com';

    private const LISTING = 'https://scbd.com/menu/page/news';

    private const PAGES = 4;

    /**
     * The source has no categories, so each post is filed by matching its
     * title. Editorial judgement, and a starting point only — every assignment
     * is editable in the admin afterwards.
     *
     * Order matters: the first pattern that matches wins, so the more specific
     * subjects come before the general ones.
     *
     * @var array<string, array<int, string>>
     */
    private const CATEGORIES = [
        'Environment' => ['earth hour', 'asri', 'clean-up', 'waste', 'environment', 'eco enzyme'],
        'Corporate' => ['shareholders', 'groundbreaking', 'agm', 'danayasa'],
        'Events' => ['lunar new year', 'olympic', 'independence day', 'challenge', 'ride to luck'],
        'Community' => [],
    ];

    private const FALLBACK_CATEGORY = 'Community';

    /**
     * Images are only ever fetched from the source site.
     *
     * The URLs come from somebody else's markup, so without this an injected
     * <img src> would make the importer fetch an arbitrary host and persist the
     * response under the public web root — an SSRF whose result is readable at
     * a guessable URL.
     */
    private const IMAGE_HOST = 'scbd.com';

    /**
     * Only these may be written. Anything the deploy might execute or the
     * browser might treat as active content (.php, .svg, .html) is refused,
     * because everything under uploads/ is served from the site's own origin.
     *
     * @var array<int, string>
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * What the bytes are allowed to actually be, as sniffed from the payload
     * rather than claimed by the URL or the Content-Type header.
     *
     * @var array<int, string>
     */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $listed = $this->fetchListing($limit);

        if ($listed === []) {
            $this->warn('No posts found — the source markup may have changed.');

            return self::SUCCESS;
        }

        $categories = $dryRun ? [] : $this->ensureCategories();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($listed as $item) {
            try {
                $detail = $this->fetchDetail($item['url']);
            } catch (Throwable $e) {
                // The listing already carries title, date and cover, so a post
                // survives a failed detail fetch with no body rather than being
                // dropped entirely.
                $this->warn("Body unavailable for \u{201c}{$item['title']}\u{201d}: {$e->getMessage()}");
                $detail = ['body' => '', 'images' => []];
            }

            if ($dryRun) {
                $this->line("would import: {$item['date']}  ".$this->baseSlug($item['title']));
                $skipped++;

                continue;
            }

            // Idempotency keys on the title, not on the slug. Two different
            // posts can slugify to the same 90-character slug, and keying on
            // the slug made the second silently overwrite the first — and let
            // the importer overwrite a hand-authored post that happened to
            // occupy the slug. The title is the stable identity of a source
            // post; the slug is only its address, and is disambiguated below.
            $existing = BlogPost::where('title', $item['title'])->first();
            $categoryName = $this->categoryFor($item['title']);

            $body = $this->composeBody($detail['body'], $detail['images']);

            $attributes = [
                'title' => $item['title'],
                'content' => $body ?: '<p></p>',
                // strip_tags first, then decode: the parser already escaped the
                // body, so stripping alone leaves &#039; and &amp; in the
                // excerpt, which Blade then escapes a second time and renders
                // literally. Decoding after stripping cannot reintroduce a tag
                // into anything, and the excerpt is always output escaped.
                'excerpt' => Str::limit(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 180),
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => $item['date'],
                'blog_category_id' => $categories[$categoryName] ?? null,
                'featured_image' => $this->download($item['cover']),
            ];

            if ($existing) {
                // A cover that failed to download this run must not wipe one
                // that imported successfully last run.
                if ($attributes['featured_image'] === null) {
                    unset($attributes['featured_image']);
                }

                $existing->update($attributes);
                $updated++;

                continue;
            }

            BlogPost::create($attributes + ['slug' => $this->uniqueSlug($item['title'])]);
            $created++;
        }

        $this->info("Created {$created}, updated {$updated}, skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * The slug a title wants, before anything else has claimed it.
     */
    private function baseSlug(string $title): string
    {
        return Str::limit(Str::slug($title), 90, '') ?: 'post';
    }

    /**
     * The same shape as App\Models\Page::uniqueSlug(): the wanted slug, or the
     * wanted slug with the first free numeric suffix.
     *
     * Only new posts go through here. A re-run matches its post by title and
     * keeps the slug it was given, so the suffix cannot creep upwards run over
     * run; it only ever separates two genuinely different posts.
     */
    private function uniqueSlug(string $title): string
    {
        $base = $this->baseSlug($title);
        $slug = $base;
        $suffix = 2;

        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /** @return array<int, array{title: string, date: string, cover: ?string, url: string}> */
    private function fetchListing(?int $limit): array
    {
        $all = [];

        for ($page = 1; $page <= self::PAGES; $page++) {
            try {
                $response = Http::timeout(30)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get(self::LISTING, ['page' => $page]);
            } catch (Throwable $e) {
                $this->warn("Listing page {$page} unavailable: {$e->getMessage()}");

                continue;
            }

            $posts = ScbdNewsParser::listing($response->body());

            // The source stops returning items past the last page.
            if ($posts === []) {
                break;
            }

            $all = array_merge($all, $posts);

            if ($limit !== null && count($all) >= $limit) {
                return array_slice($all, 0, $limit);
            }
        }

        return $limit !== null ? array_slice($all, 0, $limit) : $all;
    }

    /** @return array{body: string, images: array<int, string>} */
    private function fetchDetail(string $url): array
    {
        $response = Http::timeout(30)->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        return ScbdNewsParser::detail($response->body());
    }

    /**
     * The stored body: the post's paragraphs with its own photographs placed
     * among them.
     *
     * The reference detail layout opens with a lead paragraph, then a two-up
     * image row, then prose, then a captioned figure — so the first two images
     * become the pair and the rest become figures after the text. Images that
     * fail to download simply do not appear; the prose is unaffected.
     *
     * @param  array<int, string>  $images
     */
    private function composeBody(string $paragraphsHtml, array $images): string
    {
        $stored = [];

        foreach ($images as $url) {
            if ($path = $this->download($url)) {
                $stored[] = $path;
            }
        }

        if ($stored === []) {
            return $paragraphsHtml;
        }

        $tag = fn (string $path): string => '<img src="'.e(Storage::disk('public')->url($path)).'" alt="" loading="lazy">';

        // Split after the opening paragraph so the pair lands where the
        // reference puts it. A body with one paragraph keeps everything after.
        $paragraphs = preg_split('#(?<=</p>)#', $paragraphsHtml, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lead = array_shift($paragraphs) ?? '';

        $pair = '';

        if (count($stored) >= 2) {
            $pair = '<div class="scbd-prose-pair">'.$tag($stored[0]).$tag($stored[1]).'</div>';
            $stored = array_slice($stored, 2);
        }

        $figures = '';

        foreach ($stored as $path) {
            $figures .= '<figure>'.$tag($path).'</figure>';
        }

        return $lead.$pair.implode('', $paragraphs).$figures;
    }

    /** @return array<string, int> Category name to id. */
    private function ensureCategories(): array
    {
        $ids = [];

        foreach (array_keys(self::CATEGORIES) as $name) {
            $ids[$name] = BlogCategory::firstOrCreate(['name' => $name])->id;
        }

        return $ids;
    }

    private function categoryFor(string $title): string
    {
        $haystack = Str::lower($title);

        foreach (self::CATEGORIES as $name => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $name;
                }
            }
        }

        return self::FALLBACK_CATEGORY;
    }

    /**
     * The stored path, or null when there is nothing to store.
     *
     * Null rather than an empty string: MediaUrl guards on null, and a card
     * with no image renders its text-only variant rather than an <img src="">
     * that the browser resolves against the current page.
     *
     * Every rejection here is a warning and a null, never an exception: an
     * image the importer refuses to store must cost that post its picture, not
     * the whole run.
     */
    private function download(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        // Decided before a byte is fetched: the host and the extension are
        // properties of the URL, and a URL that fails either must not even be
        // requested.
        $path = $this->storagePathFor($url);

        if ($path === null) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }
        } catch (Throwable $e) {
            $this->warn("Image unavailable ({$url}): {$e->getMessage()}");

            return null;
        }

        $body = $response->body();

        if ($body === '') {
            return null;
        }

        if (strlen($body) > self::MAX_IMAGE_BYTES) {
            $this->warn("Image too large ({$url}): ".strlen($body).' bytes');

            return null;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if (! str_starts_with($contentType, 'image/')) {
            $this->warn("Image rejected — Content-Type {$contentType} ({$url})");

            return null;
        }

        // The header is the source's claim; this is the payload's own answer.
        // It is what stops a .jpg URL serving HTML, which would otherwise be
        // stored and served back from this site's origin.
        $sniffed = @getimagesizefromstring($body);

        if ($sniffed === false || ! in_array($sniffed['mime'] ?? '', self::IMAGE_MIMES, true)) {
            $this->warn("Image rejected — payload is not an image ({$url})");

            return null;
        }

        Storage::disk('public')->put($path, $body);

        return $path;
    }

    /**
     * Where an image URL is allowed to be written, or null if it is not
     * allowed at all.
     *
     * The returned path cannot escape uploads/news/: the filename is the
     * basename of the URL path, re-slugged, so no directory separator and no
     * "../" survives, and the extension comes from a fixed allowlist.
     *
     * It is also bounded. blog_posts.featured_image is a varchar(255), and the
     * insert that stores this path is not inside download()'s try/catch, so an
     * over-long name would take the whole run down with a QueryException on
     * PostgreSQL — the one thing every guard here exists to prevent. Shorter
     * overflows fail more quietly still: the filesystem refuses a path
     * component over 255 bytes, the public disk has 'throw' => false, and the
     * row is written pointing at a file that was never created.
     */
    private function storagePathFor(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'], $parts['scheme'])) {
            $this->warn("Image rejected — unreadable URL ({$url})");

            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $this->warn("Image rejected — scheme {$parts['scheme']} ({$url})");

            return null;
        }

        $host = strtolower($parts['host']);

        if ($host !== self::IMAGE_HOST && ! str_ends_with($host, '.'.self::IMAGE_HOST)) {
            $this->warn("Image rejected — off-site host {$host} ({$url})");

            return null;
        }

        $urlPath = $parts['path'] ?? '';
        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $this->warn("Image rejected — extension \u{201c}{$extension}\u{201d} ({$url})");

            return null;
        }

        // Truncated before the hash is appended, not after: the hash is what
        // keeps two source images sharing an 80-character prefix apart, and it
        // is only doing that job if it survives the cut. 80 + the 13-character
        // prefix + the 9-character hash + at most 5 for the extension leaves
        // the stored path around 107 — far inside both the column and the
        // filesystem's 255-byte limit on a single path component.
        $name = Str::limit(Str::slug(pathinfo(basename($urlPath), PATHINFO_FILENAME)), 80, '') ?: 'image';

        return 'uploads/news/'.$name.'-'.substr(md5($url), 0, 8).'.'.$extension;
    }
}
