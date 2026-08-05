# News index and post detail

A public News landing page at `/news` listing every published post, and a post
detail page at `/news/{slug}`. Content is imported from the current scbd.com
news section.

## Source

Layout and interaction come from two pages of the Orisa demo, whose shipped
markup was fetched and read directly rather than inferred from screenshots:

- `https://orisa-html-demo.pages.dev/archive-2` — the index
- `https://orisa-html-demo.pages.dev/blog-details` — the detail page

Orisa is a Bootstrap template: white ground, rounded cards, sentence-case Inter,
jQuery + isotope filtering. This site is the opposite — `#f3f2f2` ground,
`#ec3013` accent, uppercase Archivo, a custom cursor, GSAP reveals, no Bootstrap
and no jQuery. **Orisa's structure and interaction ideas are adopted; its visual
style is not.** Nothing from `assets/css/main.css` or `assets/js/main.js` is
ported. Where the reference reaches for a plugin, the equivalent already exists
in `resources/js/scbd/` and is reused.

Content comes from `https://scbd.com/menu/page/news` — 13 posts across 4
paginated listing pages, each with a cover image, a date, a title, full body
text and, on its detail page, three to four further images.

### What the reference gives, and what is dropped

| Reference element | Decision |
|---|---|
| Eyebrow badge + large H1 | Kept, as `data-split` heading in site type |
| Category filter chips | Kept, filtering real categories |
| 8/4 grid + sidebar split | Kept as `grid-template-columns: 1fr 380px` |
| Card thumb hover effect | Kept as grayscale→colour + slow scale |
| "Flash news" sidebar | Kept, sticky, 5 most recent |
| Newsletter panel | **Dropped** — no subscribe endpoint exists, and building one is a separate feature |
| Author avatar + byline | **Dropped** — `BlogPost` has no author, and these are corporate posts |
| Share links | Kept, as real LinkedIn / Facebook / X share URLs |
| Prev / next | Kept, walking `published_at` |
| Latest News row on detail | Kept, 4-up, reusing the index card |
| Pagination | **Not built** — the reference has none, and filtering is client-side (see *Filtering*) |

## Architecture

Two independent units sharing one model and one card partial.

**The index is a page-builder block.** `App\PageBuilder\Blocks\NewsIndexBlock`
(type `scbd_news_index`) plus `resources/views/partials/blocks/scbd-news-index.blade.php`,
following `NewsBlock` exactly — `type()`, `name()`, `icon()`, `schema()`,
`defaultData()`, `translatableKeys()` — and registered in
`PageBuilderServiceProvider`. It is placed on the existing draft page id 15
(slug `news`), which is then published. The URL `/news` comes from the existing
`/{slug}` catch-all; no route is added for it.

**The detail page is a dedicated route,** because the catch-all's slug pattern
excludes slashes:

```php
Route::get('/news/{slug}', NewsPostController::class)
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('news.show');
```

Declared **before** the page catch-all in `routes/web.php`, next to the existing
`/contact` route and for the same reason already documented there.

**No migrations.** Posts are the existing `AjayDhakal\FilamentStory\Models\BlogPost`,
categories the existing `BlogCategory`. Both are already administered by the
package's Filament resources under Content.

### Published means published now

Both units filter on `status = published AND published_at <= now()`, matching
`Page::scopePublished()`. A post marked published but dated in the future is not
listed, does not appear in the sidebar or the Latest News row, and 404s by URL.
The package's own `STATUS_SCHEDULED` is treated the same way — anything that is
not published-and-past is invisible.

### Retiring the package frontend

`config/filament-story.php` sets `frontend_enabled => false`, which removes
`/blogs`, `/blogs/{slug}` and `/blogs/category/{category:slug}`. Those routes
render Tailwind-CDN views that look nothing like this site and are now
superseded.

Three call sites move:

| Location | From | To |
|---|---|---|
| `partials/blocks/scbd-news.blade.php` (CTA) | `route('filament-story.index')` | `route('page', 'news')` |
| `partials/blocks/scbd-news.blade.php` (rows) | `route('filament-story.show', $post->slug)` | `route('news.show', $post->slug)` |
| Menu item id 27 ("News", type `custom`, url `#news`) | `#news` | page 15 |

