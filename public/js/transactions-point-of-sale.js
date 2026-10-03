// Add / edit point of sale transaction modal
const posModal = document.getElementById('posModal');
const posForm = document.getElementById('posForm');
const posFormMethod = document.getElementById('posFormMethod');
const posModalTitle = document.getElementById('posModalTitle');

const invoiceNumberInput = document.getElementById('invoice_number');
const saleDateInput = document.getElementById('sale_date');
const posCustomerIdInput = document.getElementById('customer_id');
const salesmanIdInput = document.getElementById('salesman_id');
const driverNameInput = document.getElementById('driver_name');
const posWarehouseIdInput = document.getElementById('warehouse_id');

const itemRowsBody = document.getElementById('itemRows');
const itemRowTemplate = document.getElementById('itemRowTemplate');
const noItemsMessage = document.getElementById('noItemsMessage');
const grandTotalEl = document.getElementById('grandTotal');

let rowIndex = 0;

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

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

function addItemRow(values = {}) {
  const html = itemRowTemplate.innerHTML.replaceAll('__INDEX__', String(rowIndex));
  rowIndex += 1;

  const wrapper = document.createElement('tbody');
  wrapper.innerHTML = html.trim();
  const row = wrapper.firstElementChild;
  itemRowsBody.appendChild(row);

  if (values.product_id) row.querySelector('.item-product').value = values.product_id;
  if (values.qty !== undefined) row.querySelector('.item-qty').value = values.qty;
  if (values.price !== undefined) row.querySelector('.item-price').value = values.price;

  row.querySelector('.item-qty').addEventListener('input', () => recalcRow(row));
  row.querySelector('.item-price').addEventListener('input', () => recalcRow(row));
  row.querySelector('.remove-item-btn').addEventListener('click', () => {
    row.remove();
    recalcGrandTotal();
  });

  recalcRow(row);
}

function resetItemRows() {
  itemRowsBody.innerHTML = '';
  rowIndex = 0;
  recalcGrandTotal();
}

document.getElementById('addItemRowBtn')?.addEventListener('click', () => addItemRow());

// Open "Add Transaction"
document.getElementById('addPointOfSaleBtn')?.addEventListener('click', (e) => {
  posForm.reset();
  posForm.action = e.currentTarget.dataset.action;
  posFormMethod.innerHTML = '';
  posModalTitle.textContent = __t('Add Transaction');
  resetItemRows();
  addItemRow();
  openModal(posModal);
  invoiceNumberInput?.focus();
});

// Open "Edit Transaction" for each row
document.querySelectorAll('.edit-pos-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    posForm.reset();
    posForm.action = btn.dataset.action;
    posFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    posModalTitle.textContent = __t('Edit Transaction');

    invoiceNumberInput.value = btn.dataset.invoiceNumber || '';
    saleDateInput.value = btn.dataset.saleDate || '';
    posCustomerIdInput.value = btn.dataset.customerId || '';
    salesmanIdInput.value = btn.dataset.salesmanId || '';
    driverNameInput.value = btn.dataset.driverName || '';
    posWarehouseIdInput.value = btn.dataset.warehouseId || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(posModal);
    invoiceNumberInput?.focus();
  });
});

// Transaction form must have at least one line item.
posForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-pos-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    deleteForm.action = btn.dataset.action;
    deleteModalText.textContent = `"${btn.dataset.name}" will be permanently removed. This action cannot be undone.`;
    openModal(deleteModal);
  });
});

// Shared close handlers: close button, backdrop click, Escape key
document.querySelectorAll('.modal-close').forEach((btn) => {
  btn.addEventListener('click', () => {
    closeModal(btn.closest('.modal-overlay'));
  });
});

[posModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [posModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
