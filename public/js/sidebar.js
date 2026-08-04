// Sidebar accordion open/close
document.querySelectorAll('.nav-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const group = btn.closest('.nav-group');
    const isOpen = group.classList.contains('open');
    group.classList.toggle('open', !isOpen);
    btn.setAttribute('aria-expanded', String(!isOpen));
  });
});

// Sidebar menu search — expands matching groups and hides the rest
const navSearch = document.getElementById('navSearch');
navSearch?.addEventListener('input', (e) => {
  const q = e.target.value.trim().toLowerCase();
  const allItems = document.querySelectorAll('#mainNav .sidebar-item');
  const allGroups = document.querySelectorAll('#mainNav .nav-group');

  if (!q) {
    allItems.forEach(i => i.classList.remove('nav-hidden'));
    allGroups.forEach(g => {
      g.classList.remove('open', 'nav-hidden');
      g.querySelector(':scope > .nav-toggle')?.setAttribute('aria-expanded', 'false');
    });
    return;
  }

  allItems.forEach(item => {
    const label = item.textContent.trim().toLowerCase();
    const isToggle = item.classList.contains('nav-toggle');
    const match = label.includes(q);
    item.classList.toggle('nav-hidden', !isToggle && !match);
  });

  allGroups.forEach(group => {
    const hasMatch = [...group.querySelectorAll('.sidebar-item:not(.nav-toggle)')]
      .some(i => i.textContent.trim().toLowerCase().includes(q));
    group.classList.toggle('open', hasMatch);
    group.classList.toggle('nav-hidden', !hasMatch);
    group.querySelector(':scope > .nav-toggle')?.setAttribute('aria-expanded', String(hasMatch));
  });
});
