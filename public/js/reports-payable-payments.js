// Payable Payment Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as reports-purchase-orders.js), plus a
// print button.
const apReportForm = document.getElementById('apReportFilterForm');
const apReportSearch = document.getElementById('apReportSearch');
const apReportLoading = document.getElementById('apReportLoading');
const apReportFilters = [
  document.getElementById('apReportSupplier'),
  document.getElementById('apReportMethod'),
  document.getElementById('apReportDateFrom'),
  document.getElementById('apReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  apReportLoading?.classList.remove('hidden');
  apReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
apReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    apReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let apReportSearchDebounce;
apReportSearch?.addEventListener('input', () => {
  clearTimeout(apReportSearchDebounce);
  apReportSearchDebounce = setTimeout(() => {
    showLoading();
    apReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
apReportForm?.addEventListener('submit', () => {
  clearTimeout(apReportSearchDebounce);
  showLoading();
});

document.getElementById('apReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('apReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('apReportTableWrap', 'payable-payment-report');
});
