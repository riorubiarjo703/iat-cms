export function initMarquee(gsap, ScrollTrigger) {
  const marquee = document.querySelector('[data-marquee]');
  if (!marquee) return;

  // Hold the loop tween: the velocity boost animates THIS tween's timeScale.
  // Targeting the element instead (as the reference does) makes GSAP treat
  // timeScale as an unknown CSS property, and `overwrite` then kills this loop.
  const loop = gsap.to(marquee, { xPercent: -50, duration: 26, ease: 'none', repeat: -1 });

  ScrollTrigger.create({
    trigger: marquee,
    start: 'top bottom',
    end: 'bottom top',
    onUpdate: (self) => {
      const boost = 1 + Math.min(Math.abs(self.getVelocity()) / 900, 3);
      gsap.to(loop, { timeScale: boost, duration: 0.3, overwrite: true });
    },
  });
}
