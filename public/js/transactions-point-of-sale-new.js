/*
 * Checkout terminal (Point of Sales New).
 *
 *  - The cashier finds products by typing a name (live search, 300 ms debounce,
 *    no Enter needed); a result shows stock and the reference price and goes
 *    into the cart as soon as it is tapped.
 *  - A product may be in the cart once at a price and once as a free item (Rp0).
 *    The server enforces the same rule; this file only makes it visible early.
 *  - Credit sales are for registered customers only.
 */
(() => {
  'use strict';

  const form = document.getElementById('posNewForm');
  if (!form) return;

  const labels = window.POS_LABELS || {};
  const searchUrl = window.POS_SEARCH_URL;
  const DEBOUNCE_MS = 300;

  const rowsBody = document.getElementById('itemRows');
  const rowTemplate = document.getElementById('itemRowTemplate');
  const emptyMessage = document.getElementById('noItemsMessage');
  const grandTotalEl = document.getElementById('grandTotal');
  const cartCountEl = document.getElementById('cartCount');
  const warehouseSelect = document.getElementById('warehouse_id');
  const searchInput = document.getElementById('productSearch');
  const resultsEl = document.getElementById('searchResults');
  const saleDateInput = document.getElementById('sale_date');
  const customerSelect = document.getElementById('customer_id');
  const customerLabel = document.getElementById('customerLabel');
  const creditNote = document.getElementById('creditNote');
  const methodField = document.getElementById('methodField');
  const methodSelect = document.getElementById('payment_method_id');
  const submitBtn = document.getElementById('submitBtn');
  const submitLabel = document.getElementById('submitLabel');

  let rowIndex = 0;

  // ---- Helpers --------------------------------------------------------------

  /** Label with ":name" placeholders filled in, like Laravel's __(). */
  function t(key, vars = {}) {
    let text = labels[key] ?? key;
    Object.entries(vars).forEach(([name, value]) => {
      text = text.split(`:${name}`).join(String(value));
    });
    return text;
  }

  function formatNumber(value) {
    return (Number.isFinite(value) ? value : 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function toast(message) {
    if (typeof showToast === 'function') {
      showToast(message, 'fa-triangle-exclamation', 'var(--bad-600)');
    }
  }

  const allRows = () => Array.from(rowsBody.querySelectorAll('.item-row'));
  const productIdOf = (row) => row.querySelector('.item-product').value;
  const qtyOf = (row) => parseInt(row.querySelector('.item-qty').value, 10) || 0;

  /** Typed price as a number, or null while the box is empty. */
  function priceOf(row) {
    const raw = row.querySelector('.item-price').value;
    if (raw === '') return null;
    const value = parseFloat(raw);
    return Number.isFinite(value) ? value : null;
  }

  /** A line at Rp0 is a free item. */
  const isFree = (row) => priceOf(row) === 0;

  // ---- Reference price (harga patokan) ---------------------------------------

  /** Newest dated price on or before the date (prices come newest first). */
  function referencePrice(row, date) {
    const prices = JSON.parse(row.dataset.prices || '[]');
    const day = date || new Date().toISOString().slice(0, 10);
    const hit = prices.find((entry) => entry.date <= day);
    return hit ? hit.amount : null;
  }

  function updatePriceHint(row) {
    const hint = row.querySelector('.item-price-hint');
    if (isFree(row)) {
      hint.textContent = '';
      return;
    }
    const ref = referencePrice(row, saleDateInput.value);
    if (ref === null) {
      hint.textContent = t('no_reference_price');
      hint.style.color = '';
      return;
    }
    const current = priceOf(row);
    const changed = current !== null && Math.abs(current - ref) > 0.004;
    hint.textContent = `${t('reference_price')}: ${formatNumber(ref)}${changed ? ' · ' + t('price_changed') : ''}`;
    hint.style.color = changed ? 'var(--bad-600)' : '';
  }

  /** Fill the price box from the reference price unless the cashier owns the price. */
  function applyReferencePrice(row) {
    if (row.dataset.manualPrice !== '1') {
      const ref = referencePrice(row, saleDateInput.value);
      row.querySelector('.item-price').value = ref === null ? '' : ref;
    }
  }

  // ---- Cart -------------------------------------------------------------------

  function flash(row) {
    row.classList.remove('pos-flash');
    // Force a reflow so the animation restarts when the same row is hit twice.
    void row.offsetWidth;
    row.classList.add('pos-flash');
    setTimeout(() => row.classList.remove('pos-flash'), 700);
  }

  function setRowError(row, message) {
    const box = row.querySelector('.item-error');
    box.textContent = message;
    box.classList.toggle('hidden', message === '');
    row.classList.toggle('pos-row-bad', message !== '');
  }

  /** Put the latest stock figure of a product on every cart row of that product. */
  function updateStock(productId, stock, stockLabel) {
    allRows()
      .filter((row) => productIdOf(row) === String(productId))
      .forEach((row) => {
        row.dataset.stock = String(stock);
        row.dataset.stockLabel = stockLabel;
      });
  }

  function createRow(product, { qty = 1, price = null, free = false } = {}) {
    const html = rowTemplate.innerHTML.split('__INDEX__').join(String(rowIndex));
    rowIndex += 1;

    const holder = document.createElement('tbody');
    holder.innerHTML = html.trim();
    const row = holder.firstElementChild;

    row.dataset.prices = JSON.stringify(product.prices || []);
    row.dataset.stock = String(product.stock ?? 0);
    row.dataset.stockLabel = product.stock_label || String(product.stock ?? 0);
    row.dataset.unit = product.unit || 'pcs';

    row.querySelector('.item-product').value = product.id;
    row.querySelector('.item-name').textContent = product.name;
    row.querySelector('.item-code').textContent = product.code;
    row.querySelector('.item-qty').value = qty;

    const priceInput = row.querySelector('.item-price');
    if (free) {
      priceInput.value = '0';
      row.dataset.manualPrice = '1';
    } else if (price !== null) {
      // Restored from a refused submission: keep the cashier's price when it
      // differs from the reference price.
      priceInput.value = price;
      const ref = referencePrice(row, saleDateInput.value);
      if (ref === null || Math.abs(ref - price) > 0.004) row.dataset.manualPrice = '1';
    } else {
      applyReferencePrice(row);
    }

    row.querySelector('.item-qty').addEventListener('input', refreshCart);
    row.querySelector('.qty-minus').addEventListener('click', () => {
      const input = row.querySelector('.item-qty');
      input.value = Math.max(1, qtyOf(row) - 1);
      refreshCart();
    });
    row.querySelector('.qty-plus').addEventListener('click', () => {
      const input = row.querySelector('.item-qty');
      input.value = qtyOf(row) + 1;
      refreshCart();
    });
    priceInput.addEventListener('input', () => {
      row.dataset.manualPrice = '1'; // the cashier owns the price from here on
      refreshCart();
    });
    row.querySelector('.add-free-btn').addEventListener('click', () => addFreeLine(row));
    row.querySelector('.remove-item-btn').addEventListener('click', () => {
      row.remove();
      refreshCart();
    });

    rowsBody.appendChild(row);
    return row;
  }

  /**
   * A product tapped in the search: one more of the paid line when the product
   * is already in the cart, otherwise a new line at the reference price.
   */
  function addProduct(product) {
    updateStock(product.id, product.stock, product.stock_label);

    const paidRow = allRows().find((row) => productIdOf(row) === String(product.id) && !isFree(row));
    let row;
    if (paidRow) {
      paidRow.querySelector('.item-qty').value = qtyOf(paidRow) + 1;
      row = paidRow;
    } else {
      row = createRow(product);
    }

    refreshCart();
    flash(row);
  }

  /** The free (Rp0) twin of a paid line. */
  function addFreeLine(paidRow) {
    const productId = productIdOf(paidRow);
    const existing = allRows().find((row) => productIdOf(row) === productId && isFree(row));
    if (existing) {
      existing.querySelector('.item-qty').focus();
      return;
    }

    const row = createRow(
      {
        id: productId,
        code: paidRow.querySelector('.item-code').textContent,
        name: paidRow.querySelector('.item-name').textContent,
        prices: JSON.parse(paidRow.dataset.prices || '[]'),
        stock: parseInt(paidRow.dataset.stock, 10) || 0,
        stock_label: paidRow.dataset.stockLabel,
        unit: paidRow.dataset.unit,
      },
      { qty: 1, free: true },
    );
    paidRow.after(row);
    refreshCart();
    flash(row);
    row.querySelector('.item-qty').focus();
  }

  function clearCart() {
    allRows().forEach((row) => row.remove());
    refreshCart();
  }

  /**
   * Recompute totals and mark rows that break a rule. Returns how many rows
   * have a problem (the form is not sent while there are any).
   */
  function refreshCart() {
    const rows = allRows();
    const byProduct = new Map();
    rows.forEach((row) => {
      const id = productIdOf(row);
      if (!byProduct.has(id)) byProduct.set(id, []);
      byProduct.get(id).push(row);
    });

    let total = 0;
    rows.forEach((row) => {
      const lineTotal = qtyOf(row) * (priceOf(row) ?? 0);
      total += lineTotal;
      row.querySelector('.item-line-total').textContent = formatNumber(lineTotal);
      row.querySelector('.item-free-badge').classList.toggle('hidden', !isFree(row));
      updatePriceHint(row);
    });

    let problems = 0;
    byProduct.forEach((list) => {
      const paid = list.filter((row) => !isFree(row));
      const free = list.filter((row) => isFree(row));
      const stock = parseInt(list[0].dataset.stock, 10) || 0;
      const stockLabel = list[0].dataset.stockLabel || String(stock);
      // Paid and free lines of one product draw from the same stock.
      const totalQty = list.reduce((sum, row) => sum + qtyOf(row), 0);

      list.forEach((row) => {
        const rowIsFree = isFree(row);
        let message = '';
        if ((rowIsFree ? free : paid).length > 1) {
          message = t(rowIsFree ? 'duplicate_free_row' : 'duplicate_paid_row');
        } else if (totalQty > stock) {
          message = t('stock_short', { stock: stockLabel });
        }
        setRowError(row, message);
        if (message !== '') problems += 1;

        row.querySelector('.item-stock').textContent = `${t('stock')}: ${stockLabel}`;
        // Offer the free twin only on a lone paid line.
        row.querySelector('.add-free-btn').classList.toggle('hidden', rowIsFree || free.length > 0 || paid.length !== 1);
      });
    });

    grandTotalEl.textContent = formatNumber(total);
    emptyMessage.classList.toggle('hidden', rows.length > 0);
    cartCountEl.textContent = rows.length > 0 ? t('lines', { count: rows.length }) : '';

    return problems;
  }

  // ---- Product search -----------------------------------------------------------

  let debounceTimer = null;
  let activeRequest = null;
  let results = [];
  let activeIndex = -1;

  function hideResults() {
    results = [];
    activeIndex = -1;
    resultsEl.classList.add('hidden');
    resultsEl.replaceChildren();
  }

  function showStatus(message) {
    results = [];
    activeIndex = -1;
    const status = document.createElement('div');
    status.className = 'pos-status';
    status.textContent = message;
    resultsEl.replaceChildren(status);
    resultsEl.classList.remove('hidden');
  }

  function cancelSearch() {
    clearTimeout(debounceTimer);
    if (activeRequest) {
      activeRequest.abort();
      activeRequest = null;
    }
  }

  function setActive(index) {
    activeIndex = index;
    Array.from(resultsEl.querySelectorAll('.pos-result')).forEach((el, i) => {
      el.classList.toggle('is-active', i === index);
      if (i === index && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
    });
  }

  function renderResults(products) {
    if (products.length === 0) {
      showStatus(t('no_results'));
      return;
    }

    const day = saleDateInput.value || new Date().toISOString().slice(0, 10);
    results = products;
    activeIndex = -1;

    const buttons = products.map((product, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'pos-result' + (product.stock <= 0 ? ' is-out' : '');
      button.setAttribute('role', 'option');

      const left = document.createElement('div');
      const name = document.createElement('div');
      name.className = 'pos-result-name';
      name.textContent = product.name;
      const sub = document.createElement('div');
      sub.className = 'pos-result-sub';
      sub.textContent = `${product.code} · ${product.unit}`;
      left.append(name, sub);

      const right = document.createElement('div');
      right.className = 'pos-result-side';
      const stock = document.createElement('div');
      stock.className = product.stock <= 0 ? 'pos-stock-out' : 'pos-stock-ok';
      stock.textContent = `${t('stock')}: ${product.stock_label}`;
      const price = document.createElement('div');
      const hit = (product.prices || []).find((entry) => entry.date <= day);
      price.textContent = hit ? formatNumber(hit.amount) : '—';
      right.append(stock, price);

      button.append(left, right);
      button.addEventListener('click', () => pick(index));
      return button;
    });

    resultsEl.replaceChildren(...buttons);
    resultsEl.classList.remove('hidden');
  }

  function pick(index) {
    const product = results[index];
    if (!product) return;

    if (product.stock <= 0) {
      toast(t('out_of_stock', { product: product.name }));
      return;
    }

    addProduct(product);
    searchInput.value = '';
    cancelSearch();
    hideResults();
    searchInput.focus();
  }

  async function runSearch(term) {
    if (!warehouseSelect.value) {
      showStatus(t('choose_warehouse'));
      return;
    }

    cancelSearch();
    const controller = new AbortController();
    activeRequest = controller;
    showStatus(t('searching'));

    try {
      const url = new URL(searchUrl, window.location.origin);
      url.searchParams.set('q', term);
      url.searchParams.set('warehouse_id', warehouseSelect.value);

      const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: controller.signal,
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const body = await response.json();
      if (activeRequest !== controller) return; // a newer search took over
      activeRequest = null;
      renderResults(body.data || []);
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (activeRequest === controller) activeRequest = null;
      showStatus(t('search_failed'));
    }
  }

  searchInput.addEventListener('input', () => {
    cancelSearch();
    const term = searchInput.value.trim();
    if (term === '') {
      hideResults();
      return;
    }
    debounceTimer = setTimeout(() => runSearch(term), DEBOUNCE_MS);
  });

  searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      // Enter must never send the sale; it only picks the highlighted (or only) match.
      event.preventDefault();
      if (results.length === 0) return;
      pick(activeIndex >= 0 ? activeIndex : results.length === 1 ? 0 : -1);
    } else if (event.key === 'ArrowDown' && results.length > 0) {
      event.preventDefault();
      setActive((activeIndex + 1) % results.length);
    } else if (event.key === 'ArrowUp' && results.length > 0) {
      event.preventDefault();
      setActive((activeIndex - 1 + results.length) % results.length);
    } else if (event.key === 'Escape') {
      cancelSearch();
      hideResults();
    }
  });

  document.addEventListener('click', (event) => {
    if (!resultsEl.contains(event.target) && event.target !== searchInput) hideResults();
  });

  // ---- Warehouse ----------------------------------------------------------------

  let currentWarehouse = warehouseSelect.value;

  function syncSearchState() {
    const ready = warehouseSelect.value !== '';
    searchInput.disabled = !ready;
    searchInput.placeholder = ready ? t('search_placeholder') : t('choose_warehouse');
  }

  warehouseSelect.addEventListener('change', () => {
    // Stock in the cart belongs to the warehouse it was read from.
    if (allRows().length > 0 && !window.confirm(t('confirm_clear_cart'))) {
      warehouseSelect.value = currentWarehouse;
      return;
    }
    cancelSearch();
    hideResults();
    searchInput.value = '';
    clearCart();
    currentWarehouse = warehouseSelect.value;
    syncSearchState();
    if (currentWarehouse !== '') searchInput.focus();
  });

  // ---- Sale date, payment type ----------------------------------------------------

  // A new sale date can change which price applies; refresh rows whose price the cashier did not edit.
  saleDateInput.addEventListener('change', () => {
    allRows().forEach(applyReferencePrice);
    refreshCart();
  });

  function syncPayment() {
    const checked = form.querySelector('input[name="payment_type"]:checked');
    const credit = !!checked && checked.value === 'credit';
    // A receivable needs a registered customer.
    customerSelect.required = credit;
    customerLabel.textContent = credit ? customerLabel.dataset.credit : customerLabel.dataset.cash;
    creditNote.classList.toggle('hidden', !credit);
    // The method (Tunai, Transfer, QRIS) only applies to a sale paid right away; a disabled field is not submitted.
    if (methodField && methodSelect) {
      methodField.classList.toggle('hidden', credit);
      methodSelect.disabled = credit;
    }
  }

  form.querySelectorAll('input[name="payment_type"]').forEach((radio) => radio.addEventListener('change', syncPayment));

  // ---- Submit ------------------------------------------------------------------------

  form.addEventListener('submit', (event) => {
    if (allRows().length === 0) {
      event.preventDefault();
      toast(t('cart_empty_toast'));
      return;
    }

    if (refreshCart() > 0) {
      event.preventDefault();
      toast(t('fix_cart'));
      const bad = rowsBody.querySelector('.pos-row-bad');
      if (bad && bad.scrollIntoView) bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }

    // One click, one sale: a double tap must not post the same invoice twice.
    submitBtn.disabled = true;
    submitLabel.textContent = t('processing');
  });

  // Coming back with the browser's back button must not leave the button stuck.
  window.addEventListener('pageshow', () => {
    submitBtn.disabled = false;
    submitLabel.textContent = t('complete_transaction');
  });

  // ---- Start -----------------------------------------------------------------------------

  (window.POS_CART_SEED || []).forEach((item) => {
    createRow(item, { qty: item.qty, price: item.price, free: item.price === 0 });
  });

  syncSearchState();
  syncPayment();
  refreshCart();
})();
