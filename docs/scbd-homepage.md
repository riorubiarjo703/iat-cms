# SCBD homepage — content model, locales, and animation contract

This document explains how the public homepage at `/` is assembled from the
admin panel, how the three-locale fallback works, the `data-*` contract
between Blade and the animation modules, how to manage the public nav, and
the two guarded failure modes in the animation layer.

Spec: `docs/superpowers/specs/2026-07-30-scbd-homepage-cms-design.md`
Plan: `docs/superpowers/plans/2026-07-30-scbd-homepage-cms.md`

## Which admin screen edits which part of the homepage

`HomeController::__invoke()` calls `App\Support\HomepageData::build()`, which
runs every query the page needs exactly once and hands Blade one readonly DTO
(`$data`). Blade performs no queries. The sections in `resources/views/home.blade.php`
map to admin screens as follows:

| Homepage section | Blade partial | Admin screen | Model |
|---|---|---|---|
| Loader | `partials/home/loader.blade.php` | — (static markup, no editable copy) | — |
| Header, nav, language switcher, CTA button | `partials/home/header.blade.php` | **Homepage** (brand subtitle) + **Public Menu** (nav links, CTA) | `HomepageContent`, `PublicMenuItem` |
| Hero | `partials/home/hero.blade.php` | **Homepage** → Hero tab/section + Media & Links (hero image) | `HomepageContent` |
| Marquee strip | `partials/home/marquee.blade.php` | **Homepage** → Marquee section | `HomepageContent` |
| About + stat counters | `partials/home/about.blade.php` | **Homepage** → About section + Media & Links (about image) and **Stats** resource | `HomepageContent`, `Stat` |
| District (pinned horizontal scroll) | `partials/home/district.blade.php` | **Homepage** → District section (heading/body) and **District Places** resource (each place + image) | `HomepageContent`, `DistrictPlace` |
| Facilities (stacked cards) | `partials/home/facilities.blade.php` | **Homepage** → Facilities section and **Facilities** resource | `HomepageContent`, `Facility` |
| News rows | `partials/home/news.blade.php` | **Homepage** → News section; rows themselves come from the Story plugin's **Blog Posts** (published only, newest 3) | `HomepageContent`, `BlogPost` |
| Contact | `partials/home/contact.blade.php` | **Homepage** → Contact section + Media & Links (email/phone/address) | `HomepageContent` |
| Favicon, site name, meta tags, default language | `resources/views/components/layouts/public.blade.php` | **Site Settings** | `SiteSetting` |

Both **Homepage** (`App\Filament\Pages\HomepageEditor`) and **Site Settings**
(`App\Filament\Pages\SiteSettingsPage`) are Filament singleton pages backed
by `EditsSingletonRecord` — there is always exactly one `HomepageContent` row
and one `SiteSetting` row (`Model::singleton()` creates it on first read if
missing). **District Places**, **Facilities**, **Stats**, and **Public Menu**
are ordinary Filament resources with drag-to-reorder tables (`->reorderable('sort')`)
backed by the `Orderable` concern, and District Places/Facilities/Public Menu
also use the `Activatable` concern (`is_active`) to control whether a row
renders on the public page at all.

## The three-locale fallback

Locales are exactly `en`, `id`, `cn` (`SiteSetting::LOCALES`). Every
translatable field is stored as a single JSON column (`{"en": "...", "id":
"...", "cn": "..."}`) via the `App\Concerns\HasTranslatableFields` trait,
applied to `HomepageContent`, `DistrictPlace`, `Facility`, `Stat`, and
`PublicMenuItem`. Each model lists its translatable columns in a
`TRANSLATABLE` constant (e.g. `HomepageContent::TRANSLATABLE`), which the
trait turns into `array` casts automatically.

Reading a value goes through `$model->t($column, $locale = null)`:

1. If `$locale` is omitted, it defaults to `app()->getLocale()`.
2. If the JSON map has a non-empty value for that locale, return it.
3. Otherwise fall back to `en` (`HasTranslatableFields::FALLBACK_LOCALE`).
4. If even `en` is empty, return `null`.

