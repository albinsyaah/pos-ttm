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
