// Inquiry (product lookup) page: debounced search + filter auto-submit with loading feedback
const inquiryForm = document.getElementById('inquiryFilterForm');
const inquirySearch = document.getElementById('inquirySearch');
const inquiryLoading = document.getElementById('inquiryLoading');
const inquiryFilters = [
  document.getElementById('inquiryBrand'),
  document.getElementById('inquiryItemType'),
  document.getElementById('inquiryWarehouse'),
];

function showLoading() {
  // NOTE: don't use the `disabled` attribute here — disabled fields are
  // excluded from the submitted form data, which would drop the value
  // that just changed. Dim the form visually instead.
  inquiryLoading?.classList.remove('hidden');
  inquiryForm?.classList.add('opacity-60', 'pointer-events-none');
}

// Filters submit immediately on change
inquiryFilters.forEach((el) => {
  el?.addEventListener('change', () => {
    showLoading();
    inquiryForm.submit();
  });
});

// Search box: debounce so it searches as the user types, without hammering the server
let searchDebounce;
inquirySearch?.addEventListener('input', () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    showLoading();
    inquiryForm.submit();
  }, 450);
});

// Submitting via Enter should not double-fire the debounced submit
inquiryForm?.addEventListener('submit', () => {
  clearTimeout(searchDebounce);
  showLoading();
});

// Price history: each product row has a hidden detail row, opened by its "Riwayat harga" button.
document.querySelectorAll('[data-history-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const row = document.querySelector(`[data-history-row="${button.dataset.historyToggle}"]`);
    if (!row) return;
    const open = row.classList.toggle('hidden') === false;
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
});

// Add / edit product modal (only on the page of users with inquiry.manage)
const inquiryModal = document.getElementById('inquiryModal');
const inquiryEditForm = document.getElementById('inquiryEditForm');

if (inquiryModal && inquiryEditForm) {
  const inquiryModalTitle = document.getElementById('inquiryModalTitle');
  const inquiryFormMethod = document.getElementById('inquiryFormMethod');
  const inquiryProductId = document.getElementById('inquiryProductId');
  const inquiryProductSelect = document.getElementById('inquiry_product_id');

  // Type a code or name to find the product (see product-picker.js)
  window.ProductPicker?.enhance(inquiryProductSelect);

  const openInquiryModal = (focusSearch = true) => {
    inquiryModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Into the search box, but only once the modal has finished its opening animation:
    // the result list is placed under the box, and a box that is still scaling would misplace it.
    if (focusSearch && !inquiryProductSelect.disabled) {
      setTimeout(() => {
        if (!inquiryModal.classList.contains('hidden')) inquiryProductSelect.focus();
      }, 250);
    }
  };

  const closeInquiryModal = () => {
    inquiryModal.classList.add('hidden');
    document.body.style.overflow = '';
  };

  const setField = (name, value) => {
    const field = inquiryEditForm.elements[name];
    if (field) field.value = value ?? '';
  };

  // Puts the form in "add" or "edit" mode: where it posts, and what the title says
  const setMode = (productId, action) => {
    inquiryEditForm.reset();
    inquiryEditForm.action = action;
    inquiryProductId.value = productId || '';
    inquiryFormMethod.innerHTML = productId ? '<input type="hidden" name="_method" value="PUT">' : '';
    inquiryModalTitle.textContent = productId ? inquiryModal.dataset.titleEdit : inquiryModal.dataset.titleAdd;

    // Editing: the product is fixed (it is in the address). Adding: the user picks it.
    inquiryProductSelect.value = productId || '';
    inquiryProductSelect.disabled = !!productId;
    window.ProductPicker?.sync(inquiryProductSelect);
  };

  // Puts the current data of a product into the form
  const fillForm = (data) => {
    setField('code', data.code);
    setField('unit_name', data.unit_name);
    setField('brand_id', data.brand_id);
    setField('item_type_id', data.item_type_id);
    Object.entries(data.prices || {}).forEach(([key, value]) => setField(`prices[${key}]`, value));
    inquiryEditForm.querySelectorAll('[name^="stock["]').forEach((input) => {
      input.value = (data.stock || {})[input.name.slice(6, -1)] ?? 0;
    });
  };

  const clearForm = () => {
    ['code', 'unit_name', 'brand_id', 'item_type_id', 'prices[purchase]', 'prices[retail]', 'prices[wholesale]']
      .forEach((name) => setField(name, ''));
    inquiryEditForm.querySelectorAll('[name^="stock["]').forEach((input) => { input.value = ''; });
  };

  // Adding: picking a product loads its current data, so only a changed value is saved
  inquiryProductSelect.addEventListener('change', async () => {
    const id = inquiryProductSelect.value;
    if (!id) return clearForm();

    try {
      const response = await fetch(inquiryModal.dataset.dataUrl.replace('__ID__', id), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (response.ok && inquiryProductSelect.value === id) fillForm(await response.json());
    } catch (e) {
      // the form stays as it is; the user can still type the values
    }
  });

  document.getElementById('addInquiryProductBtn')?.addEventListener('click', (e) => {
    setMode(null, e.currentTarget.dataset.action);
    openInquiryModal();
  });

  document.querySelectorAll('.edit-inquiry-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const d = btn.dataset;
      setMode(d.productId, d.action);

      setField('code', d.code);
      setField('unit_name', d.unitName);
      setField('brand_id', d.brandId);
      setField('item_type_id', d.itemTypeId);
      setField('prices[purchase]', d.pricePurchase);
      setField('prices[retail]', d.priceRetail);
      setField('prices[wholesale]', d.priceWholesale);

      // Current stock of every warehouse, so only a changed number is saved
      let stock = {};
      try { stock = JSON.parse(d.stock || '{}'); } catch (e) { stock = {}; }
      inquiryEditForm.querySelectorAll('[name^="stock["]').forEach((input) => {
        const warehouseId = input.name.slice(6, -1);
        input.value = stock[warehouseId] ?? 0;
      });

      openInquiryModal();
    });
  });

  // After a failed save the page reloads: reopen the form with what was typed
  if (window.INQUIRY_REOPEN) {
    const old = window.INQUIRY_REOPEN;
    const productId = old._product_id || null;
    setMode(productId, productId ? inquiryModal.dataset.updateUrl.replace('__ID__', productId) : inquiryEditForm.action);

    Object.entries(old).forEach(([key, value]) => {
      if (value !== null && typeof value === 'object') {
        Object.entries(value).forEach(([sub, subValue]) => setField(`${key}[${sub}]`, subValue));
      } else {
        setField(key, value);
      }
    });
    window.ProductPicker?.sync(inquiryProductSelect);

    openInquiryModal(false);
  }

  // Close: button, backdrop click, Escape
  inquiryModal.querySelectorAll('.modal-close').forEach((btn) => btn.addEventListener('click', closeInquiryModal));
  inquiryModal.addEventListener('click', (e) => {
    if (e.target === inquiryModal) closeInquiryModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !inquiryModal.classList.contains('hidden')) closeInquiryModal();
  });
}