In the Filament forms, `id` and `cn` fields are optional; only the `en` field
on each tab is `required()` (`LocaleTabs::isFallback($locale)` marks the
English tab as the required one). This means an editor can fill in English
only and the site stays fully populated in Indonesian and Chinese — it just
shows the English copy until someone translates it.

For the browser-side language switcher, the fallback is pre-resolved
server-side into a JSON payload (`HomepageData::i18nPayload()`), embedded in
the page as `<script id="scbd-i18n" type="application/json">`. Each locale
bucket already contains the same fallback-resolved value, so the client-side
switch (`resources/js/scbd/i18n.js`) never has to reason about fallback — it
just swaps `dictionary[locale][key]` into `[data-i18n]` elements' `innerHTML`.
Nav items and the CTA are added to the payload dynamically as `nav1`..`navN`
and `cta`, keyed by their position in the `PublicMenuItem` list, not by a
fixed key — so adding/removing nav items doesn't require any code change to
the i18n payload.

## The `data-*` contract between Blade and the animation modules

The animation layer (`resources/js/scbd/*.js`, entered through
`resources/js/scbd/index.js`) has zero Laravel knowledge. It only looks for
`data-*` attributes and CSS selectors in the rendered DOM. Blade's only job is
to emit the right attribute on the right element; nothing else couples the
two layers.

| Attribute | Emitted by (Blade) | Consumed by (JS) | Purpose |
|---|---|---|---|
| `data-loader`, `data-loader-num`, `data-loader-bar` | `loader.blade.php` | `loader.js` | The intro loader panel, its `000`→`100` counter, and its fill bar |
| `data-header` | `header.blade.php` | `header.js`, `loader.js` | The fixed header that hides on scroll-down / shows on scroll-up |
| `data-magnetic` | header logo, header CTA, hero/about/district/news CTAs | `cursor.js` | Buttons/links that pull toward the pointer and spring back |
| `data-navlink` | header nav `<a>` | *(styling hook only — no JS behaviour)* | |
| `data-lang="{code}"` | header language buttons | `i18n.js` | Click target for switching locale |
| `data-i18n="{key}"` | any translatable text node | `i18n.js` | Maps the element to a key in the `#scbd-i18n` JSON payload |
| `data-split` | hero/district/facilities/news/contact headings | `textSplit.js`, `loader.js`, `reveal.js` | Marks a heading to be split into one `<span data-char>` per character (line breaks are literal `<br>` from the server) |
| `data-char` | *(generated by `textSplit.js`, not Blade)* | `loader.js`, `reveal.js`, `i18n.js` | One character span; the loader and scroll reveals stagger these |
| `data-parallax-wrap` / `data-parallax` | hero image wrapper / image | `loader.js`, `reveal.js` | The clip-path reveal-from-bottom and scroll parallax on the hero image |
| `data-fade` | about heading/paragraph | `reveal.js` | Fade-and-rise-on-entry text |
| `data-reveal` | about image (also implicitly `#district img`, `#facilities img`) | `reveal.js` | Clip-path reveal-from-bottom + scale-down on entry |
| `data-marquee` | the scrolling strip's inner track | `marquee.js` | Infinite horizontal loop, sped up while the user scrolls |
| `data-horizontal-track` | district's flex track | `district.js` | The element ScrollTrigger pins and drags horizontally |
| `data-stack`, `data-card` | facilities wrapper / each facility `<article>` | `stack.js` | The scale/translate "next card arrives" effect; the *last* card is exempt (nothing arrives after it) |
| `data-news` | each news row | `reveal.js` (`initNewsHover`) | Shifts the row right 14px on hover |
| `data-count`, `data-to`, `data-suffix`, `data-plain` | each stat in `about.blade.php` | `counters.js` | `data-to` is the target number, `data-suffix` an optional string appended after counting (e.g. `/7`), `data-plain` forces `String(n)` instead of `n.toLocaleString()` — this is what keeps `1987` from rendering as `1,987` |

Two things worth calling out explicitly:

