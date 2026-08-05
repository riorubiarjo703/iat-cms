# News Index and Post Detail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a public News landing page at `/news` listing every published post with client-side category filtering, and a post detail page at `/news/{slug}`, populated by importing the 13 posts currently on scbd.com.

**Architecture:** The index is a page-builder block (`scbd_news_index`) placed on the existing draft page id 15 (slug `news`), so its URL comes free from the `/{slug}` catch-all and its copy is editable in the admin. The detail page is a dedicated invokable controller on a route declared before the catch-all. Both read the existing `BlogPost` model — no migrations — and share one card partial. The package's own `/blogs` frontend is switched off.

**Tech Stack:** Laravel 13 + Blade, Filament 5, `ajaydhakal/filament-story` 1.1 (BlogPost/BlogCategory + admin), GSAP 3.12.5 with ScrollTrigger and Flip, Lenis 1.1.18, Vite 8, PHPUnit 12, SQLite in-memory for tests.

**Spec:** `docs/superpowers/specs/2026-08-05-news-index-detail-design.md`

## Global Constraints

- **Never run `npm run dev` or any `migrate:fresh` variant in this repo.** Both have caused real damage here. Build assets with `npm run build`.
- **PHP runs in a container that mounts the project at `/var/www/iat-cms`.** Host working directory is ignored. In a worktree, invoke artisan by container path — `php /var/www/iat-cms/<relative-path>/artisan test …` — or PHP commands silently target the main checkout and report tests that never ran.
- A fresh worktree needs `npm run build` before any artisan command, or every one dies in `AdminPanelProvider:78` on a missing Vite manifest (`public/build` is gitignored).
- Tests are **PHPUnit 12, not Pest** — classes extend `Tests\TestCase`, methods prefixed `test_`. Run with `php artisan test`.
- All CSS goes in `resources/css/scbd.css`. Do not create new stylesheets.
- The responsive breakpoint is `@media (max-width: 900px)`, already open at line 1115; a deeper one exists at 560px. Do not introduce new breakpoint values.
- Palette: ground `#f3f2f2`, accent `#ec3013`, ink `#201e1d`, divider `rgba(32,30,29,0.4)`.
- JS modules follow the house pattern: one file per concern in `resources/js/scbd/`, exporting a named `init*` taking `gsap`/`ScrollTrigger` as arguments, wired up in `resources/js/scbd/index.js`, returning early when its hook element is absent.
- Blocks follow `App\PageBuilder\Blocks\NewsBlock` exactly: `type()`, `name()`, `icon()`, `schema()`, `defaultData()`, `translatableKeys()`, extending `BaseBlock`, registered explicitly in `PageBuilderServiceProvider`.
- Block views read data only through `App\PageBuilder\BlockData` (`t()`, `get()`, `i18nKey()`), and images only through `App\Support\MediaUrl::resolve()`.
- There is no JavaScript test runner in this repo. JS is verified by `npm run build` plus a Blade-level markup contract test plus a browser check.

## File Structure

| File | Responsibility |
|---|---|
| `resources/views/components/layouts/page.blade.php` | Modify: take explicit props instead of a `Page` model |
| `resources/views/page.blade.php` | Modify: derive those props from its `Page` |
| `resources/views/components/layouts/public.blade.php` | Delete: unreferenced duplicate shell |
| `app/Http/Controllers/NewsPostController.php` | Create: load post, neighbours, latest |
| `routes/web.php` | Modify: `/news/{slug}` before the catch-all |
| `resources/views/news/show.blade.php` | Create: the detail page |
| `resources/views/partials/site/news-card.blade.php` | Create: shared card, `grid` and `compact` sizes |
| `app/PageBuilder/Blocks/NewsIndexBlock.php` | Create: block definition + admin schema |
| `app/Providers/PageBuilderServiceProvider.php` | Modify: register the block |
| `resources/views/partials/blocks/scbd-news-index.blade.php` | Create: the index section |
| `resources/js/scbd/newsFilter.js` | Create: Flip category filter |
| `resources/js/scbd/index.js` | Modify: wire the filter in |
| `resources/css/scbd.css` | Modify: card, grid, sidebar, chip, prose rules + `.is-hidden` |
| `app/Console/Commands/ImportScbdNews.php` | Create: the scraper/importer |
| `app/Support/ScbdNewsParser.php` | Create: pure HTML→array parsing, no network |
| `tests/Fixtures/scbd-news/*.html` | Create: saved source HTML for tests |
| `config/filament-story.php` | Modify: `frontend_enabled => false` |
| `resources/views/partials/blocks/scbd-news.blade.php` | Modify: relink the homepage block |
| `database/seeders/…` or a one-off command | Modify: publish page 15, repoint menu item 27 |

Parsing is split from the command (`ScbdNewsParser` vs `ImportScbdNews`) so the parse can be tested against saved fixtures with no network and no database.

---

### Task 1: Layout takes explicit props

`x-layouts.page` currently reads a `Page` model for its title, description, loader and animation-bundle decisions. A post detail page has no `Page` row. Move that knowledge out to the caller.

**Files:**
- Modify: `resources/views/components/layouts/page.blade.php`
- Modify: `resources/views/page.blade.php:6`
- Delete: `resources/views/components/layouts/public.blade.php`
- Test: `tests/Feature/Pages/LayoutPropsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `<x-layouts.page :title="string" :description="?string" :animated="bool" :show-loader="bool" :i18n="array">`. `title` is the complete `<title>` with any suffix already applied — the layout renders it verbatim and never branches on page identity. Every later task that renders a page uses this signature.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Pages;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class LayoutPropsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
        SiteSetting::singleton()->update(['site_name' => 'SCBD']);
    }

    public function test_the_layout_renders_the_title_it_is_handed_verbatim(): void
    {
        // The layout no longer knows what a suffix is; the caller applies it.
        $rendered = view('components.layouts.page', [
            'title' => 'Handed Title — SCBD',
            'description' => 'A description.',
            'animated' => false,
            'showLoader' => false,
            'i18n' => [],
            'slot' => '<p>Body</p>',
        ])->render();

        $this->assertStringContainsString('<title>Handed Title — SCBD</title>', $rendered);
        $this->assertStringContainsString('content="A description."', $rendered);
    }

    public function test_an_unanimated_page_loads_no_animation_bundle_and_no_i18n_payload(): void
    {
        $rendered = view('components.layouts.page', [
            'title' => 'Plain',
            'description' => null,
            'animated' => false,
            'showLoader' => false,
            'i18n' => ['en' => ['x' => 'y']],
            'slot' => '',
        ])->render();

        $this->assertStringNotContainsString('resources/js/scbd/index.js', $rendered);
        $this->assertStringNotContainsString('scbd-i18n', $rendered);
        $this->assertStringNotContainsString('cursor:none', $rendered);
    }

    public function test_an_animated_page_loads_the_bundle_the_cursor_and_the_payload(): void
    {
        $rendered = view('components.layouts.page', [
            'title' => 'Rich',
            'description' => null,
            'animated' => true,
            'showLoader' => false,
            'i18n' => ['en' => ['x' => 'y']],
            'slot' => '',
        ])->render();

        $this->assertStringContainsString('data-cursor', $rendered);
        $this->assertStringContainsString('scbd-i18n', $rendered);
    }

    public function test_the_loader_appears_only_when_asked_for(): void
    {
        $without = view('components.layouts.page', [
            'title' => 'A', 'description' => null, 'animated' => true,
            'showLoader' => false, 'i18n' => [], 'slot' => '',
        ])->render();

        $with = view('components.layouts.page', [
            'title' => 'A', 'description' => null, 'animated' => true,
            'showLoader' => true, 'i18n' => [], 'slot' => '',
        ])->render();

        $this->assertStringNotContainsString('data-loader', $without);
        $this->assertStringContainsString('data-loader', $with);
    }

    public function test_a_real_page_still_gets_its_suffixed_title(): void
    {
        Page::create([
            'title' => ['en' => 'About Us'],
            'slug' => 'about-us',
            'type' => Page::TYPE_SIMPLE,
            'content' => ['en' => '<p>Body</p>'],
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->get('/about-us')->assertSee('<title>About Us — SCBD</title>', false);
    }

    public function test_the_homepage_takes_no_suffix(): void
    {
        $this->seedHomepage();
        SiteSetting::singleton()->update(['meta_title' => ['en' => 'SCBD Jakarta']]);

        $this->get('/')->assertSee('<title>SCBD Jakarta</title>', false);
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=LayoutPropsTest`
Expected: FAIL — the layout still requires a `page` prop, so rendering it with `title` raises "Undefined variable $page".

- [ ] **Step 3: Rewrite the layout to take props**

Replace the whole of `resources/views/components/layouts/page.blade.php` with:

```blade
@props([
    'title',
    'description' => null,
    'animated' => false,
    'showLoader' => false,
    'i18n' => [],
])

@php
    $settings = \App\Models\SiteSetting::singleton();
@endphp
<!DOCTYPE html>
<html lang="{{ $settings->default_locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Rendered verbatim. Deciding what a title should be — fallbacks, the
         site-name suffix, whether this is the front door — belongs to whoever
         knows what is being rendered. A layout that branched on page identity
         could not serve anything that is not a Page row. --}}
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    @if ($faviconUrl = \App\Support\MediaUrl::resolve($settings->favicon))
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif

    {{-- Animated pages get the bundle: every block and the news pages depend on
         its hooks (data-split, data-parallax, data-reveal, data-news-filter),
         and without it their content renders but never becomes visible.
         Standard pages skip it — they have no such hooks, and the bundle's
         `cursor:none` would leave a text page with no pointer. --}}
    @if ($animated)
        @vite(['resources/css/scbd.css', 'resources/js/scbd/index.js'])
    @else
        @vite(['resources/css/scbd.css'])
    @endif
</head>
<body>
    <div @style([
        'position:relative; width:100%; background:#f3f2f2; color:#201e1d;',
        "font-family:'Archivo',system-ui,sans-serif;",
        // The custom cursor replaces the native one, so it is only hidden where
        // that cursor actually exists.
        'cursor:none' => $animated,
    ])>
        @if ($showLoader)
            @include('partials.site.loader')
        @endif

        @if ($animated)
            <div class="scbd-cursor" data-cursor style="position:fixed;top:0;left:0;width:14px;height:14px;background:#ec3013;z-index:9999;pointer-events:none;transform:translate(-50%,-50%);"></div>
            <div class="scbd-cursor" data-cursor-ring style="position:fixed;top:0;left:0;width:44px;height:44px;border:1.5px solid rgba(32,30,29,0.45);z-index:9998;pointer-events:none;transform:translate(-50%,-50%);"></div>
        @endif

        {{ $slot }}
    </div>

    @if ($animated && $i18n !== [])
        {{-- Consumed by resources/js/scbd/i18n.js. --}}
        <script type="application/json" id="scbd-i18n">@json($i18n)</script>
    @endif
</body>
</html>
```

If `partials/site/loader.blade.php` has no `data-loader` attribute, add one to its outermost element — the loader test needs a stable hook, and the attribute is inert.

- [ ] **Step 4: Move the Page knowledge into `page.blade.php`**

Replace lines 1–6 of `resources/views/page.blade.php` (everything up to and including the opening `<x-layouts.page …>` tag) with:

