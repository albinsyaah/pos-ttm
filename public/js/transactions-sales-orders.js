// Add / edit sales order modal
const salesOrderModal = document.getElementById('salesOrderModal');
const salesOrderForm = document.getElementById('salesOrderForm');
const salesOrderFormMethod = document.getElementById('salesOrderFormMethod');
const salesOrderModalTitle = document.getElementById('salesOrderModalTitle');

const soNumberInput = document.getElementById('so_number');
const orderDateInput = document.getElementById('order_date');
const customerIdInput = document.getElementById('customer_id');
const statusInput = document.getElementById('status');

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

// Open "Add Sales Order"
document.getElementById('addSalesOrderBtn')?.addEventListener('click', (e) => {
  salesOrderForm.reset();
  salesOrderForm.action = e.currentTarget.dataset.action;
  salesOrderFormMethod.innerHTML = '';
  salesOrderModalTitle.textContent = salesOrderModalTitle.dataset.addLabel || document.querySelector('#addSalesOrderBtn').textContent.trim();
  resetItemRows();
  addItemRow();
  openModal(salesOrderModal);
  soNumberInput?.focus();
});

// Open "Edit Sales Order" for each row
document.querySelectorAll('.edit-sales-order-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    salesOrderForm.reset();
    salesOrderForm.action = btn.dataset.action;
    salesOrderFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    salesOrderModalTitle.textContent = 'Edit Sales Order';

    soNumberInput.value = btn.dataset.soNumber || '';
    orderDateInput.value = btn.dataset.orderDate || '';
    customerIdInput.value = btn.dataset.customerId || '';
    statusInput.value = btn.dataset.status || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(salesOrderModal);
    soNumberInput?.focus();
  });
});

// Sales order form must have at least one line item.
salesOrderForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-sales-order-btn').forEach((btn) => {
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

[salesOrderModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [salesOrderModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
