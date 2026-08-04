// Shared helpers used across pages

/**
 * Show a small toast notification in the top-right corner.
 * @param {string} message
 * @param {string} icon Font Awesome icon class, e.g. 'fa-circle-check'
 * @param {string} color CSS color value for the icon
 */
function showToast(message, icon = 'fa-circle-check', color = 'var(--good-600)') {
  const host = document.getElementById('toastHost');
  if (!host) return;

  const toast = document.createElement('div');
  toast.className = 'toast bg-white border border-gray-100 shadow-lg rounded-2xl px-4 py-3 flex items-center gap-3 text-sm text-[var(--ink-900)]';
  toast.innerHTML = `<i class="fa-solid ${icon}" style="color:${color}"></i><span>${message}</span>`;
  host.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .2s ease';
    setTimeout(() => toast.remove(), 200);
  }, 2400);
}

// Mobile sidebar toggle (used by navbar hamburger button)
document.getElementById('menuBtn')?.addEventListener('click', () => {
  const sidebar = document.getElementById('sidebar');
  if (!sidebar) return;
  sidebar.style.display = sidebar.style.display === 'block' ? 'none' : 'block';
});
