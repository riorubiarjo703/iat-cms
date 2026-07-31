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
      const active = button.dataset.lang === locale;
      button.style.background = active ? '#201e1d' : 'transparent';
      button.style.color = active ? '#f3f2f2' : '#201e1d';
    });

    document.documentElement.lang = locale;
    ScrollTrigger.refresh();
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => apply(button.dataset.lang));
  });
}
