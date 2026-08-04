/**
 * Entrance animations for the interior-page card hooks.
 *
 * Each is a scroll-triggered fromTo rather than a set-then-animate, so an
 * element that never enters the viewport is simply left alone rather than
 * parked off-screen forever — the failure that made the hero invisible when
 * the loader was absent.
 */
export function initCards(gsap, ScrollTrigger) {
  const groups = [
    // Milestones rise as the eye travels down the timeline.
    { selector: '[data-timeline-card]', from: { opacity: 0, y: 48 }, stagger: 0.08 },
    // Board portraits settle in a light stagger across the grid.
    { selector: '[data-award]', from: { opacity: 0, y: 32 }, stagger: 0.05 },
  ];

  groups.forEach(({ selector, from, stagger }) => {
    const elements = Array.from(document.querySelectorAll(selector));
    if (elements.length === 0) return;

    elements.forEach((element, index) => {
      gsap.fromTo(element,
        from,
        {
          opacity: 1,
          y: 0,
          scale: 1,
          duration: 0.8,
          ease: 'expo.out',
          delay: (index % 4) * stagger,
          scrollTrigger: { trigger: element, start: 'top 90%' },
        });
    });
  });
}

/**
 * Resting state for reduced motion: the same elements, already in place.
 * Without this they would keep whatever the fromTo would have started from.
 */
export function settleCards(gsap) {
  gsap.set('[data-timeline-card], [data-award]', { opacity: 1, y: 0, scale: 1 });
}