- **`gsap.set('[data-char]', { y: 0, yPercent: 105 })` in `index.js` is not
  cosmetic.** `textSplit.js` bakes a literal `transform:translateY(105%)`
  string into each generated span. The first time GSAP touches an element it
  resolves that through `getComputedStyle()`, which returns an already-baked
  pixel matrix — GSAP would otherwise cache that as a permanent `y` baseline
  separate from its own `yPercent` tracking (which starts at 0), so every
  later `yPercent` tween (including `yPercent: 0`) computes against a
  baseline that never clears, leaving characters permanently hidden. The
  explicit `gsap.set` with both `y` and `yPercent` re-claims the transform as
  a percentage from a zero baseline before anything else touches it.
- **`initNewsHover` is not in the Task 17 module list, but the reference
  design (page.jsx) and the rendered `[data-news]` markup both have it.**
  It is intentionally ported anyway (see the comment in `reveal.js`) — a hook
  that visibly exists in the DOM and in the reference source shouldn't be
  silently dropped because a brief's module list omitted it.
- **History — the marquee froze permanently on first scroll (fixed
  2026-07-31).** `page.jsx:110-113` (the reference this was ported from) does
  `gsap.to(marquee, { timeScale: boost, overwrite: true })` — `marquee` there
  is the DOM element, not the loop tween, so GSAP treats `timeScale` as an
  unrecognized CSS property (logging `Invalid property timeScale... Missing
  plugin?`) and `overwrite: true` kills every other tween on that same
  element, including the real infinite `xPercent` loop, the very first time
  the marquee's `ScrollTrigger` fired `onUpdate`. Confirmed live in a real
  browser: the strip moved correctly until the first scroll near it, then
  froze at whatever position it had reached, for the rest of the session.
  Same category of defect as the `yPercert` typo from Task 17 and the
  `data-char` baseline-caching issue in `index.js` — the reference itself was
  wrong, and this project's port had copied the defect rather than the
  intent. **Fix:** `marquee.js` now keeps a reference to the loop tween
  (`const loop = gsap.to(marquee, {...})`) and animates `loop`'s `timeScale`
  instead of the element's. `overwrite: true` is still correct there — it
  now overwrites a *prior boost tween*, not the loop itself. Re-verified
  live: no console warning, the strip moves continuously before, during, and
  after scrolling, visibly speeds up while the wheel is active, and never
  freezes across five separate scroll bursts spanning most of the page.

## Adding a nav item and marking one as the CTA

Nav items and the header CTA are both `App\Models\PublicMenuItem` rows,
managed from **Appearance → Public Menu** in the sidebar:

