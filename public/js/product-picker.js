/*
 * Product picker: turns a plain <select class="item-product"> into a box you
 * can type in to find a product by code or name.
 *
 * It also works for other lists (customer, salesman): the select can carry
 *   data-picker-placeholder="..."  text shown in the empty box
 *   data-picker-empty="..."        text shown when nothing matches
 * Without them the product texts are used. An option with an empty value
 * ("None") is not listed; clearing the box selects it.
 *
 * The <select> stays in the form as the real field (it is only hidden behind
 * the box), so what gets posted does not change. Call
 *   ProductPicker.enhance(select)   once, after the row's value is set
 *   ProductPicker.refresh(select)   after the options of the select changed
 *   ProductPicker.sync(select)      after select.value was changed by code
 */
(function () {
  const MAX_SHOWN = 50;
  const pickers = new WeakMap();

  const text = (key) => (typeof window.__t === 'function' ? window.__t(key) : key);
  const norm = (value) => String(value || '').toLowerCase();

  function enhance(select) {
    if (!select) return null;
    if (pickers.has(select)) return pickers.get(select);

    const wrapper = document.createElement('div');
    wrapper.className = 'product-picker relative';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    // The select keeps its place (so the browser's "required" check still works)
    // but is invisible and cannot be clicked.
    select.tabIndex = -1;
    select.style.cssText += ';position:absolute;inset:0;width:100%;height:100%;opacity:0;pointer-events:none;';

    const input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    // Same look as the select, but not its marker class: pages find the real field by .item-product.
    input.className = select.className.split(/\s+/).filter((c) => c !== 'item-product').join(' ');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    wrapper.insertBefore(input, select);

    // A label pointing at the select (or a failed "required" check on it) should land in the box.
    select.addEventListener('focus', () => input.focus());
    if (select.id) {
      document.querySelectorAll('label[for="' + select.id + '"]').forEach((label) => {
        label.addEventListener('click', (e) => { e.preventDefault(); input.focus(); });
      });
    }

    const list = document.createElement('div');
    list.className = 'product-picker-list';
    list.setAttribute('role', 'listbox');
    list.style.cssText = 'position:fixed;z-index:80;max-height:14rem;overflow-y:auto;background:#fff;border:1px solid #e5e7eb;border-radius:.6rem;box-shadow:0 8px 24px rgba(0,0,0,.12);font-size:.75rem;';

    let open = false;
    let shown = [];
    let active = -1;

    const options = () => [...select.options]
      .filter((o) => o.value !== '')
      .map((o) => ({ value: o.value, label: o.textContent.trim() }));

    const selectedLabel = () => {
      const option = select.options[select.selectedIndex];
      return option && option.value !== '' ? option.textContent.trim() : '';
    };

    function syncInput() {
      input.value = selectedLabel();
      input.disabled = select.disabled;
      // A first option marked data-status carries a message ("Select the invoice first"); otherwise invite a search.
      const first = select.options[0];
      input.placeholder = first && first.dataset.status
        ? first.textContent.trim()
        : (select.dataset.pickerPlaceholder || text('Search product by code or name'));
    }

    function filter(query) {
      const words = norm(query).split(/\s+/).filter(Boolean);
      const found = options().filter((o) => {
        const label = norm(o.label);
        return words.every((w) => label.includes(w));
      });
      const q = norm(query).trim();
      // Code that starts with what was typed first, then name, then anything else.
      const rank = (o) => (norm(o.label).startsWith(q) ? 0 : norm(o.label).split(' — ').slice(1).join(' ').startsWith(q) ? 1 : 2);
      return q === '' ? found : found.sort((a, b) => rank(a) - rank(b));
    }

    function place() {
      const rect = input.getBoundingClientRect();
      const below = window.innerHeight - rect.bottom;
      list.style.left = `${rect.left}px`;
      list.style.width = `${Math.max(rect.width, 220)}px`;
      if (below < 160 && rect.top > below) {
        list.style.top = '';
        list.style.bottom = `${window.innerHeight - rect.top + 2}px`;
      } else {
        list.style.bottom = '';
        list.style.top = `${rect.bottom + 2}px`;
      }
    }

    function render() {
      const all = filter(input.dataset.query ?? '');
      shown = all.slice(0, MAX_SHOWN);
      list.innerHTML = '';

      if (shown.length === 0) {
        const empty = document.createElement('div');
        empty.style.cssText = 'padding:.5rem .75rem;color:#9ca3af;';
        empty.textContent = select.dataset.pickerEmpty || text('No product found');
        list.appendChild(empty);
        active = -1;
        return;
      }

      shown.forEach((o, i) => {
        const item = document.createElement('div');
        item.setAttribute('role', 'option');
        item.dataset.value = o.value;
        item.textContent = o.label;
        item.style.cssText = 'padding:.4rem .75rem;cursor:pointer;';
        item.addEventListener('mousedown', (e) => {
          e.preventDefault(); // keep the focus in the box so blur does not close the list first
          choose(i);
        });
        item.addEventListener('mousemove', () => highlight(i));
        list.appendChild(item);
      });

      if (all.length > shown.length) {
        const more = document.createElement('div');
        more.style.cssText = 'padding:.4rem .75rem;color:#9ca3af;';
        more.textContent = text('Keep typing to narrow the list');
        list.appendChild(more);
      }

      highlight(Math.min(Math.max(active, 0), shown.length - 1));
    }

    function highlight(index) {
      active = index;
      [...list.children].forEach((el, i) => {
        el.style.background = i === active ? 'var(--surface, #f3f4f6)' : '';
        if (i === active && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
      });
    }

    function openList() {
      if (select.disabled || open) return;
      open = true;
      input.setAttribute('aria-expanded', 'true');
      document.body.appendChild(list);
      render();
      place();
      window.addEventListener('resize', place);
      window.addEventListener('scroll', place, true);
    }

    function closeList() {
      if (!open) return;
      open = false;
      input.setAttribute('aria-expanded', 'false');
      list.remove();
      window.removeEventListener('resize', place);
      window.removeEventListener('scroll', place, true);
    }

    function choose(index) {
      const picked = shown[index];
      if (!picked) return;
      if (select.value !== picked.value) {
        select.value = picked.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      syncInput();
      delete input.dataset.query;
      closeList();
    }

    input.addEventListener('focus', () => {
      input.select();
      delete input.dataset.query;
      openList();
    });

    input.addEventListener('input', () => {
      input.dataset.query = input.value;
      if (!open) openList();
      active = 0;
      render();
      place();
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!open) openList();
        if (shown.length > 0) {
          highlight((active + (e.key === 'ArrowDown' ? 1 : -1) + shown.length) % shown.length);
        }
      } else if (e.key === 'Enter') {
        if (open) {
          e.preventDefault(); // Enter picks a product; it must not submit the form
          if (active >= 0) choose(active);
        }
      } else if (e.key === 'Escape' && open) {
        e.stopPropagation(); // close the list, not the dialog behind it
        closeList();
        syncInput();
      }
    });

    input.addEventListener('blur', () => {
      closeList();
      // Emptied the box: clear the product. Otherwise put the chosen product's name back.
      if (input.value.trim() === '' && select.value !== '') {
        select.value = '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      delete input.dataset.query;
      syncInput();
    });

    const picker = {
      sync: syncInput,
      refresh() {
        syncInput();
        if (open) { render(); place(); }
      },
    };

    pickers.set(select, picker);
    syncInput();

    return picker;
  }

  window.ProductPicker = {
    enhance,
    sync: (select) => pickers.get(select)?.sync(),
    refresh: (select) => pickers.get(select)?.refresh(),
  };
})();
