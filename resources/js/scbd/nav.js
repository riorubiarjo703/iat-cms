/**
 * Touch support for the header dropdowns.
 *
 * Hover and focus-within do the work on a pointer device, entirely in CSS.
 * A touch device has neither: the first tap on a parent would follow its link
 * before the submenu could be seen. So on coarse pointers the first tap opens
 * the submenu and the second follows the link.
 */
export function initNav() {
    if (!window.matchMedia('(hover: none)').matches) return;

    const parents = Array.from(document.querySelectorAll('.scbd-nav-has-children'));
    if (parents.length === 0) return;

    const closeAll = (except) => {
        parents.forEach((parent) => {
            if (parent !== except && !parent.contains(except)) {
                parent.classList.remove('scbd-nav-open');
                parent.querySelector(':scope > .scbd-nav-link')?.setAttribute('aria-expanded', 'false');
            }
        });
    };

    parents.forEach((parent) => {
        const link = parent.querySelector(':scope > .scbd-nav-link');

        link?.addEventListener('click', (event) => {
            if (parent.classList.contains('scbd-nav-open')) return; // second tap follows the link

            event.preventDefault();
            closeAll(parent);
            parent.classList.add('scbd-nav-open');
            link.setAttribute('aria-expanded', 'true');
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.scbd-nav-has-children')) closeAll(null);
    });

    // Escape closes, matching what a keyboard user expects from a menu.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll(null);
    });
}
