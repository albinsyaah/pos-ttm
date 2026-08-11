// Sales Return Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as reports-purchase-returns.js), plus a
// print button.
const salesReturnReportForm = document.getElementById('salesReturnReportFilterForm');
const salesReturnReportSearch = document.getElementById('salesReturnReportSearch');
const salesReturnReportLoading = document.getElementById('salesReturnReportLoading');
const salesReturnReportFilters = [
  document.getElementById('salesReturnReportCustomer'),
  document.getElementById('salesReturnReportDateFrom'),
  document.getElementById('salesReturnReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  salesReturnReportLoading?.classList.remove('hidden');
  salesReturnReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
salesReturnReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    salesReturnReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let salesReturnReportSearchDebounce;
salesReturnReportSearch?.addEventListener('input', () => {
  clearTimeout(salesReturnReportSearchDebounce);
  salesReturnReportSearchDebounce = setTimeout(() => {
    showLoading();
    salesReturnReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
salesReturnReportForm?.addEventListener('submit', () => {
  clearTimeout(salesReturnReportSearchDebounce);
  showLoading();
});

document.getElementById('srReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('srReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('salesReturnReportTableWrap', 'sales-return-report');
});
