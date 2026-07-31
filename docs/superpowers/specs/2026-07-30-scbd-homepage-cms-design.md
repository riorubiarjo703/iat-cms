# SCBD Homepage + Comprehensive Admin Sidebar — Design

**Date:** 2026-07-30
**Status:** Approved (design), pending implementation plan
**Project:** `iat-cms` — Laravel 13.23, Filament 5.7.4, PHP 8.3, PostgreSQL, Tailwind 4 + Vite

## Goal

Two deliverables in one spec:

1. A curated, comprehensive admin sidebar in the Filament panel at `/superduper`, unifying the three
   installed plugins with new content models under labelled navigation groups.
2. A public homepage at `/` reproducing the design in `~/Downloads/SCBD Homepage.html`, including its
   GSAP + ScrollTrigger + Lenis animation layer, driven by database content editable from the admin.

## Starting state

Fresh Laravel skeleton. All migrations run. Frontend is still stock `welcome.blade.php`.

Installed plugins, all registered in `app/Providers/Filament/AdminPanelProvider.php:47-49`:

| Package | Version | Provides |
|---|---|---|
| `cybertroniankelvin/graper` | v1.0.1 | GrapesJS page builder. `graper_pages` table, 15 sample blocks, editor + public render routes. |
| `vaslv/filament-topbar-menu` | v1.7.0 | Menu rendered in the **admin panel's** topbar. `filament_topbar_menu_items` table. |
| `ajaydhakal/filament-story` | v1.1 | Blog. `blog_posts` + `blog_categories`, own public routes/views, scheduled publishing, AI generator. |

### Existing public routes (not modified by this spec)

| Route | Owner | Source |
|---|---|---|
| `GET /pages/{slug}` | Graper | `GraperServiceProvider.php:51` |
| `GET /blogs` | Story | `routes/web.php`, prefix from `filament-story.routes_prefix` (default `blogs`) |
| `GET /blogs/{slug}` | Story | same |
| `GET /blogs/category/{category:slug}` | Story | same |

Note the prefix is `blogs`, not `blog`, and Graper renders under `/pages/{slug}`, not `/{slug}`.

### The reference file

`~/Downloads/SCBD Homepage.html` is a 933 KB self-extracting bundle: a manifest of gzip+base64
resources plus an HTML template. Unpacked contents:

- GSAP 3.12.5, ScrollTrigger 3.12.5, Lenis, React 18.3.1 UMD, a `dc-runtime`, and 9 WOFF2 fonts.
- The page markup (~41 KB) and a **239-line vanilla animation script**.

The animation script has no React coupling — it is plain DOM work keyed entirely off `data-*`
attributes, so it ports to Blade near-verbatim.

Structure found in the markup: `#top`, `#about`, `#district`, `#facilities`, `#news`, `#contact`,
plus a loader, sticky auto-hiding header, velocity-reactive marquee, stacking cards, count-up stats,
custom cursor with magnetic buttons, and an EN/ID/CN language switcher.

Attribute inventory driving the data model: 18 `data-i18n` keys, 9 `data-src` image slots,
3 `data-count` stats, 4 `data-navlink` entries.

## Decisions

| Decision | Choice | Rejected alternatives |
|---|---|---|
| Homepage vs Graper | Hand-built Blade owning the GSAP/Lenis layer; content from DB. Graper stays for ordinary secondary pages. | Rebuild sections as GrapesJS blocks (pinning + loader fragile in the editor canvas); static hardcoded Blade (no editability). |
| Sidebar scope | Organise the three plugins + add the models the homepage genuinely needs. | Organise-only (homepage repeatable content stays hardcoded); full corporate CMS (too large for one spec). |
| Multilingual | Translatable JSON columns, client-side swap from a server-rendered payload. | Server-side locale routes (loses instant swap, larger); English-only (retrofit cost per field). |
| Images | Filament `FileUpload` to the `public` disk, seeded from the reference images. | Hotlink remote URLs (depends on scbd.com); `spatie/laravel-medialibrary` (extra dependency). |
| Public frontend scope | Homepage only; `#news` reads Story posts and links to Story's shipped views. | Also restyle blog views (~2x work); manual news entries (editors publish twice). |
| Sidebar construction | `NavigationBuilder` closure. | Reflection on vendor statics (breaks silently on update); subclass plugin resources (cannot unregister originals, duplicates). |
| Homepage storage | Singleton row + three ordered models. | `homepage_sections` typed-JSON table (loses typed fields; order is fixed by the GSAP timeline anyway); `spatie/laravel-settings` (awkward for translatable fields + uploads). |
| GSAP/Lenis delivery | npm + Vite, versions pinned. | CDN script tags (external requests; forces the load-order polling loop). |

