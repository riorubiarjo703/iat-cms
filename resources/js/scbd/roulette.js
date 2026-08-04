/**
 * Continuously drifting rows whose speed reacts to scroll velocity — the
 * "roulette" effect buckssauce.com uses on its ingredient row.
 *
 * Each [data-roulette] track holds its items twice over (the Blade view emits
 * the duplicate and hides it from assistive tech), so travelling -50% lands
 * exactly on the start of the copy and the loop has no visible seam.
 */
export function initRoulette(gsap, ScrollTrigger) {
  const tracks = Array.from(document.querySelectorAll('[data-roulette]'));
  if (tracks.length === 0) return;

  tracks.forEach((track, index) => {
    // Alternate direction per row: two rows drifting the same way read as one
    // block sliding, which loses the roulette feel entirely.
    const reverse = index % 2 === 1;

    // The tween is held in a variable and the boost animates ITS timeScale.
    // Animating the element's timeScale instead makes GSAP treat it as an
    // unknown CSS property, and overwrite then kills the loop.
    const loop = gsap.fromTo(track,
      { xPercent: reverse ? -50 : 0 },
      {
        xPercent: reverse ? 0 : -50,
        duration: 38,
        ease: 'none',
        repeat: -1,
      });

    ScrollTrigger.create({
      trigger: track,
      start: 'top bottom',
      end: 'bottom top',
      onUpdate: (self) => {
        const boost = 1 + Math.min(Math.abs(self.getVelocity()) / 700, 4);
        gsap.to(loop, { timeScale: boost, duration: 0.35, overwrite: true });
      },
    });
  });
}
