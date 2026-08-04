export function initReveals(gsap, ScrollTrigger) {
  const parallax = document.querySelector('[data-parallax]');
  const wrap = document.querySelector('[data-parallax-wrap]');

  if (parallax && wrap) {
    gsap.to(parallax, {
      yPercent: 14,
      ease: 'none',
      scrollTrigger: { trigger: wrap, start: 'top bottom', end: 'bottom top', scrub: true },
    });
  }

  // fromTo, not from: `from` reads the element's current state as the
  // destination, and a ScrollTrigger refresh partway through the tween (which
  // the loader and Lenis both provoke) can re-capture a mid-animation opacity
  // as the resting one, leaving the text permanently half-visible. An explicit
  // destination cannot be re-derived that way.
  document.querySelectorAll('[data-fade]').forEach((element) => {
    gsap.fromTo(element,
      { y: 34, opacity: 0 },
      {
        y: 0,
        opacity: 1,
        duration: 0.9,
        ease: 'expo.out',
        scrollTrigger: { trigger: element, start: 'top 88%' },
      });
  });

  document
    .querySelectorAll('[data-reveal], #district img, #facilities img')
    .forEach((element) => {
      gsap.fromTo(element,
        { clipPath: 'inset(0% 0% 100% 0%)', scale: 1.16 },
        {
          clipPath: 'inset(0% 0% 0% 0%)',
          scale: 1,
          duration: 1.2,
          ease: 'expo.out',
          scrollTrigger: { trigger: element, start: 'top 92%' },
        });
    });

  initSplitHeadings(gsap, ScrollTrigger);
}

/**
 * Reveals every `[data-split]` heading as it scrolls into view.
 *
 * index.js parks all `[data-char]` at yPercent 105 before anything else runs.
 * Only two selectors ever put them back: the loader timeline for `#top`, and —
 * previously — a hardcoded `#contact` block here. Every other split heading on
 * the site (Vision, Mission, and every interior page's section titles) was left
 * parked below its own clipping wrapper, so it never appeared at all. The
 * reference wrote `yPercert` in this spot (page.jsx:95), a silent no-op, which
 * is why the omission was inherited rather than noticed.
 *
 * `#top` is excluded because the loader owns those characters; running both
 * would fight over the same transform.
 */
function initSplitHeadings(gsap, ScrollTrigger) {
  document.querySelectorAll('[data-split]').forEach((element) => {
    if (element.closest('#top')) return;

    const chars = element.querySelectorAll('[data-char]');
    if (chars.length === 0) return;

    // Already on screen when the page settles: show it now. A trigger whose
    // start line is above the viewport top never fires, which would leave a
    // heading near the top of a short page permanently hidden.
    if (element.getBoundingClientRect().top < window.innerHeight * 0.85) {
      gsap.set(chars, { yPercent: 0 });

      return;
    }

    gsap.set(chars, { yPercent: 105 });

    ScrollTrigger.create({
      trigger: element,
      start: 'top 85%',
      once: true,
      onEnter: () =>
        gsap.to(chars, { yPercent: 0, duration: 0.8, stagger: 0.012, ease: 'expo.out' }),
    });
  });
}

/**
 * Deviation from the brief: page.jsx (lines 146-149) gives every `[data-news]`
 * row a small horizontal-nudge hover, and the rendered homepage genuinely
 * emits `data-news` (resources/views/partials/home/news.blade.php). The Task
 * 17 brief's module list omits this hook entirely. Dropping it would silently
 * lose a source behaviour bound to a hook the task explicitly says is present,
 * so it is ported here verbatim rather than left out.
 */
export function initNewsHover(gsap) {
  document.querySelectorAll('[data-news]').forEach((row) => {
    row.addEventListener('mouseenter', () =>
      gsap.to(row, { x: 14, duration: 0.4, ease: 'power3.out' }));
    row.addEventListener('mouseleave', () =>
      gsap.to(row, { x: 0, duration: 0.4, ease: 'power3.out' }));
  });
}
