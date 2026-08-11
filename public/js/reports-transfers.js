// Transfer Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as reports-purchase-orders.js), plus a
// print button.
const transferReportForm = document.getElementById('transferReportFilterForm');
const transferReportSearch = document.getElementById('transferReportSearch');
const transferReportLoading = document.getElementById('transferReportLoading');
const transferReportFilters = [
  document.getElementById('transferReportFromWarehouse'),
  document.getElementById('transferReportToWarehouse'),
  document.getElementById('transferReportStatus'),
  document.getElementById('transferReportDateFrom'),
  document.getElementById('transferReportDateTo'),
];

function showTransferReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  transferReportLoading?.classList.remove('hidden');
  transferReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
transferReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showTransferReportLoading();
    transferReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let transferReportSearchDebounce;
transferReportSearch?.addEventListener('input', () => {
  clearTimeout(transferReportSearchDebounce);
  transferReportSearchDebounce = setTimeout(() => {
    showTransferReportLoading();
    transferReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
transferReportForm?.addEventListener('submit', () => {
  clearTimeout(transferReportSearchDebounce);
  showTransferReportLoading();
});

document.getElementById('transferReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('transferReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('transferReportTableWrap', 'transfer-report');
});
