// Add / edit ledger entry modal (Transactions > General Ledger)
const txLedgerModal = document.getElementById('txLedgerModal');
const txLedgerForm = document.getElementById('txLedgerForm');
const txLedgerFormMethod = document.getElementById('txLedgerFormMethod');
const txLedgerModalTitle = document.getElementById('txLedgerModalTitle');

const txLedgerDateInput = document.getElementById('transaction_date');
const txLedgerAccountSelect = document.getElementById('account_id');
const txLedgerReferenceInput = document.getElementById('reference_number');
const txLedgerDebitInput = document.getElementById('debit');
const txLedgerCreditInput = document.getElementById('credit');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Ledger Entry"
document.getElementById('addTxLedgerBtn')?.addEventListener('click', (e) => {
  txLedgerForm.reset();
  txLedgerForm.action = e.currentTarget.dataset.action;
  txLedgerFormMethod.innerHTML = '';
  txLedgerModalTitle.textContent = 'Add Ledger Entry';
  openModal(txLedgerModal);
  txLedgerDateInput?.focus();
});

// Open "Edit Ledger Entry" for each row
document.querySelectorAll('.edit-tx-ledger-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    txLedgerForm.reset();
    txLedgerForm.action = btn.dataset.action;
    txLedgerFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    txLedgerModalTitle.textContent = 'Edit Ledger Entry';

    txLedgerDateInput.value = btn.dataset.transactionDate || '';
    txLedgerAccountSelect.value = btn.dataset.accountId || '';
    txLedgerReferenceInput.value = btn.dataset.referenceNumber || '';
    txLedgerDebitInput.value = btn.dataset.debit || '0';
    txLedgerCreditInput.value = btn.dataset.credit || '0';

    openModal(txLedgerModal);
    txLedgerDateInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-tx-ledger-btn').forEach((btn) => {
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

[txLedgerModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [txLedgerModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
