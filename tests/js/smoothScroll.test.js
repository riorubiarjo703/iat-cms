import { describe, expect, it, vi, beforeEach } from 'vitest';
import { bindAnchors } from '../../resources/js/scbd/smoothScroll.js';

/**
 * Navigation anchors point at the homepage now ("http://host/#about") rather
 * than being bare fragments, so that they work from interior pages. The
 * selector that used to catch them, a[href^="#"], no longer matches — without
 * this the homepage lost smooth scrolling entirely and every nav click became
 * a full page load.
 */
describe('bindAnchors', () => {
  const here = () => window.location.origin + window.location.pathname;

  /**
   * Returns whether the handler under test cancelled the click.
   *
   * The recorder runs after it, in the bubble phase, and cancels whatever is
   * left so jsdom does not try to follow a link it cannot navigate to.
   */
  const click = (element) => {
    let prevented = false;
    const record = (event) => {
      prevented = event.defaultPrevented;
      event.preventDefault();
    };

    document.addEventListener('click', record);
    element.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', record);

    return prevented;
  };

  beforeEach(() => {
    document.body.innerHTML = '';
  });

  const setup = (href) => {
    document.body.innerHTML = `
      <a id="link" href="${href}">Go</a>
      <section id="about">About</section>
    `;
    const scrollTo = vi.fn();
    bindAnchors(scrollTo);

    return { scrollTo, link: document.getElementById('link') };
  };

  it('scrolls for a bare fragment', () => {
    const { scrollTo, link } = setup('#about');

    expect(click(link)).toBe(true);

    expect(scrollTo).toHaveBeenCalledWith(document.getElementById('about'));
  });

  it('scrolls for an absolute url pointing at this same page', () => {
    const { scrollTo, link } = setup(`${here()}#about`);

    expect(click(link)).toBe(true);

    expect(scrollTo).toHaveBeenCalledWith(document.getElementById('about'));
  });

  it('lets a link to another page navigate', () => {
    const { scrollTo, link } = setup(`${window.location.origin}/profile#about`);

    expect(click(link)).toBe(false);

    expect(scrollTo).not.toHaveBeenCalled();
  });

  it('lets a fragment with no target on this page navigate', () => {
    const { scrollTo, link } = setup('#nowhere');

    expect(click(link)).toBe(false);

    expect(scrollTo).not.toHaveBeenCalled();
  });

  it('ignores the bare heading marker', () => {
    const { scrollTo, link } = setup('#');

    expect(click(link)).toBe(false);

    expect(scrollTo).not.toHaveBeenCalled();
  });
});