The navigation already carries **News → News / Gallery / Events**, where the
"News" child (item id 4) is a `page` link to page 15. Publishing that page makes
the child work with no menu edit. Only the top-level parent's placeholder
`#news` anchor is repointed.

`api/posts` and `api/posts/{slug}` are left alone — they are a separate
`api_enabled` flag and nothing here touches them.

### The layout boundary

`x-layouts.page` currently takes a `Page` model and reads `usesBuilder()` to
decide whether to load the animation bundle, `is_homepage` to decide the loader
and the title suffix, and `t('seo_title')` / `t('seo_description')` for metadata.
A post detail page has no `Page` row.

Rather than fabricate one, the layout takes explicit props:

```blade
<x-layouts.page :title="..." :description="..." :animated="true"
                :showLoader="false" :i18n="[]">
```

| Prop | Meaning | Page passes | Post passes |
|---|---|---|---|
| `title` | the complete `<title>`, suffix already applied | seo_title ?: title, plus " — site name" unless homepage | post seo_title ?: title, plus " — site name" |
| `description` | meta description, nullable | page seo_description ?: site meta | post seo_description ?: excerpt |
| `animated` | load the animation bundle and the custom cursor | `usesBuilder()` | always true |
| `showLoader` | include `partials.site.loader` | `is_homepage` | always false |
| `i18n` | translation payload for the JSON script tag | `SiteTranslations::forPage(...)` | `[]` |

The suffix rule moves into `page.blade.php` with the rest of the `Page`
knowledge, so the layout no longer branches on `is_homepage` at all —
it renders exactly the title it is handed. `resources/views/page.blade.php`
derives all five from its model; `news/show.blade.php` derives them from the
post. The layout stops knowing about the `Page` model, which is the right
boundary independent of this work.

`resources/views/components/layouts/public.blade.php` is referenced by nothing —
a stale near-duplicate of the same shell left behind when the hand-built
homepage was retired. It is deleted.

## The index page

```
┌─ .scbd-pad-top ──────────────────────────────────────────────┐
│ NEWS                                                          │  eyebrow 11px/.22em #ec3013
│ ███████ ███  ██████████            [ALL][COMMUNITY][ENVIRON…] │  data-split H1 + chips
├───── 2px solid rgba(32,30,29,0.4) ────────────────────────────┤
│ ┌──────────────┐ ┌──────────────┐   │  FLASH NEWS             │
│ │ img 4:3      │ │ img 4:3      │   │  ┌────┐ TITLE           │
│ │ .grayscale   │ │              │   │  │88px│ 23.07.26        │
│ └──────────────┘ └──────────────┘   │  └────┘                 │
│ TITLE UPPERCASE   TITLE UPPERCASE   │  ┌────┐ TITLE           │
│ 23.07.26 · COMMUNITY                │  └────┘ 28.03.26        │
└─────────────────────────────────────┴─────────────────────────┘
        grid 1fr 1fr, gap 32px           sticky, top 120px
```

The header divider is the same `2px solid rgba(32,30,29,0.4)` rule the existing
`scbd-news` block uses, so the two News surfaces agree.

Cards carry `.grayscale`, the filter already applied to hero and district
imagery. Orisa's `hover-effect-1` becomes grayscale→colour plus `scale(1.04)` on
the thumb over 600ms — the same gesture in this site's idiom. Titles are
uppercase Archivo; dates are `dd.mm.yy`, matching `scbd-news-row`.

The sidebar is Orisa's "Flash news": the five most recent posts, an 88px square
thumb beside title and date, `position: sticky; top: 120px`.

Below 900px — the existing breakpoint, no new value introduced — the grid
becomes one column and the sidebar drops below it, unsticky.

### Block fields

Through `LocaleTabs`, as every block does:

| Field | Type | Notes |
|---|---|---|
| `eyebrow` | translatable text, max 60 | |
| `heading` | translatable textarea | required in the fallback locale |
| `empty_text` | translatable text, max 160 | shown when no posts are published |
| `sidebar_heading` | translatable text, max 60 | defaults to "Flash news" |
| `show_filters` | toggle | not translated; default on |
| `sidebar_limit` | numeric 1–10 | not translated; default 5 |

