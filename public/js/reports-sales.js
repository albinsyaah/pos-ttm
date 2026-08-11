// Sales Report page: debounced search + filter auto-submit with loading
// feedback (same pattern as reports-purchases.js), plus a print button.
const saleReportForm = document.getElementById('saleReportFilterForm');
const saleReportSearch = document.getElementById('saleReportSearch');
const saleReportLoading = document.getElementById('saleReportLoading');
const saleReportFilters = [
  document.getElementById('saleReportCustomer'),
  document.getElementById('saleReportWarehouse'),
  document.getElementById('saleReportDateFrom'),
  document.getElementById('saleReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  saleReportLoading?.classList.remove('hidden');
  saleReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
saleReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    saleReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let saleReportSearchDebounce;
saleReportSearch?.addEventListener('input', () => {
  clearTimeout(saleReportSearchDebounce);
  saleReportSearchDebounce = setTimeout(() => {
    showLoading();
    saleReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
saleReportForm?.addEventListener('submit', () => {
  clearTimeout(saleReportSearchDebounce);
  showLoading();
});

document.getElementById('saleReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('saleReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('saleReportTableWrap', 'sales-report');
});
