// Purchase Return Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as inquiry.js / reports-purchase-orders.js),
// plus a print button.
const purchaseReturnReportForm = document.getElementById('purchaseReturnReportFilterForm');
const purchaseReturnReportSearch = document.getElementById('purchaseReturnReportSearch');
const purchaseReturnReportLoading = document.getElementById('purchaseReturnReportLoading');
const purchaseReturnReportFilters = [
  document.getElementById('purchaseReturnReportSupplier'),
  document.getElementById('purchaseReturnReportDateFrom'),
  document.getElementById('purchaseReturnReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  purchaseReturnReportLoading?.classList.remove('hidden');
  purchaseReturnReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
purchaseReturnReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    purchaseReturnReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let purchaseReturnReportSearchDebounce;
purchaseReturnReportSearch?.addEventListener('input', () => {
  clearTimeout(purchaseReturnReportSearchDebounce);
  purchaseReturnReportSearchDebounce = setTimeout(() => {
    showLoading();
    purchaseReturnReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
purchaseReturnReportForm?.addEventListener('submit', () => {
  clearTimeout(purchaseReturnReportSearchDebounce);
  showLoading();
});

document.getElementById('purchaseReturnReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});
