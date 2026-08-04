export function runLoader(gsap, ScrollTrigger, lenis, reduced) {
  const loader = document.querySelector('[data-loader]');
  const number = document.querySelector('[data-loader-num]');
  const bar = document.querySelector('[data-loader-bar]');

  // index.js parks every [data-char] at yPercent 105 before anything runs, so
  // whatever path we take from here has to put the intro elements back. Miss
  // it and the hero renders with a clean console and stays invisible.
  const settle = () => {
    gsap.set('#top [data-char]', { yPercent: 0 });
    gsap.set('#top [data-parallax-wrap]', { clipPath: 'inset(0% 0% 0% 0%)' });
    // clearProps, not yPercent: 0. Writing the transform at all makes the
    // header a containing block for its position:fixed descendants, which
    // traps the mobile drawer inside the header.
    gsap.set('header[data-header]', { clearProps: 'transform' });
  };

  // A builder page can use the hero block without the loader overlay, which
  // belongs to the homepage's intro rather than to the hero itself.
  if (!loader) {
    settle();
    ScrollTrigger.refresh();

    return;
  }

  const finish = () => {
    loader.style.display = 'none';
    lenis.start();
    ScrollTrigger.refresh();
  };

  // Reduced motion: skip the whole intro and jump to the resting state.
  if (reduced || !number || !bar) {
    settle();
    finish();

    return;
  }

  lenis.stop();

  const counter = { value: 0 };
  const timeline = gsap.timeline();

  timeline
    .to(counter, {
      value: 100,
      duration: 1.9,
      ease: 'power2.inOut',
      onUpdate: () => {
        number.textContent = String(Math.round(counter.value)).padStart(3, '0');
      },
    }, 0)
    .to(bar, { width: '100%', duration: 1.9, ease: 'power2.inOut' }, 0)
    .to([number, bar.parentNode, loader.firstElementChild.firstElementChild], { opacity: 0, duration: 0.35 }, 1.95)
    .to(loader, {
      yPercent: -100,
      duration: 0.9,
      ease: 'expo.inOut',
      onComplete: finish,
    }, 2.15)
    .fromTo('#top [data-char]',
      { yPercent: 105 },
      { yPercent: 0, duration: 0.85, stagger: 0.014, ease: 'expo.out' }, 2.5)
    .fromTo('#top [data-parallax-wrap]',
      { clipPath: 'inset(100% 0% 0% 0%)' },
      { clipPath: 'inset(0% 0% 0% 0%)', duration: 1.1, ease: 'expo.out' }, 2.6)
    .fromTo('header[data-header]',
      { yPercent: -100 },
      {
        yPercent: 0,
        duration: 0.6,
        ease: 'power3.out',
        // The transform has to go once the header has arrived: while it is set,
        // the header is the containing block for its position:fixed drawer,
        // which then cannot cover the viewport.
        onComplete: () => gsap.set('header[data-header]', { clearProps: 'transform' }),
      }, 2.8);

  // Failsafe from the reference: never trap the user behind the loader.
  const forceOpen = () => {
    if (loader.style.display === 'none') return;
    timeline.progress(1, false);
    finish();
  };

  setTimeout(forceOpen, 5000);

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && gsap.ticker.frame < 5) forceOpen();
  });
}
