# Reveal Footer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pin the site footer to the bottom of the viewport behind the main content, so it is uncovered like a lifting shade when the last section scrolls away.

**Architecture:** Three CSS rules and two class hooks. The `<footer>` is last in normal flow with `position: sticky; bottom: 0; z-index: 0`; `<main>` gets `z-index: 1` and a solid background so it covers the footer until its last pixel clears the viewport top. No JS is required for the reveal itself — only for an optional scale-up flourish.

**Tech Stack:** Laravel 12 Blade, a single hand-written stylesheet (`resources/css/scbd.css`), GSAP 3.12.5 + ScrollTrigger, Lenis 1.1.18, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-08-05-reveal-footer-design.md`

## Global Constraints

- **Never run `npm run dev` or any `migrate:fresh` variant in this repo.** Both have caused real damage here. Build assets with `npm run build`.
- Tests are **PHPUnit 12, not Pest** — test classes extending `Tests\TestCase`, methods prefixed `test_`. Run with `php artisan test`.
- All CSS goes in `resources/css/scbd.css`. Do not create new stylesheets.
- The responsive breakpoint is `@media (max-width: 900px)` — the block already open at line 1114. A second, deeper one exists at 560px. Do not introduce new breakpoint values.
- Palette: shade/page background `#f3f2f2`, footer `#ec3013`, text `#201e1d`.
- JS modules follow the house pattern: one file per concern in `resources/js/scbd/`, exporting a named `init*` function taking `gsap`/`ScrollTrigger` as arguments, wired up in `resources/js/scbd/index.js`.
- `resources/views/partials/site/footer-band.blade.php` carries inline `style` attributes. Inline styles beat class rules, so stylesheet overrides of those properties need `!important` — which is why the existing mobile footer rules already use it.

## File Structure

| File | Responsibility |
|---|---|
| `resources/views/page.blade.php` | Modify: add the `scbd-shade` hook to `<main>` |
| `resources/views/partials/site/footer.blade.php` | Modify: add the `scbd-reveal-footer` hook and an inner wrapper for the transform |
| `resources/css/scbd.css` | Modify: the sticky/stacking rules, the footer's viewport sizing, and the mobile grid change |
| `resources/js/scbd/revealFooter.js` | Create: the scrubbed scale-up |
| `resources/js/scbd/index.js` | Modify: wire the module into the non-reduced-motion branch |
| `tests/Feature/RevealFooterTest.php` | Create: markup and stylesheet contract |

---

### Task 1: The sticky reveal

The mechanism on its own. At the end of this task the footer is uncovered by scrolling, at its natural height.

**Files:**
- Modify: `resources/views/page.blade.php:9`
- Modify: `resources/views/partials/site/footer.blade.php:4-6`
- Modify: `resources/css/scbd.css` (append at end of file)
- Test: `tests/Feature/RevealFooterTest.php` (create)

**Interfaces:**
- Consumes: nothing.
- Produces: the CSS class `scbd-shade` on `<main>`, and `scbd-reveal-footer` on `<footer>`. Task 2 adds sizing to `.scbd-reveal-footer`; Task 3 queries `.scbd-shade` as a ScrollTrigger trigger.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RevealFooterTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SeedsHeaderMenu;
use Tests\TestCase;

/**
 * The reveal is position:sticky plus a z-index stack, which PHPUnit cannot
 * execute. What these hold is the contract the CSS depends on: both hooks
 * present in the markup, both defined in the stylesheet, and in the right
 * document order. Renaming either side alone breaks the effect silently and
 * completely — the page just looks normal.
 *
 * Whether it actually reveals is a browser check, and is Task 4 of
 * docs/superpowers/plans/2026-08-05-reveal-footer.md. These passing is not
 * evidence the footer reveals.
 */
class RevealFooterTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHeaderMenu;

    /**
     * Asserts the class appears as a whole token in a class attribute, so
     * renaming a hook to "scbd-shade-v2" fails rather than passing on the
     * substring. Mirrors ResponsiveMarkupTest.
     */
    private function assertHasClass(string $html, string $class): void
    {
        $this->assertMatchesRegularExpression(
            '/class="[^"]*(?<![\w-])'.preg_quote($class, '/').'(?![\w-])[^"]*"/',
            $html,
            "The [{$class}] reveal-footer hook is missing",
        );
    }

    private function home(): string
    {
        $this->seedHeaderMenu();
        $this->seedHomepage();

        return $this->get('/')->assertSuccessful()->getContent();
    }

    public function test_the_main_content_carries_the_shade_hook(): void
    {
        $this->assertHasClass($this->home(), 'scbd-shade');
    }

    public function test_the_footer_carries_the_sticky_hook(): void
    {
        $this->assertHasClass($this->home(), 'scbd-reveal-footer');
    }

    /**
     * The whole effect rests on the footer being a later sibling of the shade.
     * Sticky lifts the footer out of flow and the shade covers it only because
     * it paints over a lower z-index. Nest the footer inside <main>, or put it
     * above, and the reveal inverts.
     */
    public function test_the_footer_comes_after_the_main_content(): void
    {
        $html = $this->home();

        $mainEnd = strpos($html, '</main>');
        $footer = strpos($html, '<footer');

        $this->assertNotFalse($mainEnd, 'the main content should be present');
        $this->assertNotFalse($footer, 'the footer should be present');
        $this->assertGreaterThan(
            $mainEnd,
            $footer,
            'the footer must follow </main>, not nest inside it',
        );
    }

    /**
     * The hooks are inert without the rules that act on them, and the two live
     * in different files.
     */
    public function test_the_stylesheet_defines_the_reveal_rules(): void
    {
        $css = file_get_contents(resource_path('css/scbd.css'));

        $this->assertMatchesRegularExpression(
            '/\.scbd-reveal-footer\s*\{[^}]*position:\s*sticky/',
            $css,
            'the footer hook must be sticky',
        );
        $this->assertMatchesRegularExpression(
            '/\.scbd-shade\s*\{[^}]*z-index:\s*1/',
            $css,
            'the shade must stack above the footer',
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RevealFooterTest`

Expected: FAIL. `test_the_main_content_carries_the_shade_hook` reports "The [scbd-shade] reveal-footer hook is missing"; `test_the_stylesheet_defines_the_reveal_rules` reports "the footer hook must be sticky". `test_the_footer_comes_after_the_main_content` will already pass — the markup order is correct today; it is there to stop a later refactor from breaking it.

- [ ] **Step 3: Add the shade hook to `<main>`**

In `resources/views/page.blade.php`, line 9. Replace:

```blade
    <main style="position:relative; min-height:50vh;">
```

with:

```blade
    <main class="scbd-shade" style="position:relative; min-height:50vh;">
```

- [ ] **Step 4: Add the sticky hook to `<footer>`**

In `resources/views/partials/site/footer.blade.php`, replace lines 4-6:

```blade
<footer class="scbd-pad" style="background:#ec3013; color:#f3f2f2; padding:80px 40px;">
    @include('partials.site.footer-band', ['settings' => $settings])
</footer>
```

with:

```blade
{{-- The inner wrapper exists so Task 3 can scale the footer's contents without
     transforming the sticky element itself — a transform on the <footer> would
     give its own descendants a transformed containing block. --}}
<footer class="scbd-pad scbd-reveal-footer" style="background:#ec3013; color:#f3f2f2; padding:80px 40px;">
    <div class="scbd-reveal-footer-inner">
        @include('partials.site.footer-band', ['settings' => $settings])
    </div>
</footer>
```

- [ ] **Step 5: Add the reveal rules to the stylesheet**

Append to the end of `resources/css/scbd.css` (after line 1450, outside any media query):

```css

/* ── Reveal footer ───────────────────────────────────────────────────────
   The footer sits last in normal flow with position:sticky; bottom:0 and a
   lower z-index, so it is pinned to the viewport bottom from the first paint
   and spends the whole page hidden behind .scbd-shade, which carries a solid
   background and a higher z-index. When the shade's last pixel clears the top
   of the viewport the footer stands uncovered, like a window shade lifting.

   The reference demo needs position:fixed, a JS-measured spacer and a resize
   listener only because GSAP ScrollSmoother transforms its scroll container,
   and a transformed ancestor kills position:sticky. We scroll natively under
   Lenis, so sticky works and none of that machinery is required. */
.scbd-shade {
    position: relative;
    z-index: 1;
    background: #f3f2f2;
    border-radius: 0 0 24px 24px;
}

/* Only the final section can square off the curve with its own full-bleed
   colour, so only it is clipped. Clipping the whole shade would enclose the
   sticky cards in About and Facilities and the pinned district block.

   `clip` rather than `hidden`: hidden is a scroll container, and sticky
   descendants would then resolve against it instead of the viewport — which
   is exactly what would break those cards. clip is not a scroll container.

   [data-horizontal] is excluded because GSAP replaces that block with a
   pin-spacer positioned `fixed`; clipping it is the one combination likely to
   break the pin. It is full-bleed #201e1d with its own overflow:hidden, so it
   forfeits the rounded corner and keeps its pinning. */
.scbd-shade > :last-child:not([data-horizontal]) {
    border-radius: inherit;
    overflow: clip;
}

.scbd-reveal-footer {
    position: sticky;
    bottom: 0;
    z-index: 0;
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=RevealFooterTest`

Expected: PASS, 4 tests.

- [ ] **Step 7: Run the full suite to check nothing regressed**

Run: `php artisan test`

Expected: PASS. `ResponsiveMarkupTest`, `BrandingTest` and `CompanyPagesTest` all render `page.blade.php` and are the ones most likely to notice a Blade mistake.

- [ ] **Step 8: Commit**

```bash
git add resources/views/page.blade.php resources/views/partials/site/footer.blade.php resources/css/scbd.css tests/Feature/RevealFooterTest.php
git commit -m "feat: reveal the footer from behind the page as it scrolls"
```

---

### Task 2: Fill the viewport

The footer is ~500px tall, so at this point it reveals but leaves a gap. This sizes it to exactly one viewport and keeps it inside one screen on a phone.

**Files:**
- Modify: `resources/css/scbd.css` — the `.scbd-reveal-footer` rule from Task 1, the mobile block at line 1114 (specifically line 1193), and one new rule
- Test: `tests/Feature/RevealFooterTest.php` (extend)

**Interfaces:**
- Consumes: `.scbd-reveal-footer` and `.scbd-reveal-footer-inner` from Task 1.
- Produces: `.scbd-reveal-footer-inner` becomes a flex column — Task 3 transforms this element.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/RevealFooterTest.php`, inside the class:

```php
    /**
     * A footer taller than the viewport can never show its own top: pinned
     * bottom-anchored, its head overflows above the top edge at every scroll
     * position. 100svh — the *small* viewport height, measured with mobile
     * toolbars shown — is the sizing that cannot exceed the visible area.
     * 100vh resolves to the large viewport height on iOS and would.
     */
    public function test_the_footer_is_sized_to_the_small_viewport_height(): void
    {
        $css = file_get_contents(resource_path('css/scbd.css'));

        $this->assertMatchesRegularExpression(
            '/\.scbd-reveal-footer\s*\{[^}]*min-height:\s*100svh/',
            $css,
            'the footer must be sized in svh, not vh',
        );
    }

    /**
     * The footer grid stacks one-up below 900px, which measures roughly 860px
     * against an 844px iPhone viewport — over budget, and the top of the
     * footer would be unreachable. Two-up halves the grid's contribution.
     */
    public function test_the_footer_grid_is_two_up_on_mobile(): void
    {
        $css = file_get_contents(resource_path('css/scbd.css'));

        $this->assertStringNotContainsString(
            '.scbd-footer-grid { grid-template-columns: 1fr !important; }',
            $css,
            'a one-up footer grid overflows the viewport and hides the footer top',
        );
        $this->assertMatchesRegularExpression(
            '/\.scbd-footer-grid\s*\{[^}]*grid-template-columns:\s*repeat\(2,\s*1fr\)/',
            $css,
        );
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=RevealFooterTest`

Expected: FAIL on both new tests — "the footer must be sized in svh, not vh" and "a one-up footer grid overflows the viewport and hides the footer top". The four tests from Task 1 still pass.

- [ ] **Step 3: Size the footer to the viewport**

In `resources/css/scbd.css`, in the `Reveal footer` section added in Task 1, replace:

```css
.scbd-reveal-footer {
    position: sticky;
    bottom: 0;
    z-index: 0;
}
```

with:

```css
/* min-height rather than height so a long address or sitemap grows the footer
   instead of overflowing it.

   svh, not vh: svh is the *small* viewport height, the measurement taken with
   mobile browser toolbars shown. Sized to that, the footer fits exactly when
   the toolbars are out and has room to spare when they retract. 100vh resolves
   to the *large* viewport height on iOS, which would leave the footer taller
   than the visible area — and a footer taller than the viewport can never show
   its own top. */
.scbd-reveal-footer {
    position: sticky;
    bottom: 0;
    z-index: 0;
    min-height: 100svh;
    display: flex;
    flex-direction: column;
}

.scbd-reveal-footer-inner {
    flex: 1;
    display: flex;
    flex-direction: column;
    transform-origin: center bottom;
}

/* Auto margins on the grid take up all the free space above and below it, which
   centres the grid and drops the legal meta line onto the bottom edge. */
.scbd-reveal-footer-inner .scbd-footer-grid {
    margin: auto 0;
}
```

- [ ] **Step 4: Make the mobile grid two-up**

In `resources/css/scbd.css` line 1193, inside the existing `@media (max-width: 900px)` block, replace:

```css
    .scbd-footer-grid { grid-template-columns: 1fr !important; }
```

with:

```css
    /* Two-up, not stacked, and pinned rather than left to the inline
       auto-fit — which collapses to one column below ~460px. Four cells
       stacked at the mobile 80px padding measure ~860px against an 844px
       iPhone viewport, and a footer over one viewport tall can never show its
       own top. !important because the grid carries an inline style. */
    .scbd-footer-grid { grid-template-columns: repeat(2, 1fr) !important; }
    .scbd-footer-grid > div { padding: 20px !important; }
```

Leave line 1194 (`.scbd-footer-meta`) exactly as it is — it already stacks the meta line on mobile.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=RevealFooterTest`

Expected: PASS, 6 tests.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/css/scbd.css tests/Feature/RevealFooterTest.php
git commit -m "feat: size the reveal footer to one viewport"
```

---

### Task 3: Scale-up on reveal

The footer's contents settle from `scale(.95)` to `1` as the shade lifts, so the reveal reads as the footer arriving rather than as a static panel being exposed.

**Files:**
- Create: `resources/js/scbd/revealFooter.js`
- Modify: `resources/js/scbd/index.js` — the import block at the top, and the `if (!reduced)` branch ending at line 84

**Interfaces:**
- Consumes: `.scbd-shade` and `.scbd-reveal-footer-inner` from Tasks 1 and 2.
- Produces: `export function initRevealFooter(gsap, ScrollTrigger)`.

There is no JS test runner in this project — `package.json` has only `build` and `dev` scripts. Verification for this task is a clean production build plus the browser pass in Task 4.

- [ ] **Step 1: Create the module**

Create `resources/js/scbd/revealFooter.js`:

```js
/**
 * The footer's contents scale from 0.95 to 1 as the shade lifts off them, so
 * the reveal reads as the footer settling into place rather than as a static
 * panel being uncovered.
 *
 * The initial scale is set here rather than in CSS deliberately: index.js is
 * only loaded for builder pages (see layouts/page.blade.php), so a CSS-set
 * 0.95 would leave every plain-content page permanently shrunk with nothing
 * to animate it back.
 *
 * The transform goes on the inner wrapper, never on the <footer> itself — a
 * transform on the sticky element would give its own descendants a transformed
 * containing block.
 */
export function initRevealFooter(gsap, ScrollTrigger) {
  const shade = document.querySelector('.scbd-shade');
  const inner = document.querySelector('.scbd-reveal-footer-inner');

  if (!shade || !inner) return;

  gsap.set(inner, { scale: 0.95 });

  gsap.to(inner, {
    scale: 1,
    ease: 'none',
    scrollTrigger: {
      // The reveal is exactly the span over which the shade's bottom edge
      // travels from the bottom of the viewport to the top.
      trigger: shade,
      start: 'bottom bottom',
      end: 'bottom top',
      scrub: true,
      invalidateOnRefresh: true,
    },
  });
}
```

- [ ] **Step 2: Import it in `index.js`**

In `resources/js/scbd/index.js`, after the `initCursor` import and before the `initLanguageSwitcher` import, add:

```js
import { initRevealFooter } from './revealFooter';
```

- [ ] **Step 3: Call it in the non-reduced-motion branch**

In `resources/js/scbd/index.js`, in the `if (!reduced) {` branch, replace:

```js
      initCardStack(gsap);
    } else {
```

with:

```js
      initCardStack(gsap);
      initRevealFooter(gsap, ScrollTrigger);
    } else {
```

Nothing is added to the `else` branch: the resting state for reduced motion is the element's untouched `scale: 1`, and `initRevealFooter` is what would have set `0.95` in the first place.

- [ ] **Step 4: Build to verify the bundle compiles**

Run: `npm run build`

Expected: succeeds with no unresolved-import errors. Do **not** run `npm run dev`.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`

Expected: PASS. The Blade and CSS are untouched by this task, so this is a regression check only.

- [ ] **Step 6: Commit**

```bash
git add resources/js/scbd/revealFooter.js resources/js/scbd/index.js
git commit -m "feat: settle the reveal footer in as it is uncovered"
```

---

### Task 4: Browser verification

The six checks from the spec. **This is the task that establishes the feature works** — the PHPUnit tests above only assert that class names exist in two files. Do not report the feature complete on green tests alone.

**Files:** none — this task changes nothing unless it finds a defect.

- [ ] **Step 1: Build and serve**

```bash
npm run build
php artisan serve
```

Do **not** run `npm run dev`.

- [ ] **Step 2: Confirm the footer pins at all**

Load `/` and scroll to the bottom.

Expected: the footer is uncovered as the last section scrolls up over it, and is fully visible at maximum scroll.

If it instead scrolls up normally with the page, the cause is almost certainly `body { overflow-x: hidden }` at `resources/css/scbd.css:413` making `body` a scroll container. The fix is to move that declaration to `html`, or change it to `overflow-x: clip`. Re-run `php artisan test` after either change.

- [ ] **Step 3: Confirm nothing else that scrolls broke**

On the homepage, check `#district` still pins and scrolls horizontally, and that `#facilities` and the About section's sticky column still stick at `top: 110px` rather than scrolling away.

Then check the district block as a *last* section: temporarily reorder a page's blocks in the admin so `scbd_district` is last, confirm it still pins, and put the order back.

Then load the contact page, whose `scbd_contact_form` block is last and so is the one that actually receives `overflow: clip`. Check `.scbd-form-intro` still sticks at `top: 130px` as the form scrolls past it.

These are the specific things `overflow` on an ancestor would break, which is why the clip is `clip` and not `hidden`, and why it is scoped to `:last-child:not([data-horizontal])`.

- [ ] **Step 4: Confirm the mobile budget**

Resize to 390×844. Scroll to maximum.

Expected: the entire footer is readable — the "Address" heading at the top of the grid is on screen, not cut off above the viewport. If any of it is clipped, the footer is over one viewport tall and the cell padding in the 900px block needs to come down further.

- [ ] **Step 5: Confirm the short-page case**

Load a plain-content page whose content is shorter than the viewport.

Expected: the footer sits below the content without overlapping it, and the page still scrolls.

- [ ] **Step 6: Confirm reduced motion**

Enable "Reduce motion" at the OS level and reload.

Expected: the reveal still works (it is pure CSS) and the footer contents do not scale — they sit at full size throughout.

- [ ] **Step 7: Record the outcome**

Report which of steps 2-6 passed, quoting what was actually observed. If any failed, fix and re-run the affected steps before reporting completion.

---

## Notes

**Known limitation, accepted by the user rather than fixed.** Pages ending in `scbd-cta` or `scbd-contact-heading` close on `#ec3013`, the same red as the footer, so the reveal will read as nearly invisible there — the rounded corner exposes footer red through section red, and only the scale-up gives a motion cue. This is expected behaviour, not a defect to fix during implementation. The remedy, if wanted later, is changing the footer background to `#201e1d` in `partials/site/footer.blade.php`.