1. Create a new Public Menu Item.
2. Fill in the **Label** per locale (English required).
3. Set **URL** — an anchor like `#about` scrolls smoothly (via
   `resources/js/scbd/smoothScroll.js`'s Lenis-based anchor handler); a path
   like `/blogs` navigates normally.
4. Leave **"Render as the header call-to-action button"** off for an
   ordinary nav link, or turn it on to make this item the CTA button instead
   of a nav link.
5. **Visible** (`is_active`) controls whether it renders at all.
6. Drag rows in the table to reorder; nav links render left-to-right in that
   order.

Under the hood, `PublicMenuItem::scopeLinks()` selects active, non-CTA items
ordered by `sort` (these become `nav1..navN` in the header and in the i18n
payload), and `scopeCta()` selects active CTA items ordered by `sort`,
taking the first one (`HomepageData::build()` calls `->first()`) — **the
helper text is explicit that if more than one item is marked as CTA, the
first by sort order wins** and the rest are simply not rendered as either a
nav link or a CTA. There is no validation preventing more than one CTA row;
that is a documented convention, not an enforced constraint.

## The two guarded failure modes

Both were introduced because the reference design (`page.jsx`) had no
handling for them at all, and both are easy to miss because they only show
up with content states that don't exist in the demo seed data.

### 1. District horizontal-pin overflow guard (`resources/js/scbd/district.js`)

The reference computed `end: '+=' + (track.scrollWidth - innerWidth)`
unconditionally, then pinned the viewport for that entire computed distance.
With zero or very few district places, `track.scrollWidth - innerWidth` is
zero or negative — ScrollTrigger would still pin the viewport at `#district`
but with nowhere to scroll to release it, freezing the entire page below that
point for every visitor, not just breaking one section.

**The guard:** `initDistrict()` computes `overflow()` up front. If it is
`<= 0`, the track's transform is reset to `x: 0` and the function returns
`false` **without ever calling `ScrollTrigger.create()`** — no pin is
created at all, `#district` renders as a normal (non-pinned, non-scrolling)
section, and the rest of the page scrolls normally.

**Verified in the browser (2026-07-31):** with all three seeded
`DistrictPlace` rows temporarily set `is_active = false` (guard test from the
brief), `#district` and `[data-horizontal-track]` were both absent from the
DOM entirely (the section's own `@if ($data->places->isNotEmpty())` in
`district.blade.php` means the whole `<section>` doesn't render, so the JS
guard is in practice a second line of defense against the same failure
mode), and the page scrolled smoothly to the bottom with no freeze and no
console errors. Rows were restored to `is_active = true` immediately after
and reconfirmed (`3/3 active`).

### 2. Reduced-motion path (`resources/js/scbd/motion.js`)

The reference had no `prefers-reduced-motion` handling at all — every
visitor got the full loader sequence, staggered reveals, and parallax
regardless of vestibular or motion sensitivity, which is both an
accessibility failure and, for anyone who has the OS setting on, makes the
site feel broken (content staying invisible while nothing appears to
animate, if the reduced-motion OS setting also disables the animations the
browser itself would otherwise run).

**The guard:** `prefersReducedMotion()` checks
`window.matchMedia('(prefers-reduced-motion: reduce)').matches` once, at
`initScbd()` startup. When true:

- `loader.js` skips the entire intro timeline and jumps straight to the
  resting state (hero characters, image clip, header all set to their final
  values) then calls `finish()` immediately.
- `index.js` skips `initReveals`, `initMarquee`, `initDistrict`, and
  `initCardStack` entirely, and instead does one `gsap.set(...)` batch that
  puts every `[data-char]`, `[data-fade]`, `[data-reveal]` (plus `#district
  img` / `#facilities img`) straight into its final visual state.
- `smoothScroll.js` still constructs a Lenis instance (so `lenis.scrollTo`
  keeps working for anchor links) but with `duration: 0, smoothWheel: false,
  lerp: 1` — no eased interpolation, and native wheel scrolling is left
  alone rather than intercepted.
- Counters (`counters.js`) still run in both modes — the reduced path just
  reaches the final number fast enough that no one perceives it as an
  animation.

**Verified in the browser (2026-07-31):** with `window.matchMedia` mocked to
report `matches: true` for the reduced-motion query (before any page script
ran), the loader was `display: none` immediately, hero characters were at
their resting transform with no stagger, the hero image clip-path was
`inset(0%)`, district/facility images were already fully revealed at scale
`1`, native `window.scrollTo()` jumped instantly (`scroll-behavior: auto`,
no smooth easing), and no console errors were logged.

## Admin sidebar verification boundary

The admin sidebar (`/superduper`, built by
`App\Filament\Navigation\AdminNavigation`) has two distinct kinds of
correctness, and only one of them has been verified in a real browser.

**Covered by automated tests
(`tests/Feature/Filament/AdminNavigationTest.php`), run against the rebuilt
navigation object directly, no login required:**

- The five groups exist in the exact order Content, Homepage Data,
  Appearance, Settings, System.
- All thirteen expected items are present, with none duplicated.
- Every item's label maps to its expected destination in one pass (including
  Pages → the Graper resource).
- "Admin Topbar Menu" is labelled as such, and "Topbar Menu" does not appear.
- The Blog Posts badge shows the correct count when drafts/scheduled posts
  exist, and is absent (not a stale "0") when there are none.
- The District Places item reports itself active while visiting its own
  pages, and inactive on an unrelated page (the `isActiveWhen()` highlighting
  logic).

