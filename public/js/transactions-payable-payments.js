// Add / edit payable payment modal
const payablePaymentModal = document.getElementById('payablePaymentModal');
const payablePaymentForm = document.getElementById('payablePaymentForm');
const payablePaymentFormMethod = document.getElementById('payablePaymentFormMethod');
const payablePaymentModalTitle = document.getElementById('payablePaymentModalTitle');

const paymentNumberInput = document.getElementById('payment_number');
const paymentDateInput = document.getElementById('payment_date');
const paymentSupplierIdInput = document.getElementById('supplier_id');
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

// Open "Add Payable Payment"
document.getElementById('addPayablePaymentBtn')?.addEventListener('click', (e) => {
  payablePaymentForm.reset();
  payablePaymentForm.action = e.currentTarget.dataset.action;
  payablePaymentFormMethod.innerHTML = '';
  payablePaymentModalTitle.textContent = 'Add Payable Payment';
  openModal(payablePaymentModal);
  paymentNumberInput?.focus();
});

// Open "Edit Payable Payment" for each row
document.querySelectorAll('.edit-payable-payment-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    payablePaymentForm.reset();
    payablePaymentForm.action = btn.dataset.action;
    payablePaymentFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    payablePaymentModalTitle.textContent = 'Edit Payable Payment';

    paymentNumberInput.value = btn.dataset.paymentNumber || '';
    paymentDateInput.value = btn.dataset.paymentDate || '';
    paymentSupplierIdInput.value = btn.dataset.supplierId || '';
    paymentMethodInput.value = btn.dataset.paymentMethod || '';
    amountInput.value = btn.dataset.amount || '';

    openModal(payablePaymentModal);
    paymentNumberInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-payable-payment-btn').forEach((btn) => {
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

[payablePaymentModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [payablePaymentModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
