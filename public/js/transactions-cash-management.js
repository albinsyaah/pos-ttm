// Add / edit cash transaction modal (Transactions > Cash Management)
const cashManagementModal = document.getElementById('cashManagementModal');
const cashManagementForm = document.getElementById('cashManagementForm');
const cashManagementFormMethod = document.getElementById('cashManagementFormMethod');
const cashManagementModalTitle = document.getElementById('cashManagementModalTitle');

const cashManagementDateInput = document.getElementById('transaction_date');
const cashManagementTypeSelect = document.getElementById('type');
const cashManagementAccountSelect = document.getElementById('account_id');
const cashManagementAmountInput = document.getElementById('amount');
const cashManagementDescriptionInput = document.getElementById('description');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Cash Transaction"
document.getElementById('addCashManagementBtn')?.addEventListener('click', (e) => {
  cashManagementForm.reset();
  cashManagementForm.action = e.currentTarget.dataset.action;
  cashManagementFormMethod.innerHTML = '';
  cashManagementModalTitle.textContent = 'Add Cash Transaction';
  openModal(cashManagementModal);
  cashManagementDateInput?.focus();
});

// Open "Edit Cash Transaction" for each row
document.querySelectorAll('.edit-cash-management-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    cashManagementForm.reset();
    cashManagementForm.action = btn.dataset.action;
    cashManagementFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    cashManagementModalTitle.textContent = 'Edit Cash Transaction';

    cashManagementDateInput.value = btn.dataset.transactionDate || '';
    cashManagementTypeSelect.value = btn.dataset.type || '';
    cashManagementAccountSelect.value = btn.dataset.accountId || '';
    cashManagementAmountInput.value = btn.dataset.amount || '';
    cashManagementDescriptionInput.value = btn.dataset.description || '';

    openModal(cashManagementModal);
    cashManagementDateInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-cash-management-btn').forEach((btn) => {
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

[cashManagementModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [cashManagementModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
