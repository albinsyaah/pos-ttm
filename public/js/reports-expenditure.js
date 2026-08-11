// Expenditure ("Pengeluaran") Report page: debounced search + filter
// auto-submit with loading feedback (same pattern as
// reports-receivable-payments.js), plus a print button.
const expenditureReportForm = document.getElementById('expenditureReportFilterForm');
const expenditureReportSearch = document.getElementById('expenditureReportSearch');
const expenditureReportLoading = document.getElementById('expenditureReportLoading');
const expenditureReportFilters = [
  document.getElementById('expenditureReportAccount'),
  document.getElementById('expenditureReportDateFrom'),
  document.getElementById('expenditureReportDateTo'),
];

function showExpenditureReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  expenditureReportLoading?.classList.remove('hidden');
  expenditureReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
expenditureReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showExpenditureReportLoading();
    expenditureReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let expenditureReportSearchDebounce;
expenditureReportSearch?.addEventListener('input', () => {
  clearTimeout(expenditureReportSearchDebounce);
  expenditureReportSearchDebounce = setTimeout(() => {
    showExpenditureReportLoading();
    expenditureReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
expenditureReportForm?.addEventListener('submit', () => {
  clearTimeout(expenditureReportSearchDebounce);
  showExpenditureReportLoading();
});

document.getElementById('expenditureReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('expenditureReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('expenditureReportTableWrap', 'expenditure-report');
});
