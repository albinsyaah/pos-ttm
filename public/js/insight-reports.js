// Shared script for the Tahap 7 report pages (payment methods, salesman, sales by product).
// Same behaviour as the older report pages: search as you type (no Enter), filters submit on
// change, loading feedback, print and Excel export.
(function () {
  const form = document.getElementById('insightFilterForm');
  if (!form) return;

  const search = document.getElementById('insightSearch');
  const loading = document.getElementById('insightLoading');

  function showLoading() {
    // Do not use `disabled`: disabled fields are left out of the submitted form.
    loading?.classList.remove('hidden');
    form.classList.add('opacity-60', 'pointer-events-none');
  }

  form.querySelectorAll('select, input[type="date"]').forEach((el) => {
    el.addEventListener('change', () => {
      showLoading();
      form.submit();
    });
  });

  let debounce;
  search?.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      showLoading();
      form.submit();
    }, 450);
  });

  form.addEventListener('submit', () => {
    clearTimeout(debounce);
    showLoading();
  });

  document.getElementById('insightPrintBtn')?.addEventListener('click', () => window.print());

  const exportBtn = document.getElementById('insightExportBtn');
  exportBtn?.addEventListener('click', () => {
    exportReportTableToExcel('insightTableWrap', exportBtn.dataset.filename || 'report');
  });
})();
