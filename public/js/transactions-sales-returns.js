// Add / edit sales return modal
const salesReturnModal = document.getElementById('salesReturnModal');
const salesReturnForm = document.getElementById('salesReturnForm');
const salesReturnFormMethod = document.getElementById('salesReturnFormMethod');
const salesReturnModalTitle = document.getElementById('salesReturnModalTitle');

const returnNumberInput = document.getElementById('return_number');
const returnDateInput = document.getElementById('return_date');
const saleIdInput = document.getElementById('sale_id');
const totalAmountInput = document.getElementById('total_amount');

const itemRowsBody = document.getElementById('itemRows');
const itemRowTemplate = document.getElementById('itemRowTemplate');
const noItemsMessage = document.getElementById('noItemsMessage');

let rowIndex = 0;

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

function refreshEmptyState() {
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

  row.querySelector('.remove-item-btn').addEventListener('click', () => {
    row.remove();
    refreshEmptyState();
  });

  refreshEmptyState();
}

function resetItemRows() {
  itemRowsBody.innerHTML = '';
  rowIndex = 0;
  refreshEmptyState();
}

document.getElementById('addItemRowBtn')?.addEventListener('click', () => addItemRow());

// Open "Add Sales Return"
document.getElementById('addSalesReturnBtn')?.addEventListener('click', (e) => {
  salesReturnForm.reset();
  salesReturnForm.action = e.currentTarget.dataset.action;
  salesReturnFormMethod.innerHTML = '';
  salesReturnModalTitle.textContent = 'Add Sales Return';
  resetItemRows();
  addItemRow();
  openModal(salesReturnModal);
  returnNumberInput?.focus();
});

// Open "Edit Sales Return" for each row
document.querySelectorAll('.edit-sales-return-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    salesReturnForm.reset();
    salesReturnForm.action = btn.dataset.action;
    salesReturnFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    salesReturnModalTitle.textContent = 'Edit Sales Return';

    returnNumberInput.value = btn.dataset.returnNumber || '';
    returnDateInput.value = btn.dataset.returnDate || '';
    saleIdInput.value = btn.dataset.saleId || '';
    totalAmountInput.value = btn.dataset.totalAmount || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(salesReturnModal);
    returnNumberInput?.focus();
  });
});

// Sales return form must have at least one line item.
salesReturnForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-sales-return-btn').forEach((btn) => {
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

[salesReturnModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [salesReturnModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
