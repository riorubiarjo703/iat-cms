export function initCounters(gsap, ScrollTrigger) {
  document.querySelectorAll('[data-count]').forEach((element) => {
    const target = parseFloat(element.dataset.to);
    if (Number.isNaN(target)) return;

    const suffix = element.dataset.suffix || '';
    const plain = element.hasAttribute('data-plain');
    const state = { value: 0 };

    const render = () => {
      const rounded = Math.round(state.value);
      element.textContent = (plain ? String(rounded) : rounded.toLocaleString()) + suffix;
    };

    ScrollTrigger.create({
      trigger: element,
      start: 'top 88%',
      once: true,
      onEnter: () =>
        gsap.to(state, { value: target, duration: 1.6, ease: 'power2.out', onUpdate: render }),
    });
  });
}
