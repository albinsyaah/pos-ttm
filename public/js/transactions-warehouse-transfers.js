// Add / edit warehouse transfer modal
const warehouseTransferModal = document.getElementById('warehouseTransferModal');
const warehouseTransferForm = document.getElementById('warehouseTransferForm');
const warehouseTransferFormMethod = document.getElementById('warehouseTransferFormMethod');
const warehouseTransferModalTitle = document.getElementById('warehouseTransferModalTitle');

const mutationNumberInput = document.getElementById('mutation_number');
const mutationDateInput = document.getElementById('mutation_date');
const fromWarehouseIdInput = document.getElementById('from_warehouse_id');
const toWarehouseIdInput = document.getElementById('to_warehouse_id');
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

// Open "Add Warehouse Transfer"
document.getElementById('addWarehouseTransferBtn')?.addEventListener('click', (e) => {
  warehouseTransferForm.reset();
  warehouseTransferForm.action = e.currentTarget.dataset.action;
  warehouseTransferFormMethod.innerHTML = '';
  warehouseTransferModalTitle.textContent = 'Add Warehouse Transfer';
  resetItemRows();
  addItemRow();
  openModal(warehouseTransferModal);
  mutationNumberInput?.focus();
});

// Open "Edit Warehouse Transfer" for each row
document.querySelectorAll('.edit-warehouse-transfer-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    warehouseTransferForm.reset();
    warehouseTransferForm.action = btn.dataset.action;
    warehouseTransferFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    warehouseTransferModalTitle.textContent = 'Edit Warehouse Transfer';

    mutationNumberInput.value = btn.dataset.mutationNumber || '';
    mutationDateInput.value = btn.dataset.mutationDate || '';
    fromWarehouseIdInput.value = btn.dataset.fromWarehouseId || '';
    toWarehouseIdInput.value = btn.dataset.toWarehouseId || '';
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

    openModal(warehouseTransferModal);
    mutationNumberInput?.focus();
  });
});

// Warehouse transfer form must have at least one line item, and the
// source/destination warehouses must be different.
warehouseTransferForm?.addEventListener('submit', (e) => {
  if (itemRowsBody.querySelectorAll('.item-row').length === 0) {
    e.preventDefault();
    showToast('Add at least one line item.', 'fa-triangle-exclamation', 'var(--bad-600)');
    return;
  }
  if (fromWarehouseIdInput.value && fromWarehouseIdInput.value === toWarehouseIdInput.value) {
    e.preventDefault();
    showToast('Source and destination warehouses must be different.', 'fa-triangle-exclamation', 'var(--bad-600)');
  }
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-warehouse-transfer-btn').forEach((btn) => {
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

[warehouseTransferModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [warehouseTransferModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
