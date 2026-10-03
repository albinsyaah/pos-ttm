// Add / edit item type (Jenis Barang) modal
const itemTypeModal = document.getElementById('itemTypeModal');
const itemTypeForm = document.getElementById('itemTypeForm');
const itemTypeFormMethod = document.getElementById('itemTypeFormMethod');
const itemTypeModalTitle = document.getElementById('itemTypeModalTitle');

const itemTypeNameInput = document.getElementById('name');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Jenis Barang"
document.getElementById('addItemTypeBtn')?.addEventListener('click', (e) => {
  itemTypeForm.reset();
  itemTypeForm.action = e.currentTarget.dataset.action;
  itemTypeFormMethod.innerHTML = '';
  itemTypeModalTitle.textContent = __t('Add Item Type');
  openModal(itemTypeModal);
  itemTypeNameInput?.focus();
});

// Open "Edit Jenis Barang" for each row
document.querySelectorAll('.edit-item-type-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    itemTypeForm.reset();
    itemTypeForm.action = btn.dataset.action;
    itemTypeFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    itemTypeModalTitle.textContent = __t('Edit Item Type');

    itemTypeNameInput.value = btn.dataset.name || '';

    openModal(itemTypeModal);
    itemTypeNameInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-item-type-btn').forEach((btn) => {
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

[itemTypeModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [itemTypeModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
