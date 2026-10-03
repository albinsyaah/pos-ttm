// Add / edit salesman modal
const salesmanModal = document.getElementById('salesmanModal');
const salesmanForm = document.getElementById('salesmanForm');
const salesmanFormMethod = document.getElementById('salesmanFormMethod');
const salesmanModalTitle = document.getElementById('salesmanModalTitle');

const codeInput = document.getElementById('code');
const nameInput = document.getElementById('name');
const phoneInput = document.getElementById('phone');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Salesman"
document.getElementById('addSalesmanBtn')?.addEventListener('click', (e) => {
  salesmanForm.reset();
  salesmanForm.action = e.currentTarget.dataset.action;
  salesmanFormMethod.innerHTML = '';
  salesmanModalTitle.textContent = __t('Add Salesman');
  openModal(salesmanModal);
  codeInput?.focus();
});

// Open "Edit Salesman" for each row
document.querySelectorAll('.edit-salesman-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    salesmanForm.reset();
    salesmanForm.action = btn.dataset.action;
    salesmanFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    salesmanModalTitle.textContent = __t('Edit Salesman');

    codeInput.value = btn.dataset.code || '';
    nameInput.value = btn.dataset.name || '';
    phoneInput.value = btn.dataset.phone || '';

    openModal(salesmanModal);
    codeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-salesman-btn').forEach((btn) => {
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

[salesmanModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [salesmanModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
