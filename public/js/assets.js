// Add / edit asset modal
const assetModal = document.getElementById('assetModal');
const assetForm = document.getElementById('assetForm');
const assetFormMethod = document.getElementById('assetFormMethod');
const assetModalTitle = document.getElementById('assetModalTitle');

const assetCodeInput = document.getElementById('asset_code');
const assetNameInput = document.getElementById('name');
const assetPurchaseDateInput = document.getElementById('purchase_date');
const assetValueInput = document.getElementById('value');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Asset"
document.getElementById('addAssetBtn')?.addEventListener('click', (e) => {
  assetForm.reset();
  assetForm.action = e.currentTarget.dataset.action;
  assetFormMethod.innerHTML = '';
  assetModalTitle.textContent = 'Add Asset';
  openModal(assetModal);
  assetCodeInput?.focus();
});

// Open "Edit Asset" for each row
document.querySelectorAll('.edit-asset-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    assetForm.reset();
    assetForm.action = btn.dataset.action;
    assetFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    assetModalTitle.textContent = 'Edit Asset';

    assetCodeInput.value = btn.dataset.assetCode || '';
    assetNameInput.value = btn.dataset.name || '';
    assetPurchaseDateInput.value = btn.dataset.purchaseDate || '';
    assetValueInput.value = btn.dataset.value || '';

    openModal(assetModal);
    assetCodeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-asset-btn').forEach((btn) => {
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

[assetModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [assetModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
