import { Flip } from 'gsap/Flip';

/**
 * The awards index: a certificate is summoned rather than displayed.
 *
 * Two behaviours, deliberately independent. The hover preview is a pointer
 * enhancement and is skipped entirely on touch. The reader is the real feature
 * and opens from click, Enter or Space on the row button, so the gallery works
 * without a mouse.
 *
 * Flip is what connects them: the reader image starts life at the geometry of
 * whatever the visitor was already looking at — the floating preview on a
 * desktop, the row's own thumbnail on a phone — and grows from there, so the
 * certificate you asked for is the one that arrives.
 */
export function initAwards(gsap, lenis) {
  const section = document.querySelector('[data-awards]');
  const reader = document.querySelector('[data-award-reader]');

  if (!section || !reader) return;

  const rows = Array.from(section.querySelectorAll('[data-award-row][data-award-src]'));
  const preview = section.querySelector('[data-award-preview]');
  const previewImage = preview.querySelector('img');
  const readerImage = reader.querySelector('[data-award-reader-img]');
  const caption = reader.querySelector('[data-award-reader-caption]');

  // A coarse pointer has no hover state to speak of: a preview chasing a
  // finger would flicker in and out on every tap.
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  if (finePointer) initPreview(gsap, section, rows, preview, previewImage);

  // ── The reader ────────────────────────────────────────────────────────
  let lastFocused = null;

  const open = (row) => {
    const source = row.dataset.awardSrc;
    if (!source) return;

    lastFocused = row;
    readerImage.src = source;
    caption.textContent = row.dataset.awardTitle || '';

    // Grow from what the visitor is already looking at. On a desktop mid-hover
    // that is the floating preview; otherwise it is the row's own thumbnail.
    const origin = finePointer && preview.classList.contains('is-visible')
      ? preview
      : row.querySelector('.scbd-awards-thumb');

    reader.hidden = false;
    gsap.set(reader, { opacity: 1 });

    if (origin) {
      const from = Flip.getState(origin);

      Flip.from(from, {
        targets: readerImage,
        duration: 0.62,
        ease: 'expo.out',
        absolute: true,
        scale: true,
      });
    }

    gsap.fromTo(reader.querySelector('.scbd-awards-reader-backdrop'),
      { opacity: 0 }, { opacity: 1, duration: 0.35 });

    hidePreview(gsap, preview);
    // Stopping Lenis rather than setting overflow:hidden — Lenis owns the
    // scroll position, and a body lock alone leaves it scrolling underneath.
    lenis?.stop();
    document.body.classList.add('scbd-reader-open');
    reader.querySelector('.scbd-awards-reader-close').focus();
  };

  const close = () => {
    gsap.to(reader, {
      opacity: 0,
      duration: 0.28,
      onComplete: () => {
        reader.hidden = true;
        readerImage.removeAttribute('src');
      },
    });

    lenis?.start();
    document.body.classList.remove('scbd-reader-open');
    lastFocused?.focus();
  };

  rows.forEach((row) => row.addEventListener('click', () => open(row)));

  reader.querySelectorAll('[data-award-close]')
    .forEach((element) => element.addEventListener('click', close));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !reader.hidden) close();
  });
}

function initPreview(gsap, section, rows, preview, previewImage) {
  // quickTo, not a fresh tween per pointermove: it reuses one tween and only
  // updates its target, which is what keeps the follow smooth at pointer rate
  // instead of queuing hundreds of overlapping tweens.
  const moveX = gsap.quickTo(preview, 'x', { duration: 0.55, ease: 'power3' });
  const moveY = gsap.quickTo(preview, 'y', { duration: 0.55, ease: 'power3' });

  section.addEventListener('pointermove', (event) => {
    moveX(event.clientX);
    moveY(event.clientY);
  });

  rows.forEach((row) => {
    const show = () => {
      previewImage.src = row.dataset.awardSrc;
      preview.classList.add('is-visible');
      gsap.to(preview, { opacity: 1, scale: 1, duration: 0.4, ease: 'expo.out', overwrite: true });
    };

    row.addEventListener('pointerenter', show);
    row.addEventListener('pointerleave', () => hidePreview(gsap, preview));
  });

  // Leaving the list entirely, rather than one row for another.
  section.addEventListener('pointerleave', () => hidePreview(gsap, preview));
}

function hidePreview(gsap, preview) {
  preview.classList.remove('is-visible');
  gsap.to(preview, { opacity: 0, scale: 0.92, duration: 0.3, ease: 'power2.out', overwrite: true });
}
