// Add / edit payment method (Metode Pembayaran) modal
const paymentMethodModal = document.getElementById('paymentMethodModal');
const paymentMethodForm = document.getElementById('paymentMethodForm');
const paymentMethodFormMethod = document.getElementById('paymentMethodFormMethod');
const paymentMethodModalTitle = document.getElementById('paymentMethodModalTitle');

const methodNameInput = document.getElementById('name');
const methodIsCashInput = document.getElementById('is_cash');
const methodIsActiveInput = document.getElementById('is_active');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add"
document.getElementById('addPaymentMethodBtn')?.addEventListener('click', (e) => {
  paymentMethodForm.reset();
  paymentMethodForm.action = e.currentTarget.dataset.action;
  paymentMethodFormMethod.innerHTML = '';
  paymentMethodModalTitle.textContent = __t('Add Payment Method');
  methodIsCashInput.checked = false;
  methodIsActiveInput.checked = true;
  openModal(paymentMethodModal);
  methodNameInput?.focus();
});

// Open "Edit" for each row
document.querySelectorAll('.edit-payment-method-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    paymentMethodForm.reset();
    paymentMethodForm.action = btn.dataset.action;
    paymentMethodFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    paymentMethodModalTitle.textContent = __t('Edit Payment Method');

    methodNameInput.value = btn.dataset.name || '';
    methodIsCashInput.checked = btn.dataset.isCash === '1';
    methodIsActiveInput.checked = btn.dataset.isActive === '1';

    openModal(paymentMethodModal);
    methodNameInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-payment-method-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    deleteForm.action = btn.dataset.action;
    // A method that already has transactions is refused by the server; say so up front.
    deleteModalText.textContent = btn.dataset.used === '1'
      ? `"${btn.dataset.name}" — ${deleteModalText.dataset.usedText}`
      : `"${btn.dataset.name}" — ${deleteModalText.dataset.defaultText}`;
    openModal(deleteModal);
  });
});

// Shared close handlers: close button, backdrop click, Escape key
document.querySelectorAll('.modal-close').forEach((btn) => {
  btn.addEventListener('click', () => {
    closeModal(btn.closest('.modal-overlay'));
  });
});

[paymentMethodModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [paymentMethodModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
