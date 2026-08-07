import Lenis from 'lenis';

export function createSmoothScroll(ScrollTrigger, reduced) {
  // Reduced motion still gets Lenis (for programmatic scrollTo) but with
  // smoothing disabled, so anchor links work without animated easing.
  const lenis = new Lenis(
    reduced
      ? { duration: 0, smoothWheel: false, lerp: 1 }
      : { duration: 1.15, smoothWheel: true, lerp: 0.09 },
  );

  lenis.on('scroll', ScrollTrigger.update);

  const raf = (time) => {
    lenis.raf(time);
    requestAnimationFrame(raf);
  };
  requestAnimationFrame(raf);

  bindAnchors((target) => lenis.scrollTo(target, { offset: -70 }));

  return lenis;
}

/**
 * Smooth-scrolls the links that point at a section of the page you are on.
 *
 * Matched on the anchor's resolved hash and path rather than on `href^="#"`:
 * navigation entries carry an absolute URL now, so that "#about" reaches the
 * homepage section from an interior page too. Under the old selector those
 * stopped matching here, which turned every homepage nav click into a full
 * page load. Reading `.hash` and `.pathname` off the element covers both
 * spellings, because the browser resolves them the same way.
 */
export function bindAnchors(scrollTo, root = document) {
  root.querySelectorAll('a[href*="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
      // A bare "#" has an empty hash — that is the heading marker, which
      // exists to group children and goes nowhere.
      if (!anchor.hash) return;

      // Another page's section: let the browser navigate there and land on it.
      if (anchor.pathname !== window.location.pathname) return;
      if (anchor.origin !== window.location.origin) return;

      const target = root.querySelector(anchor.hash);
      if (!target) return;

      event.preventDefault();
      scrollTo(target);
    });
  });
}
