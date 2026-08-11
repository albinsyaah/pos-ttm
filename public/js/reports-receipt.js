// Receipt ("Penerimaan") Report page: debounced search + filter
// auto-submit with loading feedback (same pattern as
// reports-expenditure.js), plus a print button.
const receiptReportForm = document.getElementById('receiptReportFilterForm');
const receiptReportSearch = document.getElementById('receiptReportSearch');
const receiptReportLoading = document.getElementById('receiptReportLoading');
const receiptReportFilters = [
  document.getElementById('receiptReportAccount'),
  document.getElementById('receiptReportDateFrom'),
  document.getElementById('receiptReportDateTo'),
];

function showReceiptReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  receiptReportLoading?.classList.remove('hidden');
  receiptReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
receiptReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showReceiptReportLoading();
    receiptReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let receiptReportSearchDebounce;
receiptReportSearch?.addEventListener('input', () => {
  clearTimeout(receiptReportSearchDebounce);
  receiptReportSearchDebounce = setTimeout(() => {
    showReceiptReportLoading();
    receiptReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
receiptReportForm?.addEventListener('submit', () => {
  clearTimeout(receiptReportSearchDebounce);
  showReceiptReportLoading();
});

document.getElementById('receiptReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('receiptReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('receiptReportTableWrap', 'receipt-report');
});
