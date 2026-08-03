# Block Page Builder (Slice A1) — Design

> **SUPERSEDED on 2026-08-03.** This design assumed Filament's `Builder` field and the removal
> of Graper. The owner chose a live visual canvas instead, so the page builder is now
> GrapesJS/Graper with SCBD-styled custom blocks and a separate translations screen. See
> `docs/superpowers/specs/2026-08-03-graper-page-builder-design.md`.
>
> Retained here for its reasoning: the block-vs-bespoke trade-off, the animation-binding
> analysis, and the Filament `Builder` storage findings (ephemeral UUIDs, `array_values` on
> dehydrate) which remain true and are worth not rediscovering.


**Date:** 2026-07-31
**Status:** Approved (design), pending implementation plan
**Project:** `iat-cms` — Laravel 13.23, Filament 5.7.4, PHP 8.4, PostgreSQL, Vite 8
**Supersedes parts of:** `2026-07-30-scbd-homepage-cms-design.md`

## Goal

Replace the bespoke SCBD homepage and the GrapesJS page editor with a single block-based
page builder, so that every page — including the homepage — is assembled from the same
reusable, reorderable blocks in one editor.

The acceptance test is concrete and falsifiable: **the existing SCBD homepage must be
rebuildable entirely from blocks**, with its pinned horizontal scroll, char-split headings,
count-up stats and velocity-reactive marquee intact.

## Why this slice exists

The current build has two editing models. The homepage is hand-written Blade driven by a
fixed-field Filament form; Pages is GrapesJS. Editors must learn both, the homepage cannot
be reordered or reused, and three SCBD-specific content types (District Places, Facilities,
Stats) occupy the sidebar as if they were CMS concepts rather than one client's nouns.

## Scope

This is slice **A1** of a six-slice programme. The others are specified separately:

| Slice | Contains | Status |
|---|---|---|
| **A1** | Block builder core, `Page` model, renderer, animation binding, reusable block library | **this spec** |
| A2 | Blog group: Blog Dashboard, Authors, posts gain blocks | next |
| B | Media Library integration (`MediaPicker` everywhere) | later |
| C | Site chrome: Branding, Header, Footer | later |
| D | Contact Messages: public form + inbox | later |
| E | Users & Roles | later |
| F | SEO defaults and per-page SEO completion | later |

## Decisions

Each was made explicitly during brainstorming.

| Decision | Choice | Rejected |
|---|---|---|
| Homepage | Becomes an ordinary `Page` flagged `is_homepage`; effects live inside blocks | Keep bespoke; discard entirely; defer migration |
| Graper (GrapesJS) | Removed entirely — it holds zero pages, so nothing migrates | Keep as a Raw HTML block; keep for Pages only |
| SCBD content types | Dropped; blocks own their items inline via repeaters | Keep global models; generic Collections type |
| Block i18n | Shared structure, translatable leaf fields (`{en,id,cn}` per text field) | Per-locale block trees; translations table; English only |
| Block set | SCBD parity — nine blocks | Minimal four; generic seven |
| Animation binding | Per-block init with a scoped `gsap.context()` | Global `data-*` scanning; per-block dynamic imports |
| Content reuse | Reusable block library with live references and detach | Global collections; copy-only presets; page duplication |

## Starting state

Carried forward unchanged:

- `App\Concerns\HasTranslatableFields` — `{en,id,cn}` JSON with per-key English fallback
- `App\Models\SiteSetting` — `LOCALES`, site-wide meta, branding fields
- `App\Models\PublicMenuItem` — public nav with `is_cta`
- `App\Support\ReferenceImageFetcher`
- `App\Filament\Support\LocaleTabs`, `App\Concerns\EditsSingletonRecord`
- `App\Filament\Navigation\AdminNavigation` — sole owner of the sidebar
- The GSAP/ScrollTrigger/Lenis modules, rebound from document scope to block roots
- `resources/css/scbd.css` and the nine self-hosted Archivo WOFF2 files

Removed by this slice:

- `cybertroniankelvin/graper` package, the `graper_pages` table, the `/pages/{slug}` route
- `App\Models\DistrictPlace`, `App\Models\Facility`, `App\Models\Stat` and their tables
- `DistrictPlaceResource`, `FacilityResource`, `StatResource` and their pages
- The nine partials under `resources/views/partials/home/`
- `App\Filament\Pages\HomepageEditor`, the `App\Models\HomepageContent` model and the entire
  `homepage_contents` table — its content moves into the homepage `Page`'s blocks during
  migration step 2, and the table is dropped in step 4, not before
