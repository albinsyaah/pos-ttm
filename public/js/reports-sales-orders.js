// Sales Order Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as reports-purchase-orders.js), plus a
// print button.
const soReportForm = document.getElementById('soReportFilterForm');
const soReportSearch = document.getElementById('soReportSearch');
const soReportLoading = document.getElementById('soReportLoading');
const soReportFilters = [
  document.getElementById('soReportCustomer'),
  document.getElementById('soReportStatus'),
  document.getElementById('soReportDateFrom'),
  document.getElementById('soReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  soReportLoading?.classList.remove('hidden');
  soReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
soReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    soReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let soReportSearchDebounce;
soReportSearch?.addEventListener('input', () => {
  clearTimeout(soReportSearchDebounce);
  soReportSearchDebounce = setTimeout(() => {
    showLoading();
    soReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
soReportForm?.addEventListener('submit', () => {
  clearTimeout(soReportSearchDebounce);
  showLoading();
});

document.getElementById('soReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('soReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('soReportTableWrap', 'sales-order-report');
});
