/**
 * Header navigation behaviour.
 *
 * Desktop dropdowns open on hover and focus, entirely in CSS. This script
 * covers the two cases CSS cannot: the mobile drawer, and touch devices, where
 * hover does not exist and the first tap on a parent would follow its link
 * before the submenu could be seen.
 */
export function initNav() {
  const header = document.querySelector('.scbd-header');
  if (!header) return;

  const toggle = header.querySelector('[data-nav-toggle]');
  const backdrop = header.querySelector('[data-nav-backdrop]');
  const drawer = header.querySelector('.scbd-header-nav');
  const parents = Array.from(header.querySelectorAll('.scbd-nav-has-children'));

  const isDrawerLayout = () => window.matchMedia('(max-width: 1023px)').matches;

  const closeSubmenus = (except = null) => {
    parents.forEach((parent) => {
      if (parent === except || parent.contains(except)) return;
      parent.classList.remove('scbd-nav-open');
      parent.querySelector(':scope > .scbd-nav-link')?.setAttribute('aria-expanded', 'false');
    });
  };

  // ── Drawer ────────────────────────────────────────────────────────────
  const setDrawer = (open) => {
    header.classList.toggle('scbd-nav-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    toggle?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    if (backdrop) backdrop.hidden = !open;
    // Stops the page behind the drawer from scrolling under it.
    document.body.style.overflow = open ? 'hidden' : '';
    if (!open) closeSubmenus();
  };

  toggle?.addEventListener('click', () => setDrawer(!header.classList.contains('scbd-nav-open')));
  backdrop?.addEventListener('click', () => setDrawer(false));

  // Following a link should leave the drawer closed behind you.
  drawer?.addEventListener('click', (event) => {
    const link = event.target.closest('a');
    if (link && !link.closest('.scbd-nav-has-children > .scbd-nav-link')) setDrawer(false);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    setDrawer(false);
    closeSubmenus();
  });

  // Resizing past the breakpoint with the drawer open would leave the body
  // locked and the desktop nav hidden behind a transform.
  window.addEventListener('resize', () => {
    if (!isDrawerLayout()) setDrawer(false);
  });

  // ── Submenus ──────────────────────────────────────────────────────────
  // In the drawer every parent toggles. On a pointer device CSS already
  // handles hover, so only touch needs this.
  const needsTapToOpen = () => isDrawerLayout() || window.matchMedia('(hover: none)').matches;

  parents.forEach((parent) => {
    const link = parent.querySelector(':scope > .scbd-nav-link');

    link?.addEventListener('click', (event) => {
      if (!needsTapToOpen()) return;
      if (parent.classList.contains('scbd-nav-open')) return; // second tap follows the link

      event.preventDefault();
      closeSubmenus(parent);
      parent.classList.add('scbd-nav-open');
      link.setAttribute('aria-expanded', 'true');
    });
  });

  document.addEventListener('click', (event) => {
    if (isDrawerLayout()) return;
    if (!event.target.closest('.scbd-nav-has-children')) closeSubmenus();
  });
}
