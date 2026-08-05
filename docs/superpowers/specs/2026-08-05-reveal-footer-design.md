# Reveal footer

A "shade lift" footer: the footer sits pinned to the bottom of the viewport for
the whole page, hidden behind the main content, and is uncovered when the last
section scrolls away. Applies to every public page.

## Source

`https://orisa-html-demo.pages.dev/contact-1`, whose shipped `assets/css/main.css`
and `assets/js/main.js` were read directly rather than inferred from the rendered
page.

The reference builds the effect with `position: fixed` plus a JS-sized spacer:

```
#smooth-wrapper
  #smooth-content .z-index-3          transformed by GSAP ScrollSmoother
    <main class="bg-neutral-0">       solid background, higher stacking
    <div class="footer-placeholder">  height set from JS
  <footer class="footer-fixed-bottom">  position:fixed; bottom:0; z-index:1
```

It needs the placeholder, a resize listener and a `ScrollTrigger.refresh()`
because ScrollSmoother transforms `#smooth-content`, and a transformed ancestor
kills `position: sticky`. We scroll natively under Lenis
(`resources/js/scbd/smoothScroll.js`), so sticky works and none of that
machinery is required. The geometry is identical; the implementation is three
CSS rules instead of a measured spacer.

The reference also documents the one hard constraint of the technique, in
`assets/js/main.js`:

```js
// Cap placeholder at viewport height so <main> can't scroll past
// the point where the footer is fully revealed (footer-2 can be
// taller than the viewport — scrolling by full footer height
// would push <main> entirely off-screen).
footerPlaceholder.style.height = Math.min(footerH, viewportH) + 'px';
```

A footer taller than the viewport can never show its own top. Bottom-anchored
against the viewport, its upper portion overflows above the top edge at every
scroll position. The clamp stops `main` flying off; it does not rescue the
footer's head. Our footer is sized to avoid the situation entirely — see
*Footer sizing*.

## Structure

`resources/views/page.blade.php` already has the required shape: `<main>` wraps
every block, `partials.site.footer` follows it immediately, and both sit inside
the `position:relative` wrapper from `layouts/page.blade.php`. Nothing is
restructured. Two class hooks are added:

```blade
<main class="scbd-shade" style="position:relative; min-height:50vh;">
@include('partials.site.footer')
```

and in `partials/site/footer.blade.php` the `<footer>` gains
`.scbd-reveal-footer` alongside its existing `.scbd-pad`. Its inline
`padding:80px 40px` already beats `.scbd-pad`'s 120px, but not the
`!important` override at `max-width: 900px`, so the mobile padding stays
80px/24px. Left as is.

`page.blade.php` is the single entry point for the homepage, builder pages and
plain-content pages alike, so one change covers all three.

## Mechanism

```css
.scbd-shade { position: relative; z-index: 1; background: #f3f2f2;
              border-radius: 0 0 24px 24px; }
.scbd-shade > :last-child:not([data-horizontal]) {
              border-radius: inherit; overflow: clip; }
.scbd-reveal-footer { position: sticky; bottom: 0; z-index: 0; }
```

The footer is the last child of a wrapper that spans the document, so
`bottom: 0` shifts it upward out of normal flow from the first paint and holds
it against the viewport bottom. It is covered the whole time by `.scbd-shade`,
which carries a solid background and a higher z-index. When the shade's last
pixel clears the top of the viewport the footer stands uncovered. At maximum
scroll the sticky offset resolves to zero and the footer rests in its natural
position.

No negative margins, no absolute positioning, no JS.

### Why the clip is scoped to the last section

Rounding `.scbd-shade` alone rounds only its own `#f3f2f2` background. Pages
ending in a full-bleed colour — `scbd-cta`, `scbd-location`, `scbd-district` —
would keep a square corner, so the effect would appear on some pages and not
others.

Clipping the whole of `.scbd-shade` would fix that but would enclose the pinned
district block (`resources/js/scbd/district.js:37` creates a
`ScrollTrigger` with `pin: true`) and the sticky cards in
`scbd-about`/`scbd-facilities` in a clipping context. Only the final section can
square off the curve, so the rule targets just that element.

