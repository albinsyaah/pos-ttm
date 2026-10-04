// Add / edit brand (Merk) modal
const brandModal = document.getElementById('brandModal');
const brandForm = document.getElementById('brandForm');
const brandFormMethod = document.getElementById('brandFormMethod');
const brandModalTitle = document.getElementById('brandModalTitle');

const brandNameInput = document.getElementById('name');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Merk"
document.getElementById('addBrandBtn')?.addEventListener('click', (e) => {
  brandForm.reset();
  brandForm.action = e.currentTarget.dataset.action;
  brandFormMethod.innerHTML = '';
  brandModalTitle.textContent = __t('Add Brand');
  openModal(brandModal);
  brandNameInput?.focus();
});

// Open "Edit Merk" for each row
document.querySelectorAll('.edit-brand-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    brandForm.reset();
    brandForm.action = btn.dataset.action;
    brandFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    brandModalTitle.textContent = __t('Edit Brand');

    brandNameInput.value = btn.dataset.name || '';

    openModal(brandModal);
    brandNameInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-brand-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    deleteForm.action = btn.dataset.action;
    deleteModalText.textContent = `"${btn.dataset.name}" will be permanently removed. This action cannot be undone.`;
    openModal(deleteModal);
  });
});

// Shared close handlers: close button, backdrop click, Escape key
document.querySelectorAll('.modal-close').forEach((btn) => {
  btn.addEventListener('click', () => {
    closeModal(btn.closest('.modal-overlay'));
  });
});

[brandModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [brandModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
