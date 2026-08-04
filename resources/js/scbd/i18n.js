import { splitElement } from './textSplit';

/**
 * Instant, no-reload language switching. The reference used a hardcoded
 * dictionary; this reads the server-rendered payload so the copy is editable in
 * the admin. Values arrive pre-escaped with <br> line breaks.
 */
export function initLanguageSwitcher(ScrollTrigger) {
  const payloadNode = document.getElementById('scbd-i18n');
  const buttons = Array.from(document.querySelectorAll('[data-lang]'));

  if (!payloadNode || buttons.length === 0) return;

  let dictionary;
  try {
    dictionary = JSON.parse(payloadNode.textContent);
  } catch (error) {
    console.warn('SCBD: could not parse the i18n payload.', error);
    return;
  }

  const apply = (locale) => {
    const strings = dictionary[locale];
    if (!strings) return;

    document.querySelectorAll('[data-i18n]').forEach((element) => {
      const value = strings[element.dataset.i18n];
      if (value == null || value === '') return;

      element.innerHTML = value;

      if (element.hasAttribute('data-split')) {
        element._origHTML = value;
        splitElement(element);
        element.querySelectorAll('[data-char]').forEach((char) => {
          char.style.transform = 'translateY(0%)';
        });
      }
    });

    buttons.forEach((button) => {
      // aria-current is what the stylesheet marks the active row with, so the
      // highlight and the accessible state cannot drift apart.
      if (button.dataset.lang === locale) button.setAttribute('aria-current', 'true');
      else button.removeAttribute('aria-current');
    });

    // The trigger shows the language you are reading, which means copying the
    // chosen row's flag rather than reloading a server-rendered one.
    const chosen = buttons.find((button) => button.dataset.lang === locale);
    const triggerFlag = document.querySelector('[data-locale-trigger-flag]');
    const triggerCode = document.querySelector('[data-locale-trigger-code]');
    const chosenFlag = chosen?.querySelector('img');

    if (triggerFlag && chosenFlag) triggerFlag.src = chosenFlag.src;
    if (triggerCode) triggerCode.textContent = locale.toUpperCase();

    document.documentElement.lang = locale;
    ScrollTrigger.refresh();
  };

  const trigger = document.querySelector('[data-locale-trigger]');
  const menu = document.querySelector('[data-locale-menu]');

  const close = () => {
    if (!menu || !trigger) return;
    menu.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
  };

  if (trigger && menu) {
    trigger.addEventListener('click', (event) => {
      event.stopPropagation();
      menu.hidden = !menu.hidden;
      trigger.setAttribute('aria-expanded', String(!menu.hidden));
    });

    document.addEventListener('click', (event) => {
      if (!menu.hidden && !event.target.closest('[data-locale-switcher]')) close();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape' || menu.hidden) return;
      close();
      trigger.focus();
    });
  }

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      apply(button.dataset.lang);
      close();
    });
  });
}
