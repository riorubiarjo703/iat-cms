/**
 * Pinned horizontal scroll.
 *
 * The reference computed `end: '+=' + (track.scrollWidth - innerWidth)`
 * unconditionally. With few or no district places that value is zero or
 * negative, and ScrollTrigger pins the viewport with nowhere to scroll — the
 * whole page appears frozen. This is the only failure mode that breaks the
 * entire site rather than one section, so it is guarded explicitly.
 *
 * @returns {boolean} whether pinning was created
 */
export function initDistrict(gsap, ScrollTrigger) {
  const track = document.querySelector('[data-horizontal-track]');
  const section = document.querySelector('#district');

  if (!track || !section) return false;

  const overflow = () => track.scrollWidth - window.innerWidth;

  if (overflow() <= 0) {
    gsap.set(track, { x: 0 });
    return false;
  }

  ScrollTrigger.create({
    trigger: '#district',
    start: 'top top',
    pin: true,
    scrub: 0.8,
    anticipatePin: 1,
    end: () => `+=${overflow()}`,
    onRefresh: () => gsap.set(track, { x: 0 }),
    animation: gsap.to(track, { x: () => -overflow(), ease: 'none' }),
    invalidateOnRefresh: true,
  });

  return true;
}
