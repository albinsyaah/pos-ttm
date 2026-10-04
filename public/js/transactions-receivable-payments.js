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
const balancePanel = document.getElementById('balancePanel');

// What the chosen customer owes (and the open invoices behind it), as the server sees it.
let balance = { total: 0, invoices: [] };
let balanceToken = 0;

function showBalance() {
  if (!paymentCustomerIdInput.value) {
    PaymentBalance.hide(balancePanel);
    return;
  }
  PaymentBalance.render(balancePanel, {
    total: balance.total,
    pay: Number(amountInput.value) || 0,
    rows: balance.invoices,
    texts: { over: receivablePaymentForm.dataset.textOver },
  });
}

/**
 * Load what a customer owes. While editing, paymentId makes the server leave that
 * payment out, so the balance shown is what it was before this payment.
 */
async function loadBalance(customerId, paymentId = '') {
  const token = ++balanceToken;
  balance = { total: 0, invoices: [] };

  if (!customerId) {
    showBalance();
    return;
  }

  try {
    const params = new URLSearchParams({ customer_id: customerId });
    if (paymentId) params.set('payment_id', paymentId);

    const response = await fetch(`${receivablePaymentForm.dataset.outstandingUrl}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) throw new Error(response.status);
    const data = await response.json();
    if (token !== balanceToken) return; // a newer customer was chosen meanwhile

    balance = { total: Number(data.total) || 0, invoices: data.invoices || [] };
    showBalance();
  } catch (e) {
    if (token !== balanceToken) return;
    PaymentBalance.hide(balancePanel);
    showToast(receivablePaymentForm.dataset.textFailed, 'fa-triangle-exclamation', 'var(--bad-600)');
  }
}

paymentCustomerIdInput?.addEventListener('change', () => loadBalance(paymentCustomerIdInput.value));
amountInput?.addEventListener('input', showBalance);

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
  receivablePaymentModalTitle.textContent = __t('Add Receivable Payment');
  loadBalance('');
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
    receivablePaymentModalTitle.textContent = __t('Edit Receivable Payment');

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

    loadBalance(btn.dataset.customerId || '', btn.dataset.paymentId || '');
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
