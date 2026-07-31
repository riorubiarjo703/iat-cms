export function initCursor(gsap) {
  const dot = document.querySelector('[data-cursor]');
  const ring = document.querySelector('[data-cursor-ring]');

  if (!dot || !ring) return;
  // Coarse pointers have no cursor to follow.
  if (window.matchMedia('(pointer: coarse)').matches) return;

  const dotX = gsap.quickTo(dot, 'x', { duration: 0.12, ease: 'power3' });
  const dotY = gsap.quickTo(dot, 'y', { duration: 0.12, ease: 'power3' });
  const ringX = gsap.quickTo(ring, 'x', { duration: 0.45, ease: 'power3' });
  const ringY = gsap.quickTo(ring, 'y', { duration: 0.45, ease: 'power3' });

  window.addEventListener('mousemove', (event) => {
    dotX(event.clientX);
    dotY(event.clientY);
    ringX(event.clientX);
    ringY(event.clientY);
  });

  document.querySelectorAll('a, button, [data-magnetic]').forEach((element) => {
    element.addEventListener('mouseenter', () =>
      gsap.to(ring, { scale: 1.9, borderColor: 'rgba(236,48,19,0.9)', duration: 0.3 }));
    element.addEventListener('mouseleave', () =>
      gsap.to(ring, { scale: 1, borderColor: 'rgba(32,30,29,0.45)', duration: 0.3 }));
  });

  document.querySelectorAll('[data-magnetic]').forEach((element) => {
    const moveX = gsap.quickTo(element, 'x', { duration: 0.4, ease: 'power3' });
    const moveY = gsap.quickTo(element, 'y', { duration: 0.4, ease: 'power3' });

    element.addEventListener('mousemove', (event) => {
      const rect = element.getBoundingClientRect();
      moveX((event.clientX - (rect.left + rect.width / 2)) * 0.35);
      moveY((event.clientY - (rect.top + rect.height / 2)) * 0.45);
    });

    element.addEventListener('mouseleave', () => {
      moveX(0);
      moveY(0);
    });
  });
}
