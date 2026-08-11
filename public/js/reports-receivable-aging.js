// Receivable Aging Report page: debounced search + filter auto-submit
// with loading feedback (same pattern as reports-receivable-payments.js),
// plus print and export buttons.
const arAgingReportForm = document.getElementById('arAgingReportFilterForm');
const arAgingReportSearch = document.getElementById('arAgingReportSearch');
const arAgingReportLoading = document.getElementById('arAgingReportLoading');
const arAgingReportFilters = [
  document.getElementById('arAgingReportCustomer'),
  document.getElementById('arAgingReportAsOf'),
];

function showArAgingReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  arAgingReportLoading?.classList.remove('hidden');
  arAgingReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
arAgingReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showArAgingReportLoading();
    arAgingReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let arAgingReportSearchDebounce;
arAgingReportSearch?.addEventListener('input', () => {
  clearTimeout(arAgingReportSearchDebounce);
  arAgingReportSearchDebounce = setTimeout(() => {
    showArAgingReportLoading();
    arAgingReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
arAgingReportForm?.addEventListener('submit', () => {
  clearTimeout(arAgingReportSearchDebounce);
  showArAgingReportLoading();
});

document.getElementById('arAgingReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('arAgingReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('arAgingReportTableWrap', 'receivable-aging-report');
});
