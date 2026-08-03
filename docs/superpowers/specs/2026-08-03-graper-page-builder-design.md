# Graper Page Builder with Separate Translations (Slice A1-revised) — Design

**Date:** 2026-08-03
**Status:** Approved (design), pending implementation plan
**Project:** `iat-cms` — Laravel 13.23, Filament 5.7.4, PHP 8.4.16, PostgreSQL, Vite 8
**Supersedes:** `2026-07-31-block-page-builder-design.md` and its plan

## Goal

Make every page — the homepage included — editable on a **live visual drag-and-drop canvas**,
while keeping the trilingual content, the instant no-reload language switcher, and the GSAP
animation layer that the SCBD design depends on.

The acceptance test is unchanged and still concrete: **the SCBD homepage must be rebuildable by
dragging blocks onto a canvas**, with its pinned horizontal scroll, char-split headings,
count-up stats and velocity-reactive marquee intact, in all three languages.

## Why this supersedes the previous design

The previous spec used Filament's `Builder` field: structured data, translations as `{en,id,cn}`
leaves, reorder by dragging labelled cards in a form. The owner wants a **live visual canvas** —
dragging sections on a rendered preview — which Filament's Builder does not provide and Graper
(GrapesJS) does.

Graper was already installed and was slated for removal by the previous plan. It is now the
foundation instead.

## The problem this design solves

A GrapesJS page is stored as an HTML blob (`graper_pages.html` + `.css`). A blob holds exactly
one language, so the naive reading is that a visual canvas costs you multilingual content.

It does not, because the switcher already works by **key lookup, not by structure**: it reads
`data-i18n` attributes from the DOM and swaps their contents from a JSON payload. If the
canvas-authored HTML carries those attributes, the payload can come from anywhere — including a
side table filled in on a separate screen. The canvas holds the layout and the English copy;
the translations live beside it.

## Decisions

| Decision | Choice | Rejected |
|---|---|---|
| Page builder | Graper / GrapesJS visual canvas | Filament `Builder` field (no live preview) |
| Block source | Custom SCBD-styled blocks extending Graper's `Block` | Graper's 15 stock Tailwind blocks; GrapesJS defaults |
| Page chrome and assets | Override `graper::display` to use our layout, `scbd.css` and the animation bundle | Graper's standalone document with CDN Tailwind |
| Translations | Separate screen driven by `data-i18n` keys parsed from the saved HTML, stored in a side table | One page per locale; drop multilingual; inline canvas translation |
| Authoring language | English on the canvas; other locales on the translations screen | Per-locale canvases |
| Hook integrity | Validation pass on save warning about missing `data-*` hooks | Trust editors not to delete attributes |

## Verified starting facts

Established against the installed source, not assumed:

- `CybertronianKelvin\Graper\Blocks\Block` is abstract with `getId()`, `getName()`,
  `getCategory()`, `getTemplate()`, `getOrder()`, `getThumbnail()`. Custom blocks are ordinary
  subclasses — `getTemplate()` returns raw HTML.
- Graper registers a `graper` **view namespace** via spatie package-tools `->hasViews()`.
  Confirmed at runtime that the `graper` hint resolves, so
  `resources/views/vendor/graper/display.blade.php` overrides the render view.
- `graper_pages` columns: `title`, `slug`, `project_data` (json), `html` (text), `css` (text),
  `css_class`, `is_published`, `created_by`, timestamps. **No `is_homepage`** — this design adds one.
- Graper's public route is `GET /{prefix}/{slug}` registered in `GraperServiceProvider:51`,
  prefix from `config('graper.page_route_prefix')`, default `pages`.
- Graper's stock blocks are generic Tailwind marketing templates (`HeroBlock` opens
  `<section class="relative bg-gradient-to-br from-indigo-900 via-purple-900...">`). They are
  **not** used; they carry no `data-*` hooks and do not match the SCBD design.
- Graper's shipped `display.blade.php` is a standalone document loading Tailwind from
  `cdn.tailwindcss.com`. It loads neither `scbd.css` nor the animation bundle, which is why the
  override is mandatory rather than cosmetic.

## Starting state

Carried forward unchanged:

- `App\Concerns\HasTranslatableFields` — still used by `SiteSetting` and `PublicMenuItem`
- `App\Models\SiteSetting` (`LOCALES`, branding, site-wide meta), `App\Models\PublicMenuItem`
- `App\Filament\Support\LocaleTabs`, `App\Concerns\EditsSingletonRecord`
- `App\Filament\Navigation\AdminNavigation` — sole owner of the sidebar
- `App\Support\ReferenceImageFetcher`, `App\Enums\StatFormat`
- `resources/css/scbd.css` and the nine self-hosted Archivo WOFF2 files
- The GSAP/ScrollTrigger/Lenis modules, rebound from fixed section IDs to block roots
- `resources/views/partials/home/{loader,header}.blade.php` — page chrome, reused by the override

