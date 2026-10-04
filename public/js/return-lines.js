/*
 * Return form helper: the products of a return are the products on the chosen
 * invoice, never the whole catalogue, and each line is limited to what may
 * still be returned (bought / sold minus what earlier returns took back).
 *
 *   const lines = ReturnLines.create({ sourceSelect, rowsBody });
 *   await lines.load(sourceId, excludeReturnId)   // when the invoice changes
 *   lines.attachRow(row, { product_id, qty })      // for every item row
 *
 * The invoice select carries data-lines-url with __ID__ where the id goes.
 */
(function () {
  const text = (key) => (typeof window.__t === 'function' ? window.__t(key) : key);
  const toast = (message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(message, 'fa-triangle-exclamation', 'var(--bad-600)');
    }
  };

  function create({ sourceSelect, rowsBody }) {
    let lines = [];
    let state = 'idle'; // idle | loading | ready | empty | blocked | failed
    let ticket = 0;

    const byProduct = (id) => lines.find((l) => String(l.product_id) === String(id));

    function placeholder() {
      switch (state) {
        case 'loading': return text('Loading...');
        case 'empty': return text('No products on this invoice');
        case 'blocked': return text('This invoice cannot be returned');
        case 'failed': return text('Could not load the products of this invoice.');
        case 'ready': return text('Select');
        default: return text('Select the invoice first');
      }
    }

    function hint(line) {
      if (!line) return '';
      return `${text('Max')} ${line.remaining} · ${text('On invoice')} ${line.original} · ${text('Returned')} ${line.returned}`;
    }

    function applyRow(row, wanted) {
      const select = row.querySelector('.item-product');
      const qty = row.querySelector('.item-qty');
      const hintEl = row.querySelector('.item-hint');
      const keep = wanted !== undefined ? wanted : select.value;

      select.innerHTML = '';
      const first = document.createElement('option');
      first.value = '';
      first.textContent = placeholder();
      if (state !== 'ready') first.dataset.status = '1';
      select.appendChild(first);

      lines.forEach((line) => {
        const option = document.createElement('option');
        option.value = String(line.product_id);
        option.textContent = `${line.code} — ${line.name}`;
        select.appendChild(option);
      });

      select.disabled = state !== 'ready';
      select.value = byProduct(keep) ? String(keep) : '';

      window.ProductPicker?.enhance(select);
      window.ProductPicker?.refresh(select);

      limitQty(row);
      if (hintEl) hintEl.classList.toggle('hidden', !hintEl.textContent);
    }

    function limitQty(row) {
      const select = row.querySelector('.item-product');
      const qty = row.querySelector('.item-qty');
      const hintEl = row.querySelector('.item-hint');
      const line = byProduct(select.value);

      if (line) {
        qty.max = String(line.remaining);
        if (qty.value !== '' && Number(qty.value) > line.remaining) qty.value = String(line.remaining);
        if (line.remaining === 0) qty.value = '';
      } else {
        qty.removeAttribute('max');
      }

      if (hintEl) {
        hintEl.textContent = hint(line);
        hintEl.classList.toggle('hidden', !line);
      }
    }

    function attachRow(row, values = {}) {
      const select = row.querySelector('.item-product');
      const qty = row.querySelector('.item-qty');

      select.addEventListener('change', () => limitQty(row));
      qty.addEventListener('input', () => {
        const max = Number(qty.max);
        if (qty.max !== '' && Number(qty.value) > max) qty.value = String(max);
      });

      applyRow(row, values.product_id !== undefined ? values.product_id : '');
      if (values.qty !== undefined) qty.value = values.qty;
    }

    function applyAll() {
      rowsBody.querySelectorAll('.item-row').forEach((row) => applyRow(row));
    }

    async function load(sourceId, excludeReturnId) {
      const mine = ++ticket;
      lines = [];

      if (!sourceId) {
        state = 'idle';
        applyAll();
        return;
      }

      state = 'loading';
      applyAll();

      const template = sourceSelect.dataset.linesUrl || '';
      let url = template.replace('__ID__', encodeURIComponent(sourceId));
      if (excludeReturnId) url += `${url.includes('?') ? '&' : '?'}exclude=${encodeURIComponent(excludeReturnId)}`;

      try {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error(String(response.status));
        const data = await response.json();
        if (mine !== ticket) return; // a newer invoice was chosen meanwhile

        lines = data.lines || [];
        if (data.returnable === false) {
          state = 'blocked';
          if (data.message) toast(data.message);
        } else {
          state = lines.length > 0 ? 'ready' : 'empty';
        }
      } catch (err) {
        if (mine !== ticket) return;
        state = 'failed';
        toast(text('Could not load the products of this invoice.'));
      }

      applyAll();
    }

    return { load, attachRow, applyAll, limitQty };
  }

  window.ReturnLines = { create };
})();
