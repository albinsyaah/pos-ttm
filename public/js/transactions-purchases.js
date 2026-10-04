// Add / edit purchase modal
const purchaseModal = document.getElementById('purchaseModal');
const purchaseForm = document.getElementById('purchaseForm');
const purchaseFormMethod = document.getElementById('purchaseFormMethod');
const purchaseModalTitle = document.getElementById('purchaseModalTitle');

const invoiceNumberInput = document.getElementById('invoice_number');
const purchaseDateInput = document.getElementById('purchase_date');
const purchaseOrderIdInput = document.getElementById('purchase_order_id');
const purchaseSupplierIdInput = document.getElementById('supplier_id');
const warehouseIdInput = document.getElementById('warehouse_id');
const purchaseStatusInput = document.getElementById('status');

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
  window.ProductPicker?.enhance(row.querySelector('.item-product'));
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

// Open "Add Purchase"
document.getElementById('addPurchaseBtn')?.addEventListener('click', (e) => {
  purchaseForm.reset();
  purchaseForm.action = e.currentTarget.dataset.action;
  purchaseFormMethod.innerHTML = '';
  purchaseModalTitle.textContent = __t('Add Purchase');
  resetItemRows();
  addItemRow();
  openModal(purchaseModal);
  invoiceNumberInput?.focus();
});

// Open "Edit Purchase" for each row
document.querySelectorAll('.edit-purchase-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    purchaseForm.reset();
    purchaseForm.action = btn.dataset.action;
    purchaseFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    purchaseModalTitle.textContent = __t('Edit Purchase');

    invoiceNumberInput.value = btn.dataset.invoiceNumber || '';
    purchaseDateInput.value = btn.dataset.purchaseDate || '';
    purchaseOrderIdInput.value = btn.dataset.purchaseOrderId || '';
    purchaseSupplierIdInput.value = btn.dataset.supplierId || '';
    warehouseIdInput.value = btn.dataset.warehouseId || '';
    purchaseStatusInput.value = btn.dataset.status || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(purchaseModal);
    invoiceNumberInput?.focus();
  });
});

// Purchase form must have at least one line item.
purchaseForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-purchase-btn').forEach((btn) => {
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

[purchaseModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [purchaseModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
