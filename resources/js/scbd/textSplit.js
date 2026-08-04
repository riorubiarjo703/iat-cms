/**
 * Wraps each character of a `[data-split]` heading in its own inline-block span
 * so the loader and scroll reveals can stagger them. Lines are delimited by
 * <br> in the server-rendered markup.
 *
 * Characters are grouped into words. Without that grouping every character is
 * independently wrappable and a narrow viewport breaks the heading mid-word —
 * one letter per line on a phone.
 *
 * The line wrapper takes its height from its own content rather than a fixed
 * value: a transform does not affect layout height, so overflow:hidden still
 * masks the offset while the box scales with the font size.
 */
export function splitElement(element) {
  if (element._origHTML == null) element._origHTML = element.innerHTML;

  const charSpan = (char) =>
    `<span data-char style="display:inline-block;white-space:pre;transform:translateY(105%);">${
      char === ' ' ? '&nbsp;' : char
    }</span>`;

  element.innerHTML = element._origHTML
    .split(/<br[^>]*>/i)
    .map((line) => {
      const words = line
        .trim()
        // Keep the separators so spacing survives the round trip.
        .split(/(\s+)/)
        .filter((part) => part !== '')
        .map((part) => {
          const chars = part.split('').map(charSpan).join('');

          // A word is one unbreakable unit; whitespace stays breakable so the
          // heading can still wrap between words.
          return /^\s+$/.test(part)
            ? chars
            : `<span style="display:inline-block;white-space:nowrap;">${chars}</span>`;
        })
        .join('');

      return `<span style="display:block;overflow:hidden;padding-bottom:0.08em;">${words}</span>`;
    })
    .join('');
}

export function splitTargets(root = document) {
  root.querySelectorAll('[data-split]').forEach(splitElement);
}