## Architecture

Four layers, each independently understandable and testable:

1. **Content** — Eloquent models + a translatable-fields concern. No Filament or Blade knowledge.
2. **Admin** — one file owning the sidebar tree, plus Filament resources and pages. Depends on (1).
3. **Presentation** — `home.blade.php` and section partials, fed by one DTO. Depends on (1).
4. **Animation** — ES modules, `data-*`-driven. No Laravel knowledge; works against any conforming markup.

### Translatable fields

`App\Concerns\HasTranslatableFields` — local trait, no new dependency.

- `protected array $translatable = ['hero_line', ...]` declares which columns hold `{en,id,cn}` JSON.
- Casts those columns to `array`.
- `t(string $key): ?string` — current locale, falling back to `en` per key.
- `translations(string $key): array` — the full locale map, for building the JS payload.

## Data model

Six tables. Columns marked *(json)* are translatable `{en,id,cn}` maps.

### `homepage_contents` — singleton, `id = 1`

Translatable: `brand_sub`, `hero_line`, `hero_sub`, `about_heading`, `about_body`,
`about_cta_label`, `district_heading`, `district_body`, `facilities_heading`, `facilities_body`,
`news_heading`, `news_cta_label`, `contact_heading`, `marquee_text`.

Plain: `hero_image`, `about_image`, `about_cta_url`, `contact_email`, `contact_phone`,
`contact_address`, timestamps.

### `district_places` — sortable

`title` *(json)*, `caption` *(json)*, `image`, `sort`, `is_active`.

### `facilities` — sortable

`title` *(json)*, `body` *(json)*, `image`, `sort`, `is_active`.

### `stats` — sortable

`label` *(json)*, `value` (decimal), `suffix`, `format` (`plain` | `thousands`), `sort`.

`format` exists because the reference distinguishes `data-plain` (renders `45`) from
thousands-separated count-ups (renders `1,200`).

### `public_menu_items` — sortable

`label` *(json)*, `url`, `target`, `sort`, `is_active`, `is_cta`.

Supplies the reference's `nav1`–`nav4` and header CTA, so the public nav stops being hardcoded.
Distinct from the plugin's admin topbar menu.

`is_cta` (boolean, default false) distinguishes the header call-to-action button from the ordinary
nav links. Exactly one item is expected to carry it; the presentation layer takes the first and
ignores any others. *Added during planning — the original draft assigned this table both roles
without a discriminating column.*

### `site_settings` — singleton, `id = 1`

`meta_title` *(json)*, `meta_description` *(json)*, `site_name`, `logo`, `favicon`,
`default_locale`, `available_locales` *(json)*, `social` *(json)*.

### Explicitly not built

No `homepage_sections` table, no media library, no roles/permissions, no Careers/Events/Tenants,
no contact-form submissions. Section order lives in the Blade template because the GSAP timeline
depends on it.

## Admin layer

### Sidebar

`app/Filament/Navigation/AdminNavigation.php` exposes `build(NavigationBuilder $b): NavigationBuilder`,
wired in `AdminPanelProvider` as:

```php
->navigation(fn (NavigationBuilder $b) => AdminNavigation::build($b))
```

This is necessary because **none of the three plugins expose configuration for their sidebar
placement** — each hardcodes it as `protected static` properties on its resource class:

- `BlogPostResource.php:28` pins itself to a group named `Blogs`.
- `GraperPageResource.php:32` sets label `Pages` with no group.
- `TopbarMenuItemResource.php:41` sets no group.