Removed:

- `App\Models\{DistrictPlace, Facility, Stat, HomepageContent}` and their tables
- `DistrictPlaceResource`, `FacilityResource`, `StatResource`, `HomepageEditor`
- `resources/views/partials/home/{hero,about,marquee,district,facilities,news,contact}.blade.php`
  — their markup moves into block templates
- `App\Support\HomepageData`, `App\Http\Controllers\HomeController`, `resources/views/home.blade.php`
- The `Homepage Data` sidebar group

**Not removed, contrary to the previous plan:** `cybertroniankelvin/graper`.

## Architecture

Five parts, each independently testable:

1. **Blocks** — SCBD-styled subclasses of Graper's `Block`, each returning markup carrying its
   `data-*` animation hooks and `data-i18n` translation keys.
2. **Render override** — `resources/views/vendor/graper/display.blade.php`, wrapping Graper's
   HTML in the public layout with the real stylesheet, fonts, chrome and animation bundle.
3. **Translations** — a `page_translations` table plus a Filament screen that parses the page's
   saved HTML for `data-i18n` keys and presents one row per key per locale.
4. **Payload** — a builder that turns those rows into the `#scbd-i18n` JSON the existing
   switcher already consumes.
5. **Animation** — the existing modules, rebound to `[data-block]` roots with scoped
   `gsap.context()` so block order and duplication stop mattering.

## Data model

### `graper_pages` (existing, extended)

One added column, via our own migration:

| Column | Type | Notes |
|---|---|---|
| `is_homepage` | boolean, default false | exactly one true row, partial unique index |

`/` resolves the page flagged `is_homepage`. Graper's own `/pages/{slug}` route continues to
serve everything else.

### `page_translations` (new)

| Column | Type | Notes |
|---|---|---|
| `graper_page_id` | foreign id, cascade delete | |
| `key` | string | the `data-i18n` value, e.g. `hero.heading` |
| `locale` | string | `id` or `cn`; English lives in the canvas HTML |
| `value` | text | translated copy, newlines allowed |

Unique on `(graper_page_id, key, locale)`.

English is deliberately **not** stored here. It is whatever the canvas HTML says, so there is
one source of truth for it and no chance of the canvas and the translation table disagreeing
about the default language.

## Blocks

Nine blocks, matching the sections the SCBD homepage needs. Each is a subclass of Graper's
`Block` whose `getTemplate()` returns markup transcribed from the existing partial:

| Block id | From partial | Animation hooks it must emit | i18n keys |
|---|---|---|---|
| `scbd-hero` | `hero` | `data-split`, `data-parallax-wrap`, `data-parallax` | `hero.heading`, `hero.sub` |
| `scbd-text-image` | `about` | `data-fade`, `data-reveal` | `textimage.heading`, `textimage.body` |
| `scbd-stats` | `about` (stats row) | `data-count` + `data-to`/`data-suffix`/`data-plain` | `stats.label.N` |
| `scbd-marquee` | `marquee` | `data-marquee` | `marquee.text` |
| `scbd-horizontal` | `district` | `data-horizontal-track` | `horizontal.heading`, `horizontal.title.N` |
| `scbd-cards` | `facilities` | `data-stack`, `data-card` | `cards.heading`, `cards.title.N` |
| `scbd-posts` | `news` | `data-news` | `posts.heading` |
| `scbd-cta` | header CTA markup | `data-magnetic` | `cta.heading`, `cta.button` |
| `scbd-contact` | `contact` | `data-split` | `contact.heading` |

Every block root carries `data-block="{id}"`. Keys are namespaced by block id so two blocks of
the same type on one page do not collide; where a block repeats items, the key carries an index
(`stats.label.0`). Because an editor may duplicate a block on the canvas, the translations
screen presents whatever keys it actually finds — duplicates included — rather than a fixed list.

`scbd-posts` renders a server-side placeholder in the canvas and is filled with real posts at
render time, since GrapesJS cannot execute a query.

## Render override

`resources/views/vendor/graper/display.blade.php` replaces Graper's standalone document:

```blade
<x-layouts.public :data="$pageData">
    @include('partials.home.loader')
    @include('partials.home.header', ['data' => $pageData])

    <main style="position:relative; width:100%; background:#f3f2f2; color:#201e1d; font-family:'Archivo',system-ui,sans-serif; cursor:none;">
        {!! $html !!}
    </main>
</x-layouts.public>
```

