/**
 * Scroll-scrubbed word reveal — the effect lenis.dev uses on its "why smooth
 * scroll?" copy. Words sit dim and light up one after another as the block
 * travels through the viewport, tied to scroll position rather than to a timer,
 * so scrolling back dims them again.
 *
 * Not applied to elements the language switcher rewrites: it assigns
 * innerHTML, which would replace the word spans these tweens hold references
 * to, leaving the text stuck at its dim starting opacity.
 */
const DIM = 0.16;

function wrapWords(element) {
  // Word spans only — splitting to characters here would multiply the node
  // count by ~5 for no visible gain, since the reveal steps word by word.
  const words = element.textContent.trim().split(/(\s+)/);

  element.innerHTML = words
    .map((part) => (/^\s+$/.test(part) ? part : `<span data-word style="display:inline-block;">${part}</span>`))
    .join('');

  return Array.from(element.querySelectorAll('[data-word]'));
}

export function initScrollText(gsap, ScrollTrigger) {
  document.querySelectorAll('[data-scroll-text]').forEach((element) => {
    if (element.dataset.i18n) return;

    const words = wrapWords(element);
    if (words.length === 0) return;

    gsap.fromTo(words,
      { opacity: DIM },
      {
        opacity: 1,
        ease: 'none',
        stagger: 1,
        scrollTrigger: {
          trigger: element,
          start: 'top 82%',
          end: 'bottom 58%',
          scrub: true,
        },
      });
  });
}

/** Resting state for reduced motion: every word at full strength. */
export function settleScrollText(gsap) {
  document.querySelectorAll('[data-scroll-text]').forEach((element) => {
    if (element.dataset.i18n) return;
    gsap.set(wrapWords(element), { opacity: 1 });
  });
}
