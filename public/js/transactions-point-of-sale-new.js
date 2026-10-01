const posNewForm = document.getElementById('posNewForm');

const itemRowsBody = document.getElementById('itemRows');
const itemRowTemplate = document.getElementById('itemRowTemplate');
const noItemsMessage = document.getElementById('noItemsMessage');
const grandTotalEl = document.getElementById('grandTotal');

let rowIndex = 0;

function formatNumber(value) {
  return (Number.isFinite(value) ? value : 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function recalcRow(row) {
  const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
  const price = parseFloat(row.querySelector('.item-price').value) || 0;
  row.querySelector('.item-line-total').textContent = formatNumber(qty * price);
  recalcGrandTotal();
}

function recalcGrandTotal() {
  let total = 0;
  itemRowsBody.querySelectorAll('.item-row').forEach((row) => {
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    total += qty * price;
  });
  grandTotalEl.textContent = formatNumber(total);
  noItemsMessage.classList.toggle('hidden', itemRowsBody.querySelectorAll('.item-row').length > 0);
}

// ---- Reference price (harga patokan) -------------------------------------

const saleDateInput = document.getElementById('sale_date');
const priceBook = window.POS_PRICE_BOOK || {};
const priceLabels = window.POS_PRICE_LABELS || {};

// Newest dated price whose date is on or before the sale date (rows are
// sorted newest first; ISO dates compare correctly as strings).
function referencePrice(productId, date) {
  const rows = priceBook[productId] || [];
  const day = date || new Date().toISOString().slice(0, 10);
  const hit = rows.find((r) => r.date <= day);
  return hit ? hit.amount : null;
}

function updatePriceHint(row) {
  const hint = row.querySelector('.item-price-hint');
  const productId = row.querySelector('.item-product').value;
  if (!productId) {
    hint.textContent = '';
    return;
  }
  const ref = referencePrice(productId, saleDateInput?.value);
  if (ref === null) {
    hint.textContent = priceLabels.none || '';
    hint.style.color = '';
    return;
  }
  const current = parseFloat(row.querySelector('.item-price').value);
  const changed = Number.isFinite(current) && Math.abs(current - ref) > 0.004;
  hint.textContent = `${priceLabels.reference || 'Reference'}: ${formatNumber(ref)}${changed ? ' · ' + (priceLabels.changed || 'price changed') : ''}`;
  hint.style.color = changed ? 'var(--bad-600)' : '';
}

// Put the reference price in the price box (unless the cashier typed their own).
function applyReferencePrice(row) {
  const priceInput = row.querySelector('.item-price');
  const productId = row.querySelector('.item-product').value;
  if (productId && row.dataset.manualPrice !== '1') {
    const ref = referencePrice(productId, saleDateInput?.value);
    priceInput.value = ref === null ? '' : ref;
  }
  updatePriceHint(row);
  recalcRow(row);
}

function addItemRow() {
  const html = itemRowTemplate.innerHTML.replaceAll('__INDEX__', String(rowIndex));
  rowIndex += 1;

  const wrapper = document.createElement('tbody');
  wrapper.innerHTML = html.trim();
  const row = wrapper.firstElementChild;
  itemRowsBody.appendChild(row);

  row.querySelector('.item-qty').addEventListener('input', () => recalcRow(row));
  row.querySelector('.item-price').addEventListener('input', () => {
    row.dataset.manualPrice = '1'; // the cashier owns the price from here on
    updatePriceHint(row);
    recalcRow(row);
  });
  row.querySelector('.item-product').addEventListener('change', () => {
    // A different product starts over from its own reference price.
    delete row.dataset.manualPrice;
    applyReferencePrice(row);
  });
  row.querySelector('.remove-item-btn').addEventListener('click', () => {
    row.remove();
    recalcGrandTotal();
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();
  });

  recalcRow(row);
}

// A new sale date can change which price applies; refresh rows the cashier has not edited.
saleDateInput?.addEventListener('change', () => {
  itemRowsBody.querySelectorAll('.item-row').forEach((row) => applyReferencePrice(row));
});

document.getElementById('addItemRowBtn')?.addEventListener('click', () => addItemRow());

// Start every visit to the terminal with one empty cart row ready to go.
addItemRow();

posNewForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one item to the cart.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});