- The `Homepage Data` sidebar group

`App\Enums\StatFormat` is retained — the Stats block's repeater reuses it.

## Architecture

Four layers, each independently testable:

1. **Registry** — PHP block definitions. Knows Filament schemas and view names; knows nothing about rendering or animation.
2. **Content** — `Page` and `ReusableBlock` models plus `HasBlocks`. No Filament or Blade knowledge.
3. **Presentation** — one Blade component per block type, driven by a renderer that walks the block list.
4. **Animation** — one JS initialiser per block type, each opening a `gsap.context()` scoped to its own root element.

The PHP registry and the JS registry are mirrors keyed by the same type strings. That
correspondence is the contract, and a test asserts the two key sets match exactly.

## Data model

### `pages`

| Column | Type | Notes |
|---|---|---|
| `title` | json | translatable |
| `slug` | string, unique | generated from the English title |
| `blocks` | json | the block list; see shape below |
| `status` | string | `draft` \| `published` |
| `is_homepage` | boolean | exactly one true row, enforced by a partial unique index |
| `meta_title`, `meta_description` | json | translatable, per-page SEO |
| `og_image` | string | media path |
| timestamps | | |

`is_homepage` is how the homepage stops being special-cased. `/` resolves the flagged row;
every other path resolves by slug. There is no homepage controller and no homepage editor.

### `reusable_blocks`

| Column | Type | Notes |
|---|---|---|
| `name` | string | shown in the picker, e.g. "Facilities" |
| `type` | string | block type; a reusable block is a single block, not a list |
| `data` | json | the block's data, same shape as an inline block's `data` |
| timestamps | | |

### Block JSON shape

**This shape is dictated by Filament, not chosen by us.** `Builder::mutateDehydratedStateUsing`
does `array_values($state)` on save, and `hydrateItems()` regenerates UUID keys on every load
(`vendor/filament/forms/src/Components/Builder.php:141-158`). So the persisted column is a
plain indexed array, and only `type` and `data` survive a round trip:

```json
[
  {
    "type": "hero",
    "data": {
      "heading": { "en": "A district", "id": "Kawasan", "cn": "商务区" },
      "image": "uploads/pages/hero.jpg",
      "cta_url": "#contact"
    }
  },
  { "type": "reusable", "data": { "ref": 3 } }
]
```

Two consequences follow, and both were design errors in an earlier draft of this spec:

**There is no stable per-block identifier.** Filament's UUIDs are ephemeral — generated at
hydration, stripped at dehydration. Anything keyed by them would silently reassign itself on
every save. Blocks are therefore addressed by their **render-time array index**. That is safe
because the DOM and the i18n payload are generated in the same pass from the same array, so
the two always agree; reordering changes both together.

**A referenced block is its own block type, not a flag.** Since only `type` and `data`
persist, `ref` cannot sit alongside them. A library reference is a `reusable` block whose
schema is a single Select over `reusable_blocks`, storing `{"ref": 3}`. The renderer resolves
it to the referenced entry's own `type` and `data` before rendering.

**Detaching** replaces the `reusable` entry in the page's array with a plain block carrying a
copy of the library entry's `type` and `data`, so the page keeps a private copy that no
longer tracks the library.

### `HasBlocks` concern

- casts `blocks` to `array`
- `renderableBlocks(): array` — resolves `reusable` entries against `reusable_blocks`, filters
  out types absent from the registry (logging each), and returns render-ready entries
- `blockTypes(): array` — the distinct types on this record, used by tests and tooling

Reference resolution eager-loads every referenced `ReusableBlock` in one query, so a page
with twenty referenced blocks costs one extra query, not twenty.

Note Filament's Builder already discards items whose `type` is not a registered block
(`Builder.php:949`), so the admin form self-heals. `renderableBlocks()` must repeat that
filter independently, because it reads the column directly and never passes through the form.

## Block registry

`App\Blocks\Block` is the abstract base:

```php
abstract public static function type(): string;    // 'hero'
abstract public static function label(): string;   // 'Hero'
abstract public static function icon(): string;    // heroicon name
abstract public static function schema(): array;   // Filament components
public static function view(): string;             // defaults to "blocks.{type}"
public static function defaults(): array;          // seed data for a new instance
```

