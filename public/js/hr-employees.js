// Add / edit employee modal
const employeeModal = document.getElementById('employeeModal');
const employeeForm = document.getElementById('employeeForm');
const employeeFormMethod = document.getElementById('employeeFormMethod');
const employeeModalTitle = document.getElementById('employeeModalTitle');

const codeInput = document.getElementById('code');
const nameInput = document.getElementById('name');
const positionInput = document.getElementById('position');
const phoneInput = document.getElementById('phone');

function openModal(modal) {
  modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
  modal.classList.add('hidden');
  document.body.style.overflow = '';
}

// Open "Add Employee"
document.getElementById('addEmployeeBtn')?.addEventListener('click', (e) => {
  employeeForm.reset();
  employeeForm.action = e.currentTarget.dataset.action;
  employeeFormMethod.innerHTML = '';
  employeeModalTitle.textContent = __t('Add Employee');
  openModal(employeeModal);
  codeInput?.focus();
});

// Open "Edit Employee" for each row
document.querySelectorAll('.edit-employee-btn').forEach((btn) => {
  btn.addEventListener('click', () => {
    employeeForm.reset();
    employeeForm.action = btn.dataset.action;
    employeeFormMethod.innerHTML = '<input type="hidden" name="_method" value="PUT">';
    employeeModalTitle.textContent = __t('Edit Employee');

    codeInput.value = btn.dataset.code || '';
    nameInput.value = btn.dataset.name || '';
    positionInput.value = btn.dataset.position || '';
    phoneInput.value = btn.dataset.phone || '';

    openModal(employeeModal);
    codeInput?.focus();
  });
});

// Delete confirmation modal
const deleteModal = document.getElementById('deleteModal');
const deleteForm = document.getElementById('deleteForm');
const deleteModalText = document.getElementById('deleteModalText');

document.querySelectorAll('.delete-employee-btn').forEach((btn) => {
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

[employeeModal, deleteModal].forEach((modal) => {
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  [employeeModal, deleteModal].forEach((modal) => {
    if (modal && !modal.classList.contains('hidden')) closeModal(modal);
  });
});