Post titles and bodies are **not** translatable — `BlogPost` has no translatable
columns. The surrounding chrome translates; the posts themselves are
single-language. Making posts multilingual is separate work and is not in scope
here.

### Empty state

With no published posts the grid renders `empty_text` in a single full-width
row, and the sidebar section is omitted entirely rather than rendering an empty
heading.

## Filtering

All published posts render at once; chips filter client-side. There is no
pagination, matching the reference. At 13 posts growing by roughly four a year
this is well within budget; thumbnails carry `loading="lazy"`.

One new module, `resources/js/scbd/newsFilter.js`, exporting
`initNewsFilter(gsap, Flip, ScrollTrigger)` in the house style — one file per
concern, a named `init*` taking its dependencies as arguments, wired into
`resources/js/scbd/index.js`, returning early when `[data-news-filter]` is
absent so every other page pays nothing.

```
chip click → Flip.getState(cards)
           → toggle .is-hidden on non-matching cards
           → Flip.from(state, { duration: .6, stagger: .03,
                                ease: 'power3.out', absolute: true })
           → ScrollTrigger.refresh()
```

`Flip` is already registered in `index.js` and needs no new import. `.is-hidden`
is a new rule in `resources/css/scbd.css` — `display: none` — and is the only
CSS the filter needs; everything else is Flip reading and replaying positions.
The active chip is marked with `aria-pressed`, and chips are `<button>`
elements, not links — they change nothing about the document's address.

Under `prefers-reduced-motion` the chips still filter: the class toggles and the
Flip tween is skipped. This matches how every other module here degrades — the
function still works, the flourish does not run.

Chips carry `data-magnetic`, so the custom cursor treats them as it treats the
site's other buttons.

**Deliberately not built:** per-category URLs. Chips are a browsing aid over a
13-post archive, not a taxonomy needing its own crawlable pages. If the archive
grows past roughly forty posts, revisit this together with pagination.

## The detail page

```
┌─ .scbd-pad-top ──────────────────────────────────────────────┐
│ NEWS / ARTHA GRAHA PEDULI HOLDS…              breadcrumb      │
│ ██████████████ ████████  ███████              data-split H1   │
│ 23.07.26 · COMMUNITY              SHARE  [in][f][x]           │
├──────────────────────────────────────────────────────────────┤
│ ████████ full-bleed hero, data-reveal clip-path ██████████████│
├──────────────────────────────────────────────────────────────┤
│              max-width 820px, .scbd-prose                     │
│              lead paragraph, then body                        │
│              ┌────────┐ ┌────────┐   image pair               │
│              └────────┘ └────────┘                            │
│              ┌──────────────────┐    captioned figure         │
│  ← PREV                                        NEXT →         │
├──────────────────────────────────────────────────────────────┤
│ LATEST NEWS    4-up card row, same card partial as the index  │
└──────────────────────────────────────────────────────────────┘
```

Breadcrumb is `News / <title>`, the first segment linking to `/news`.

Share links are real share URLs — `linkedin.com/sharing/share-offsite`,
`facebook.com/sharer/sharer.php`, `twitter.com/intent/tweet` — each taking the
canonical post URL. No SDK, no script.

Body HTML is stored sanitised by the package's editor and rendered inside
`.scbd-prose`, the existing class used by standard pages.

Prev and next walk `published_at`: previous is the newest post older than this
one, next the oldest post newer than it. At either end of the archive the
corresponding column is omitted and the remaining one keeps its side.

The Latest News row shows the four most recent published posts **excluding the
one being viewed**, so a reader never sees a link back to the page they are on.

### The shared card

`resources/views/partials/site/news-card.blade.php` takes a post and a size, and
is used in three places. Card styling changes in one place.

| Size | Used by | Shape |
|---|---|---|
| `grid` | index grid (2-up), Latest News row (4-up) | 4:3 thumb above title, then date · category. Fluid — the column count is the caller's grid, not the card's business |
| `compact` | sidebar "Flash news" | 88px square thumb beside title and date, no category |

