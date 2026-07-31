/**
 * Wraps each character of a `[data-split]` heading in its own inline-block span
 * so the loader and scroll reveals can stagger them. Lines are delimited by
 * <br> in the server-rendered markup.
 */
export function splitElement(element) {
  if (element._origHTML == null) element._origHTML = element.innerHTML;

  element.innerHTML = element._origHTML
    .split(/<br[^>]*>/i)
    .map((line) => {
      const chars = line
        .trim()
        .split('')
        .map(
          (char) =>
            `<span data-char style="display:inline-block;white-space:pre;transform:translateY(105%);">${
              char === ' ' ? '&nbsp;' : char
            }</span>`,
        )
        .join('');

      return `<span style="display:block;overflow:hidden;padding-bottom:0.06em;">${chars}</span>`;
    })
    .join('');
}

export function splitTargets(root = document) {
  root.querySelectorAll('[data-split]').forEach(splitElement);
}
