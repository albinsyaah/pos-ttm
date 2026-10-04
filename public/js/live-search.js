// Live search for every list page: a GET form marked data-live-search="auto" filters while the
// user types (300 ms pause), with no Enter or search button. Selects and date fields in the same
// form apply as soon as they change. Pages marked data-live-search="custom" run their own script
// and are left alone, so nothing fires twice.
(function () {
  const DELAY = 300;
  const FOCUS_KEY = 'liveSearchFocus';

  function remember(form, input) {
    try {
      sessionStorage.setItem(FOCUS_KEY, `${location.pathname}|${input.name}`);
    } catch (e) {
      // Private mode or storage disabled: the search still works, the caret is just not restored.
    }
  }

  function restoreFocus(form) {
    let stored = null;
    try {
      stored = sessionStorage.getItem(FOCUS_KEY);
      sessionStorage.removeItem(FOCUS_KEY);
    } catch (e) {
      return;
    }
    if (!stored) return;

    const [path, name] = stored.split('|');
    if (path !== location.pathname) return;

    const input = form.querySelector(`input[type="search"][name="${name}"]`);
    if (!input) return;
    input.focus();
    const end = input.value.length;
    try {
      input.setSelectionRange(end, end);
    } catch (e) {
      // type="search" does not always allow a selection range; focus alone is fine.
    }
  }

  function enhance(form) {
    const searchInputs = form.querySelectorAll('input[type="search"][name]');
    if (!searchInputs.length) return;

    let timer = null;
    let composing = false;
    let lastValue = new Map();
    searchInputs.forEach((input) => lastValue.set(input, input.value));

    function submit(input) {
      clearTimeout(timer);
      if (input) remember(form, input);
      form.classList.add('opacity-60');
      form.submit();
    }

    searchInputs.forEach((input) => {
      // Do not search half-composed text (Japanese, Chinese, Korean input methods).
      input.addEventListener('compositionstart', () => { composing = true; });
      input.addEventListener('compositionend', () => { composing = false; });

      input.addEventListener('input', () => {
        if (composing) return;
        if (input.value === lastValue.get(input)) return;
        lastValue.set(input, input.value);
        clearTimeout(timer);
        timer = setTimeout(() => submit(input), DELAY);
      });
    });

    // Filters next to the search box apply immediately.
    form.querySelectorAll('select, input[type="date"], input[type="month"]').forEach((el) => {
      el.addEventListener('change', () => submit(null));
    });

    // Enter still works, and must not fire the pending debounced submit a second time.
    form.addEventListener('submit', () => clearTimeout(timer));

    restoreFocus(form);
  }

  document.querySelectorAll('form[data-live-search="auto"]').forEach(enhance);
})();
