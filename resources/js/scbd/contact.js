import { Flip } from 'gsap/Flip';

/**
 * The contact page enquiry form.
 *
 * The form already works without any of this — it is an ordinary POST that
 * redirects back with the confirmation flashed. Everything here is an
 * enhancement over that, which is why every path falls back to a real submit
 * rather than swallowing the click.
 */
export function initContact(gsap) {
  initForm(gsap);
}

function initForm(gsap) {
  const form = document.querySelector('[data-contact-form]');
  const done = document.querySelector('[data-contact-done]');

  if (!form || !done) return;

  const status = form.querySelector('[data-contact-status]');
  const submit = form.querySelector('[data-contact-submit]');
  const label = form.querySelector('[data-contact-submit-label]');
  const original = label?.textContent ?? '';

  // Server-rendered confirmation (the no-JS path came back): animate it in so
  // the arrival reads the same either way.
  if (!done.hidden) revealDone(gsap, done);

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    clearErrors(form);
    submit.disabled = true;
    if (label) label.textContent = 'Sending…';
    status.textContent = '';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });

      if (response.status === 422) {
        const { errors = {} } = await response.json();
        showErrors(gsap, form, errors);
        status.textContent = 'Please check the fields marked below.';

        return;
      }

      if (response.status === 429) {
        status.textContent = 'Too many enquiries from this connection. Please try again later.';

        return;
      }

      if (!response.ok) throw new Error(`Unexpected ${response.status}`);

      const { reference } = await response.json();
      const referenceNode = done.querySelector('[data-contact-reference]');
      if (referenceNode) referenceNode.textContent = reference ?? '';

      swapToDone(gsap, form, done);
    } catch (error) {
      // Never strand the enquiry because fetch failed. The form is a working
      // form; letting it submit normally is a real fallback, not a message.
      console.warn('SCBD: falling back to a normal submit.', error);
      form.submit();
    } finally {
      submit.disabled = false;
      if (label) label.textContent = original;
    }
  });

  // Clear a field's error the moment it is edited, rather than making the
  // visitor submit again to find out whether they fixed it.
  form.querySelectorAll('input, textarea, select').forEach((field) => {
    field.addEventListener('input', () => {
      field.removeAttribute('aria-invalid');
      field.closest('.scbd-field')?.querySelector('.scbd-field-error')?.remove();
    });
  });
}

/** Flip the panel from the form's own box, so the confirmation takes its place
 *  rather than appearing somewhere else on the page. */
function swapToDone(gsap, form, done) {
  const state = Flip.getState(form);

  form.hidden = true;
  done.hidden = false;

  Flip.from(state, {
    targets: done,
    duration: 0.55,
    ease: 'expo.out',
    absolute: true,
    onEnter: (elements) =>
      gsap.fromTo(elements, { opacity: 0 }, { opacity: 1, duration: 0.4 }),
  });

  revealDone(gsap, done);
  done.setAttribute('tabindex', '-1');
  done.focus();
}

function revealDone(gsap, done) {
  const mark = done.querySelector('.scbd-form-done-mark');

  if (mark) {
    gsap.fromTo(mark,
      { scale: 0.4, opacity: 0 },
      { scale: 1, opacity: 1, duration: 0.7, ease: 'back.out(2)' });
  }

  gsap.fromTo(done.querySelectorAll('.scbd-form-done-text, .scbd-form-done-ref'),
    { y: 16, opacity: 0 },
    { y: 0, opacity: 1, duration: 0.5, stagger: 0.08, ease: 'expo.out', delay: 0.12 });
}

function clearErrors(form) {
  form.querySelectorAll('.scbd-field-error').forEach((node) => node.remove());
  form.querySelectorAll('[aria-invalid]').forEach((node) => node.removeAttribute('aria-invalid'));
}

function showErrors(gsap, form, errors) {
  let first = null;

  Object.entries(errors).forEach(([name, messages]) => {
    const field = form.querySelector(`[name="${name}"]`);
    if (!field) return;

    field.setAttribute('aria-invalid', 'true');

    const note = document.createElement('span');
    note.className = 'scbd-field-error';
    note.textContent = Array.isArray(messages) ? messages[0] : String(messages);
    field.closest('.scbd-field')?.append(note);

    first ??= field;
  });

  if (!first) return;

  // A short shake on the first offender, then focus it. Focus alone is easy to
  // miss halfway down a long form.
  gsap.fromTo(first.closest('.scbd-field') ?? first,
    { x: -6 },
    { x: 0, duration: 0.5, ease: 'elastic.out(1, 0.35)' });

  first.focus();
}

