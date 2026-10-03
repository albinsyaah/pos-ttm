// Add / edit purchase return modal
const purchaseReturnModal = document.getElementById('purchaseReturnModal');
const purchaseReturnForm = document.getElementById('purchaseReturnForm');
const purchaseReturnFormMethod = document.getElementById('purchaseReturnFormMethod');
const purchaseReturnModalTitle = document.getElementById('purchaseReturnModalTitle');

const returnNumberInput = document.getElementById('return_number');
const returnDateInput = document.getElementById('return_date');
const purchaseIdInput = document.getElementById('purchase_id');
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
  if (values.reason) row.querySelector('.item-reason').value = values.reason;

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

// Open "Add Purchase Return"
document.getElementById('addPurchaseReturnBtn')?.addEventListener('click', (e) => {
  purchaseReturnForm.reset();
  purchaseReturnForm.action = e.currentTarget.dataset.action;
  purchaseReturnFormMethod.innerHTML = '';
  purchaseReturnModalTitle.textContent = __t('Add Purchase Return');
  resetItemRows();
  addItemRow();
  openModal(purchaseReturnModal);
  returnNumberInput?.focus();
});

// Open "Edit Purchase Return" for each row
document.querySelectorAll('.edit-purchase-return-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    purchaseReturnForm.reset();
    purchaseReturnForm.action = btn.dataset.action;
    purchaseReturnFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    purchaseReturnModalTitle.textContent = __t('Edit Purchase Return');

    returnNumberInput.value = btn.dataset.returnNumber || '';
    returnDateInput.value = btn.dataset.returnDate || '';
    purchaseIdInput.value = btn.dataset.purchaseId || '';
    totalAmountInput.value = btn.dataset.totalAmount || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(purchaseReturnModal);
    returnNumberInput?.focus();
  });
});

// Purchase return form must have at least one line item.
purchaseReturnForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-purchase-return-btn').forEach((btn) => {
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

[purchaseReturnModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [purchaseReturnModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
