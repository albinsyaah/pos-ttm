// Add / edit general ledger (Buku Besar) modal
const ledgerModal = document.getElementById('ledgerModal');
const ledgerForm = document.getElementById('ledgerForm');
const ledgerFormMethod = document.getElementById('ledgerFormMethod');
const ledgerModalTitle = document.getElementById('ledgerModalTitle');

const ledgerDateInput = document.getElementById('transaction_date');
const ledgerAccountSelect = document.getElementById('account_id');
const ledgerReferenceInput = document.getElementById('reference_number');
const ledgerDebitInput = document.getElementById('debit');
const ledgerCreditInput = document.getElementById('credit');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Ledger Entry"
document.getElementById('addLedgerBtn')?.addEventListener('click', (e) => {
  ledgerForm.reset();
  ledgerForm.action = e.currentTarget.dataset.action;
  ledgerFormMethod.innerHTML = '';
  ledgerModalTitle.textContent = 'Add Ledger Entry';
  openModal(ledgerModal);
  ledgerDateInput?.focus();
});

// Open "Edit Ledger Entry" for each row
document.querySelectorAll('.edit-ledger-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    ledgerForm.reset();
    ledgerForm.action = btn.dataset.action;
    ledgerFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    ledgerModalTitle.textContent = 'Edit Ledger Entry';

    ledgerDateInput.value = btn.dataset.transactionDate || '';
    ledgerAccountSelect.value = btn.dataset.accountId || '';
    ledgerReferenceInput.value = btn.dataset.referenceNumber || '';
    ledgerDebitInput.value = btn.dataset.debit || '0';
    ledgerCreditInput.value = btn.dataset.credit || '0';

    openModal(ledgerModal);
    ledgerDateInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-ledger-btn').forEach((btn) => {
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

[ledgerModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [ledgerModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
