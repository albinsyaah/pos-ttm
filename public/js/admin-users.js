// Add / edit user modal
const userModal = document.getElementById('userModal');
const userForm = document.getElementById('userForm');
const userFormMethod = document.getElementById('userFormMethod');
const userModalTitle = document.getElementById('userModalTitle');

const usernameInput = document.getElementById('username');
const passwordInput = document.getElementById('password');
const passwordHint = document.getElementById('passwordHint');
const employeeSelect = document.getElementById('employee_id');
const roleCheckboxes = document.querySelectorAll('.role-checkbox');
const isActiveInput = document.getElementById('is_active');
const activeRow = document.getElementById('activeRow');
const intendedActionInput = document.getElementById('intendedAction');
const intendedTitleInput = document.getElementById('intendedTitle');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

function resetRoleCheckboxes(selectedIds = []) {
  roleCheckboxes.forEach((cb) => {
    cb.checked = selectedIds.includes(cb.value);
  });
}

// Open "Add User"
document.getElementById('addUserBtn')?.addEventListener('click', (e) => {
  userForm.reset();
  userForm.action = e.currentTarget.dataset.action;
  userFormMethod.innerHTML = '';
  userModalTitle.textContent = 'Add User';
  intendedActionInput.value = e.currentTarget.dataset.action;
  intendedTitleInput.value = 'Add User';
  passwordInput.required = true;
  passwordHint.textContent = '';
  resetRoleCheckboxes([]);
  isActiveInput.checked = true;
  isActiveInput.disabled = false;
  activeRow.classList.remove('opacity-50');
  openModal(userModal);
  usernameInput?.focus();
});

// Open "Edit User" for each row
document.querySelectorAll('.edit-user-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    userForm.reset();
    userForm.action = btn.dataset.action;
    userFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    userModalTitle.textContent = 'Edit User';
    intendedActionInput.value = btn.dataset.action;
    intendedTitleInput.value = 'Edit User';

    usernameInput.value = btn.dataset.username || '';
    employeeSelect.value = btn.dataset.employeeId || '';

    passwordInput.required = false;
    passwordHint.textContent = '(leave blank to keep current password)';

    const selectedRoleIds = (btn.dataset.roleIds || '').split(',').filter(Boolean);
    resetRoleCheckboxes(selectedRoleIds);

    const isSelf = btn.dataset.isSelf === '1';
    isActiveInput.checked = btn.dataset.isActive === '1';
    isActiveInput.disabled = isSelf;
    activeRow.classList.toggle('opacity-50', isSelf);

    openModal(userModal);
    usernameInput?.focus();
  });
});

// If the form was just submitted and failed validation (e.g. no role picked),
// reopen the modal with what the user already entered instead of failing silently.
if (window.__userFormErrors && window.__userFormOld?.action) {
  const old = window.__userFormOld;

  userForm.action = old.action;
  intendedActionInput.value = old.action;

  if (old.method === 'PUT') {
    userFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    passwordInput.required = false;
    passwordHint.textContent = '(leave blank to keep current password)';
  } else {
    passwordInput.required = true;
    passwordHint.textContent = '';
  }

  userModalTitle.textContent = old.title || 'Add User';
  intendedTitleInput.value = old.title || 'Add User';
  usernameInput.value = old.username || '';
  employeeSelect.value = old.employee_id || '';
  resetRoleCheckboxes((old.roles || []).map(String));
  isActiveInput.checked = old.is_active === '1' || old.is_active === 1 || old.is_active === true;
  isActiveInput.disabled = false;
  activeRow.classList.remove('opacity-50');

  openModal(userModal);
  usernameInput?.focus();
}

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-user-btn').forEach((btn) => {
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

[userModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [userModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
