// Purchase Report page: debounced search + filter auto-submit with loading
// feedback (same pattern as inquiry.js / reports-purchase-orders.js), plus
// a print button.
const purchaseReportForm = document.getElementById('purchaseReportFilterForm');
const purchaseReportSearch = document.getElementById('purchaseReportSearch');
const purchaseReportLoading = document.getElementById('purchaseReportLoading');
const purchaseReportFilters = [
  document.getElementById('purchaseReportSupplier'),
  document.getElementById('purchaseReportWarehouse'),
  document.getElementById('purchaseReportStatus'),
  document.getElementById('purchaseReportDateFrom'),
  document.getElementById('purchaseReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  purchaseReportLoading?.classList.remove('hidden');
  purchaseReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
purchaseReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    purchaseReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let purchaseReportSearchDebounce;
purchaseReportSearch?.addEventListener('input', () => {
  clearTimeout(purchaseReportSearchDebounce);
  purchaseReportSearchDebounce = setTimeout(() => {
    showLoading();
    purchaseReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
purchaseReportForm?.addEventListener('submit', () => {
  clearTimeout(purchaseReportSearchDebounce);
  showLoading();
});

document.getElementById('purchaseReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});
