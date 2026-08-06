import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { initNewsFilter } from '../../resources/js/scbd/newsFilter.js';

/**
 * The news category filter.
 *
 * The property under test is a control-flow one, which is why it lives here and
 * not in a PHP source-text contract: under reduced motion the filtering still
 * happens in full — the `is-hidden` toggle AND the `aria-pressed` update — and
 * only the Flip tween is skipped. The chips are how the archive is browsed.
 *
 * The motion preference is controlled by stubbing window.matchMedia rather than
 * by vi.mock-ing './motion.js'. Two reasons. First, the real motion.js runs, so
 * the tests fail if the module stops consulting it or starts asking for a
 * different media query — a mock would happily answer whatever it was asked.
 * Second, prefersReducedMotion() is called once, at init, so the stub has to be
 * in place BEFORE initNewsFilter runs; mount() below enforces that ordering,
 * which is also the ordering the real page has.
 */

const CARDS = [
    { id: 'a', category: 'design' },
    { id: 'b', category: 'build' },
    { id: 'c', category: 'design' },
    // The card with no category at all. The Blade partial renders
    // `data-news-category=""` when the post has no category, and the All chip
    // has to bring it back like any other.
    { id: 'd', category: '' },
];

function markup() {
    // Mirrors resources/views/partials/blocks/scbd-news-index.blade.php and
    // resources/views/partials/site/news-card.blade.php: the section carries
    // data-news-filter, the grid data-news-grid, each chip a
    // data-news-filter-chip whose value is the slug (empty for All), and each
    // card a data-news-category.
    const chips = ['', 'design', 'build']
        .map((slug) => `<button type="button" data-news-filter-chip="${slug}" aria-pressed="${slug === '' ? 'true' : 'false'}">${slug || 'All'}</button>`)
        .join('');

    const cards = CARDS
        .map((card) => `<a id="card-${card.id}" href="/news/${card.id}" data-news-category="${card.category}"></a>`)
        .join('');

    return `
        <section data-news-filter>
            <div role="group">${chips}</div>
            <div data-news-grid>${cards}</div>
            <aside>
                <a id="sidebar-card" href="/news/a" data-news-category="design"></a>
            </aside>
        </section>
    `;
}

function stubs() {
    const state = { sentinel: 'flip-state' };

    return {
        state,
        gsap: { fromTo: vi.fn(), to: vi.fn() },
        Flip: { getState: vi.fn(() => state), from: vi.fn() },
        ScrollTrigger: { refresh: vi.fn() },
    };
}

/**
 * Set the motion preference, build the page, then init — in that order,
 * because the module reads the preference once when it starts.
 */
function mount({ reducedMotion = false, html = markup() } = {}) {
    window.matchMedia = vi.fn((query) => ({
        // Answering per-query, not blanket-true: if the module ever asked for a
        // different media query, `matches` would come back false and the
        // reduced-motion tests below would fail rather than quietly pass.
        matches: query === '(prefers-reduced-motion: reduce)' ? reducedMotion : false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    }));

    document.body.innerHTML = html;

    const stub = stubs();

    initNewsFilter(stub.gsap, stub.Flip, stub.ScrollTrigger);

    return stub;
}

const chip = (slug) => document.querySelector(`[data-news-filter-chip="${slug}"]`);
const card = (id) => document.getElementById(`card-${id}`);
const hidden = (id) => card(id).classList.contains('is-hidden');
const pressed = () => ['', 'design', 'build'].map((slug) => chip(slug).getAttribute('aria-pressed'));

beforeEach(() => {
    document.body.innerHTML = '';
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('initNewsFilter', () => {
    it('hides the cards a category chip does not match and leaves the rest visible', () => {
        mount();

        chip('design').click();

        expect(hidden('a')).toBe(false);
        expect(hidden('c')).toBe(false);
        expect(hidden('b')).toBe(true);
        expect(hidden('d')).toBe(true);

        // The sidebar is a different list on the same page, outside the grid.
        expect(document.getElementById('sidebar-card').classList.contains('is-hidden')).toBe(false);
    });

    it('restores every card — the uncategorised one included — when All is clicked', () => {
        mount();

        chip('build').click();

        expect(hidden('a')).toBe(true);
        expect(hidden('d')).toBe(true);

        chip('').click();

        CARDS.forEach(({ id }) => expect(hidden(id)).toBe(false));
    });

    it('marks the active chip aria-pressed and clears it from the others', () => {
        mount();

        chip('build').click();

        expect(pressed()).toEqual(['false', 'false', 'true']);

        chip('').click();

        expect(pressed()).toEqual(['true', 'false', 'false']);
    });

    it('tweens with Flip under normal motion', () => {
        const { Flip, state } = mount();

        chip('design').click();

        expect(Flip.getState).toHaveBeenCalledWith(expect.arrayContaining([card('a')]));
        expect(Flip.from).toHaveBeenCalledTimes(1);
        expect(Flip.from.mock.calls[0][0]).toBe(state);
    });

    it('still filters under reduced motion, and skips only the tween', () => {
        const { Flip } = mount({ reducedMotion: true });

        chip('design').click();

        // The filtering itself is not motion.
        expect(hidden('a')).toBe(false);
        expect(hidden('c')).toBe(false);
        expect(hidden('b')).toBe(true);
        expect(hidden('d')).toBe(true);

        // Neither is the accessible state of the chips.
        expect(pressed()).toEqual(['false', 'true', 'false']);

        // Only this is.
        expect(Flip.from).not.toHaveBeenCalled();
    });

    it('still restores every card from the All chip under reduced motion', () => {
        const { Flip } = mount({ reducedMotion: true });

        chip('build').click();

        expect(hidden('a')).toBe(true);

        chip('').click();

        CARDS.forEach(({ id }) => expect(hidden(id)).toBe(false));
        expect(pressed()).toEqual(['true', 'false', 'false']);
        expect(Flip.from).not.toHaveBeenCalled();
    });

    it.each([
        ['normal motion', false],
        ['reduced motion', true],
    ])('refreshes ScrollTrigger after filtering under %s', (_label, reducedMotion) => {
        const { Flip, ScrollTrigger } = mount({ reducedMotion });

        chip('design').click();

        if (reducedMotion) {
            // Nothing tweens, so the refresh has to happen inline.
            expect(ScrollTrigger.refresh).toHaveBeenCalledTimes(1);

            return;
        }

        // Under full motion the grid is still moving, so the refresh is the
        // tween's onComplete — stale trigger positions until it lands.
        expect(ScrollTrigger.refresh).not.toHaveBeenCalled();

        Flip.from.mock.calls[0][1].onComplete();

        expect(ScrollTrigger.refresh).toHaveBeenCalledTimes(1);
    });

    it.each([
        ['the section is absent', '<div data-news-grid></div>'],
        ['the grid is absent', '<section data-news-filter><button data-news-filter-chip=""></button></section>'],
        ['there are no chips', '<section data-news-filter><div data-news-grid><a data-news-category="design"></a></div></section>'],
        ['there are no cards', '<section data-news-filter><button data-news-filter-chip=""></button><div data-news-grid></div></section>'],
    ])('does nothing and throws nothing when %s', (_label, html) => {
        // Every other page on the site loads this module.
        const { Flip, ScrollTrigger } = mount({ html });

        expect(Flip.getState).not.toHaveBeenCalled();
        expect(ScrollTrigger.refresh).not.toHaveBeenCalled();
    });
});
