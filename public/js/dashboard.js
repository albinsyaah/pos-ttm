// Order filter tabs
const tabs = document.querySelectorAll('.filter-tab');
const rows = document.querySelectorAll('#orderBody tr');
const emptyState = document.getElementById('emptyState');

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => {
      t.classList.remove('bg-[var(--brand-600)]', 'text-white');
      t.classList.add('text-[var(--ink-400)]');
      t.setAttribute('aria-selected', 'false');
    });
    tab.classList.add('bg-[var(--brand-600)]', 'text-white');
    tab.classList.remove('text-[var(--ink-400)]');
    tab.setAttribute('aria-selected', 'true');

    const filter = tab.dataset.filter;
    let visibleCount = 0;
    rows.forEach(row => {
      const match = filter === 'all' || row.dataset.type === filter;
      row.style.display = match ? '' : 'none';
      if (match) visibleCount++;
    });
    emptyState?.classList.toggle('hidden', visibleCount !== 0);
  });
});

// Live search across the orders table (header search box)
const searchInput = document.getElementById('searchInput');
searchInput?.addEventListener('input', (e) => {
  const q = e.target.value.trim().toLowerCase();
  let visibleCount = 0;
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    const match = text.includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visibleCount++;
  });
  emptyState?.classList.toggle('hidden', visibleCount !== 0);
});

// Receipt print buttons
document.querySelectorAll('.dl-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const orderId = btn.closest('tr').querySelector('td').textContent.trim();
    showToast(`Sending ${orderId} to receipt printer`, 'fa-print');
  });
});

// New sale button
document.getElementById('newSaleBtn')?.addEventListener('click', () => {
  showToast('Opening new sale screen', 'fa-cart-shopping');
});

// Notification bell
document.getElementById('notifBtn')?.addEventListener('click', () => {
  showToast('4 alerts: low stock and pending orders', 'fa-bell', 'var(--warn-600)');
});
