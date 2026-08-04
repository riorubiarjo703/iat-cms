/**
 * Scroll-scrubbed block reveal — the effect lenis.dev uses on its "why smooth
 * scroll?" feature blocks.
 *
 * The distinction that matters: progress is driven by scroll position itself,
 * not by a timer started when the element crosses a threshold. Scrolling back
 * up runs it backwards. That is the whole point of the reference section — it
 * is the library demonstrating its own claim — and an IntersectionObserver
 * fade would not show it.
 *
 * Each block animates opacity and a Y-translate together, and its lead element
 * (the number) arrives slightly ahead of its body text, so the two are offset
 * by a fraction of the scroll rather than moving as one rigid unit. Blocks
 * stagger against each other for free: each has its own trigger, so they enter
 * in sequence as the row passes.
 */
const START = 'top 92%';
const END = 'top 52%';

export function initScrollText(gsap, ScrollTrigger) {
  document.querySelectorAll('[data-scroll-block]').forEach((block) => {
    const lead = block.querySelector('[data-scroll-lead]');
    const body = block.querySelector('[data-scroll-body]');

    if (!lead && !body) return;

    const timeline = gsap.timeline({
      scrollTrigger: { trigger: block, start: START, end: END, scrub: true },
    });

    // ease:'none' throughout — with scrub the scroll position IS the easing,
    // and an eased tween on top of it makes the text lag the pointer.
    if (lead) {
      timeline.fromTo(lead, { opacity: 0, y: 26 }, { opacity: 1, y: 0, ease: 'none' }, 0);
    }

    if (body) {
      // Offset into the same timeline rather than a second trigger: one
      // scroll range drives both, which is what keeps the gap between them
      // constant instead of drifting with scroll speed.
      timeline.fromTo(body, { opacity: 0, y: 40 }, { opacity: 1, y: 0, ease: 'none' }, 0.18);
    }
  });
}

/** Resting state for reduced motion: everything in place, at full strength. */
export function settleScrollText(gsap) {
  const targets = document.querySelectorAll('[data-scroll-lead], [data-scroll-body]');

  if (targets.length > 0) gsap.set(targets, { opacity: 1, y: 0 });
}
