/**
 * Hides the header on the way down, brings it back on the way up.
 *
 * Skipped below the drawer breakpoint, and any transform GSAP already left on
 * the header is cleared there. A transformed element becomes the containing
 * block for its position:fixed descendants, so a header carrying even
 * `translate(0,0)` traps the mobile drawer inside the header's own box instead
 * of letting it cover the viewport.
 */
export function initHeader(gsap, lenis) {
  const header = document.querySelector('[data-header]');
  if (!header) return;

  const isDrawerLayout = () => window.matchMedia('(max-width: 1023px)').matches;

  const releaseTransform = () => gsap.set(header, { clearProps: 'transform' });

  if (isDrawerLayout()) releaseTransform();

  let previous = 0;

  lenis.on('scroll', ({ scroll }) => {
    if (isDrawerLayout()) {
      previous = scroll;

      return;
    }

    const hide = scroll > 140 && scroll > previous;
    gsap.to(header, { yPercent: hide ? -100 : 0, duration: 0.4, ease: 'power3.out' });
    previous = scroll;
  });

  // Crossing the breakpoint either way needs the transform state to match.
  window.addEventListener('resize', () => {
    if (isDrawerLayout()) releaseTransform();
  });
}