The wrapper's declarations are not decoration: dropping `cursor:none` leaves the native pointer
visible alongside the custom cursor, and dropping `font-family` silently falls off Archivo. Both
regressions happened during the previous build.

`{!! $html !!}` is unescaped by necessity — it is editor-authored markup, same as any WYSIWYG
CMS. That is a deliberate trust boundary: anyone who can edit a page can inject script, which is
equivalent to the admin access they already hold.

## Translations screen

A Filament page reachable from the Pages list. On open it:

1. Loads the page's saved `html`.
2. Parses it for `data-i18n="..."` attributes, preserving document order.
3. For each key, shows the English text extracted from the element as read-only context, then an
   input per non-English locale, prefilled from `page_translations`.
4. On save, upserts one row per (key, locale) with a non-empty value, and deletes rows whose
   value was cleared.

Keys that vanish from the HTML — because the editor deleted that block — leave orphaned rows.
The screen shows them in a collapsed "No longer on this page" section with a delete action,
rather than silently discarding translations for a block that might be re-added.

## Payload

At render, the payload becomes:

```json
{
  "en": { "hero.heading": "A district<br>that never<br>clocks out" },
  "id": { "hero.heading": "Kawasan<br>yang tak<br>pernah tidur" },
  "cn": { "hero.heading": "永不<br>停歇的<br>商务区" }
}
```

English entries are extracted from the rendered HTML; `id` and `cn` come from
`page_translations`. A missing translation falls back to the English value per key, matching
`HasTranslatableFields` semantics. Values are escaped and newline-converted to `<br>` exactly as
today, in that order — escaping afterwards would escape the tags.

The existing switcher consumes this unchanged.

## Hook integrity

GrapesJS lets editors edit markup, so a `data-split` or `data-i18n` attribute can be deleted —
killing an effect or a translation with no error. A validation pass runs on page save:

- For each `[data-block]` found, look up the block's declared required hooks.
- Report any missing hook as a warning notification naming the block and the attribute.

It **warns rather than blocks**, because refusing to save an editor's work over a missing
attribute is worse than a degraded section. The warning is the point: this class of failure is
silent by nature, and this project has repeatedly shipped silent failures.

## Error handling

| Condition | Behaviour |
|---|---|
| No page flagged `is_homepage` | `/` returns 404 with a logged warning |
| Two rows flagged `is_homepage` | Prevented by a partial unique index |
| Unpublished page requested by a guest | 404 |
| `data-i18n` key present in HTML, absent from `page_translations` | Falls back to the English text from the HTML |
| Orphaned translation rows | Listed on the translations screen, never auto-deleted |
| Block missing a required hook | Warning on save; the section renders without that effect |
| Horizontal track narrower than viewport | Pinning not created — prevents pinning the page with nowhere to scroll |
| `prefers-reduced-motion` | No block initialisers run; resting states applied |

## Testing

Unit:
- Each block class: `getId()`, `getTemplate()` returns markup containing its declared hooks
- Hook validator: detects a missing `data-split`, passes a well-formed block
- Key parser: extracts keys in document order, handles duplicates and nesting
- Payload builder: English from HTML, `id`/`cn` from rows, per-key fallback, escape-then-`<br>` order

Feature:
- `/` resolves the flagged page; 404 when none
- The override renders `scbd.css`, the animation bundle, the header and the loader
- The rendered page's `data-i18n` set exactly matches the payload's key set for every locale
- Saving translations upserts and clears correctly; orphans are listed, not deleted
- Two blocks of the same type on one page produce distinct keys

Browser (manual — no JS harness in this project):
- Drag a block onto the canvas, save, and see it render in the SCBD design
- Reorder blocks and confirm every effect still works
- Pinning engages and releases; counters run once; marquee accelerates without freezing
- EN/ID/CN switching swaps text without a reload and without breaking scroll

## Migration

1. Add `is_homepage`, `page_translations`, and the render override — nothing user-visible changes.
2. Build the nine blocks and register them.
3. Compose the homepage on the canvas and translate it.
4. Point `/` at the flagged page and verify in a browser.
5. Only then remove the old models, resources and partials.

Step 5 is last for the same reason as before: until the canvas-built homepage is confirmed
correct, the hand-built one is the only thing it can be compared against.

## Out of scope

Blog restructure (A2), Media Library integration (B), Branding/Header/Footer (C), Contact
Messages (D), Users & Roles (E), SEO completion (F). Also out of scope: block-level revision
history, canvas previews per locale, and editor-defined custom blocks.
