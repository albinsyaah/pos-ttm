// Add / edit customer modal
const customerModal = document.getElementById('customerModal');
const customerForm = document.getElementById('customerForm');
const customerFormMethod = document.getElementById('customerFormMethod');
const customerModalTitle = document.getElementById('customerModalTitle');

const codeInput = document.getElementById('code');
const nameInput = document.getElementById('name');
const phoneInput = document.getElementById('phone');
const addressInput = document.getElementById('address');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Customer"
document.getElementById('addCustomerBtn')?.addEventListener('click', (e) => {
  customerForm.reset();
  customerForm.action = e.currentTarget.dataset.action;
  customerFormMethod.innerHTML = '';
  customerModalTitle.textContent = __t('Add Customer');
  openModal(customerModal);
  codeInput?.focus();
});

// Open "Edit Customer" for each row
document.querySelectorAll('.edit-customer-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    customerForm.reset();
    customerForm.action = btn.dataset.action;
    customerFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    customerModalTitle.textContent = __t('Edit Customer');

    codeInput.value = btn.dataset.code || '';
    nameInput.value = btn.dataset.name || '';
    phoneInput.value = btn.dataset.phone || '';
    addressInput.value = btn.dataset.address || '';

    openModal(customerModal);
    codeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-customer-btn').forEach((btn) => {
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

[customerModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [customerModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
