/**
 * The reference had no reduced-motion handling at all, which makes the page
 * unusable for anyone with the preference set. Every module consults this.
 */
export function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