`App\Blocks\BlockRegistry` is a singleton bound in `AppServiceProvider`, exposing `all()`,
`get(string $type): ?Block`, `has(string $type): bool`, and `toBuilderBlocks(): array`
which maps each class to a `Filament\Forms\Components\Builder\Block`.

Registering a block in one place makes it available to the admin form, the renderer and
validation simultaneously. Nothing else enumerates block types.

### Translatable fields inside blocks

Each block's schema splits into translatable copy and locale-independent settings:

```php
public static function schema(): array
{
    return [
        LocaleTabs::make(fn (string $locale) => [
            Textarea::make("heading.$locale")->required(LocaleTabs::isFallback($locale)),
            Textarea::make("body.$locale"),
        ]),
        FileUpload::make('image')->image()->disk('public')->directory('uploads/pages'),
        TextInput::make('cta_url'),
    ];
}
```

English is required; Indonesian and Chinese are optional and fall back per key, exactly as
`HasTranslatableFields` already behaves. Slice B replaces the `FileUpload` with `MediaPicker`.

### The nine blocks

| Type | Fields | Effect it owns |
|---|---|---|
| `hero` | heading, sub, image, cta label + url | char-split, parallax, clip reveal |
| `text-image` | heading, body, image, `reversed` toggle | fade, clip reveal |
| `stats` | repeater: label, value, suffix, format (`StatFormat`) | count-up |
| `marquee` | text, speed | velocity-reactive scroll |
| `horizontal-scroll` | heading, body, repeater: title, caption, image | **pinning** |
| `stacked-cards` | heading, body, repeater: title, body, image | scrub scale/translate |
| `post-list` | heading, count, category filter, CTA label | row hover |
| `cta` | heading, button label, url | magnetic button |
| `contact` | heading, email, phone, address | char-split |

`stats`, `horizontal-scroll` and `stacked-cards` carry repeaters — this is where District
Places, Facilities and Stats now live, inline and portable.

## Rendering

```
resources/views/blocks/{type}.blade.php          one per block type
resources/views/components/block-renderer.blade.php
```

`<x-block-renderer :blocks="$page->renderableBlocks()" />` walks the list and includes each
block's view with its resolved `data`. Every block partial emits the same envelope:

```blade
<section data-block="hero" data-block-index="{{ $index }}" style="...">
```

Markup and inline styles are transcribed from the existing partials, which were themselves
transcribed from the reference design. Headings bound from the i18n payload use `{!! !!}`
because those values are pre-escaped and carry `<br>`; everything else uses `{{ }}`.

### Routing

| Route | Resolves |
|---|---|
| `GET /` | the `Page` where `is_homepage` is true |
| `GET /{slug}` | the published `Page` with that slug |
| `GET /blogs/*` | unchanged — Story's routes |

`/{slug}` is registered last so it cannot shadow `/blogs` or `/superduper`. A draft page
returns 404 to guests.

## Animation binding

`resources/js/blocks/index.js` maps type → initialiser. The page bootstrap queries once:

```js
document.querySelectorAll('[data-block]').forEach((el) => {
  BLOCKS[el.dataset.block]?.(el);
});
```

Each initialiser opens `gsap.context(fn, el)`, scoping every selector inside it to that
block's root. Consequences:

- Block order is irrelevant — no initialiser reaches outside its own element.
- Two instances of the same block on one page each get their own context instead of
  fighting over `document.querySelector`.
- It fixes a latent defect in the current code: `reveal.js` queries `#district img` and
  `#facilities img` **by ID**, which stops existing once sections become blocks.

Page-level concerns initialise once per page, not per block: the loader, Lenis smooth
scroll, the custom cursor, and the language switcher. The loader's staged handoff to the
hero becomes "reveal the first block" rather than a cross-section timeline — the
choreography deliberately traded away when blocks were chosen.

The `prefers-reduced-motion` guard moves to the bootstrap: when set, no block initialiser
runs and a single pass sets all blocks to their resting state.

### Retained animation lessons

Three defects were found in the reference implementation during the SCBD build and must not
regress. Each carries a test or a comment at the site:

- Baking `transform: translateY(105%)` as literal inline CSS poisons GSAP's `yPercent`
  cache, leaving split headings permanently invisible. Set it via `gsap.set` instead.
