// Receivable Card ("Kartu Piutang") Report page: filters auto-submit on
// change (same pattern as reports-receivable-payments.js), plus print and
// export buttons. No debounced search box here — the customer select is
// the primary filter and always submits immediately.
const arCardReportForm = document.getElementById('arCardReportFilterForm');
const arCardReportLoading = document.getElementById('arCardReportLoading');
const arCardReportFilters = [
  document.getElementById('arCardReportCustomer'),
  document.getElementById('arCardReportDateFrom'),
  document.getElementById('arCardReportDateTo'),
];

function showArCardReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  arCardReportLoading?.classList.remove('hidden');
  arCardReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

arCardReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showArCardReportLoading();
    arCardReportForm.submit();
  });
});

arCardReportForm?.addEventListener('submit', () => {
  showArCardReportLoading();
});

document.getElementById('arCardReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('arCardReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('arCardReportTableWrap', 'receivable-card-report');
});
