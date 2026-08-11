// Purchase Order Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as inquiry.js), plus a print button.
const poReportForm = document.getElementById('poReportFilterForm');
const poReportSearch = document.getElementById('poReportSearch');
const poReportLoading = document.getElementById('poReportLoading');
const poReportFilters = [
  document.getElementById('poReportSupplier'),
  document.getElementById('poReportStatus'),
  document.getElementById('poReportDateFrom'),
  document.getElementById('poReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  poReportLoading?.classList.remove('hidden');
  poReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
poReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    poReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let poReportSearchDebounce;
poReportSearch?.addEventListener('input', () => {
  clearTimeout(poReportSearchDebounce);
  poReportSearchDebounce = setTimeout(() => {
    showLoading();
    poReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
poReportForm?.addEventListener('submit', () => {
  clearTimeout(poReportSearchDebounce);
  showLoading();
});

document.getElementById('poReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('poReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('poReportTableWrap', 'purchase-order-report');
});
