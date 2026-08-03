# Handoff — iat-cms

**Written:** 2026-07-31, revised 2026-08-03. Read this before continuing work on the page builder.

## Where things stand

| Item | State |
|---|---|
| SCBD homepage + admin (slice 0) | Complete, 144 tests / 417 assertions, published |
| Page builder spec (slice A1) | `docs/superpowers/specs/2026-08-03-graper-page-builder-design.md` — **approved, current**. The 2026-07-31 Filament-Builder spec is SUPERSEDED. |
| Page builder plan (slice A1) | **Not yet written for the current direction.** The 2026-07-31 plan is SUPERSEDED but retained for its animation-binding analysis. |
| Repository | `main`, pushed to `github.com/riorubiarjo703/iat-cms` (**public**) |

**The immediate next job** is to write the implementation plan for
`2026-08-03-graper-page-builder-design.md` with `superpowers:writing-plans`, then execute it.

**Direction changed on 2026-08-03.** The owner wants a live visual drag-and-drop canvas, so the
page builder is GrapesJS via the Graper plugin — which the previous plan was going to delete.
Graper is NOT removed. Its stock Tailwind blocks are unused; we write SCBD-styled blocks and
override `graper::display` so pages render in our layout with our CSS and animation bundle.
Translations move to a side table plus a screen driven by the `data-i18n` keys parsed from the
saved HTML, which keeps the instant no-reload switcher working.

Removal of the old models and partials stays last: until the canvas-built homepage is verified
in a browser, the hand-built one is the only thing it can be compared against.

## Hard rules for this project

These were learned expensively. Two of them describe damage that already happened.

- **Never run `migrate:fresh`, `migrate:refresh`, `migrate:rollback` or `db:wipe`.** The live
  PostgreSQL was wiped once during the previous build. It destroyed two real user accounts
  whose password hashes were unrecoverable; the owner had to recreate them by hand. Tests use
  sqlite `:memory:` via `phpunit.xml` — if a run ever resolves to `pgsql`, stop and report it.
- **Never run `npm run dev`; never create `public/hot`.** A stale hot-file pointing at a dead
  dev server left the homepage serving script tags that resolved to nothing — the site was
  broken in a browser while every test stayed green. Use `npm run build` and verify against
  the production bundle.
- **Do not create user accounts.** Account creation is the owner's, via
  `php artisan make:filament-user`, so passwords never pass through an agent or a transcript.
- Commit per task. Do not push without asking — the remote is public.

## Verified platform facts

Established empirically against the installed source. Do not re-derive or contradict.

- **PHP 8.4.16 forbids direct trait-constant access.** `HasTranslatableFields::FALLBACK_LOCALE`
  raises `Error: Cannot access trait constant ... directly`. Use `SiteSetting::FALLBACK_LOCALE`.
  This shipped once as a latent 500 that 143 green tests hid, because PHP's ternary is lazy and
  the guarded branch never ran.
- **PHPUnit 12.5.33 ships no `AnnotationParser`.** Doc-block metadata (`@dataProvider`, `@test`,
  `@depends`, `@group`) is silently ignored. Use `PHPUnit\Framework\Attributes\*`.
- **Filament `Builder` storage shape is fixed.** `mutateDehydratedStateUsing` does
  `array_values($state)` on save; `hydrateItems()` regenerates UUID keys on load
  (`vendor/filament/forms/src/Components/Builder.php:141-158`). Only `type` and `data` survive.
  **Block UUIDs are ephemeral** — anything keyed by them silently reassigns on every save.
  `Builder.php:949` already discards unregistered types in the form, but a renderer reading the
  column directly must repeat that filter.
- **`Tabs::getChildComponents()` throws** on an unattached component; use
  `getDefaultChildComponents()`.
- **A `NavigationGroup` and its items cannot both carry icons** — Filament throws at render.
- **`$view` on a Filament `Page` is non-static.**
- **Filament forms materialise every locale key**, so `assertFormSet` on a translatable field
  must expect the full `['en' => …, 'id' => null, 'cn' => null]` map.
- **Filament `Select` already validates against `->options()`** server-side.
- **Lenis ignores `scrollTo()`** and tracks its own wheel-driven target. Browser verification
  must dispatch `WheelEvent`s or measurements stall misleadingly.

## Bugs fixed in the reference design — do not regress

The SCBD reference implementation contained three defects. Each is corrected in the current
code and each is easy to reintroduce:

- Baking `transform: translateY(105%)` as literal inline CSS poisons GSAP's `yPercent` cache,
  leaving split headings **permanently invisible** while the build stays green and the console
  stays clean. Set it via `gsap.set` instead.
- `gsap.to(el, { timeScale })` targets a DOM element, so GSAP treats `timeScale` as an unknown
  property and `overwrite: true` kills the loop tween — the marquee freezes. Animate the tween,
  not the element.
- `yPercert` → `yPercent` typo, silently ignored by GSAP.

## Known gaps carried forward

- The `logo` field in Site Settings is uploaded but **never rendered anywhere**. The public
  header hardcodes the text `SCBD`; the panel sets no `brandLogo()`. Only `favicon` is wired.
  Belongs to slice C.
- `DistrictPlace`, `Facility` and `Stat` have locale tabs in the admin but no i18n payload
  keys, so **only English ever renders** for them. Slice A1 removes these models entirely.
- There is **no contact form** — the contact section only displays an address. Slice D.
- No footer exists; the reference design has none. Slice C.
- `User::canAccessPanel()` returns `true` for any authenticated user. Documented and
  test-pinned, but it is the access boundary. Slice E.

## Slice roadmap

A1 (this plan) → A2 blog restructure (Blog Dashboard, Authors, posts gain blocks) →
B media library integration → C branding/header/footer → D contact messages →
E users & roles → F SEO completion. The owner's preferred sidebar structure is recorded in
the A1 spec's brainstorming decisions.

## Working notes

- The owner verifies visually and will want screenshots or a live check before accepting UI
  work. Static full-page screenshots are misleading on this site: reveals and count-ups only
  fire on real scroll, so an unscrolled capture shows empty image boxes and `0` stats that are
  not bugs.
- Reviews on this project have repeatedly found tests that pass regardless of the behaviour
  they name. Prove each test by breaking the thing it is named for, observing the failure, and
  restoring. Across 18 tasks, 20 of 24 findings were toothless tests; only three were incorrect
  implementations.