```blade
{{--
    A standalone page. The header and footer are part of the layout rather than
    rows in the page's own content, so every page gets them automatically and
    editing one changes them everywhere.
--}}
@php
    $settings = \App\Models\SiteSetting::singleton();

    // Per-page SEO wins; Site Settings is the fallback, so a page with nothing
    // filled in still gets sensible metadata rather than an empty title.
    //
    // The homepage is the exception: it is the site's front door, so it falls
    // back to the site meta title and takes no " — site name" suffix. Titling
    // it "Home — SCBD" would be worse than what it replaced.
    $isHomepage = (bool) $page->is_homepage;
    $pageTitle = $page->t('seo_title')
        ?: ($isHomepage ? ($settings->t('meta_title') ?: $settings->site_name) : $page->t('title'));
    $suffix = (! $isHomepage && $settings->site_name) ? ' — '.$settings->site_name : '';
@endphp

<x-layouts.page
    :title="$pageTitle.$suffix"
    :description="$page->t('seo_description') ?: $settings->t('meta_description')"
    :animated="$page->usesBuilder()"
    :show-loader="$isHomepage"
    :i18n="$page->usesBuilder()
        ? \App\PageBuilder\SiteTranslations::forPage($page, app(\App\PageBuilder\BlockRegistry::class))
        : []">
```

Leave the rest of the file (the `<main>` through `</x-layouts.page>`) untouched.

- [ ] **Step 5: Delete the dead shell**

```bash
git rm resources/views/components/layouts/public.blade.php
```

Confirm nothing referenced it first:

Run: `grep -rn "layouts.public\|layouts\.public" resources app routes tests`
Expected: no output.

- [ ] **Step 6: Run the new test and the whole existing suite**

Run: `php artisan test --filter=LayoutPropsTest`
Expected: PASS, 6 tests.

Run: `php artisan test`
Expected: PASS — this is a behaviour-preserving refactor, so every pre-existing test must still be green. `tests/Feature/Pages/PageRenderTest.php` already asserts the title fallbacks (`test_seo_title_falls_back_to_the_page_title`, `test_seo_title_wins_when_set`) and is the real guard here.

- [ ] **Step 7: Prove the tests bite**

Temporarily change `:title="$pageTitle.$suffix"` to `:title="$pageTitle"` in `page.blade.php`.

Run: `php artisan test --filter=LayoutPropsTest`
Expected: FAIL on `test_a_real_page_still_gets_its_suffixed_title`. If it passes, the test is not testing what it names — fix the test before continuing. Revert the change afterwards.

- [ ] **Step 8: Commit**

```bash
git add -A resources/views tests/Feature/Pages/LayoutPropsTest.php
git commit -m "refactor: the page layout takes explicit props instead of a Page"
```

---

### Task 2: The detail route and its publish semantics

**Files:**
- Create: `app/Http/Controllers/NewsPostController.php`
- Modify: `routes/web.php`
- Create: `resources/views/news/show.blade.php` (minimal for now; Task 5 builds it out)
- Test: `tests/Feature/News/NewsPostRouteTest.php`

