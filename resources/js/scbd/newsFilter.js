import { prefersReducedMotion } from './motion';

/**
 * Category filtering for the news index.
 *
 * The reference template does this with isotope; Flip does the same job with
 * what is already in the bundle. Flip reads every card's position before the
 * change, lets the browser re-flow, then animates each card from where it was
 * to where it now is — so the survivors glide into their new places instead of
 * snapping.
 *
 * The chips are function, not decoration: under reduced motion the class
 * toggle still runs and only the tween is skipped, which is how every other
 * module here degrades.
 */
export function initNewsFilter(gsap, Flip, ScrollTrigger) {
  const root = document.querySelector('[data-news-filter]');
  const grid = root?.querySelector('[data-news-grid]');

  // Every other page pays nothing.
  if (!root || !grid) {
    return;
  }

  const chips = Array.from(root.querySelectorAll('[data-news-filter-chip]'));
  const cards = Array.from(grid.querySelectorAll('[data-news-category]'));

  if (chips.length === 0 || cards.length === 0) {
    return;
  }

  const reduced = prefersReducedMotion();

  const apply = (slug) => {
    // Captured before anything moves: Flip needs the old geometry to animate
    // from, and reading it after the class toggle would capture the new one.
    const state = reduced ? null : Flip.getState(cards);

    cards.forEach((card) => {
      const matches = slug === '' || card.getAttribute('data-news-category') === slug;
      card.classList.toggle('is-hidden', !matches);
    });

    chips.forEach((chip) => {
      chip.setAttribute('aria-pressed', String(chip.getAttribute('data-news-filter-chip') === slug));
    });

    if (state) {
      Flip.from(state, {
        duration: 0.6,
        stagger: 0.03,
        ease: 'power3.out',
        // Cards leaving and arriving overlap during the tween; taking them out
        // of flow stops the ones that remain from being shoved about mid-flight.
        absolute: true,
        onEnter: (elements) => gsap.fromTo(elements, { opacity: 0, scale: 0.94 }, { opacity: 1, scale: 1, duration: 0.4 }),
        onLeave: (elements) => gsap.to(elements, { opacity: 0, scale: 0.94, duration: 0.3 }),
        // The page just got shorter or taller, so every trigger below it is
        // now measuring against stale positions.
        onComplete: () => ScrollTrigger.refresh(),
      });

      return;
    }

    ScrollTrigger.refresh();
  };

  chips.forEach((chip) => {
    chip.addEventListener('click', () => apply(chip.getAttribute('data-news-filter-chip') ?? ''));
  });
}
