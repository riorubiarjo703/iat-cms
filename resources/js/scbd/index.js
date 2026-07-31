import gsap from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

import { prefersReducedMotion } from './motion';
import { createSmoothScroll } from './smoothScroll';
import { splitTargets } from './textSplit';
import { runLoader } from './loader';
import { initHeader } from './header';
import { initReveals, initNewsHover } from './reveal';
import { initMarquee } from './marquee';
import { initDistrict } from './district';
import { initCardStack } from './stack';
import { initCounters } from './counters';
import { initCursor } from './cursor';
import { initLanguageSwitcher } from './i18n';

gsap.registerPlugin(ScrollTrigger);

export function initScbd() {
  // The reference polled `window.gsap && window.ScrollTrigger && window.Lenis`
  // every 60ms because a bundled page cannot guarantee script order. With Vite
  // imports, load order is a fact — no polling, and no
  // `window.__scbdInstance` teardown singleton either.
  const reduced = prefersReducedMotion();
  const lenis = createSmoothScroll(ScrollTrigger, reduced);

  splitTargets();

  // Bug found in browser testing, present in both the reference and the
  // brief: textSplit.js bakes `transform:translateY(105%)` into the
  // generated markup as literal CSS text. GSAP resolves that through
  // getComputedStyle() the first time it touches the element, which returns
  // an already-resolved pixel matrix (browsers never report `%` back from
  // computed style) — GSAP caches that resolved pixel value as a permanent
  // "y" baseline separate from its own `yPercent` tracking, which starts at
  // 0. Every later `yPercent` tween (including `yPercent: 0`) is then
  // computed as `y(baseline) + yPercent% * height`, so the baseline never
  // clears and the characters stay hidden forever — confirmed by isolating
  // a bare span with the same literal style outside of this codebase.
  // Explicitly re-claiming the transform through gsap.set with both `y` and
  // `yPercent` (as the very first gsap touch on these elements) makes gsap
  // track the offset as a percentage from a zero baseline, matching the
  // exact same visual position, so every subsequent yPercent tween in
  // loader.js and reveal.js resolves correctly instead of silently no-oping.
  gsap.set('[data-char]', { y: 0, yPercent: 105 });

  initCursor(gsap);
  initLanguageSwitcher(ScrollTrigger);
  initNewsHover(gsap);

  gsap.context(() => {
    runLoader(gsap, ScrollTrigger, lenis, reduced);
    initHeader(gsap, lenis);

    if (!reduced) {
      initReveals(gsap, ScrollTrigger);
      initMarquee(gsap, ScrollTrigger);
      initDistrict(gsap, ScrollTrigger);
      initCardStack(gsap);
    } else {
      // Resting state for everything the reveals would have animated.
      gsap.set('[data-char]', { yPercent: 0 });
      gsap.set('[data-fade]', { opacity: 1, y: 0 });
      gsap.set('[data-reveal], #district img, #facilities img', {
        clipPath: 'inset(0% 0% 0% 0%)',
        scale: 1,
      });
    }

    // Counters run in both modes; the reduced path simply reaches the final
    // number faster than the eye tracks it.
    initCounters(gsap, ScrollTrigger);
  });

  ScrollTrigger.refresh();
  window.addEventListener('resize', () => ScrollTrigger.refresh());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initScbd);
} else {
  initScbd();
}