**Interfaces:**
- Consumes: the layout signature from Task 1.
- Produces: route name `news.show` taking a `slug` parameter — `route('news.show', $post->slug)`. The view receives `$post` (a `BlogPost`). Tasks 3, 5 and 10 rely on both.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsPostRouteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    /** Every field the view needs, so a test can vary one thing at a time. */
    private function post(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'A Community Service Programme',
            'slug' => 'a-community-service-programme',
            'content' => '<p>Body copy.</p>',
            'excerpt' => 'A short summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_a_published_post_is_reachable(): void
    {
        $this->post();

        $this->get('/news/a-community-service-programme')
            ->assertSuccessful()
            ->assertSee('A Community Service Programme', false);
    }

    public function test_a_draft_post_is_not_found(): void
    {
        // 404 rather than 403: an unlisted URL must not confirm a post exists.
        $this->post(['status' => BlogPost::STATUS_DRAFT, 'published_at' => null]);

        $this->get('/news/a-community-service-programme')->assertNotFound();
    }

    public function test_a_post_dated_in_the_future_is_not_found(): void
    {
        // Marked published but scheduled. It looks live in the admin and must
        // not be reachable by URL yet.
        $this->post(['published_at' => now()->addWeek()]);

        $this->get('/news/a-community-service-programme')->assertNotFound();
    }

    public function test_a_post_with_the_scheduled_status_is_not_found(): void
    {
        $this->post([
            'status' => BlogPost::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ]);

        $this->get('/news/a-community-service-programme')->assertNotFound();
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->get('/news/nothing-here')->assertNotFound();
    }

    public function test_the_post_route_is_not_shadowed_by_the_page_catch_all(): void
    {
        // The catch-all only matches slugs without a slash, but the ordering is
        // load-bearing and worth pinning.
        $this->post();

        $this->assertSame(
            url('/news/a-community-service-programme'),
            route('news.show', 'a-community-service-programme'),
        );
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=NewsPostRouteTest`
Expected: FAIL — every case 404s because the route does not exist, and `route('news.show', …)` throws `RouteNotFoundException`.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/NewsPostController.php`:

```php
<?php

namespace App\Http\Controllers;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Contracts\View\View;

class NewsPostController extends Controller
{
    public function __invoke(string $slug): View
    {
        // Published means published now. A post marked published but dated in
        // the future is scheduled, not live, and must 404 rather than 403 — an
        // unlisted URL should not confirm that a post exists.
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('status', BlogPost::STATUS_PUBLISHED)
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return view('news.show', ['post' => $post]);
    }
}
```

`STATUS_SCHEDULED` needs no separate clause: it is not `STATUS_PUBLISHED`, so the status filter already excludes it.

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the `use` line beside the existing controller imports:

```php
use App\Http\Controllers\NewsPostController;
```

and insert this **above** the `Route::get('/{slug}', PageController::class)` block:

```php
/*
 * Declared before the page catch-all, whose slug pattern excludes anything
 * containing a slash — so a post URL could never reach this controller if the
 * two were the other way round.
 */
Route::get('/news/{slug}', NewsPostController::class)
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('news.show');
```

- [ ] **Step 5: Write the minimal view**

Create `resources/views/news/show.blade.php`. Task 5 replaces this body; for now it only has to render the title through the layout.

```blade
@php
    $settings = \App\Models\SiteSetting::singleton();
    $suffix = $settings->site_name ? ' — '.$settings->site_name : '';
@endphp

<x-layouts.page
    :title="($post->seo_title ?: $post->title).$suffix"
    :description="$post->seo_description ?: $post->excerpt"
    :animated="true"
    :show-loader="false"
    :i18n="[]">

    @include('partials.site.header')

    <main class="scbd-shade" style="min-height:50vh;">
        <article class="scbd-pad-top">
            <h1>{{ $post->title }}</h1>
            <div class="scbd-prose">{!! $post->content !!}</div>
        </article>
    </main>

    @include('partials.site.footer')
</x-layouts.page>
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --filter=NewsPostRouteTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Prove the tests bite**

Temporarily delete the `->where('published_at', '<=', now())` clause from the controller.

Run: `php artisan test --filter=NewsPostRouteTest`
Expected: FAIL on `test_a_post_dated_in_the_future_is_not_found`. If it stays green the test is worthless — fix it before continuing. Restore the clause.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/NewsPostController.php routes/web.php resources/views/news/show.blade.php tests/Feature/News/NewsPostRouteTest.php
git commit -m "feat: a post detail route that respects publish state"
```

---

### Task 3: Neighbours and the latest-news row

**Files:**
- Modify: `app/Http/Controllers/NewsPostController.php`
- Test: `tests/Feature/News/NewsPostNeighboursTest.php`

**Interfaces:**
- Consumes: `NewsPostController` and `news.show` from Task 2.
- Produces: the `news.show` view additionally receives `$previous` (`?BlogPost`), `$next` (`?BlogPost`) and `$latest` (`Collection<BlogPost>`, at most 4). Task 5 renders all three.

Definitions, fixed here so the view and the tests agree: **previous** is the newest post older than this one, **next** is the oldest post newer than it. Reading order — "previous" is what you would have read before this one.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsPostNeighboursTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    private function post(string $title, string $date): BlogPost
    {
        return BlogPost::create([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $date,
        ]);
    }

    /** Oldest to newest. */
    private function seedFive(): void
    {
        $this->post('Oldest Post', '2024-05-14');
        $this->post('Second Post', '2024-06-26');
        $this->post('Middle Post', '2025-02-11');
        $this->post('Fourth Post', '2025-08-17');
        $this->post('Newest Post', '2026-07-23');
    }

    public function test_previous_is_the_newest_older_post(): void
    {
        $this->seedFive();

        $this->get('/news/middle-post')
            ->assertSuccessful()
            ->assertViewHas('previous', fn ($p) => $p?->title === 'Second Post');
    }

    public function test_next_is_the_oldest_newer_post(): void
    {
        $this->seedFive();

        $this->get('/news/middle-post')
            ->assertViewHas('next', fn ($n) => $n?->title === 'Fourth Post');
    }

    public function test_the_oldest_post_has_no_previous(): void
    {
        $this->seedFive();

        // A closure, not null: assertViewHas($key, null) only asserts the key
        // exists, so it would pass with a populated neighbour.
        $this->get('/news/oldest-post')->assertViewHas('previous', fn ($p) => $p === null);
    }

    public function test_the_newest_post_has_no_next(): void
    {
        $this->seedFive();

        $this->get('/news/newest-post')->assertViewHas('next', fn ($n) => $n === null);
    }

    public function test_neighbours_skip_unpublished_posts(): void
    {
        $this->seedFive();
        BlogPost::where('slug', 'fourth-post')->update(['status' => BlogPost::STATUS_DRAFT]);

        // With Fourth drafted, Middle's next must jump to Newest rather than
        // pointing at a URL that 404s.
        $this->get('/news/middle-post')
            ->assertViewHas('next', fn ($n) => $n?->title === 'Newest Post');
    }

    public function test_latest_excludes_the_post_being_viewed(): void
    {
        $this->seedFive();

        $this->get('/news/newest-post')
            ->assertViewHas('latest', fn ($latest) => $latest
                ->pluck('slug')
                ->doesntContain('newest-post'));
    }

    public function test_latest_is_capped_at_four_newest_first(): void
    {
        $this->seedFive();

        $this->get('/news/oldest-post')
            ->assertViewHas('latest', fn ($latest) => $latest->count() === 4
                && $latest->first()->title === 'Newest Post');
    }

    public function test_latest_omits_unpublished_posts(): void
    {
        $this->seedFive();
        BlogPost::where('slug', 'newest-post')->update(['published_at' => now()->addYear()]);

        $this->get('/news/oldest-post')
            ->assertViewHas('latest', fn ($latest) => $latest
                ->pluck('slug')
                ->doesntContain('newest-post'));
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=NewsPostNeighboursTest`
Expected: FAIL — `assertViewHas('previous', …)` fails because the view has no such key.

- [ ] **Step 3: Extend the controller**

Replace the body of `app/Http/Controllers/NewsPostController.php` with:

```php
<?php

namespace App\Http\Controllers;

use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class NewsPostController extends Controller
{
    public function __invoke(string $slug): View
    {
        // Published means published now. A post marked published but dated in
        // the future is scheduled, not live, and must 404 rather than 403 — an
        // unlisted URL should not confirm that a post exists.
        $post = self::published()->where('slug', $slug)->firstOrFail();

        return view('news.show', [
            'post' => $post,
            // Reading order: "previous" is the post you would have read before
            // this one. Both walk the published set, so a draft between two
            // posts is stepped over rather than linked to a URL that 404s.
            'previous' => self::published()
                ->where('published_at', '<', $post->published_at)
                ->orderByDesc('published_at')
                ->first(),
            'next' => self::published()
                ->where('published_at', '>', $post->published_at)
                ->orderBy('published_at')
                ->first(),
            // Excludes the post being viewed: a reader must never be offered a
            // link back to the page they are already on.
            'latest' => self::published()
                ->whereKeyNot($post->getKey())
                ->orderByDesc('published_at')
                ->limit(4)
                ->get(),
        ]);
    }

    /**
     * The one definition of "visible", shared by the post itself, its
     * neighbours and the latest row — so they can never disagree about what is
     * live.
     */
    private static function published(): Builder
    {
        return BlogPost::query()
            ->where('status', BlogPost::STATUS_PUBLISHED)
            ->where('published_at', '<=', now());
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter=NewsPostNeighboursTest`
Expected: PASS, 8 tests.

- [ ] **Step 5: Prove the tests bite**

Temporarily remove `->whereKeyNot($post->getKey())` from the `latest` query.

Run: `php artisan test --filter=NewsPostNeighboursTest`
Expected: FAIL on `test_latest_excludes_the_post_being_viewed`. Restore it.

Then temporarily swap `orderByDesc('published_at')` to `orderBy('published_at')` in the `previous` query.

Run: `php artisan test --filter=NewsPostNeighboursTest`
Expected: FAIL on `test_previous_is_the_newest_older_post` — it would return the oldest post rather than the nearest. Restore it.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/NewsPostController.php tests/Feature/News/NewsPostNeighboursTest.php
git commit -m "feat: prev/next neighbours and a latest-news set for post detail"
```

---

### Task 4: The shared news card and its styles

**Files:**
- Create: `resources/views/partials/site/news-card.blade.php`
- Modify: `resources/css/scbd.css`
- Test: `tests/Feature/News/NewsCardTest.php`

**Interfaces:**
- Consumes: `route('news.show', …)` from Task 2.
- Produces: `@include('partials.site.news-card', ['post' => $post, 'size' => 'grid'])`. `size` is `'grid'` or `'compact'`. The rendered root element carries `class="scbd-news-card scbd-news-card-{size}"` and `data-news-category="{category-slug-or-empty}"` — Task 7 groups these in a grid and Task 8's filter reads that attribute.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCardTest extends TestCase
{
    use RefreshDatabase;

    private function post(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'Earth Hour 2026',
            'slug' => 'earth-hour-2026',
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => '2026-03-28',
        ], $overrides));
    }

    private function render(BlogPost $post, string $size): string
    {
        return view('partials.site.news-card', ['post' => $post, 'size' => $size])->render();
    }

    public function test_the_grid_card_links_to_the_post_and_shows_its_date(): void
    {
        $html = $this->render($this->post(), 'grid');

        $this->assertStringContainsString(route('news.show', 'earth-hour-2026'), $html);
        $this->assertStringContainsString('Earth Hour 2026', $html);
        $this->assertStringContainsString('28.03.26', $html);
    }

    public function test_a_card_carries_its_category_slug_for_the_filter(): void
    {
        $category = BlogCategory::create(['name' => 'Environment']);
        $post = $this->post(['blog_category_id' => $category->id]);

        $html = $this->render($post->fresh(), 'grid');

        $this->assertStringContainsString('data-news-category="environment"', $html);
        $this->assertStringContainsString('Environment', $html);
    }

    public function test_an_uncategorised_card_carries_an_empty_category(): void
    {
        // Still present, still empty: the filter reads the attribute on every
        // card, and a missing attribute would make an uncategorised post
        // invisible to "All" rather than merely unfiltered.
        $html = $this->render($this->post(), 'grid');

        $this->assertStringContainsString('data-news-category=""', $html);
    }

    public function test_a_post_without_an_image_renders_no_img_tag(): void
    {
        // An empty src resolves against the current page and re-requests it.
        $html = $this->render($this->post(), 'grid');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('src=""', $html);
    }

    public function test_the_compact_card_omits_the_category(): void
    {
        $category = BlogCategory::create(['name' => 'Environment']);
        $post = $this->post(['blog_category_id' => $category->id]);

        $html = $this->render($post->fresh(), 'compact');

        $this->assertStringContainsString('scbd-news-card-compact', $html);
        $this->assertStringContainsString('28.03.26', $html);
        $this->assertStringNotContainsString('scbd-news-card-category', $html);
    }

    public function test_thumbnails_are_lazy(): void
    {
        // Every published post renders at once, with no pagination.
        $post = $this->post(['featured_image' => 'uploads/news/earth-hour.jpg']);

        $html = $this->render($post, 'grid');

        $this->assertStringContainsString('loading="lazy"', $html);
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=NewsCardTest`
Expected: FAIL — `View [partials.site.news-card] not found`.

- [ ] **Step 3: Write the card partial**

Create `resources/views/partials/site/news-card.blade.php`:

```blade
@php
    /**
     * One card, two sizes. The column count is the caller's grid, not the
     * card's business — `grid` is fluid and serves both the index's 2-up and
     * the detail page's 4-up row.
     */
    $size = $size ?? 'grid';
    $image = \App\Support\MediaUrl::resolve($post->featured_image);
    $category = $post->category;
@endphp

<a class="scbd-news-card scbd-news-card-{{ $size }}"
   href="{{ route('news.show', $post->slug) }}"
   data-news-category="{{ $category?->slug }}">

    {{-- No image, no element. An empty src resolves against the current page
         and makes the browser request it a second time. --}}
    @if ($image)
        <span class="scbd-news-card-thumb">
            <img src="{{ $image }}"
                 alt="{{ $post->title }}"
                 loading="lazy"
                 class="grayscale">
        </span>
    @endif

    <span class="scbd-news-card-body">
        <span class="scbd-news-card-title">{{ $post->title }}</span>

        <span class="scbd-news-card-meta">
            <span class="scbd-news-card-date">{{ $post->published_at?->format('d.m.y') }}</span>

            @if ($size === 'grid' && $category)
                <span class="scbd-news-card-category">{{ $category->name }}</span>
            @endif
        </span>
    </span>
</a>
```

- [ ] **Step 4: Add the card styles**

Append to `resources/css/scbd.css`, before the `@media (max-width: 900px)` block at line 1115:

```css
/* News card ---------------------------------------------------------------
   One component, two sizes. `grid` stacks a 4:3 thumb over the text and is
   fluid, so the same card serves the index's two columns and the detail
   page's four. `compact` puts an 88px square beside the text for the sidebar.
   The grayscale-to-colour lift replaces the reference template's
   `hover-effect-1`, which is the same gesture in this site's idiom. */
.scbd-news-card {
  display: flex;
  text-decoration: none;
  color: #201e1d;
}

.scbd-news-card-grid {
  flex-direction: column;
  gap: 16px;
}

.scbd-news-card-compact {
  flex-direction: row;
  gap: 16px;
  align-items: flex-start;
  padding: 18px 0;
  border-bottom: 1px solid rgba(32, 30, 29, 0.18);
}

.scbd-news-card-thumb {
  display: block;
  overflow: hidden;
}

.scbd-news-card-grid .scbd-news-card-thumb { aspect-ratio: 4 / 3; }

.scbd-news-card-compact .scbd-news-card-thumb {
  flex: 0 0 88px;
  width: 88px;
  height: 88px;
}

.scbd-news-card-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: filter 600ms ease, transform 600ms ease;
}

.scbd-news-card:hover .scbd-news-card-thumb img {
  filter: none;
  transform: scale(1.04);
}

.scbd-news-card-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.scbd-news-card-title {
  text-transform: uppercase;
  letter-spacing: -0.02em;
  line-height: 1.12;
}

.scbd-news-card-grid .scbd-news-card-title { font-size: clamp(17px, 1.5vw, 22px); }
.scbd-news-card-compact .scbd-news-card-title { font-size: 14px; }

.scbd-news-card-meta {
  display: flex;
  gap: 12px;
  font-size: 12px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(32, 30, 29, 0.55);
}

.scbd-news-card-category { color: #ec3013; }
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --filter=NewsCardTest`
Expected: PASS, 6 tests.

- [ ] **Step 6: Prove the tests bite**

Temporarily change the partial's `@if ($image)` guard to `@if (true)`.

Run: `php artisan test --filter=NewsCardTest`
Expected: FAIL on `test_a_post_without_an_image_renders_no_img_tag`. Restore the guard.

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/site/news-card.blade.php resources/css/scbd.css tests/Feature/News/NewsCardTest.php
git commit -m "feat: a shared news card in grid and compact sizes"
```

---

### Task 5: The detail page layout

Replaces the placeholder view from Task 2 with the full design: breadcrumb, split heading, meta and share row, full-bleed hero, prose body, prev/next, and the Latest News row.

**Files:**
- Modify: `resources/views/news/show.blade.php`
- Modify: `resources/css/scbd.css`
- Test: `tests/Feature/News/NewsDetailPageTest.php`

**Interfaces:**
- Consumes: `$post`, `$previous`, `$next`, `$latest` from Task 3; the card partial from Task 4.
- Produces: nothing later tasks depend on.

`.scbd-prose` is referenced by `page.blade.php` but **has no rules anywhere in `scbd.css`** — it is an unstyled hook today. This task writes those rules, which benefits standard pages too.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsDetailPageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    private function post(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => 'Earth Hour 2026',
            'slug' => 'earth-hour-2026',
            'content' => '<p>Body copy.</p>',
            'excerpt' => 'Summary.',
            'featured_image' => 'uploads/news/earth-hour.jpg',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => '2026-03-28',
        ], $overrides));
    }

    public function test_the_breadcrumb_leads_back_to_the_index(): void
    {
        $this->post();

        $this->get('/news/earth-hour-2026')
            ->assertSuccessful()
            ->assertSee('href="'.route('page', 'news').'"', false);
    }

    public function test_the_heading_carries_the_split_hook(): void
    {
        // Without data-split the heading renders and never becomes visible.
        $this->post();

        $this->get('/news/earth-hour-2026')->assertSee('data-split', false);
    }

    public function test_the_hero_image_carries_the_reveal_hook(): void
    {
        $this->post();

        $this->get('/news/earth-hour-2026')->assertSee('data-reveal', false);
    }

    public function test_the_share_links_point_at_the_canonical_post_url(): void
    {
        $this->post();
        $encoded = urlencode(route('news.show', 'earth-hour-2026'));

        $response = $this->get('/news/earth-hour-2026')->assertSuccessful();

        $response->assertSee('linkedin.com/sharing/share-offsite/?url='.$encoded, false);
        $response->assertSee('facebook.com/sharer/sharer.php?u='.$encoded, false);
        $response->assertSee('twitter.com/intent/tweet?url='.$encoded, false);
    }

    public function test_the_body_renders_as_html_inside_the_prose_wrapper(): void
    {
        $this->post(['content' => '<p>First paragraph.</p><p>Second paragraph.</p>']);

        $this->get('/news/earth-hour-2026')
            ->assertSee('scbd-prose', false)
            ->assertSee('<p>Second paragraph.</p>', false);
    }

    public function test_the_category_shows_beside_the_date(): void
    {
        $category = BlogCategory::create(['name' => 'Environment']);
        $this->post(['blog_category_id' => $category->id]);

        $this->get('/news/earth-hour-2026')
            ->assertSee('28.03.26', false)
            ->assertSee('Environment', false);
    }

    public function test_prev_and_next_render_their_neighbours_titles(): void
    {
        $this->post();
        BlogPost::create([
            'title' => 'Older Story', 'slug' => 'older-story', 'content' => '<p>x</p>',
            'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => '2025-01-01',
        ]);
        BlogPost::create([
            'title' => 'Newer Story', 'slug' => 'newer-story', 'content' => '<p>x</p>',
            'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => '2026-07-23',
        ]);

        $this->get('/news/earth-hour-2026')
            ->assertSee('Older Story', false)
            ->assertSee('Newer Story', false);
    }

    public function test_a_lone_post_renders_no_prev_or_next_affordance(): void
    {
        $this->post();

        $this->get('/news/earth-hour-2026')
            ->assertDontSee('scbd-news-nav-prev', false)
            ->assertDontSee('scbd-news-nav-next', false);
    }

    public function test_the_latest_row_is_omitted_when_there_are_no_other_posts(): void
    {
        // A heading over an empty row reads as a rendering fault.
        $this->post();

        $this->get('/news/earth-hour-2026')->assertDontSee('LATEST NEWS', false);
    }

    public function test_the_latest_row_appears_when_other_posts_exist(): void
    {
        $this->post();
        BlogPost::create([
            'title' => 'Another Story', 'slug' => 'another-story', 'content' => '<p>x</p>',
            'status' => BlogPost::STATUS_PUBLISHED, 'published_at' => '2025-01-01',
        ]);

        $this->get('/news/earth-hour-2026')
            ->assertSee('LATEST NEWS', false)
            ->assertSee('Another Story', false);
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=NewsDetailPageTest`
Expected: FAIL on the breadcrumb, share, hero and latest-row tests — the placeholder view from Task 2 has none of them.

- [ ] **Step 3: Write the full detail view**

Replace the whole of `resources/views/news/show.blade.php`:

```blade
@php
    $settings = \App\Models\SiteSetting::singleton();
    $suffix = $settings->site_name ? ' — '.$settings->site_name : '';

    $hero = \App\Support\MediaUrl::resolve($post->featured_image);
    $canonical = route('news.show', $post->slug);
    $shareUrl = urlencode($canonical);
    $shareText = urlencode($post->title);
@endphp

<x-layouts.page
    :title="($post->seo_title ?: $post->title).$suffix"
    :description="$post->seo_description ?: $post->excerpt"
    :animated="true"
    :show-loader="false"
    :i18n="[]">

    @include('partials.site.header')

    <main class="scbd-shade" style="min-height:50vh;">
        <article class="scbd-pad-top scbd-news-detail">

            <nav class="scbd-news-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('page', 'news') }}">News</a>
                <span aria-hidden="true">/</span>
                <span>{{ $post->title }}</span>
            </nav>

            <h1 data-split class="scbd-h2 scbd-news-detail-title">{{ $post->title }}</h1>

            <div class="scbd-news-detail-meta">
                <div class="scbd-news-detail-facts">
                    <span>{{ $post->published_at?->format('d.m.y') }}</span>
                    @if ($post->category)
                        <span class="scbd-news-detail-category">{{ $post->category->name }}</span>
                    @endif
                </div>

                <div class="scbd-news-share">
                    <span class="scbd-news-share-label">Share this article</span>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}"
                       target="_blank" rel="noopener noreferrer" data-magnetic aria-label="Share on LinkedIn">LI</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"
                       target="_blank" rel="noopener noreferrer" data-magnetic aria-label="Share on Facebook">FB</a>
                    <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}"
                       target="_blank" rel="noopener noreferrer" data-magnetic aria-label="Share on X">X</a>
                </div>
            </div>

            @if ($hero)
                <figure class="scbd-news-hero">
                    <img data-reveal src="{{ $hero }}" alt="{{ $post->title }}" class="grayscale">
                </figure>
            @endif

            {{-- Stored as sanitised HTML by the post editor. --}}
            <div class="scbd-prose scbd-news-body">{!! $post->content !!}</div>

            @if ($previous || $next)
                <nav class="scbd-news-nav" aria-label="More posts">
                    @if ($previous)
                        <a class="scbd-news-nav-prev" href="{{ route('news.show', $previous->slug) }}">
                            <span class="scbd-news-nav-label">← Prev</span>
                            <span class="scbd-news-nav-title">{{ $previous->title }}</span>
                        </a>
                    @endif

                    @if ($next)
                        <a class="scbd-news-nav-next" href="{{ route('news.show', $next->slug) }}">
                            <span class="scbd-news-nav-label">Next →</span>
                            <span class="scbd-news-nav-title">{{ $next->title }}</span>
                        </a>
                    @endif
                </nav>
            @endif
        </article>

        {{-- A heading over an empty row reads as a rendering fault, so the
             whole section goes when there is nothing to put in it. --}}
        @if ($latest->isNotEmpty())
            <section class="scbd-pad scbd-news-latest">
                <h2 class="scbd-news-latest-heading">LATEST NEWS</h2>

                <div class="scbd-news-latest-grid">
                    @foreach ($latest as $item)
                        @include('partials.site.news-card', ['post' => $item, 'size' => 'grid'])
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    @include('partials.site.footer')
</x-layouts.page>
```

- [ ] **Step 4: Add the detail-page and prose styles**

Append to `resources/css/scbd.css`, before the `@media (max-width: 900px)` block:

```css
/* Post detail -------------------------------------------------------------
   The article column is centred and narrow; the hero image breaks out of it
   full-bleed, which is the one place this page ignores the measure. */
