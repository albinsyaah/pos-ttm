// Receivable Payment Report page: debounced search + filter auto-submit
// with loading feedback (same pattern as reports-payable-payments.js),
// plus a print button.
const arReportForm = document.getElementById('arReportFilterForm');
const arReportSearch = document.getElementById('arReportSearch');
const arReportLoading = document.getElementById('arReportLoading');
const arReportFilters = [
  document.getElementById('arReportCustomer'),
  document.getElementById('arReportMethod'),
  document.getElementById('arReportDateFrom'),
  document.getElementById('arReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  arReportLoading?.classList.remove('hidden');
  arReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
arReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    arReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let arReportSearchDebounce;
arReportSearch?.addEventListener('input', () => {
  clearTimeout(arReportSearchDebounce);
  arReportSearchDebounce = setTimeout(() => {
    showLoading();
    arReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
arReportForm?.addEventListener('submit', () => {
  clearTimeout(arReportSearchDebounce);
  showLoading();
});

document.getElementById('arReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('arReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('arReportTableWrap', 'receivable-payment-report');
});
