// Add / edit purchase order modal
const purchaseOrderModal = document.getElementById('purchaseOrderModal');
const purchaseOrderForm = document.getElementById('purchaseOrderForm');
const purchaseOrderFormMethod = document.getElementById('purchaseOrderFormMethod');
const purchaseOrderModalTitle = document.getElementById('purchaseOrderModalTitle');

const poNumberInput = document.getElementById('po_number');
const orderDateInput = document.getElementById('order_date');
const supplierIdInput = document.getElementById('supplier_id');
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

// Open "Add Purchase Order"
document.getElementById('addPurchaseOrderBtn')?.addEventListener('click', (e) => {
  purchaseOrderForm.reset();
  purchaseOrderForm.action = e.currentTarget.dataset.action;
  purchaseOrderFormMethod.innerHTML = '';
  purchaseOrderModalTitle.textContent = purchaseOrderModalTitle.dataset.addLabel || document.querySelector('#addPurchaseOrderBtn').textContent.trim();
  resetItemRows();
  addItemRow();
  openModal(purchaseOrderModal);
  poNumberInput?.focus();
});

// Open "Edit Purchase Order" for each row
document.querySelectorAll('.edit-purchase-order-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    purchaseOrderForm.reset();
    purchaseOrderForm.action = btn.dataset.action;
    purchaseOrderFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    purchaseOrderModalTitle.textContent = 'Edit Purchase Order';

    poNumberInput.value = btn.dataset.poNumber || '';
    orderDateInput.value = btn.dataset.orderDate || '';
    supplierIdInput.value = btn.dataset.supplierId || '';
    statusInput.value = btn.dataset.status || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(purchaseOrderModal);
    poNumberInput?.focus();
  });
});

// Purchase order form must have at least one line item.
purchaseOrderForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-purchase-order-btn').forEach((btn) => {
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

[purchaseOrderModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [purchaseOrderModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
