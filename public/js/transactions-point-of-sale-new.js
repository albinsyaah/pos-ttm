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

function addItemRow() {
  const html = itemRowTemplate.innerHTML.replaceAll('__INDEX__', String(rowIndex));
  rowIndex += 1;

  const wrapper = document.createElement('tbody');
  wrapper.innerHTML = html.trim();
  const row = wrapper.firstElementChild;
  itemRowsBody.appendChild(row);

  row.querySelector('.item-qty').addEventListener('input', () => recalcRow(row));
  row.querySelector('.item-price').addEventListener('input', () => recalcRow(row));
  row.querySelector('.remove-item-btn').addEventListener('click', () => {
    row.remove();
    recalcGrandTotal();
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();
  });

  recalcRow(row);
}

document.getElementById('addItemRowBtn')?.addEventListener('click', () => addItemRow());

// Start every visit to the terminal with one empty cart row ready to go.
addItemRow();

posNewForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one item to the cart.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});