**Not verified, and requiring a human with credentials:** the *visual*
rendering of the sidebar in a browser (icon placement, spacing, no icon/group
icon conflict at render time — see the "cannot both carry icons" constraint
noted in the project's global constraints) and the *drag-and-drop reordering
interaction* on District Places / Facilities / Stats / Public Menu tables.
Two Filament accounts exist in this environment, but their credentials
belong solely to the project owner; this task did not request them, did not
attempt to create a user, and did not attempt any login bypass. The backend
half of "does reordering work" was checked without logging in — directly
changing a `sort` column and confirming the new order rendered on `/` — but
that only proves the read path from the database to the page; it says
nothing about whether the Filament table's drag handles actually write that
column correctly. Anyone picking this up next should log in as an existing
account and manually drag-reorder each of the four resources once, confirming
the new order survives a page reload of both the admin table and `/`.

## Known gaps

These are deliberate, documented limitations — not bugs to silently work
around, and out of scope for this task. They belong to a future spec.

> **⚠️ Editor-facing data loss: Indonesian/Chinese District Place, Facility,
> and Stat copy is unreachable on the public site.**
>
> `DistrictPlace.title`/`caption`, `Facility.title`/`body`, and `Stat.label`
> are translatable (`HasTranslatableFields`), and the Filament admin gives
> each of them the same `id`/`cn` locale tabs as every other translatable
> field. **Those tabs currently have no effect.** `HomepageData::I18N_MAP`
> (`app/Support/HomepageData.php`) — the map that decides which columns get
> a key in the `#scbd-i18n` payload the client-side language switcher reads
> — only covers `HomepageContent` columns plus the dynamic `navN`/`cta` keys.
> There is no `#scbd-i18n` key for district places, facilities, or stats at
> all, so the switcher has nothing to swap for them, and the Blade partials
> render them with a bare `->t('title')` / `->t('caption')` / `->t('body')`
> / `->t('label')` call (no explicit locale), which resolves through
> `app()->getLocale()` — i.e. **only English, always**, no matter what
> `default_locale` is set to or which language a visitor selects.
>
> Concretely: an editor can open **District Places**, **Facilities**, or
> **Stats** in the admin, fill in the Indonesian or Chinese tab, save
> successfully with no error — and that copy will never be visible to any
> visitor, in any language, until this is fixed. It silently vanishes.
>
> **Do not extend `I18N_MAP` (or add three more Blade branches) to patch
> this.** The homepage is scheduled to be rebuilt as a block builder, and
> wiring these three models into today's fixed key scheme is throwaway work
> that the rebuild would immediately discard. This gap must be solved as
> part of that block-builder redesign, with a payload scheme that isn't
> hand-enumerated per column.

- **The Site Settings "logo" upload does nothing.** The field exists
  (`FileUpload::make('logo')` in `SiteSettingsPage`) and uploads save
  correctly to the `public` disk, but nothing on the public site reads it —
  the header hardcodes the literal text `SCBD`
  (`resources/views/partials/home/header.blade.php:9`), and the admin panel
  itself sets no `brandLogo()`. Only **favicon** is actually wired up
  (`resources/views/components/layouts/public.blade.php:10-11`). An editor
  who uploads a logo expecting it to appear somewhere will see no visible
  change anywhere.
- **There is no contact form.** `contact.blade.php` only displays a static
  address, email, and phone number pulled from `HomepageContent`. There is
  no submission handling, no inbox, and nothing for a visitor to submit.
- **There is no footer.** The reference design has none, so none was built.
- **SEO is site-wide only.** `SiteSetting` carries one meta title/description
  per locale for the whole site. Neither `graper_pages` (GrapesJS pages) nor
  `blog_posts` has per-page meta columns, so every page shares the same
  `<title>`/description regardless of its own content.
- **No roles.** `slimani/filament-media-manager` is installed and reachable
  from the sidebar (**Content** → Media Library), but homepage/content field
  uploads (hero image, facility/district images, branding) still go straight
  to disk paths per field rather than through the media browser — the two
  systems don't share storage yet. Separately, there is a single `Users`
  resource with no role/permission distinctions — anyone with a Filament
  account has full access to everything in the sidebar.
- **"Pages" and "Homepage" are two different editing models.** The
  GrapesJS-based Graper "Pages" resource (freeform drag-and-drop page
  building) and the fixed-schema Filament "Homepage" singleton form
  (translatable text fields + two image uploads) do not share any code,
  data, or editing UI. An editor cannot, for example, add a new freeform
  section to the homepage the way they could add a new Graper page — the
  homepage's structure is fixed by the Blade partials and can only be
  re-ordered or have its images/copy/list content changed through the
  models described above.
