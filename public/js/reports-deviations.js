// Deviation Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as reports-purchase-orders.js), plus a
// print button.
const deviationReportForm = document.getElementById('deviationReportFilterForm');
const deviationReportSearch = document.getElementById('deviationReportSearch');
const deviationReportLoading = document.getElementById('deviationReportLoading');
const deviationReportFilters = [
  document.getElementById('deviationReportWarehouse'),
  document.getElementById('deviationReportStatus'),
  document.getElementById('deviationReportDateFrom'),
  document.getElementById('deviationReportDateTo'),
];

function showDeviationReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  deviationReportLoading?.classList.remove('hidden');
  deviationReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
deviationReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showDeviationReportLoading();
    deviationReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let deviationReportSearchDebounce;
deviationReportSearch?.addEventListener('input', () => {
  clearTimeout(deviationReportSearchDebounce);
  deviationReportSearchDebounce = setTimeout(() => {
    showDeviationReportLoading();
    deviationReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
deviationReportForm?.addEventListener('submit', () => {
  clearTimeout(deviationReportSearchDebounce);
  showDeviationReportLoading();
});

document.getElementById('deviationReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('deviationReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('deviationReportTableWrap', 'deviation-report');
});