The `:not([data-horizontal])` guard covers the case where `scbd-district` is
itself the last block: GSAP's pin replaces the element with a pin-spacer and
positions it `fixed`, and clipping that is the one combination likely to break.
That block is already full-bleed `#201e1d` with its own `overflow: hidden`, so
it forfeits the rounded corner and keeps its pin. No other block pins.

### Why `clip` and never `hidden`

`overflow: hidden` is a scroll container. Sticky descendants would resolve
against it instead of the viewport, which would break the sticky cards in
`scbd-facilities` (`position:sticky; top:110px`), `scbd-about`, and the
`.scbd-values-sticky` / `.scbd-form-intro` rules. `overflow: clip` is not a
scroll container, so sticky descendants continue to resolve against the
viewport. Safari 16+.

## Footer sizing

`partials/site/footer.blade.php` becomes a flex column at `min-height: 100svh`:
the four-cell grid centered in the free space, the legal meta line pushed to the
bottom edge. No new content and no new CMS fields — it is the existing band
redistributed.

`svh` rather than `vh` deliberately. `svh` is the *small* viewport height, the
measurement taken with mobile browser toolbars shown. Sized to that, the footer
fits exactly when the toolbars are visible and has room to spare when they
retract. `100vh` on iOS resolves to the large viewport height and would leave
the footer taller than the visible area whenever the toolbars are out.

At `max-width: 900px` — the breakpoint the stylesheet already uses everywhere
else — the four-cell grid goes 2-up instead of stacking, and the cell padding
tightens. Stacked one-up at the mobile 80px/24px padding the band measures
roughly 860px against an 844px iPhone viewport: narrowly over, which is exactly
the failure the source comment describes. The 2-up grid halves the grid's
contribution and brings the whole footer comfortably inside one screen.

The grid is currently `repeat(auto-fit, minmax(220px, 1fr))`, which already
collapses to one column below ~460px. The mobile rule pins it to
`repeat(2, 1fr)` so the 2-up survives down to 320px, where four stacked cells
would otherwise overflow again.

The effect therefore runs at every breakpoint, matching the reference, without
the reference's mobile clipping.

## Scale-up on reveal

`resources/js/scbd/revealFooter.js`, following the existing module convention:
`gsap.set(inner, { scale: 0.95 })`, then a scrubbed `ScrollTrigger` on
`.scbd-shade` (`start: 'bottom bottom'`, `end: 'bottom top'`) taking it to `1`
as the footer is uncovered. `transform-origin: center bottom`.

The transform is applied to an inner wrapper, not to the `<footer>` itself — a
transform on the sticky element would give its own descendants a transformed
containing block.

The initial `0.95` is set from JS, not CSS. `resources/js/scbd/index.js` is only
loaded for builder pages (`layouts/page.blade.php:38-42`), so a CSS-set `0.95`
would strand plain-content pages permanently shrunk with nothing to animate it
back. Registered inside the existing `if (!reduced)` branch, so reduced-motion
users get the CSS reveal with no scaling.

## Known limitation: red on red

Pages ending in `scbd-cta` or `scbd-contact-heading` close on `#ec3013`, the
same red as the footer. The rounded corner will expose footer red through
section red and the reveal will read as nearly invisible there; only the
scale-up gives a motion cue. Accepted for now — the fix, if wanted later, is
changing the footer to `#201e1d`, a one-value change in
`partials/site/footer.blade.php`.

## Verification

Sticky positioning is easy to break silently, so these are checked in a real
browser and not assumed:

1. `resources/css/scbd.css:413` sets `body { overflow-x: hidden }`. Per spec
   this propagates to the viewport — `html` has `overflow: visible`, so `body`'s
   used value becomes `visible` and it does not become a scroll container — but
   this is the single most common cause of sticky silently failing. Confirm the
   footer pins. If it does not, move the rule to `html` or change it to
   `overflow-x: clip`.
2. The pinned `scbd-district` block still pins and scrolls horizontally, both
   mid-page and as the last section on a page.
3. The sticky cards in `scbd-facilities` and `scbd-about` still stick.
4. At 390×844 the entire footer is readable at maximum scroll — nothing above
   the fold is cut off.
5. A page shorter than the viewport still behaves: footer pinned below the
   content, no overlap.
6. Reduced motion: reveal works, no scaling.
