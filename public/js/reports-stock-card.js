// Stock Card ("Kartu Stok") Report page: filters auto-submit on change
// (same pattern as reports-receivable-card.js), plus print and export
// buttons. No debounced search box here — the product select is the
// primary filter and always submits immediately.
const stockCardReportForm = document.getElementById('stockCardReportFilterForm');
const stockCardReportLoading = document.getElementById('stockCardReportLoading');
const stockCardReportFilters = [
  document.getElementById('stockCardReportProduct'),
  document.getElementById('stockCardReportWarehouse'),
  document.getElementById('stockCardReportDateFrom'),
  document.getElementById('stockCardReportDateTo'),
];

function showStockCardReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  stockCardReportLoading?.classList.remove('hidden');
  stockCardReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

stockCardReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showStockCardReportLoading();
    stockCardReportForm.submit();
  });
});

stockCardReportForm?.addEventListener('submit', () => {
  showStockCardReportLoading();
});

document.getElementById('stockCardReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('stockCardReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('stockCardReportTableWrap', 'stock-card-report');
});