A `NavigationBuilder` closure suppresses all auto-registered items cleanly:
`NavigationManager::get()` early-returns `$this->panel->buildNavigation()` at lines 49–50, bypassing
the auto-registration path below it. No duplicates, no reflection, no vendor patching.

Target tree. The **Source** column distinguishes what already exists from what this spec builds:

```
Dashboard                                                      existing (Filament)

CONTENT
  Homepage             HomepageEditor            NEW custom page
  Pages                GraperPageResource        plugin, relabelled
  Blog Posts           BlogPostResource          plugin — badge: draft + scheduled count
  Blog Categories      BlogCategoryResource      NEW — ours, wrapping the plugin's model

HOMEPAGE DATA
  District Places      DistrictPlaceResource     NEW, reorderable
  Facilities           FacilityResource          NEW, reorderable
  Stats                StatResource              NEW, reorderable

APPEARANCE
  Public Menu          PublicMenuItemResource    NEW, reorderable — the site's own nav
  Admin Topbar Menu    TopbarMenuItemResource    plugin, relabelled

SETTINGS
  Site Settings        SiteSettingsPage          NEW custom page

SYSTEM
  Users                UserResource              NEW
```

Items pointing at plugin resources reference them via `::getUrl()`, so labels are ours and
destinations stay theirs.

Two entries need building that might look like they come for free:

- **`BlogCategoryResource` is ours, not Story's.** Story registers only `BlogPostResource`
  (`FilamentStoryPlugin.php:23-25`). It ships a `BlogCategory` model with no admin UI at all, so
  categories are currently unmanageable. We add a resource against
  `AjayDhakal\FilamentStory\Models\BlogCategory` (fields: name, slug).
- **`UserResource` is new.** `app/Filament` does not exist yet; nothing user-facing is registered.

The Blog Posts badge counts `BlogPost::STATUS_DRAFT` + `BlogPost::STATUS_SCHEDULED` records.

Topbar Menu is relabelled **"Admin Topbar Menu"** because it renders inside the admin panel's
topbar, not the public site. Left as plain "Topbar Menu" beside "Public Menu" it reads as though the
two are a pair, which misleads editors.

**Accepted trade-off:** resources added later must be added to this file or they will not appear.

### Translatable form layout

Locale is the **outer** axis. Wrapping every field in its own 3-tab group would produce ~40 nested
tab sets and is unusable.

```
[ English ] [ Indonesian ] [ 中文 ]          ← outer Tabs
  Brand & Nav    brand_sub
  Hero           hero_line, hero_sub
  About          about_heading, about_body, about_cta_label
  District       district_heading, district_body
  Facilities     facilities_heading, facilities_body
  News           news_heading, news_cta_label
  Contact        contact_heading
  Marquee        marquee_text

[ Media & Links ]                            ← locale-independent
  hero_image, about_image, about_cta_url,
  contact_email, contact_phone, contact_address
```

Fields bind by dot-notation state path (`hero_line.en`) against the `array`-cast JSON columns. One
tab per language means a translator sees their entire job in one place.

### Singleton pages

`HomepageContent::singleton()` and `SiteSetting::singleton()` each do `firstOrCreate(['id' => 1])`.
A shared `App\Concerns\EditsSingletonRecord` trait handles the `mount()`-fill / `save()`-update
cycle so both Filament pages stay short.

### Validation

- English is `required` on every translatable field; `id` and `cn` are optional and fall back to
  English at render time, so a half-finished translation degrades to a coherent page.
- Uploads constrained to images with a size cap.
- Line-break-bearing fields (`hero_line`, `district_heading`, `facilities_heading`, `news_heading`,
  `contact_heading`) are `Textarea`s with helper text explaining that newlines become the
  `<br>`-separated lines the char-split animation consumes.

## Presentation layer

### Route

One new route: `Route::get('/', HomeController::class)`. `welcome.blade.php` is deleted.

### Controller and view model

`HomeController` assembles a readonly `App\Support\HomepageData` DTO:

