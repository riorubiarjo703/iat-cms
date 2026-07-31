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

  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (!target) return;
      event.preventDefault();
      lenis.scrollTo(target, { offset: -70 });
    });
  });

  return lenis;
}
