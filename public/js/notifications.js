// Navbar notification bell (dropdown) and the once-per-login due-date pop-up.
(function () {
  const btn = document.getElementById('notifBtn');
  const menu = document.getElementById('notifMenu');

  if (btn && menu) {
    const setOpen = (open) => {
      menu.classList.toggle('hidden', !open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      setOpen(menu.classList.contains('hidden'));
    });

    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target)) setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') setOpen(false);
    });
  }

  const popup = document.getElementById('duePopup');
  if (popup) {
    const close = () => popup.classList.add('hidden');
    document.getElementById('duePopupClose')?.addEventListener('click', close);
    popup.addEventListener('click', (e) => {
      if (e.target === popup) close();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') close();
    });
  }
})();
