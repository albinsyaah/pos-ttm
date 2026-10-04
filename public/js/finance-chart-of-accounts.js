// Add / edit chart of account (Bagan Akun) modal
const accountModal = document.getElementById('accountModal');
const accountForm = document.getElementById('accountForm');
const accountFormMethod = document.getElementById('accountFormMethod');
const accountModalTitle = document.getElementById('accountModalTitle');

const accountCodeInput = document.getElementById('account_code');
const accountNameInput = document.getElementById('account_name');
const accountTypeSelect = document.getElementById('type');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Account"
document.getElementById('addAccountBtn')?.addEventListener('click', (e) => {
  accountForm.reset();
  accountForm.action = e.currentTarget.dataset.action;
  accountFormMethod.innerHTML = '';
  accountModalTitle.textContent = __t('Add Account');
  openModal(accountModal);
  accountCodeInput?.focus();
});

// Open "Edit Account" for each row
document.querySelectorAll('.edit-account-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    accountForm.reset();
    accountForm.action = btn.dataset.action;
    accountFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    accountModalTitle.textContent = __t('Edit Account');

    accountCodeInput.value = btn.dataset.accountCode || '';
    accountNameInput.value = btn.dataset.accountName || '';
    accountTypeSelect.value = btn.dataset.type || '';

    openModal(accountModal);
    accountCodeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-account-btn').forEach((btn) => {
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

[accountModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [accountModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
