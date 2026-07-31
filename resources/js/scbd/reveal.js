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

  document.querySelectorAll('[data-fade]').forEach((element) => {
    gsap.from(element, {
      y: 34,
      opacity: 0,
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

  // The reference wrote `yPercert` here (page.jsx:95) — a silent no-op that left
  // the contact heading visible before its own reveal ran.
  const contactChars = document.querySelectorAll('#contact [data-char]');
  const contact = document.querySelector('#contact');

  if (!contact || contactChars.length === 0) return;

  if (contact.getBoundingClientRect().top < window.innerHeight * 0.7) {
    gsap.set(contactChars, { yPercent: 0 });
    return;
  }

  gsap.set(contactChars, { yPercent: 105 });

  ScrollTrigger.create({
    trigger: '#contact',
    start: 'top 70%',
    once: true,
    onEnter: () =>
      gsap.to(contactChars, { yPercent: 0, duration: 0.8, stagger: 0.01, ease: 'expo.out' }),
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
