// Add / edit warehouse modal
const warehouseModal = document.getElementById('warehouseModal');
const warehouseForm = document.getElementById('warehouseForm');
const warehouseFormMethod = document.getElementById('warehouseFormMethod');
const warehouseModalTitle = document.getElementById('warehouseModalTitle');

const codeInput = document.getElementById('code');
const nameInput = document.getElementById('name');
const locationInput = document.getElementById('location');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Warehouse"
document.getElementById('addWarehouseBtn')?.addEventListener('click', (e) => {
  warehouseForm.reset();
  warehouseForm.action = e.currentTarget.dataset.action;
  warehouseFormMethod.innerHTML = '';
  warehouseModalTitle.textContent = 'Add Warehouse';
  openModal(warehouseModal);
  codeInput?.focus();
});

// Open "Edit Warehouse" for each row
document.querySelectorAll('.edit-warehouse-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    warehouseForm.reset();
    warehouseForm.action = btn.dataset.action;
    warehouseFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    warehouseModalTitle.textContent = 'Edit Warehouse';

    codeInput.value = btn.dataset.code || '';
    nameInput.value = btn.dataset.name || '';
    locationInput.value = btn.dataset.location || '';

    openModal(warehouseModal);
    codeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-warehouse-btn').forEach((btn) => {
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

[warehouseModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [warehouseModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
