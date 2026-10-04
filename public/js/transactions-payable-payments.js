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
const purchaseSelect = document.getElementById('purchase_id');
const invoiceHint = document.getElementById('invoiceHint');
const balancePanel = document.getElementById('balancePanel');

const money = (n) => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Open invoices of the chosen supplier, as returned by the server. Kept so the
// amount field can be capped to what is still owed on the picked invoice.
let openInvoices = [];
let loadToken = 0;

function resetInvoiceSelect(message) {
  purchaseSelect.innerHTML = '';
  purchaseSelect.add(new Option(message, ''));
  purchaseSelect.disabled = true;
  purchaseSelect.required = true;
  invoiceHint.textContent = '';
  amountInput.removeAttribute('max');
  openInvoices = [];
  PaymentBalance.hide(balancePanel);
}

function showInvoiceHint() {
  const invoice = openInvoices.find((i) => String(i.id) === purchaseSelect.value);
  if (!invoice) {
    invoiceHint.textContent = '';
    amountInput.removeAttribute('max');
    showBalance();
    return;
  }
  const t = payablePaymentForm.dataset;
  invoiceHint.textContent = `${t.textDue} ${invoice.due_date} · ${t.textOutstanding} ${money(invoice.outstanding)}`;
  amountInput.max = invoice.outstanding;
  showBalance();
}

/**
 * What is owed and what this payment takes off. The invoice block follows the
 * chosen invoice; the supplier block is the sum of the supplier's open invoices
 * (as listed by the server, which leaves out the payment being edited).
 */
function showBalance() {
  if (!paymentSupplierIdInput.value || !balancePanel) {
    PaymentBalance.hide(balancePanel);
    return;
  }

  const pay = Number(amountInput.value) || 0;
  const invoice = openInvoices.find((i) => String(i.id) === purchaseSelect.value);
  const part = (name) => balancePanel.querySelector(`[data-bal="${name}"]`);

  PaymentBalance.render(balancePanel, {
    total: invoice ? invoice.outstanding : 0,
    pay,
    texts: { over: payablePaymentForm.dataset.textOver },
  });
  part('invoice-block').classList.toggle('hidden', !invoice);

  const supplierTotal = openInvoices.reduce((sum, i) => sum + Number(i.outstanding), 0);
  part('supplier-total').textContent = PaymentBalance.money(supplierTotal);
  part('supplier-after').textContent = PaymentBalance.money(PaymentBalance.summarize(supplierTotal, pay).after);
}

/**
 * Fill the invoice list for a supplier. While editing, paymentId makes the
 * server leave that payment out of the paid total, so its own invoice still
 * shows; selectedId pre-selects it. A payment that predates invoices
 * (legacy) may stay without one.
 */
async function loadInvoices(supplierId, { paymentId = '', selectedId = '', legacy = false } = {}) {
  const t = payablePaymentForm.dataset;
  const token = ++loadToken;

  if (!supplierId) {
    resetInvoiceSelect(t.textChooseSupplier);
    return;
  }

  try {
    const params = new URLSearchParams({ supplier_id: supplierId });
    if (paymentId) params.set('payment_id', paymentId);

    const response = await fetch(`${t.invoicesUrl}?${params}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(response.status);
    const { invoices } = await response.json();
    if (token !== loadToken) return; // a newer supplier choice replaced this one

    openInvoices = invoices;
    purchaseSelect.innerHTML = '';
    purchaseSelect.required = !legacy;
    purchaseSelect.disabled = false;

    if (legacy) {
      purchaseSelect.add(new Option(t.textNoInvoice, ''));
    } else {
      purchaseSelect.add(new Option(invoices.length ? t.textChooseInvoice : t.textNoOpen, ''));
    }
    invoices.forEach((i) => {
      purchaseSelect.add(new Option(`${i.invoice_number} — ${t.textDue} ${i.due_date} — ${t.textOutstanding} ${money(i.outstanding)}`, i.id));
    });
    purchaseSelect.value = selectedId ? String(selectedId) : '';
    showInvoiceHint();
  } catch (e) {
    if (token !== loadToken) return;
    resetInvoiceSelect(t.textFailed);
  }
}

paymentSupplierIdInput?.addEventListener('change', () => loadInvoices(paymentSupplierIdInput.value));
amountInput?.addEventListener('input', showBalance);

purchaseSelect?.addEventListener('change', () => {
  showInvoiceHint();
  const invoice = openInvoices.find((i) => String(i.id) === purchaseSelect.value);
  // Suggest paying the whole balance; the user can lower it for a part payment.
  if (invoice && (amountInput.value === '' || Number(amountInput.value) > invoice.outstanding)) {
    amountInput.value = invoice.outstanding;
  }
  showBalance();
});

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
  paymentMethodInput.querySelectorAll('option[data-temporary]').forEach((o) => o.remove());
  payablePaymentForm.reset();
  payablePaymentForm.action = e.currentTarget.dataset.action;
  payablePaymentFormMethod.innerHTML = '';
  payablePaymentModalTitle.textContent = __t('Add Payable Payment');
  resetInvoiceSelect(payablePaymentForm.dataset.textChooseSupplier);
  openModal(payablePaymentModal);
  paymentNumberInput?.focus();
});

// Open "Edit Payable Payment" for each row
document.querySelectorAll('.edit-payable-payment-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    paymentMethodInput.querySelectorAll('option[data-temporary]').forEach((o) => o.remove());
    payablePaymentForm.reset();
    payablePaymentForm.action = btn.dataset.action;
    payablePaymentFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    payablePaymentModalTitle.textContent = __t('Edit Payable Payment');

    paymentNumberInput.value = btn.dataset.paymentNumber || '';
    paymentDateInput.value = btn.dataset.paymentDate || '';
    paymentSupplierIdInput.value = btn.dataset.supplierId || '';
    // A method deactivated after this payment was saved is not in the list; keep it selectable.
    const methodId = btn.dataset.paymentMethodId || '';
    if (methodId !== '' && ![...paymentMethodInput.options].some((o) => o.value === methodId)) {
      const kept = new Option(btn.dataset.paymentMethodName || methodId, methodId);
      kept.dataset.temporary = '1';
      paymentMethodInput.add(kept);
    }
    paymentMethodInput.value = methodId;
    amountInput.value = btn.dataset.amount || '';

    loadInvoices(btn.dataset.supplierId || '', {
      paymentId: btn.dataset.paymentId || '',
      selectedId: btn.dataset.purchaseId || '',
      legacy: !btn.dataset.purchaseId,
    });

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
