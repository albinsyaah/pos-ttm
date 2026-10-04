// Add / edit cash flow (Arus Kas) modal
const cashFlowModal = document.getElementById('cashFlowModal');
const cashFlowForm = document.getElementById('cashFlowForm');
const cashFlowFormMethod = document.getElementById('cashFlowFormMethod');
const cashFlowModalTitle = document.getElementById('cashFlowModalTitle');

const cashFlowDateInput = document.getElementById('transaction_date');
const cashFlowTypeSelect = document.getElementById('type');
const cashFlowAccountSelect = document.getElementById('account_id');
const cashFlowAmountInput = document.getElementById('amount');
const cashFlowDescriptionInput = document.getElementById('description');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Cash Flow"
document.getElementById('addCashFlowBtn')?.addEventListener('click', (e) => {
  cashFlowForm.reset();
  cashFlowForm.action = e.currentTarget.dataset.action;
  cashFlowFormMethod.innerHTML = '';
  cashFlowModalTitle.textContent = __t('Add Cash Flow');
  openModal(cashFlowModal);
  cashFlowDateInput?.focus();
});

// Open "Edit Cash Flow" for each row
document.querySelectorAll('.edit-cash-flow-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    cashFlowForm.reset();
    cashFlowForm.action = btn.dataset.action;
    cashFlowFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    cashFlowModalTitle.textContent = __t('Edit Cash Flow');

    cashFlowDateInput.value = btn.dataset.transactionDate || '';
    cashFlowTypeSelect.value = btn.dataset.type || '';
    cashFlowAccountSelect.value = btn.dataset.accountId || '';
    cashFlowAmountInput.value = btn.dataset.amount || '';
    cashFlowDescriptionInput.value = btn.dataset.description || '';

    openModal(cashFlowModal);
    cashFlowDateInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-cash-flow-btn').forEach((btn) => {
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

[cashFlowModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [cashFlowModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
