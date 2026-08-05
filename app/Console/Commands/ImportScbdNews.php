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
 * Matching is by slug, so a second run updates rather than duplicates.
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

            $slug = Str::limit(Str::slug($item['title']), 90, '');

            if ($dryRun) {
                $this->line("would import: {$item['date']}  {$slug}");
                $skipped++;

                continue;
            }

            $existing = BlogPost::where('slug', $slug)->first();
            $categoryName = $this->categoryFor($item['title']);

            $body = $this->composeBody($detail['body'], $detail['images']);

            $attributes = [
                'title' => $item['title'],
                'content' => $body ?: '<p></p>',
                'excerpt' => Str::limit(strip_tags($body), 180),
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

            BlogPost::create($attributes + ['slug' => $slug]);
            $created++;
        }

        $this->info("Created {$created}, updated {$updated}, skipped {$skipped}.");

        return self::SUCCESS;
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
     */
    private function download(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful() || $response->body() === '') {
                return null;
            }
        } catch (Throwable $e) {
            $this->warn("Image unavailable ({$url}): {$e->getMessage()}");

            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $path = 'uploads/news/'.Str::slug(pathinfo($url, PATHINFO_FILENAME)).'-'.substr(md5($url), 0, 8).'.'.$extension;

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }
}
