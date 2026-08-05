// Add / edit supplier modal
const supplierModal = document.getElementById('supplierModal');
const supplierForm = document.getElementById('supplierForm');
const supplierFormMethod = document.getElementById('supplierFormMethod');
const supplierModalTitle = document.getElementById('supplierModalTitle');

const codeInput = document.getElementById('code');
const nameInput = document.getElementById('name');
const contactPersonInput = document.getElementById('contact_person');
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

// Open "Add Supplier"
document.getElementById('addSupplierBtn')?.addEventListener('click', (e) => {
  supplierForm.reset();
  supplierForm.action = e.currentTarget.dataset.action;
  supplierFormMethod.innerHTML = '';
  supplierModalTitle.textContent = 'Add Supplier';
  openModal(supplierModal);
  codeInput?.focus();
});

// Open "Edit Supplier" for each row
document.querySelectorAll('.edit-supplier-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    supplierForm.reset();
    supplierForm.action = btn.dataset.action;
    supplierFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    supplierModalTitle.textContent = 'Edit Supplier';

    codeInput.value = btn.dataset.code || '';
    nameInput.value = btn.dataset.name || '';
    contactPersonInput.value = btn.dataset.contactPerson || '';
    phoneInput.value = btn.dataset.phone || '';
    addressInput.value = btn.dataset.address || '';

    openModal(supplierModal);
    codeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-supplier-btn').forEach((btn) => {
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

[supplierModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [supplierModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