- `content` — `HomepageContent` singleton
- `menu` — active `PublicMenuItem`s ordered by `sort`
- `places` — active `DistrictPlace`s ordered by `sort`
- `facilities` — active `Facility`s ordered by `sort`
- `stats` — `Stat`s ordered by `sort`
- `posts` — 3 latest published `BlogPost`s
- `settings` — `SiteSetting` singleton
- `i18n` — `locale => key => html` payload

Blade receives one object and performs no queries, so page assembly is a single testable step.

### Templates

```
resources/views/
  layouts/public.blade.php        fonts, Vite, meta, cursor nodes
  home.blade.php                  section order = GSAP timeline order
  partials/home/
    loader.blade.php    header.blade.php    hero.blade.php
    marquee.blade.php   about.blade.php     district.blade.php
    facilities.blade.php news.blade.php     contact.blade.php
```

Each partial emits the `data-*` attributes the animation layer keys off.

There is **no** footer partial: the reference markup contains no `<footer>` element and the page
ends at `#contact`. The stats row lives inside `about.blade.php` rather than getting its own partial,
matching where the reference places it. *Both corrected during planning — an earlier draft listed a
`footer.blade.php` and a standalone `stats.blade.php`.*

### Styling

The reference is **bespoke CSS, not Tailwind**: 16,528 characters across three `<style>` blocks, 159
inline `style` attributes, its own `.btn` / `.btn-primary` / `.btn-secondary` / `.btn-ghost` /
`.btn-block` / `.btn-icon` / `.grayscale` component classes, and zero Tailwind utilities. That CSS is
ported verbatim into `resources/css/scbd.css` as a separate Vite entry point, and the nine extracted
Archivo WOFF2 files are self-hosted from `public/fonts` (no Google Fonts CDN).

Tailwind stays scoped to the admin panel and future pages. Converting this page to Tailwind would
risk fidelity for no reuse benefit, since nothing else shares the design language yet.

The reference defers images via `data-src` plus a hardcoded JS URL map (`page.jsx:156-174`) purely
because the bundle had no server. We render real `src` attributes server-side and delete that
indirection.

## Animation layer

`resources/js/scbd/` — `index.js` plus one module per behaviour: `lenis`, `loader`, `split`,
`header`, `reveal`, `marquee`, `district`, `stack`, `counters`, `cursor`, `i18n`. Imported from
`resources/js/app.js`. `gsap@3.12.5` and `lenis` pinned in `package.json`.

Ported from the extracted script with four deliberate changes:

1. **Drop the `wait()` polling loop and the `window.__scbdInstance` teardown singleton.** Both exist
   only because the bundle could not guarantee script load order or component lifecycle. With Vite
   imports, load order is a fact. `gsap.context()` still handles cleanup.

2. **Add a `prefers-reduced-motion` guard.** The reference has none. When set: skip the loader
   sequence, set all reveal and split states to final, run Lenis without smoothing. Without this the
   page is unusable for anyone with that preference.

3. **Guard the pinned horizontal scroll.** `#district` pins the viewport and computes
   `end: '+=' + (track.scrollWidth - window.innerWidth)`. With few or no district places that value
   is zero or negative and ScrollTrigger pins the page with nowhere to scroll — the site appears
   frozen. The module bails out unless the track genuinely overflows. This is the only failure mode
   that breaks the entire page rather than one section.

4. **Fix a typo carried in the reference.** `page.jsx:95` reads
   `gsap.set(contactChars, { yPercert: 105 })` — `yPercert`, not `yPercent`. The property is silently
   ignored, so the contact heading's characters are never moved offscreen and the reveal animates
   from an already-visible state. Ported as `yPercent`.

Behaviours retained as-is: loader 000→100 counter with progress bar and 5-second force-open failsafe;
header hide-on-scroll-down via Lenis scroll events; hero parallax and clip-path reveal; char-split
stagger; velocity-reactive marquee timeScale; `[data-fade]` and `[data-reveal]` scroll reveals;
count-up stats triggered once; scale/translate card stacking; news row hover offset; custom cursor
dot + ring with magnetic buttons; anchor links routed through `lenis.scrollTo` with a `-70` offset;
`ScrollTrigger.refresh()` on resize.

