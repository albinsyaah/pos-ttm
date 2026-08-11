// Shared helper for report pages: export a rendered table to a real .xlsx file
function exportReportTableToExcel(wrapId, filename) {
  const table = document.querySelector(`#${wrapId} table`);
  if (!table) return;
  if (typeof XLSX === 'undefined') {
    console.error('XLSX library not loaded');
    return;
  }
  const wb = XLSX.utils.table_to_book(table, { sheet: 'Report' });
  XLSX.writeFile(wb, `${filename}.xlsx`);
}
