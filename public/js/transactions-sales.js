// Add / edit sale modal
const saleModal = document.getElementById('saleModal');
const saleForm = document.getElementById('saleForm');
const saleFormMethod = document.getElementById('saleFormMethod');
const saleModalTitle = document.getElementById('saleModalTitle');

const invoiceNumberInput = document.getElementById('invoice_number');
const saleDateInput = document.getElementById('sale_date');
const salesOrderIdInput = document.getElementById('sales_order_id');
const saleCustomerIdInput = document.getElementById('customer_id');
const salesmanIdInput = document.getElementById('salesman_id');
const saleWarehouseIdInput = document.getElementById('warehouse_id');
const driverNameInput = document.getElementById('driver_name');

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

// Open "Add Sale"
document.getElementById('addSaleBtn')?.addEventListener('click', (e) => {
  saleForm.reset();
  saleForm.action = e.currentTarget.dataset.action;
  saleFormMethod.innerHTML = '';
  saleModalTitle.textContent = 'Add Sale';
  resetItemRows();
  addItemRow();
  openModal(saleModal);
  invoiceNumberInput?.focus();
});

// Open "Edit Sale" for each row
document.querySelectorAll('.edit-sale-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    saleForm.reset();
    saleForm.action = btn.dataset.action;
    saleFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    saleModalTitle.textContent = 'Edit Sale';

    invoiceNumberInput.value = btn.dataset.invoiceNumber || '';
    saleDateInput.value = btn.dataset.saleDate || '';
    salesOrderIdInput.value = btn.dataset.salesOrderId || '';
    saleCustomerIdInput.value = btn.dataset.customerId || '';
    salesmanIdInput.value = btn.dataset.salesmanId || '';
    saleWarehouseIdInput.value = btn.dataset.warehouseId || '';
    driverNameInput.value = btn.dataset.driverName || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(saleModal);
    invoiceNumberInput?.focus();
  });
});

// Sale form must have at least one line item.
saleForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-sale-btn').forEach((btn) => {
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

[saleModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [saleModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
