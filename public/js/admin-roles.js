// Add / edit role modal
const roleModal = document.getElementById('roleModal');
const roleForm = document.getElementById('roleForm');
const roleFormMethod = document.getElementById('roleFormMethod');
const roleModalTitle = document.getElementById('roleModalTitle');

const roleNameInput = document.getElementById('name');
const permissionCheckboxes = document.querySelectorAll('.permission-checkbox');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

function resetPermissionCheckboxes(selected = []) {
  permissionCheckboxes.forEach((cb) => {
    cb.checked = selected.includes(cb.value);
  });
}

// Open "Add Role"
document.getElementById('addRoleBtn')?.addEventListener('click', (e) => {
  roleForm.reset();
  roleForm.action = e.currentTarget.dataset.action;
  roleFormMethod.innerHTML = '';
  roleModalTitle.textContent = 'Add Role';
  roleNameInput.disabled = false;
  resetPermissionCheckboxes([]);
  permissionCheckboxes.forEach((cb) => (cb.disabled = false));
  openModal(roleModal);
  roleNameInput?.focus();
});

// Open "Edit Role" for each row
document.querySelectorAll('.edit-role-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    roleForm.reset();
    roleForm.action = btn.dataset.action;
    roleFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    roleModalTitle.textContent = `Edit Role — ${btn.dataset.name}`;

    roleNameInput.value = btn.dataset.name || '';

    const selectedPermissions = (btn.dataset.permissions || '').split(',').filter(Boolean);
    resetPermissionCheckboxes(selectedPermissions);

    const isProtected = btn.dataset.protected === '1';
    roleNameInput.disabled = isProtected;
    // Super Admin always keeps every permission (enforced server-side too).
    permissionCheckboxes.forEach((cb) => {
      if (isProtected) cb.checked = true;
      cb.disabled = isProtected;
    });

    openModal(roleModal);
    if (!isProtected) roleNameInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-role-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    deleteForm.action = btn.dataset.action;
    deleteModalText.textContent = `"${btn.dataset.name}" will be permanently removed. Users on this role must be reassigned first.`;
    openModal(deleteModal);
  });
});

// Shared close handlers: close button, backdrop click, Escape key
document.querySelectorAll('.modal-close').forEach((btn) => {
  btn.addEventListener('click', () => {
    closeModal(btn.closest('.modal-overlay'));
  });
});

[roleModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [roleModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