.scbd-news-detail {
  max-width: 820px;
  margin: 0 auto;
  box-sizing: border-box;
}

.scbd-news-breadcrumb {
  display: flex;
  gap: 10px;
  font-size: 12px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(32, 30, 29, 0.55);
  margin-bottom: 24px;
}

.scbd-news-breadcrumb a { color: #ec3013; text-decoration: none; }

.scbd-news-detail-title {
  font-size: clamp(30px, 4vw, 56px);
  line-height: 1.02;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  margin: 0;
}

.scbd-news-detail-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  justify-content: space-between;
  align-items: flex-end;
  padding: 28px 0;
  border-bottom: 2px solid rgba(32, 30, 29, 0.4);
}

.scbd-news-detail-facts,
.scbd-news-share {
  display: flex;
  align-items: center;
  gap: 16px;
  font-size: 12px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(32, 30, 29, 0.55);
}

.scbd-news-detail-category { color: #ec3013; }
.scbd-news-share a { color: #201e1d; text-decoration: none; }
.scbd-news-share a:hover { color: #ec3013; }

/* Breaks the 820px measure deliberately: the hero is the page's one
   full-width moment. */
.scbd-news-hero {
  margin: 48px calc(50% - 50vw);
  width: 100vw;
  overflow: hidden;
}

.scbd-news-hero img { width: 100%; display: block; }

.scbd-news-nav {
  display: flex;
  justify-content: space-between;
  gap: 32px;
  padding-top: 48px;
  border-top: 2px solid rgba(32, 30, 29, 0.4);
}

.scbd-news-nav-next { margin-left: auto; text-align: right; }

.scbd-news-nav a,
.scbd-news-nav-prev,
.scbd-news-nav-next {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 42%;
  text-decoration: none;
  color: #201e1d;
}

.scbd-news-nav-label {
  font-size: 12px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #ec3013;
}

.scbd-news-nav-title { text-transform: uppercase; line-height: 1.15; }

.scbd-news-latest-heading {
  font-size: 12px;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: #ec3013;
  margin: 0 0 32px;
  padding-bottom: 20px;
  border-bottom: 2px solid rgba(32, 30, 29, 0.4);
}

.scbd-news-latest-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 28px;
}

/* Prose -------------------------------------------------------------------
   Referenced by page.blade.php since standard pages were introduced but never
   styled, so editor HTML has been rendering at browser defaults. Written here
   because the post body needs it; standard pages get it too. */
.scbd-prose {
  font-size: 16px;
  line-height: 1.75;
  color: rgba(32, 30, 29, 0.85);
}

.scbd-prose > * + * { margin-top: 1.2em; }
.scbd-prose p { margin: 0; }

.scbd-prose h2,
.scbd-prose h3 {
  text-transform: uppercase;
  letter-spacing: -0.02em;
  line-height: 1.15;
  color: #201e1d;
  margin: 2em 0 0;
}

.scbd-prose h2 { font-size: clamp(22px, 2.4vw, 30px); }
.scbd-prose h3 { font-size: clamp(18px, 1.8vw, 22px); }
.scbd-prose a { color: #ec3013; }
.scbd-prose img { max-width: 100%; height: auto; display: block; }
.scbd-prose ul, .scbd-prose ol { padding-left: 1.4em; }
.scbd-prose li + li { margin-top: 0.4em; }

.scbd-prose figure { margin: 2.4em 0; }

/* The reference's two-up image row, which the importer emits between the
   opening paragraphs. Collapses to one column with the rest of the page. */
.scbd-prose-pair {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin: 2.4em 0;
}

.scbd-prose figcaption {
  text-align: center;
  font-style: italic;
  font-size: 13px;
  color: rgba(32, 30, 29, 0.6);
  margin-top: 10px;
}

.scbd-prose blockquote {
  margin: 2em 0;
  padding-left: 24px;
  border-left: 2px solid #ec3013;
  font-size: 18px;
}
```

and inside the existing `@media (max-width: 900px)` block:

```css
  .scbd-news-latest-grid { grid-template-columns: 1fr 1fr !important; gap: 20px !important; }
  .scbd-prose-pair { grid-template-columns: 1fr !important; }
  .scbd-news-nav { flex-direction: column !important; gap: 28px !important; }
  .scbd-news-nav-next { margin-left: 0 !important; text-align: left !important; }
  .scbd-news-nav a, .scbd-news-nav-prev, .scbd-news-nav-next { max-width: 100% !important; }
  .scbd-news-detail-meta { align-items: flex-start !important; }
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --filter=NewsDetailPageTest`
Expected: PASS, 10 tests.

- [ ] **Step 6: Prove the tests bite**

Temporarily change `@if ($latest->isNotEmpty())` to `@if (true)`.

Run: `php artisan test --filter=NewsDetailPageTest`
Expected: FAIL on `test_the_latest_row_is_omitted_when_there_are_no_other_posts`. Restore it.

- [ ] **Step 7: Build and check in a browser**

Run: `npm run build`
Expected: succeeds, no errors.

Then load a post in the browser (the chrome-devtools MCP tools are available; use `navigate_page` then `take_screenshot`). Confirm: the heading animates in character by character, the hero image wipes open on scroll, the hero spans the full viewport width with no horizontal scrollbar on the page body, and share links open the right networks.

- [ ] **Step 8: Commit**

```bash
git add resources/views/news/show.blade.php resources/css/scbd.css tests/Feature/News/NewsDetailPageTest.php
git commit -m "feat: the post detail page, and prose styles it turns out nothing had"
```

---

### Task 6: The index block definition

**Files:**
- Create: `app/PageBuilder/Blocks/NewsIndexBlock.php`
- Modify: `app/Providers/PageBuilderServiceProvider.php`
- Test: `tests/Feature/News/NewsIndexBlockTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `NewsIndexBlock::type()` returns `'scbd_news_index'`; `renderView()` resolves to `partials.blocks.scbd-news-index` by the `BaseBlock` convention. Data keys, which Task 7's view reads through `BlockData`: `eyebrow`, `heading`, `empty_text`, `sidebar_heading` (all translatable maps), `show_filters` (bool), `sidebar_limit` (int).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\News;

use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Tests\TestCase;

class NewsIndexBlockTest extends TestCase
{
    public function test_the_block_is_registered(): void
    {
        // Registration is explicit, so a class in the directory is not enough.
        $registry = app(BlockRegistry::class);

        $this->assertTrue($registry->has('scbd_news_index'));
        $this->assertSame(NewsIndexBlock::class, $registry->get('scbd_news_index'));
    }

    public function test_its_render_view_exists(): void
    {
        $this->assertSame(
            'partials.blocks.scbd-news-index',
            NewsIndexBlock::renderView(),
        );

        $this->assertTrue(view()->exists(NewsIndexBlock::renderView()));
    }

    public function test_every_translatable_key_has_a_default(): void
    {
        // A key the editor writes but defaultData omits comes back null on a
        // freshly added block, and the view then renders nothing for it.
        $defaults = NewsIndexBlock::defaultData();

        foreach (NewsIndexBlock::translatableKeys() as $key) {
            $this->assertArrayHasKey($key, $defaults);
        }

        $this->assertSame(true, $defaults['show_filters']);
        $this->assertSame(5, $defaults['sidebar_limit']);
    }

    public function test_the_translatable_keys_are_the_copy_fields(): void
    {
        $this->assertSame(
            ['eyebrow', 'heading', 'empty_text', 'sidebar_heading'],
            NewsIndexBlock::translatableKeys(),
        );
    }

    public function test_it_offers_a_schema(): void
    {
        $this->assertNotEmpty(NewsIndexBlock::schema());
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=NewsIndexBlockTest`
Expected: FAIL — `Class "App\PageBuilder\Blocks\NewsIndexBlock" not found`.

- [ ] **Step 3: Write the block**

Create `app/PageBuilder/Blocks/NewsIndexBlock.php`:

```php
<?php

namespace App\PageBuilder\Blocks;

use App\Filament\Support\LocaleTabs;
use App\PageBuilder\BaseBlock;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * The News landing page's whole body.
 *
 * A block rather than a dedicated route so the page keeps its URL from the
 * ordinary page catch-all and its copy stays editable, like every other page
 * on this site. The posts themselves come from the blog tables and are
 * administered separately — nothing about them is configured here.
 */
class NewsIndexBlock extends BaseBlock
{
    public static function type(): string
    {
        return 'scbd_news_index';
    }

    public static function name(): string
    {
        return 'News index';
    }

    public static function icon(): string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public static function schema(): array
    {
        return [
            LocaleTabs::make(fn (string $locale): array => [
                TextInput::make("eyebrow.{$locale}")->label('Eyebrow')->maxLength(60),
                Textarea::make("heading.{$locale}")->label('Heading')->rows(2)->required(LocaleTabs::isFallback($locale)),
                TextInput::make("empty_text.{$locale}")->label('Text when there are no posts')->maxLength(160),
                TextInput::make("sidebar_heading.{$locale}")->label('Sidebar heading')->maxLength(60),
            ]),
            Toggle::make('show_filters')
                ->label('Show category filters')
                ->default(true),
            TextInput::make('sidebar_limit')
                ->label('Posts in the sidebar')
                ->numeric()
                ->minValue(1)
                ->maxValue(10)
                ->default(5),
        ];
    }

    public static function defaultData(): array
    {
        return [
            'eyebrow' => [],
            'heading' => [],
            'empty_text' => [],
            'sidebar_heading' => [],
            'show_filters' => true,
            'sidebar_limit' => 5,
        ];
    }

    public static function translatableKeys(): array
    {
        return ['eyebrow', 'heading', 'empty_text', 'sidebar_heading'];
    }
}
```

- [ ] **Step 4: Register it**

In `app/Providers/PageBuilderServiceProvider.php`, add to the explicit list immediately after `Blocks\NewsBlock::class,`:

```php
                Blocks\NewsIndexBlock::class,
```

- [ ] **Step 5: Create a stub view so the registration test can pass**

Create `resources/views/partials/blocks/scbd-news-index.blade.php` containing only:

```blade
{{-- Built in the next task. --}}
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --filter=NewsIndexBlockTest`
Expected: PASS, 5 tests.

Run: `php artisan test --filter=BlockRegistryTest`
Expected: PASS — the existing registry test must not be disturbed by a new entry. If it asserts an exact block count or list, update it to include `scbd_news_index`.

- [ ] **Step 7: Commit**

```bash
git add app/PageBuilder/Blocks/NewsIndexBlock.php app/Providers/PageBuilderServiceProvider.php resources/views/partials/blocks/scbd-news-index.blade.php tests/Feature/News/NewsIndexBlockTest.php
git commit -m "feat: register a news index block"
```

---

### Task 7: The index block view

**Files:**
- Modify: `resources/views/partials/blocks/scbd-news-index.blade.php`
- Modify: `resources/css/scbd.css`
- Test: `tests/Feature/News/NewsIndexRenderTest.php`

**Interfaces:**
- Consumes: the block from Task 6, the card partial from Task 4.
- Produces: the markup contract Task 8's filter binds to — a container with `data-news-filter`, chips as `<button data-news-filter-chip="{slug}">` (the All chip uses an empty value), and cards carrying `data-news-category` inside an element with `data-news-grid`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogCategory;
use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\Page;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsIndexRenderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    private function indexPage(array $data = []): Page
    {
        return Page::create([
            'title' => ['en' => 'News'],
            'slug' => 'news',
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => NewsIndexBlock::type(),
                'data' => array_merge([
                    'eyebrow' => ['en' => 'Newsroom'],
                    'heading' => ['en' => 'News'],
                    'empty_text' => ['en' => 'Nothing published yet.'],
                    'sidebar_heading' => ['en' => 'Flash news'],
                    'show_filters' => true,
                    'sidebar_limit' => 5,
                ], $data),
                'children' => null,
            ]],
        ]);
    }

    private function post(string $title, string $date, array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => $date,
        ], $overrides));
    }

    public function test_published_posts_are_listed_newest_first(): void
    {
        $this->indexPage();
        $this->post('Older Story', '2025-01-01');
        $this->post('Newer Story', '2026-07-23');

        $html = $this->get('/news')->assertSuccessful()->getContent();

        $this->assertLessThan(
            strpos($html, 'Older Story'),
            strpos($html, 'Newer Story'),
            'The newest post should appear before the older one.',
        );
    }

    public function test_a_draft_post_is_not_listed(): void
    {
        $this->indexPage();
        $this->post('Hidden Story', '2026-01-01', ['status' => BlogPost::STATUS_DRAFT]);

        $this->get('/news')->assertDontSee('Hidden Story', false);
    }

    public function test_a_post_dated_in_the_future_is_not_listed(): void
    {
        $this->indexPage();
        $this->post('Scheduled Story', now()->addWeek()->toDateString());

        $this->get('/news')->assertDontSee('Scheduled Story', false);
    }

    public function test_the_empty_state_shows_and_the_sidebar_is_omitted(): void
    {
        $this->indexPage();

        $this->get('/news')
            ->assertSuccessful()
            ->assertSee('Nothing published yet.', false)
            ->assertDontSee('Flash news', false);
    }

    public function test_the_sidebar_honours_its_limit(): void
    {
        $this->indexPage(['sidebar_limit' => 2]);
        $this->post('One Story', '2026-01-01');
        $this->post('Two Story', '2026-02-01');
        $this->post('Three Story', '2026-03-01');

        $html = $this->get('/news')->getContent();

        // Three cards in the grid; two in the sidebar.
        $this->assertSame(2, substr_count($html, 'scbd-news-card-compact'));
        $this->assertSame(3, substr_count($html, 'scbd-news-card-grid'));
    }

    public function test_chips_are_rendered_for_categories_that_have_posts(): void
    {
        $this->indexPage();
        $used = BlogCategory::create(['name' => 'Environment']);
        BlogCategory::create(['name' => 'Unused Category']);
        $this->post('Earth Hour', '2026-03-28', ['blog_category_id' => $used->id]);

        $response = $this->get('/news')->assertSuccessful();

        $response->assertSee('data-news-filter-chip="environment"', false);
        // An empty chip is a dead end — it would filter to nothing.
        $response->assertDontSee('data-news-filter-chip="unused-category"', false);
    }

    public function test_the_all_chip_carries_an_empty_value(): void
    {
        $this->indexPage();
        $category = BlogCategory::create(['name' => 'Environment']);
        $this->post('Earth Hour', '2026-03-28', ['blog_category_id' => $category->id]);

        $this->get('/news')->assertSee('data-news-filter-chip=""', false);
    }

    public function test_chips_can_be_switched_off(): void
    {
        $this->indexPage(['show_filters' => false]);
        $category = BlogCategory::create(['name' => 'Environment']);
        $this->post('Earth Hour', '2026-03-28', ['blog_category_id' => $category->id]);

        $this->get('/news')->assertDontSee('data-news-filter-chip', false);
    }

    public function test_the_heading_carries_the_split_hook_and_an_i18n_key(): void
    {
        $this->indexPage();

        $this->get('/news')
            ->assertSee('data-split', false)
            ->assertSee('data-i18n="b1_heading"', false);
    }
}
```

`b1_heading` is what `BlockData::i18nKey('block_1', 'heading')` returns — it strips the `block_` prefix and prefixes `b`. Verified by running it, not inferred.

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=NewsIndexRenderTest`
Expected: FAIL — the stub view renders nothing, so no post title, chip or empty text appears.

- [ ] **Step 3: Write the index view**

Replace `resources/views/partials/blocks/scbd-news-index.blade.php`:

```blade
@php
    use App\PageBuilder\BlockData;
    use AjayDhakal\FilamentStory\Models\BlogPost;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $eyebrow = BlockData::t($data, 'eyebrow', $locale);
    $heading = BlockData::t($data, 'heading', $locale);
    $emptyText = BlockData::t($data, 'empty_text', $locale);
    $sidebarHeading = BlockData::t($data, 'sidebar_heading', $locale);
    $showFilters = (bool) BlockData::get($data, 'show_filters', true);
    $sidebarLimit = max(1, (int) BlockData::get($data, 'sidebar_limit', 5));

    // Published means published now: a post dated in the future is scheduled,
    // not live, and must not appear here any more than it is reachable by URL.
    $posts = BlogPost::query()
        ->with('category')
        ->where('status', BlogPost::STATUS_PUBLISHED)
        ->where('published_at', '<=', now())
        ->orderByDesc('published_at')
        ->get();

    // Only categories that actually have a published post. A chip that filters
    // to nothing is a dead end the reader cannot tell from a broken one.
    $categories = $posts->pluck('category')->filter()->unique('id')->sortBy('name')->values();

    $sidebarPosts = $posts->take($sidebarLimit);
@endphp

<section id="news" class="scbd-pad-top scbd-news-index" data-news-filter>

    <div class="scbd-news-index-head">
        <div>
            @if ($eyebrow)
                <div class="scbd-news-index-eyebrow" data-i18n="{{ BlockData::i18nKey($blockId, 'eyebrow') }}">{{ $eyebrow }}</div>
            @endif

            <h1 data-split class="scbd-h2 scbd-news-index-heading" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h1>
        </div>

        @if ($showFilters && $categories->isNotEmpty())
            {{-- Buttons, not links: filtering happens in place and changes
                 nothing about the document's address. --}}
            <div class="scbd-news-chips" role="group" aria-label="Filter by category">
                <button type="button" class="scbd-news-chip" data-news-filter-chip="" data-magnetic aria-pressed="true">All</button>

                @foreach ($categories as $category)
                    <button type="button" class="scbd-news-chip" data-news-filter-chip="{{ $category->slug }}" data-magnetic aria-pressed="false">{{ $category->name }}</button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="scbd-news-index-body">
        <div class="scbd-news-index-grid" data-news-grid>
            @forelse ($posts as $post)
                @include('partials.site.news-card', ['post' => $post, 'size' => 'grid'])
            @empty
                <p class="scbd-news-index-empty" data-i18n="{{ BlockData::i18nKey($blockId, 'empty_text') }}">{{ $emptyText }}</p>
            @endforelse
        </div>

        {{-- No posts, no sidebar: a heading over an empty column reads as a
             rendering fault rather than an empty archive. --}}
        @if ($sidebarPosts->isNotEmpty())
            <aside class="scbd-news-index-side">
                @if ($sidebarHeading)
                    <h2 class="scbd-news-index-side-heading" data-i18n="{{ BlockData::i18nKey($blockId, 'sidebar_heading') }}">{{ $sidebarHeading }}</h2>
                @endif

                @foreach ($sidebarPosts as $post)
                    @include('partials.site.news-card', ['post' => $post, 'size' => 'compact'])
                @endforeach
            </aside>
        @endif
    </div>
</section>
```

- [ ] **Step 4: Add the index styles**

Append to `resources/css/scbd.css`, before the `@media (max-width: 900px)` block:

```css
/* News index --------------------------------------------------------------
   The 8/4 split of the reference layout, as a two-column grid. The sidebar is
   sticky because the left column is much the taller of the two. */
.scbd-news-index-head {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
  align-items: flex-end;
  justify-content: space-between;
  padding-bottom: 24px;
  border-bottom: 2px solid rgba(32, 30, 29, 0.4);
}

.scbd-news-index-eyebrow {
  font-size: 11px;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: #ec3013;
  margin-bottom: 16px;
}

.scbd-news-index-heading {
  font-size: clamp(34px, 4.4vw, 66px);
  line-height: 0.98;
  letter-spacing: -0.035em;
  text-transform: uppercase;
  margin: 0;
}

.scbd-news-chips { display: flex; flex-wrap: wrap; gap: 8px; }

.scbd-news-chip {
  font: inherit;
  font-size: 11px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  padding: 10px 18px;
  background: transparent;
  color: #201e1d;
  border: 1px solid rgba(32, 30, 29, 0.4);
  cursor: none;
  transition: background 240ms ease, color 240ms ease, border-color 240ms ease;
}

.scbd-news-chip:hover { border-color: #201e1d; }

.scbd-news-chip[aria-pressed='true'] {
  background: #ec3013;
  border-color: #ec3013;
  color: #f3f2f2;
}

.scbd-news-index-body {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 56px;
  padding-top: 48px;
}

.scbd-news-index-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px 32px;
  align-content: start;
}

.scbd-news-index-empty {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 15px;
  color: rgba(32, 30, 29, 0.65);
}

.scbd-news-index-side {
  position: sticky;
  top: 120px;
  align-self: start;
}

.scbd-news-index-side-heading {
  font-size: 11px;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgba(32, 30, 29, 0.55);
  margin: 0 0 8px;
}

/* The filter's only rule. Everything else is Flip reading and replaying
   positions. */
.is-hidden { display: none; }
```

and inside the existing `@media (max-width: 900px)` block:

```css
  .scbd-news-index-body { grid-template-columns: 1fr !important; gap: 48px !important; }
  .scbd-news-index-grid { grid-template-columns: 1fr !important; gap: 32px !important; }
  .scbd-news-index-side { position: static !important; }
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --filter=NewsIndexRenderTest`
Expected: PASS, 9 tests.

- [ ] **Step 6: Prove the tests bite**

Temporarily remove `->where('published_at', '<=', now())` from the view's query.

Run: `php artisan test --filter=NewsIndexRenderTest`
Expected: FAIL on `test_a_post_dated_in_the_future_is_not_listed`. Restore it.

Then temporarily change `$posts->pluck('category')->filter()` to `\AjayDhakal\FilamentStory\Models\BlogCategory::all()`.

Run: `php artisan test --filter=NewsIndexRenderTest`
Expected: FAIL on `test_chips_are_rendered_for_categories_that_have_posts`. Restore it.

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/blocks/scbd-news-index.blade.php resources/css/scbd.css tests/Feature/News/NewsIndexRenderTest.php
git commit -m "feat: the news index grid, sidebar and category chips"
```

---

### Task 8: The Flip category filter

**Files:**
- Create: `resources/js/scbd/newsFilter.js`
- Modify: `resources/js/scbd/index.js`
- Test: `tests/Feature/News/NewsFilterContractTest.php`

**Interfaces:**
- Consumes: the markup contract from Task 7 — `[data-news-filter]`, `[data-news-grid]`, `[data-news-filter-chip]`, `[data-news-category]`; and `.is-hidden` from Task 7's CSS.
- Produces: `initNewsFilter(gsap, Flip, ScrollTrigger)`, called from `index.js`.

There is no JS test runner here. Verification is three-part: a PHP test pinning the markup contract the module binds to, a successful `npm run build`, and a browser check.

- [ ] **Step 1: Write the failing contract test**

```php
<?php

namespace Tests\Feature\News;

use Tests\TestCase;

class NewsFilterContractTest extends TestCase
{
    /**
     * The filter module binds to attributes rather than classes, and nothing
     * in PHP would fail if a rename silently broke that binding — the page
     * would render perfectly and the chips would simply stop working. This
     * pins both halves of the contract in one place.
     */
    public function test_the_module_and_the_markup_agree_on_every_hook(): void
    {
        $module = file_get_contents(base_path('resources/js/scbd/newsFilter.js'));
        $view = file_get_contents(base_path('resources/views/partials/blocks/scbd-news-index.blade.php'));
        $card = file_get_contents(base_path('resources/views/partials/site/news-card.blade.php'));
        $markup = $view.$card;

        foreach (['data-news-filter', 'data-news-grid', 'data-news-filter-chip', 'data-news-category'] as $hook) {
            $this->assertStringContainsString($hook, $module, "newsFilter.js does not reference {$hook}");
            $this->assertStringContainsString($hook, $markup, "No view emits {$hook}");
        }

        $this->assertStringContainsString('is-hidden', $module);
        $this->assertStringContainsString('.is-hidden', file_get_contents(base_path('resources/css/scbd.css')));
    }

    public function test_the_module_is_wired_into_the_bundle(): void
    {
        // A module nobody imports is dead code that still passes every test.
        $index = file_get_contents(base_path('resources/js/scbd/index.js'));

        $this->assertStringContainsString("from './newsFilter'", $index);
        $this->assertStringContainsString('initNewsFilter(', $index);
    }

    public function test_reduced_motion_still_filters(): void
    {
        // The chips are function, not decoration. Under reduced motion the
        // class toggle must still run — only the tween is skipped.
        $module = file_get_contents(base_path('resources/js/scbd/newsFilter.js'));

        $this->assertStringContainsString('prefersReducedMotion', $module);
    }
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `php artisan test --filter=NewsFilterContractTest`
Expected: FAIL — `file_get_contents` on `newsFilter.js` warns and returns false, so the first assertion fails.

- [ ] **Step 3: Write the module**

Create `resources/js/scbd/newsFilter.js`:

```js
import { prefersReducedMotion } from './motion';

/**
 * Category filtering for the news index.
 *
 * The reference template does this with isotope; Flip does the same job with
 * what is already in the bundle. Flip reads every card's position before the
 * change, lets the browser re-flow, then animates each card from where it was
 * to where it now is — so the survivors glide into their new places instead of
 * snapping.
 *
 * The chips are function, not decoration: under reduced motion the class
 * toggle still runs and only the tween is skipped, which is how every other
 * module here degrades.
 */
export function initNewsFilter(gsap, Flip, ScrollTrigger) {
  const root = document.querySelector('[data-news-filter]');
  const grid = root?.querySelector('[data-news-grid]');

  // Every other page pays nothing.
  if (!root || !grid) {
    return;
  }

  const chips = Array.from(root.querySelectorAll('[data-news-filter-chip]'));
  const cards = Array.from(grid.querySelectorAll('[data-news-category]'));

  if (chips.length === 0 || cards.length === 0) {
    return;
  }

  const reduced = prefersReducedMotion();

  const apply = (slug) => {
    // Captured before anything moves: Flip needs the old geometry to animate
    // from, and reading it after the class toggle would capture the new one.
    const state = reduced ? null : Flip.getState(cards);

    cards.forEach((card) => {
      const matches = slug === '' || card.getAttribute('data-news-category') === slug;
      card.classList.toggle('is-hidden', !matches);
    });

    chips.forEach((chip) => {
      chip.setAttribute('aria-pressed', String(chip.getAttribute('data-news-filter-chip') === slug));
    });

    if (state) {
      Flip.from(state, {
        duration: 0.6,
        stagger: 0.03,
        ease: 'power3.out',
        // Cards leaving and arriving overlap during the tween; taking them out
        // of flow stops the ones that remain from being shoved about mid-flight.
        absolute: true,
        onEnter: (elements) => gsap.fromTo(elements, { opacity: 0, scale: 0.94 }, { opacity: 1, scale: 1, duration: 0.4 }),
        onLeave: (elements) => gsap.to(elements, { opacity: 0, scale: 0.94, duration: 0.3 }),
        // The page just got shorter or taller, so every trigger below it is
        // now measuring against stale positions.
        onComplete: () => ScrollTrigger.refresh(),
      });

      return;
    }

    ScrollTrigger.refresh();
  };

  chips.forEach((chip) => {
    chip.addEventListener('click', () => apply(chip.getAttribute('data-news-filter-chip') ?? ''));
  });
}
```

- [ ] **Step 4: Wire it into the bundle**

In `resources/js/scbd/index.js`, add the import beside the other module imports:

```js
import { initNewsFilter } from './newsFilter';
```

and call it inside `initScbd()`, immediately after the existing `initNewsHover(gsap);` line — **outside** the `gsap.context()` block and outside the `if (!reduced)` branch, because the filter must work in both motion modes:

```js
  // Outside the reduced-motion branch: the chips are how the archive is
  // browsed, not an effect. Only the Flip tween is motion.
  initNewsFilter(gsap, Flip, ScrollTrigger);
```

`Flip` is already imported and registered at the top of `index.js`; no new import is needed for it.

- [ ] **Step 5: Run the test**

Run: `php artisan test --filter=NewsFilterContractTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Build**

Run: `npm run build`
Expected: succeeds with no errors and no unresolved-import warnings for `./newsFilter`.

- [ ] **Step 7: Verify in a browser**

With posts imported (or seeded by hand), load `/news` and use the chrome-devtools MCP tools:

1. `navigate_page` to `/news`, then `take_snapshot`.
2. `click` the "Environment" chip.
3. `take_screenshot` — only Environment cards remain, they have glided rather than jumped, and the chip is filled red.
4. `list_console_messages` — no errors.
5. `click` the "All" chip — every card returns.
6. Scroll to the footer and confirm the reveal footer still uncovers correctly after filtering, which is what the `ScrollTrigger.refresh()` exists for.

- [ ] **Step 8: Commit**

```bash
git add resources/js/scbd/newsFilter.js resources/js/scbd/index.js tests/Feature/News/NewsFilterContractTest.php
git commit -m "feat: instant category filtering with GSAP Flip"
```

---

### Task 9: Parsing and importing the scbd.com archive

Split in two: `ScbdNewsParser` turns HTML into arrays with no network and no database, so it can be tested against saved fixtures; `ImportScbdNews` does the fetching, downloading and writing.

**Files:**
- Create: `app/Support/ScbdNewsParser.php`
- Create: `app/Console/Commands/ImportScbdNews.php`
- Create: `tests/Fixtures/scbd-news/listing-page-1.html`
- Create: `tests/Fixtures/scbd-news/detail-earth-hour.html`
- Test: `tests/Feature/News/ScbdNewsParserTest.php`
- Test: `tests/Feature/News/ImportScbdNewsTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces:
  - `ScbdNewsParser::listing(string $html): array` → a list of `['title' => string, 'date' => string (Y-m-d), 'cover' => ?string (absolute URL), 'url' => string (absolute URL)]`
  - `ScbdNewsParser::detail(string $html): array` → `['body' => string (HTML), 'images' => string[] (absolute URLs)]`
  - `php artisan news:import-scbd {--limit=} {--dry-run}`

- [ ] **Step 1: Save the fixtures**

```bash
mkdir -p tests/Fixtures/scbd-news
curl -sL -A "Mozilla/5.0" -o tests/Fixtures/scbd-news/listing-page-1.html "https://scbd.com/menu/page/news"
curl -sL -A "Mozilla/5.0" -o tests/Fixtures/scbd-news/detail-earth-hour.html "$(php -r '
  $h = file_get_contents("tests/Fixtures/scbd-news/listing-page-1.html");
  preg_match_all("#https://scbd\.com/menu/detail/news/[a-f0-9-]+#", $h, $m);
  echo $m[0][1] ?? $m[0][0];
')"
wc -c tests/Fixtures/scbd-news/*.html
```

Expected: both files are tens of kilobytes, not a few hundred bytes (a few hundred means the fetch was blocked and the fixture is useless).

- [ ] **Step 2: Write the failing parser test**

```php
<?php

namespace Tests\Feature\News;

use App\Support\ScbdNewsParser;
use Tests\TestCase;

class ScbdNewsParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/scbd-news/{$name}.html"));
    }

    public function test_it_finds_every_post_on_a_listing_page(): void
    {
        // The source paginates four to a page.
        $posts = ScbdNewsParser::listing($this->fixture('listing-page-1'));

        $this->assertCount(4, $posts);
    }

    public function test_it_reads_a_posts_title_date_cover_and_url(): void
    {
        $posts = ScbdNewsParser::listing($this->fixture('listing-page-1'));
        $first = $posts[0];

        $this->assertStringContainsString("National Children's Day", $first['title']);
        $this->assertSame('2026-07-23', $first['date']);
        $this->assertStringStartsWith('https://scbd.com/', $first['cover']);
        $this->assertStringStartsWith('https://scbd.com/menu/detail/news/', $first['url']);
    }

    public function test_titles_come_back_decoded(): void
    {
        // The source emits &#039; for apostrophes; storing that literally would
        // render as &amp;#039; once Blade escapes it.
        $posts = ScbdNewsParser::listing($this->fixture('listing-page-1'));

        $this->assertStringNotContainsString('&#039;', $posts[0]['title']);
        $this->assertStringNotContainsString('&amp;', $posts[0]['title']);
    }

    public function test_it_extracts_a_detail_body_and_its_images(): void
    {
        $detail = ScbdNewsParser::detail($this->fixture('detail-earth-hour'));

        $this->assertStringContainsString('<p>', $detail['body']);
        $this->assertNotEmpty($detail['images']);

        foreach ($detail['images'] as $image) {
            $this->assertStringStartsWith('http', $image);
        }
    }

    public function test_the_body_carries_no_script_or_style(): void
    {
        // Whatever is scraped is stored and later rendered with {!! !!}.
        $detail = ScbdNewsParser::detail($this->fixture('detail-earth-hour'));

        $this->assertStringNotContainsString('<script', $detail['body']);
        $this->assertStringNotContainsString('<style', $detail['body']);
        $this->assertStringNotContainsString('onerror=', $detail['body']);
    }

    public function test_malformed_html_yields_nothing_rather_than_throwing(): void
    {
        $this->assertSame([], ScbdNewsParser::listing('<html><body>nothing here</body></html>'));
        $this->assertSame('', ScbdNewsParser::detail('<html><body></body></html>')['body']);
    }
}
```

- [ ] **Step 3: Run it and confirm it fails**

Run: `php artisan test --filter=ScbdNewsParserTest`
Expected: FAIL — `Class "App\Support\ScbdNewsParser" not found`.

- [ ] **Step 4: Write the parser**

Create `app/Support/ScbdNewsParser.php`:

```php
<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Turns scbd.com news HTML into arrays.
 *
 * Kept apart from the import command so the parsing — which is all of the
 * fiddly, breakable logic — can be tested against saved pages with no network
 * and no database.
 *
 * Every method returns empty rather than throwing on markup it does not
 * recognise: the source is somebody else's site and may change without notice,
 * and an import that skips a post it cannot read beats one that dies partway.
 */
final class ScbdNewsParser
{
    private const BASE = 'https://scbd.com';

    /**
     * One listing page's posts.
     *
     * @return array<int, array{title: string, date: string, cover: ?string, url: string}>
     */
    public static function listing(string $html): array
    {
        $xpath = self::xpath($html);
        $posts = [];

        foreach ($xpath->query("//div[contains(@class, 'niche-box-post')]") as $box) {
            /** @var DOMElement $box */
            $link = $xpath->query(".//h2/a", $box)->item(0);

            if (! $link instanceof DOMElement) {
                continue;
            }

            $day = $xpath->query(".//p[contains(@class, 'bd-day')]", $box)->item(0)?->textContent;
            $month = $xpath->query(".//p[contains(@class, 'bd-month')]", $box)->item(0)?->textContent;

            $posts[] = [
                'title' => self::text($link->textContent),
                'date' => self::date($day, $month),
                'cover' => self::cover($xpath, $box),
                'url' => self::absolute($link->getAttribute('href')),
            ];
        }

        return $posts;
    }

    /**
     * A post's body and its images.
     *
     * @return array{body: string, images: array<int, string>}
     */
    public static function detail(string $html): array
    {
        $xpath = self::xpath($html);
        $content = $xpath->query("//div[contains(@class, 'niche-box-content')]")->item(0);

        if (! $content instanceof DOMElement) {
            return ['body' => '', 'images' => []];
        }

        $paragraphs = [];

        foreach ($xpath->query('.//p', $content) as $p) {
            $text = self::text($p->textContent);

            if ($text !== '') {
                $paragraphs[] = '<p>'.e($text).'</p>';
            }
        }

        $images = [];

        foreach ($xpath->query("//img[contains(@src, '/news/images/')]") as $img) {
            /** @var DOMElement $img */
            $images[] = self::absolute($img->getAttribute('src'));
        }

        return [
            // Rebuilt from text, never passed through. The scraped markup is
            // stored and later rendered with {!! !!}, so nothing that arrives
            // as a tag survives — no script, no style, no event attribute.
            'body' => implode('', $paragraphs),
            'images' => array_values(array_unique($images)),
        ];
    }

    private static function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        // The source is not well-formed; warnings here are expected and
        // uninteresting.
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private static function text(string $raw): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function date(?string $day, ?string $month): string
    {
        try {
            return Carbon::parse(trim(($day ?? '').' '.($month ?? '')))->format('Y-m-d');
        } catch (Throwable) {
            // An unreadable date must not cost us the post. Today is wrong but
            // recoverable by hand; a dead import is not.
            return now()->format('Y-m-d');
        }
    }

    private static function cover(DOMXPath $xpath, DOMElement $box): ?string
    {
        foreach ($xpath->query('.//div[@style]', $box) as $div) {
            /** @var DOMElement $div */
            if (preg_match("#background-image:\s*url\(['\"]?([^'\")]+)#", $div->getAttribute('style'), $matches)) {
                return self::absolute($matches[1]);
            }
        }

        return null;
    }

    private static function absolute(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : self::BASE.'/'.ltrim($url, '/');
    }
}
```

- [ ] **Step 5: Run the parser test**

Run: `php artisan test --filter=ScbdNewsParserTest`
Expected: PASS, 6 tests.

If `test_it_finds_every_post_on_a_listing_page` fails on the count, print what the parser found (`php artisan tinker --execute="print_r(App\Support\ScbdNewsParser::listing(file_get_contents('tests/Fixtures/scbd-news/listing-page-1.html')));"`) and adjust the XPath to the real class names in your fixture — **fix the selector, not the expected count.**

- [ ] **Step 6: Write the failing import test**

```php
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

        // No test touches the network. Listing pages 2-4 come back empty, so
        // the run stops after the first.
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
        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame(4, BlogPost::count());
        $this->assertTrue(BlogPost::where('status', BlogPost::STATUS_PUBLISHED)->exists());
    }

    public function test_imported_posts_keep_their_real_dates(): void
    {
        $this->artisan('news:import-scbd')->assertSuccessful();

        $newest = BlogPost::orderByDesc('published_at')->first();

        $this->assertSame('2026-07-23', $newest->published_at->format('Y-m-d'));
    }

    public function test_it_creates_the_four_categories(): void
    {
        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['Community', 'Corporate', 'Environment', 'Events'],
            BlogCategory::pluck('name')->sort()->values()->all(),
        );
    }

    public function test_every_imported_post_has_a_category(): void
    {
        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame(0, BlogPost::whereNull('blog_category_id')->count());
    }

    public function test_covers_are_downloaded_and_stored_as_paths(): void
    {
        $this->artisan('news:import-scbd')->assertSuccessful();

        $post = BlogPost::whereNotNull('featured_image')->firstOrFail();

        $this->assertStringStartsWith('uploads/news/', $post->featured_image);
        Storage::disk('public')->assertExists($post->featured_image);
    }

    public function test_body_images_are_downloaded_and_embedded_in_the_content(): void
    {
        // The detail pages carry three or four photographs each, and the
        // layout has places for them — a two-up row and captioned figures.
        $this->artisan('news:import-scbd')->assertSuccessful();

        $post = BlogPost::where('content', 'like', '%scbd-prose-pair%')->first();

        $this->assertNotNull($post, 'No imported post embedded an image pair.');
        $this->assertStringContainsString('/storage/uploads/news/', $post->content);
        $this->assertStringContainsString('loading="lazy"', $post->content);
    }

    public function test_the_lead_paragraph_still_comes_first(): void
    {
        // The pair goes after the opening paragraph, not before it.
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
        $this->artisan('news:import-scbd')->assertSuccessful();
        $first = BlogPost::count();

        $this->artisan('news:import-scbd')->assertSuccessful();

        $this->assertSame($first, BlogPost::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->artisan('news:import-scbd', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, BlogPost::count());
        $this->assertSame(0, BlogCategory::count());
    }

    public function test_the_limit_option_is_honoured(): void
    {
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
```

- [ ] **Step 7: Run it and confirm it fails**

Run: `php artisan test --filter=ImportScbdNewsTest`
Expected: FAIL — "The command 'news:import-scbd' does not exist."

- [ ] **Step 8: Write the command**

Create `app/Console/Commands/ImportScbdNews.php`:

```php
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
                $this->warn("Body unavailable for “{$item['title']}”: {$e->getMessage()}");
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
```

- [ ] **Step 9: Run the import test**

Run: `php artisan test --filter=ImportScbdNewsTest`
Expected: PASS, 12 tests.

- [ ] **Step 10: Prove the tests bite**

Temporarily change the `$existing` branch to always `BlogPost::create(...)`.

Run: `php artisan test --filter=ImportScbdNewsTest`
Expected: FAIL on `test_running_it_twice_creates_no_duplicates`. Restore it.

Then temporarily remove the `if ($dryRun)` early-continue.

Run: `php artisan test --filter=ImportScbdNewsTest`
Expected: FAIL on `test_a_dry_run_writes_nothing`. Restore it.

Then temporarily make `composeBody` return `$paragraphsHtml` immediately.

Run: `php artisan test --filter=ImportScbdNewsTest`
Expected: FAIL on `test_body_images_are_downloaded_and_embedded_in_the_content`. Restore it.

- [ ] **Step 11: Run it for real**

Run: `php artisan news:import-scbd --dry-run`
Expected: lists 13 posts with plausible dates from 2024 to 2026.

Run: `php artisan news:import-scbd`
Expected: "Created 13, updated 0, skipped 0."

Run: `php artisan tinker --execute="echo AjayDhakal\FilamentStory\Models\BlogPost::count().' posts, '.AjayDhakal\FilamentStory\Models\BlogPost::whereNotNull('featured_image')->count().' with covers, '.AjayDhakal\FilamentStory\Models\BlogPost::where('content','like','%uploads/news/%')->count().' with body images';"`
Expected: `13 posts, 13 with covers, 13 with body images`. Fewer means some downloads failed — inspect the warnings before continuing.

Run: `ls storage/app/public/uploads/news | wc -l`
Expected: roughly 45 files — 13 covers plus the body photographs.

- [ ] **Step 12: Commit**

```bash
git add app/Support/ScbdNewsParser.php app/Console/Commands/ImportScbdNews.php tests/Fixtures/scbd-news tests/Feature/News/ScbdNewsParserTest.php tests/Feature/News/ImportScbdNewsTest.php
git commit -m "feat: import the scbd.com news archive"
```

---

### Task 10: Publish the page and retire the package frontend

The last task: put the block on page 15, publish it, repoint the two links and the menu item, and switch off `/blogs`.

**Files:**
- Modify: `config/filament-story.php`
- Modify: `resources/views/partials/blocks/scbd-news.blade.php:31,37`
- Create: `database/seeders/NewsPageSeeder.php`
- Test: `tests/Feature/News/NewsWireUpTest.php`

**Interfaces:**
- Consumes: everything above.
- Produces: nothing.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\News;

use AjayDhakal\FilamentStory\Models\BlogPost;
use App\Models\Page;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Database\Seeders\NewsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

class NewsWireUpTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHeaderMenu();
    }

    public function test_the_package_blog_routes_are_gone(): void
    {
        // They render Tailwind-CDN views that look nothing like this site.
        $this->assertFalse(app('router')->has('filament-story.index'));
        $this->assertFalse(app('router')->has('filament-story.show'));

        $this->get('/blogs')->assertNotFound();
    }

    public function test_the_api_routes_are_untouched(): void
    {
        // A separate flag; nothing here should have disabled them.
        $this->getJson('/api/posts')->assertSuccessful();
    }

    public function test_the_homepage_news_block_links_to_the_new_pages(): void
    {
        BlogPost::create([
            'title' => 'Earth Hour 2026',
            'slug' => 'earth-hour-2026',
            'content' => '<p>Body.</p>',
            'excerpt' => 'Summary.',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => '2026-03-28',
        ]);

        $this->seedHomepage([[
            'id' => 'block_1',
            'type' => 'scbd_news',
            'data' => ['heading' => ['en' => 'News'], 'cta_label' => ['en' => 'All news'], 'limit' => 3],
            'children' => null,
        ]]);

        $this->get('/')
            ->assertSuccessful()
            ->assertSee('href="'.route('news.show', 'earth-hour-2026').'"', false)
            ->assertSee('href="'.route('page', 'news').'"', false);
    }

    public function test_the_seeder_publishes_a_news_page_carrying_the_index_block(): void
    {
        Page::create([
            'title' => ['en' => 'News'],
            'slug' => 'news',
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_DRAFT,
        ]);

        $this->seed(NewsPageSeeder::class);

        $page = Page::where('slug', 'news')->firstOrFail();

        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertSame(NewsIndexBlock::type(), $page->blocks()[0]['type']);

        $this->get('/news')->assertSuccessful();
    }

    public function test_the_seeder_is_idempotent(): void
    {
        Page::create([
            'title' => ['en' => 'News'], 'slug' => 'news',
            'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_DRAFT,
        ]);

        $this->seed(NewsPageSeeder::class);
        $this->seed(NewsPageSeeder::class);

        $this->assertSame(1, Page::where('slug', 'news')->count());
        $this->assertCount(1, Page::where('slug', 'news')->firstOrFail()->blocks());
    }

    public function test_the_seeder_does_not_overwrite_an_edited_page(): void
    {
        // Once an editor has arranged the page, re-seeding must leave it alone.
        Page::create([
            'title' => ['en' => 'News'], 'slug' => 'news', 'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1', 'type' => NewsIndexBlock::type(),
                'data' => ['heading' => ['en' => 'Newsroom']], 'children' => null,
            ]],
        ]);

        $this->seed(NewsPageSeeder::class);

        $this->assertSame(
            'Newsroom',
            Page::where('slug', 'news')->firstOrFail()->blocks()[0]['data']['heading']['en'],
        );
    }
}
```

- [ ] **Step 2: Run it and confirm it fails**

Run: `php artisan test --filter=NewsWireUpTest`
Expected: FAIL — the package routes still exist and `Database\Seeders\NewsPageSeeder` is not found.

- [ ] **Step 3: Switch off the package frontend**

In `config/filament-story.php`:

```php
    // The public blog pages are served by this application instead — see
    // routes/web.php and the scbd_news_index block. The package's own views
    // are Tailwind-CDN and share nothing with this site's design.
    'frontend_enabled' => false,
```

Leave `'api_enabled' => true` alone.

- [ ] **Step 4: Relink the homepage news block**

In `resources/views/partials/blocks/scbd-news.blade.php`, replace `route('filament-story.index')` with `route('page', 'news')` and `route('filament-story.show', $post->slug)` with `route('news.show', $post->slug)`.

Confirm nothing else refers to the old names:

Run: `grep -rn "filament-story\." resources app routes --include="*.php" --include="*.blade.php"`
Expected: no output.

- [ ] **Step 5: Write the seeder**

Create `database/seeders/NewsPageSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Page;
use App\PageBuilder\Blocks\NewsIndexBlock;
use Illuminate\Database\Seeder;

/**
 * Turns the empty draft "news" page into the News landing page.
 *
 * Only ever fills a page that has no blocks. Once an editor has arranged it,
 * re-seeding must leave their work alone — a seeder that overwrites is a
 * seeder nobody can safely run twice.
 */
class NewsPageSeeder extends Seeder
{
    public function run(): void
    {
        $page = Page::firstOrCreate(
            ['slug' => 'news'],
            ['title' => ['en' => 'News'], 'type' => Page::TYPE_BUILDER, 'status' => Page::STATUS_DRAFT],
        );

        if ($page->blocks() !== []) {
            // Already built. Publishing is still safe and still wanted.
            $page->update(['status' => Page::STATUS_PUBLISHED]);

            return;
        }

        $page->update([
            'type' => Page::TYPE_BUILDER,
            'status' => Page::STATUS_PUBLISHED,
            'builder_payload' => [[
                'id' => 'block_1',
                'type' => NewsIndexBlock::type(),
                'data' => [
                    'eyebrow' => ['en' => 'Newsroom'],
                    'heading' => ['en' => 'News'],
                    'empty_text' => ['en' => 'There are no published posts yet.'],
                    'sidebar_heading' => ['en' => 'Flash news'],
                    'show_filters' => true,
                    'sidebar_limit' => 5,
                ],
                'children' => null,
            ]],
        ]);
    }
}
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --filter=NewsWireUpTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Prove the tests bite**

Temporarily set `'frontend_enabled' => true` in the config.

Run: `php artisan test --filter=NewsWireUpTest`
Expected: FAIL on `test_the_package_blog_routes_are_gone`. Restore it.

- [ ] **Step 8: Apply it to the real database**

Run: `php artisan db:seed --class=NewsPageSeeder`
Expected: no errors.

Repoint the top-level News menu item, which is a placeholder anchor:

Run:
```bash
php artisan tinker --execute="
\$page = App\Models\Page::where('slug','news')->firstOrFail();
\$item = App\Models\MenuItem::find(27);
\$item->update(['type' => App\Models\MenuItem::TYPE_PAGE, 'url' => null, 'linkable_type' => App\Models\Page::class, 'linkable_id' => \$page->id]);
echo 'menu item 27 -> page '.\$page->id;
"
```

Expected: `menu item 27 -> page 15`. Item id 4, the "News" child already linked to page 15, needs no change.

- [ ] **Step 9: Full verification**

Run: `php artisan test`
Expected: the whole suite green. Anything newly red is a regression from this task, not a flake.

Run: `npm run build`
Expected: succeeds.

Then in the browser, walk the whole feature: the header News menu leads to `/news`; the grid shows 13 cards with covers; chips filter with cards gliding; the sidebar sticks while the grid scrolls; a card leads to a detail page with a working hero, prev/next and Latest News; the footer reveals correctly on both pages; `/blogs` 404s. Check `list_console_messages` for errors on both pages.

- [ ] **Step 10: Commit**

```bash
git add config/filament-story.php resources/views/partials/blocks/scbd-news.blade.php database/seeders/NewsPageSeeder.php tests/Feature/News/NewsWireUpTest.php
git commit -m "feat: publish the news page and retire the package blog frontend"
```

---

## Verification Summary

Before calling this done, all of the following must be true, each confirmed by running the command and reading its output — not by assumption:

| Check | Command | Expected |
|---|---|---|
| Full suite green | `php artisan test` | 0 failures |
| Assets build | `npm run build` | exit 0 |
| Posts imported | `php artisan tinker --execute="echo AjayDhakal\FilamentStory\Models\BlogPost::count();"` | 13 |
| Covers present | `php artisan tinker --execute="echo AjayDhakal\FilamentStory\Models\BlogPost::whereNotNull('featured_image')->count();"` | 13 |
| Body images embedded | `php artisan tinker --execute="echo AjayDhakal\FilamentStory\Models\BlogPost::where('content','like','%uploads/news/%')->count();"` | 13 |
| Old routes gone | `php artisan route:list \| grep filament-story` | only `api/posts` rows |
| No stale links | `grep -rn "filament-story\." resources app routes --include="*.php" --include="*.blade.php"` | no output |
| Index live | browser at `/news` | grid, sidebar, working chips, no console errors |
| Detail live | browser at any post | hero, prose, prev/next, latest row, no console errors |

Each task's "prove the tests bite" step is not optional. On this project a suite has passed green while hiding 20 of 24 real defects; a test that stays green when you break the thing it names is not evidence of anything.
