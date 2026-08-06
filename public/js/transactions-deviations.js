// Add / edit deviation modal
const deviationModal = document.getElementById('deviationModal');
const deviationForm = document.getElementById('deviationForm');
const deviationFormMethod = document.getElementById('deviationFormMethod');
const deviationModalTitle = document.getElementById('deviationModalTitle');

const mutationNumberInput = document.getElementById('mutation_number');
const mutationDateInput = document.getElementById('mutation_date');
const warehouseIdInput = document.getElementById('warehouse_id');
const requestedByInput = document.getElementById('requested_by');
const statusInput = document.getElementById('status');

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
  if (values.notes !== undefined) row.querySelector('.item-notes').value = values.notes || '';

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

// Open "Add Deviation"
document.getElementById('addDeviationBtn')?.addEventListener('click', (e) => {
  deviationForm.reset();
  deviationForm.action = e.currentTarget.dataset.action;
  deviationFormMethod.innerHTML = '';
  deviationModalTitle.textContent = 'Add Deviation';
  resetItemRows();
  addItemRow();
  openModal(deviationModal);
  mutationNumberInput?.focus();
});

// Open "Edit Deviation" for each row
document.querySelectorAll('.edit-deviation-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    deviationForm.reset();
    deviationForm.action = btn.dataset.action;
    deviationFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    deviationModalTitle.textContent = 'Edit Deviation';

    mutationNumberInput.value = btn.dataset.mutationNumber || '';
    mutationDateInput.value = btn.dataset.mutationDate || '';
    warehouseIdInput.value = btn.dataset.warehouseId || '';
    requestedByInput.value = btn.dataset.requestedBy || '';
    statusInput.value = btn.dataset.status || '';

    resetItemRows();
    try {
      const items = JSON.parse(btn.dataset.items || '[]');
      items.forEach((item) => addItemRow(item));
    } catch (err) {
      addItemRow();
    }
    if (itemRowsBody.querySelectorAll('.item-row').length === 0) addItemRow();

    openModal(deviationModal);
    mutationNumberInput?.focus();
  });
});

// Deviation form must have at least one line item, and qty must not be zero.
deviationForm?.addEventListener('submit', (e) => {
  const rows = itemRowsBody.querySelectorAll('.item-row');
  if (rows.length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
    return;
  }

  const hasZeroQty = Array.from(rows).some((row) => Number(row.querySelector('.item-qty').value) === 0);
  if (hasZeroQty) {
    e.preventDefault();
    showToast('Qty cannot be 0 — use a positive number for overage or negative for shortage.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-deviation-btn').forEach((btn) => {
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

[deviationModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [deviationModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
