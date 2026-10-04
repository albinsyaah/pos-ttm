// Add / edit internal expenditure modal
const internalExpenditureModal = document.getElementById('internalExpenditureModal');
const internalExpenditureForm = document.getElementById('internalExpenditureForm');
const internalExpenditureFormMethod = document.getElementById('internalExpenditureFormMethod');
const internalExpenditureModalTitle = document.getElementById('internalExpenditureModalTitle');

const mutationNumberInput = document.getElementById('mutation_number');
const mutationDateInput = document.getElementById('mutation_date');
const fromWarehouseIdInput = document.getElementById('from_warehouse_id');
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

// Open "Add Internal Expenditure"
document.getElementById('addInternalExpenditureBtn')?.addEventListener('click', (e) => {
  internalExpenditureForm.reset();
  internalExpenditureForm.action = e.currentTarget.dataset.action;
  internalExpenditureFormMethod.innerHTML = '';
  internalExpenditureModalTitle.textContent = __t('Add Internal Expenditure');
  resetItemRows();
  addItemRow();
  openModal(internalExpenditureModal);
  mutationNumberInput?.focus();
});

// Open "Edit Internal Expenditure" for each row
document.querySelectorAll('.edit-internal-expenditure-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    internalExpenditureForm.reset();
    internalExpenditureForm.action = btn.dataset.action;
    internalExpenditureFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    internalExpenditureModalTitle.textContent = __t('Edit Internal Expenditure');

    mutationNumberInput.value = btn.dataset.mutationNumber || '';
    mutationDateInput.value = btn.dataset.mutationDate || '';
    fromWarehouseIdInput.value = btn.dataset.fromWarehouseId || '';
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

    openModal(internalExpenditureModal);
    mutationNumberInput?.focus();
  });
});

// Internal expenditure form must have at least one line item.
internalExpenditureForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-internal-expenditure-btn').forEach((btn) => {
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

[internalExpenditureModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [internalExpenditureModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
