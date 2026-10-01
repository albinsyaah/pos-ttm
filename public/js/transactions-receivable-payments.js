// Add / edit receivable payment modal
const receivablePaymentModal = document.getElementById('receivablePaymentModal');
const receivablePaymentForm = document.getElementById('receivablePaymentForm');
const receivablePaymentFormMethod = document.getElementById('receivablePaymentFormMethod');
const receivablePaymentModalTitle = document.getElementById('receivablePaymentModalTitle');

const paymentNumberInput = document.getElementById('payment_number');
const paymentDateInput = document.getElementById('payment_date');
const paymentCustomerIdInput = document.getElementById('customer_id');
const paymentMethodInput = document.getElementById('payment_method');
const amountInput = document.getElementById('amount');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Receivable Payment"
document.getElementById('addReceivablePaymentBtn')?.addEventListener('click', (e) => {
  paymentMethodInput.querySelectorAll('option[data-temporary]').forEach((o) => o.remove());
  receivablePaymentForm.reset();
  receivablePaymentForm.action = e.currentTarget.dataset.action;
  receivablePaymentFormMethod.innerHTML = '';
  receivablePaymentModalTitle.textContent = 'Add Receivable Payment';
  openModal(receivablePaymentModal);
  paymentNumberInput?.focus();
});

// Open "Edit Receivable Payment" for each row
document.querySelectorAll('.edit-receivable-payment-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    paymentMethodInput.querySelectorAll('option[data-temporary]').forEach((o) => o.remove());
    receivablePaymentForm.reset();
    receivablePaymentForm.action = btn.dataset.action;
    receivablePaymentFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    receivablePaymentModalTitle.textContent = 'Edit Receivable Payment';

    paymentNumberInput.value = btn.dataset.paymentNumber || '';
    paymentDateInput.value = btn.dataset.paymentDate || '';
    paymentCustomerIdInput.value = btn.dataset.customerId || '';
    // A method deactivated after this payment was saved is not in the list; keep it selectable.
    const methodId = btn.dataset.paymentMethodId || '';
    if (methodId !== '' && ![...paymentMethodInput.options].some((o) => o.value === methodId)) {
      const kept = new Option(btn.dataset.paymentMethodName || methodId, methodId);
      kept.dataset.temporary = '1';
      paymentMethodInput.add(kept);
    }
    paymentMethodInput.value = methodId;
    amountInput.value = btn.dataset.amount || '';

    openModal(receivablePaymentModal);
    paymentNumberInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-receivable-payment-btn').forEach((btn) => {
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

[receivablePaymentModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [receivablePaymentModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
