// Add / edit product (Barang) modal
const productModal = document.getElementById('productModal');
const productForm = document.getElementById('productForm');
const productFormMethod = document.getElementById('productFormMethod');
const productModalTitle = document.getElementById('productModalTitle');

const productCodeInput = document.getElementById('code');
const productNameInput = document.getElementById('name');
const productBrandSelect = document.getElementById('brand_id');
const productItemTypeSelect = document.getElementById('item_type_id');
const productGroupSelect = document.getElementById('product_group_id');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Barang"
document.getElementById('addProductBtn')?.addEventListener('click', (e) => {
  productForm.reset();
  productForm.action = e.currentTarget.dataset.action;
  productFormMethod.innerHTML = '';
  productModalTitle.textContent = 'Add Barang';
  openModal(productModal);
  productCodeInput?.focus();
});

// Open "Edit Barang" for each row
document.querySelectorAll('.edit-product-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    productForm.reset();
    productForm.action = btn.dataset.action;
    productFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    productModalTitle.textContent = 'Edit Barang';

    productCodeInput.value = btn.dataset.code || '';
    productNameInput.value = btn.dataset.name || '';
    productBrandSelect.value = btn.dataset.brandId || '';
    productItemTypeSelect.value = btn.dataset.itemTypeId || '';
    productGroupSelect.value = btn.dataset.productGroupId || '';

    openModal(productModal);
    productCodeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-product-btn').forEach((btn) => {
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

[productModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [productModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