- `gsap.to(el, { timeScale })` targets a DOM element, so GSAP treats `timeScale` as an
  unknown property and `overwrite: true` kills the loop tween. Animate the tween, not the
  element.
- Lenis ignores `scrollTo()` and tracks its own wheel-driven target, so browser
  verification must dispatch `WheelEvent`s.

## i18n

Unchanged in mechanism. Leaves inside block data carry `{en,id,cn}`; the payload builder
walks the block tree instead of a fixed column map, emitting one entry per translatable
leaf keyed by `{index}.{field}`, where `index` is the block's position in the render-time
array. Because structure is identical across locales, the existing no-reload switcher
continues to work.

**The key contract:** a block partial rendering a translatable leaf must emit
`data-i18n="{index}.{field}"` matching the payload key exactly. The switcher looks each
element's key up in the payload and silently skips anything it cannot find, so a mismatched
or missing attribute produces text that never translates and never errors. A feature test
asserts that the set of `data-i18n` attributes in the rendered page is exactly the set of
keys in the embedded payload — neither side may carry an entry the other lacks.

The payload is keyed by index rather than block type because two `hero` blocks on one page
would otherwise collide. Index is safe — and a Filament UUID is not — because the DOM and
the payload are produced in the same render pass from the same array, whereas Filament's
UUIDs are regenerated on every form load and stripped on every save.

`HomepageData` is replaced by `PageData`, which assembles content, settings, menu, resolved
blocks and the i18n payload for any page.

## Error handling

| Condition | Behaviour |
|---|---|
| Block type absent from the registry | Filtered from render, logged once with the type and page id |
| A `reusable` block's `ref` points at a deleted library entry | Filtered from render, logged; the admin form shows the Select as empty |
| No page flagged `is_homepage` | `/` returns 404 with a clear log line; a seeder creates one |
| Two rows flagged `is_homepage` | Prevented by a partial unique index |
| Draft page requested by a guest | 404 |
| Missing image on a block | Neutral placeholder, never an empty `src` |
| `horizontal-scroll` track narrower than the viewport | Pinning is not created — prevents pinning the page with nowhere to scroll |
| `prefers-reduced-motion` | No initialisers run; resting states applied |

## Testing

Unit:
- `BlockRegistry` — registration, lookup, unknown type, `toBuilderBlocks()` shape
- Every block class — `type()`, `view()` resolves to an existing file, `schema()` builds
- `HasBlocks` — casting, unknown-type filtering, `ref` resolution, detach semantics
- **The PHP and JS registries expose identical type sets** — asserted by parsing both

Feature:
- `/` resolves the `is_homepage` page; 404 when none exists
- `/{slug}` resolves published pages, 404s drafts, and does not shadow `/blogs`
- Rendered output carries one `[data-block]` per block, in the stored order
- Reordering blocks reorders the rendered sections
- A referenced block renders the library's data; editing the library changes both pages
- Detaching stops tracking the library
- The i18n payload contains every translatable leaf across all three locales
- Reference resolution costs one extra query regardless of reference count

Filament:
- The Builder field offers all nine blocks
- A page saves with blocks and reloads with identical structure
- English is required per block; other locales optional
- Saving from the library picker stores a `ref`, not a copy

Browser (manual, no JS harness in this project):
- The homepage rebuilt from blocks matches the current design
- Pinning engages for `horizontal-scroll` and releases cleanly
- Two `stats` blocks on one page count independently
- Reordering blocks in the admin changes the front-end order without breaking effects
- Reduced motion applies resting states without throwing

## Migration

1. Introduce `pages`, `reusable_blocks`, the registry, blocks and the renderer alongside
   the existing homepage, changing nothing user-visible.
2. Seed one `Page` flagged `is_homepage` whose blocks reproduce the current homepage,
   transcribing copy and images from `homepage_contents`, `district_places`, `facilities`
   and `stats`.
3. Switch `/` to the page resolver and verify the rebuilt homepage in a browser.
4. Only then remove Graper, the three models and resources, the nine partials, and
   `HomepageEditor`.

Step 4 is deliberately last: the old homepage stays available as a reference until the new
one is confirmed correct.

## Out of scope

Blog restructure (slice A2), Media Library integration (B), Branding/Header/Footer (C),
Contact Messages (D), Users & Roles (E), SEO defaults (F). Also out of scope: block
versioning, draft previews, per-block visibility scheduling, and multi-site support.