## Multilingual behaviour

- Blade renders English into `[data-i18n]` elements.
- The full map ships as `<script type="application/json" id="scbd-i18n">{en:…,id:…,cn:…}</script>`.
- The switcher module reads it, swaps `innerHTML`, re-runs the char-split for `[data-split]`
  elements, and calls `ScrollTrigger.refresh()`.

Same instant no-reload UX as the reference, now database-driven.

The reference's hardcoded dictionary (`page.jsx:196-216`) already contains complete Indonesian and
Chinese translations for all 18 keys. The seeder imports them, so the page starts genuinely
trilingual rather than with empty locale tabs.

## Images and seeding

- `FileUpload` → `public` disk, per-model directories (`uploads/district`, `uploads/facilities`, …).
- `php artisan storage:link` required.
- `HomepageSeeder` fetches the 9 reference images from scbd.com once into local storage, then creates
  the homepage content, 4 public menu items, the district places, 4 facilities and 3 stats with
  EN/ID/CN copy taken from the reference.
- Missing images render a neutral placeholder rather than a broken `<img>`.

## Error handling

| Condition | Behaviour |
|---|---|
| Fresh DB, no seed | `singleton()` `firstOrCreate`s the row; page renders instead of 500ing. |
| Missing `id`/`cn` translation | Per-key fallback to English. |
| Empty `district_places` / `facilities` / `stats` / `news` | Section is skipped. |
| District track narrower than viewport | Pinning is not created — prevents an apparently frozen page. |
| Missing image file | Neutral placeholder. |
| Loader assets stall | 5-second failsafe force-completes the intro and restarts Lenis. |
| `prefers-reduced-motion` | Loader skipped, final states set, no smooth scrolling. |

## Verification

### Automated (PHPUnit)

The project has **no Pest** — `composer.json` ships `phpunit/phpunit ^12.5.12` with `tests/TestCase.php`
and `phpunit.xml`. Tests extend `Tests\TestCase`, use `RefreshDatabase`, and run against sqlite
`:memory:`. *Corrected during planning — an earlier draft of this section said Pest.*

Feature:
- Homepage returns 200 on an unseeded database.
- All sections render with seeded data.
- English fallback applies when a locale value is blank.
- The i18n payload contains all three locales.
- `#news` shows at most 3 items and only published posts.
- Inactive `district_places` and `public_menu_items` are excluded.
- `sort` ordering is respected for all four ordered models.

Unit:
- `HasTranslatableFields`: current-locale read, English fallback, full map, missing key.

Filament:
- `HomepageEditor` and `SiteSettingsPage` mount, save, and enforce English-required validation.
- The four new ordered resources (`DistrictPlace`, `Facility`, `Stat`, `PublicMenuItem`):
  create, edit, reorder.
- `BlogCategoryResource` and `UserResource`: create and edit.
- The sidebar renders all five groups, and each plugin resource appears exactly once — guarding
  against the duplicate-registration failure mode the `NavigationBuilder` approach is chosen to avoid.

### Manual (browser)

The animation layer has no automated coverage — there is no JS test harness in this project and
adding one is out of scope. Verified by driving a real browser and confirming:

- Loader counts 000→100 and lifts away.
- Hero characters stagger in; hero image clip-path reveals.
- Header hides on scroll-down, returns on scroll-up.
- `#district` pins, scrolls horizontally, then releases.
- Stats count up once on enter.
- Cards stack with scale/translate.
- Cursor dot and ring track the pointer; magnetic buttons respond.
- EN/ID/CN switching swaps all text without breaking scroll position or ScrollTrigger.

Observed results — including anything that does not work — are to be reported honestly, not assumed.

## Out of scope (candidate follow-up specs)

- Restyling Story's blog index/detail views to match the SCBD design language.
- New Graper blocks in the SCBD design language for secondary pages.
- Media library, roles and permissions, contact-form submissions.
- Server-side locale routes and `hreflang` for SEO.