A post with no `featured_image` renders the text portion with no thumb rather
than a broken or empty image.

## Content import

`php artisan news:import-scbd {--limit=} {--dry-run}` in
`app/Console/Commands/ImportScbdNews.php`.

1. Fetch the 4 listing pages (`?page=1..4`), parse each `.grid-item` for date,
   title, cover image URL and detail URL.
2. Fetch each detail page for the full body and its additional images.
3. Create the four categories if absent, then upsert each post.
4. Download images to `storage/app/public/uploads/news/`, storing the path in
   `featured_image`.

**Idempotent by slug.** The slug is `Str::slug($title)` truncated to 90
characters, with a numeric suffix on collision — the same shape as
`Page::uniqueSlug()`. Re-running updates the existing row rather than creating a
second one.

**Images are stored as paths, not media-library ids.** The package's admin form
uses a plain `FileUpload` writing a public-disk path, and `App\Support\MediaUrl`
resolves paths and library ids alike. Views call `MediaUrl::resolve()`, so the
field can move to the media library later without a view changing. A post whose
image download fails still imports, without a cover — `MediaUrl` returns null
and the card renders its text-only variant rather than an empty `<img src="">`.

**Failures are per-post.** A post whose fetch or parse fails is logged and
skipped; the remaining posts still import. The command reports how many were
created, updated and skipped.

### Category assignment

scbd.com's news has no categories. Four are created and each post assigned by
editorial judgement of its subject. The assignment is a starting point, fully
editable in Content → Blog Categories and on each post:

| Category | Posts |
|---|---|
| Community | Children's Day service (Jul 2026), Health counselling (Mar 2025), Supplementary feeding (Feb 2025), Friday rice assistance (Dec 2024), Free takjil (May 2024) |
| Events | Lunar New Year "Ride to Luck" (Feb 2026), Olympic Day (Oct 2025), Jakarta Fire Safety Challenge (Sep 2025), 80th Independence Day (Aug 2025) |
| Environment | Earth Hour (Mar 2026), ASRI clean-up movement (Feb 2026) |
| Corporate | Kebayoran Baru groundbreaking (Jul 2024), Danayasa Arthatama AGM (Jun 2024) |

Five, four, two and two — thirteen posts.

## Testing

PHPUnit 12, not Pest: classes extend `Tests\TestCase`, methods prefixed `test_`,
run with `php artisan test`.

**Index**
- lists published posts, newest first
- omits a draft post and a post dated in the future
- renders `empty_text` when nothing is published, and omits the sidebar
- the sidebar shows `sidebar_limit` posts
- the block is registered and renders through the builder dispatcher

**Detail**
- 200 for a published post; 404 for draft, future-dated, and unknown slugs
- prev and next resolve to the correct neighbours by `published_at`
- prev is absent on the oldest post, next on the newest
- Latest News excludes the post being viewed
- a post with no `featured_image` renders no `<img>` rather than an empty `src`

**Import**
- parses a **saved fixture** of the scraped HTML; no test touches the network
- re-running produces the same post count
- a post whose detail fetch fails is skipped without aborting the run

**Verification.** Each test is proven by breaking the thing it names — removing
the `published_at` guard must turn the scheduled-post test red, removing the
current-post exclusion must turn the Latest News test red. A test that stays
green when its subject is broken is not evidence.

## Constraints

- Never run `npm run dev` or any `migrate:fresh` variant here. Build with
  `npm run build`.
- All CSS goes in `resources/css/scbd.css`. No new stylesheets.
- The responsive breakpoint is `@media (max-width: 900px)`, already open at line
  1115, with a deeper one at 560px. No new breakpoint values.
- Palette: ground `#f3f2f2`, accent `#ec3013`, ink `#201e1d`.
- JS modules follow the house pattern: one file per concern in
  `resources/js/scbd/`, exporting a named `init*` taking `gsap`/`ScrollTrigger`
  as arguments, wired up in `resources/js/scbd/index.js`.
- PHP runs in a container; host working directories are ignored.

## Out of scope

- Multilingual post titles and bodies
- Newsletter subscription
- Pagination and per-category URLs
- The Gallery and Events pages (ids 16 and 17), still draft
- Post authors
