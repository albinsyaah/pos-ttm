/*
 * Print dialog shown after a sale on the checkout terminals.
 *
 *  - Cashier terminal: one question, yes (small receipt) or no.
 *  - Head cashier terminal: large receipt only, small receipt only, delivery note only,
 *    large receipt with delivery note, or none.
 *
 * The dialog carries the URLs of the documents the user may print (data-small, data-large,
 * data-note); a document without a URL is never offered. Each document opens in its own tab.
 * If the browser blocks a tab, the dialog stays open with a link for each blocked document.
 */
(function () {
  const dialog = document.getElementById('printDialog');
  if (!dialog) return;

  const blockedEl = document.getElementById('printBlocked');
  const urls = { small: dialog.dataset.small, large: dialog.dataset.large, note: dialog.dataset.note };
  const labels = { small: 'small', large: 'large', note: 'note' };
  const names = { small: '', large: '', note: '' };
  dialog.querySelectorAll('[data-print]').forEach((b) => {
    if (labels[b.dataset.print]) names[b.dataset.print] = b.textContent.trim();
  });

  function close() {
    dialog.remove();
    document.removeEventListener('keydown', onKey);
    const search = document.getElementById('productSearch');
    if (search && !search.disabled) search.focus();
  }

  function onKey(event) {
    if (event.key === 'Escape') close(); // Escape means "no"
  }

  function showBlocked(kinds) {
    blockedEl.textContent = blockedEl.dataset.text + ' ';
    kinds.forEach((kind) => {
      const a = document.createElement('a');
      a.href = urls[kind];
      a.target = '_blank';
      a.rel = 'noopener';
      a.className = 'underline text-[var(--brand-600)] mr-2';
      a.textContent = names[kind] || kind;
      a.addEventListener('click', () => a.remove());
      blockedEl.appendChild(a);
    });
    blockedEl.classList.remove('hidden');
  }

  function print(choice) {
    const kinds = choice === 'none' ? [] : choice.split('+').filter((k) => urls[k]);
    const blocked = [];
    kinds.forEach((kind) => {
      // No "noopener" here: it makes window.open return null, and a blocked tab could not be noticed.
      if (!window.open(urls[kind], '_blank')) blocked.push(kind);
    });
    if (blocked.length > 0) {
      showBlocked(blocked);
      return;
    }
    close();
  }

  dialog.addEventListener('click', (event) => {
    const button = event.target.closest('[data-print]');
    if (button) print(button.dataset.print);
  });

  document.addEventListener('keydown', onKey);

  const first = dialog.querySelector('[data-print]');
  if (first) first.focus();
})();
