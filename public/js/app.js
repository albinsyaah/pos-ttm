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
// Sidebar: overlay on mobile, collapsible on desktop
(() => {
  const sidebar  = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const menuBtn  = document.getElementById('menuBtn');
  if (!sidebar || !backdrop || !menuBtn) return;

  const desktop = window.matchMedia('(min-width: 1024px)');

  // --- mobile (overlay) ---
  const openMobile = () => {
    sidebar.classList.add('sidebar-open');
    backdrop.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  };
  const closeMobile = () => {
    sidebar.classList.remove('sidebar-open');
    backdrop.classList.add('hidden');
    document.body.style.overflow = '';
  };

  // --- desktop (collapse) ---
  const setCollapsed = (collapsed) => {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
  };
  try {
    if (localStorage.getItem('sidebarCollapsed') === '1') {
      document.body.classList.add('sidebar-collapsed');
    }
  } catch (e) {}

  menuBtn.addEventListener('click', () => {
    if (desktop.matches) {
      setCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    } else {
      sidebar.classList.contains('sidebar-open') ? closeMobile() : openMobile();
    }
  });

  backdrop.addEventListener('click', closeMobile);
  document.getElementById('sidebarClose')?.addEventListener('click', () => {
    desktop.matches ? setCollapsed(true) : closeMobile();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMobile(); });

  // Close the overlay after tapping a real link (mobile only)
  sidebar.querySelectorAll('a[href]').forEach(a =>
    a.addEventListener('click', () => { if (!desktop.matches) closeMobile(); })
  );

  // Crossing the breakpoint: reset overlay state
  desktop.addEventListener('change', closeMobile);
})();

// Profile / user menu dropdown (navbar)
const userMenuBtn = document.getElementById('userMenuBtn');
const userMenu = document.getElementById('userMenu');

userMenuBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  const isOpen = !userMenu.classList.contains('hidden');
  userMenu.classList.toggle('hidden', isOpen);
  userMenuBtn.setAttribute('aria-expanded', String(!isOpen));
});

// Language switcher dropdown (navbar)
const langMenuBtn = document.getElementById('langMenuBtn');
const langMenu = document.getElementById('langMenu');

langMenuBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  const isOpen = !langMenu.classList.contains('hidden');
  langMenu.classList.toggle('hidden', isOpen);
  langMenuBtn.setAttribute('aria-expanded', String(!isOpen));
});

document.addEventListener('click', (e) => {
  if (!userMenu || userMenu.classList.contains('hidden')) return;
  if (!userMenu.contains(e.target) && e.target !== userMenuBtn) {
    userMenu.classList.add('hidden');
    userMenuBtn?.setAttribute('aria-expanded', 'false');
  }
});

document.addEventListener('click', (e) => {
  if (!langMenu || langMenu.classList.contains('hidden')) return;
  if (!langMenu.contains(e.target) && e.target !== langMenuBtn) {
    langMenu.classList.add('hidden');
    langMenuBtn?.setAttribute('aria-expanded', 'false');
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && userMenu && !userMenu.classList.contains('hidden')) {
    userMenu.classList.add('hidden');
    userMenuBtn?.setAttribute('aria-expanded', 'false');
  }
  if (e.key === 'Escape' && langMenu && !langMenu.classList.contains('hidden')) {
    langMenu.classList.add('hidden');
    langMenuBtn?.setAttribute('aria-expanded', 'false');
  }
});
