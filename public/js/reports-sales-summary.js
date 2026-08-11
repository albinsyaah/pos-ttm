// Sales Summary Report page ("Laporan Penjualan"): debounced search +
// filter auto-submit with loading feedback (same pattern as
// reports-purchases.js), plus a print button. Unlike reports-sales.js
// (the single-channel "Sales Report" page), this one also has a channel
// (source) filter.
const ssReportForm = document.getElementById('ssReportFilterForm');
const ssReportSearch = document.getElementById('ssReportSearch');
const ssReportLoading = document.getElementById('ssReportLoading');
const ssReportFilters = [
  document.getElementById('ssReportSource'),
  document.getElementById('ssReportCustomer'),
  document.getElementById('ssReportWarehouse'),
  document.getElementById('ssReportDateFrom'),
  document.getElementById('ssReportDateTo'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  ssReportLoading?.classList.remove('hidden');
  ssReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
ssReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    ssReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let ssReportSearchDebounce;
ssReportSearch?.addEventListener('input', () => {
  clearTimeout(ssReportSearchDebounce);
  ssReportSearchDebounce = setTimeout(() => {
    showLoading();
    ssReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
ssReportForm?.addEventListener('submit', () => {
  clearTimeout(ssReportSearchDebounce);
  showLoading();
});

document.getElementById('ssReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('ssReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('ssReportTableWrap', 'sales-summary-report');
});
