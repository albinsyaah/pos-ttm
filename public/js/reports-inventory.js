// Inventory Report page: debounced search + filter auto-submit with
// loading feedback (same pattern as reports-position.js), plus print and
// export buttons.
const inventoryReportForm = document.getElementById('inventoryReportFilterForm');
const inventoryReportSearch = document.getElementById('inventoryReportSearch');
const inventoryReportLoading = document.getElementById('inventoryReportLoading');
const inventoryReportFilters = [
  document.getElementById('inventoryReportBrand'),
  document.getElementById('inventoryReportItemType'),
  document.getElementById('inventoryReportProductGroup'),
];

function showInventoryReportLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  inventoryReportLoading?.classList.remove('hidden');
  inventoryReportForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
inventoryReportFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showInventoryReportLoading();
    inventoryReportForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let inventoryReportSearchDebounce;
inventoryReportSearch?.addEventListener('input', () => {
  clearTimeout(inventoryReportSearchDebounce);
  inventoryReportSearchDebounce = setTimeout(() => {
    showInventoryReportLoading();
    inventoryReportForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
inventoryReportForm?.addEventListener('submit', () => {
  clearTimeout(inventoryReportSearchDebounce);
  showInventoryReportLoading();
});

document.getElementById('inventoryReportPrintBtn')?.addEventListener('click', () => {
  window.print();
});

document.getElementById('inventoryReportExportBtn')?.addEventListener('click', () => {
  exportReportTableToExcel('inventoryReportTableWrap', 'inventory-report');
});
