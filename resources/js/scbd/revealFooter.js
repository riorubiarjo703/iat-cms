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
