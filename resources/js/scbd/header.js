export function initHeader(gsap, lenis) {
  const header = document.querySelector('[data-header]');
  if (!header) return;

  let previous = 0;

  lenis.on('scroll', ({ scroll }) => {
    const hide = scroll > 140 && scroll > previous;
    gsap.to(header, { yPercent: hide ? -100 : 0, duration: 0.4, ease: 'power3.out' });
    previous = scroll;
  });
}
