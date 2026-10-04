// Add / edit product group (Grup Produk) modal
const productGroupModal = document.getElementById('productGroupModal');
const productGroupForm = document.getElementById('productGroupForm');
const productGroupFormMethod = document.getElementById('productGroupFormMethod');
const productGroupModalTitle = document.getElementById('productGroupModalTitle');

const productGroupNameInput = document.getElementById('name');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Grup Produk"
document.getElementById('addProductGroupBtn')?.addEventListener('click', (e) => {
  productGroupForm.reset();
  productGroupForm.action = e.currentTarget.dataset.action;
  productGroupFormMethod.innerHTML = '';
  productGroupModalTitle.textContent = __t('Add Product Group');
  openModal(productGroupModal);
  productGroupNameInput?.focus();
});

// Open "Edit Grup Produk" for each row
document.querySelectorAll('.edit-product-group-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    productGroupForm.reset();
    productGroupForm.action = btn.dataset.action;
    productGroupFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    productGroupModalTitle.textContent = __t('Edit Product Group');

    productGroupNameInput.value = btn.dataset.name || '';

    openModal(productGroupModal);
    productGroupNameInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-product-group-btn').forEach((btn) => {
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

[productGroupModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [productGroupModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
