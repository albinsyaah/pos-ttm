// Position ("Kartu Posisi Stok") Report page: debounced search + filter
// auto-submit with loading feedback (same pattern as
// reports-purchase-orders.js), plus a print button.
const positionReportForm = document.getElementById('positionReportFilterForm');
const positionReportSearch = document.getElementById('positionReportSearch');
const positionReportLoading = document.getElementById('positionReportLoading');
const positionReportFilters = [
  document.getElementById('positionReportWarehouse'),
  document.getElementById('positionReportLowStockOnly'),
];

function showPositionReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  positionReportLoading?.classList.remove('hidden');
  positionReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
positionReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showPositionReportLoading();
    positionReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let positionReportSearchDebounce;
positionReportSearch?.addEventListener('input', () => {
  clearTimeout(positionReportSearchDebounce);
  positionReportSearchDebounce = setTimeout(() => {
    showPositionReportLoading();
    positionReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
positionReportForm?.addEventListener('submit', () => {
  clearTimeout(positionReportSearchDebounce);
  showPositionReportLoading();
});

document.getElementById('positionReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('positionReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('positionReportTableWrap', 'position-report');
});
