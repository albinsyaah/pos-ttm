// Add / edit price setup (Setup Harga) modal
const priceSetupModal = document.getElementById('priceSetupModal');
const priceSetupForm = document.getElementById('priceSetupForm');
const priceSetupFormMethod = document.getElementById('priceSetupFormMethod');
const priceSetupModalTitle = document.getElementById('priceSetupModalTitle');

const priceSetupProductSelect = document.getElementById('product_id');
const priceSetupCategoryInput = document.getElementById('price_category');
const priceSetupAmountInput = document.getElementById('amount');
const priceSetupEffectiveDateInput = document.getElementById('effective_date');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Setup Harga"
document.getElementById('addPriceSetupBtn')?.addEventListener('click', (e) => {
  priceSetupForm.reset();
  priceSetupForm.action = e.currentTarget.dataset.action;
  priceSetupFormMethod.innerHTML = '';
  priceSetupModalTitle.textContent = 'Add Setup Harga';
  openModal(priceSetupModal);
  priceSetupProductSelect?.focus();
});

// Open "Edit Setup Harga" for each row
document.querySelectorAll('.edit-price-setup-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    priceSetupForm.reset();
    priceSetupForm.action = btn.dataset.action;
    priceSetupFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    priceSetupModalTitle.textContent = 'Edit Setup Harga';

    priceSetupProductSelect.value = btn.dataset.productId || '';
    priceSetupCategoryInput.value = btn.dataset.priceCategory || '';
    priceSetupAmountInput.value = btn.dataset.amount || '';
    priceSetupEffectiveDateInput.value = btn.dataset.effectiveDate || '';

    openModal(priceSetupModal);
    priceSetupProductSelect?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-price-setup-btn').forEach((btn) => {
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

[priceSetupModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [priceSetupModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
