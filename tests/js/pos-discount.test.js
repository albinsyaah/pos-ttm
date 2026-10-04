// Run: npm i -D jsdom && node tests/js/pos-discount.test.js
// Drives public/js/transactions-point-of-sale-new.js against a minimal copy of the terminal's markup.
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const script = fs.readFileSync(path.join(__dirname, '../../public/js/transactions-point-of-sale-new.js'), 'utf8');

let passed = 0;
const ok = (name, fn) => Promise.resolve().then(fn).then(() => { passed++; console.log('ok  -', name); })
  .catch((e) => { console.log('FAIL-', name, '\n', e.message); process.exitCode = 1; });

const html = `<form id="posNewForm">
  <input id="productSearch"><div id="searchResults" class="hidden"></div>
  <table><tbody id="itemRows"></tbody></table><p id="noItemsMessage"></p><span id="cartCount"></span>
  <template id="itemRowTemplate"><tr class="item-row">
    <td><input type="hidden" class="item-product"><span class="item-name"></span><span class="item-free-badge hidden"></span>
      <span class="item-code"></span><span class="item-stock"></span><div class="item-error hidden"></div><button type="button" class="add-free-btn hidden"></button></td>
    <td><button type="button" class="qty-minus"></button><input type="number" class="item-qty"><button type="button" class="qty-plus"></button></td>
    <td><input type="number" class="item-price"><span class="item-price-hint"></span></td>
    <td class="item-line-total"></td><td><button type="button" class="remove-item-btn"></button></td></tr></template>
  <input id="sale_date" value="2026-10-01">
  <input type="radio" name="payment_type" value="cash" checked><input type="radio" name="payment_type" value="credit">
  <p id="creditNote" class="hidden"></p><div id="methodField"><select id="payment_method_id"><option value="1">Tunai</option></select></div>
  <label id="customerLabel" data-cash="a" data-credit="b"></label><select id="customer_id"><option value=""></option></select>
  <span id="subtotalValue"></span>
  <input id="discount_percent" name="discount_percent" type="number"><input id="discount_amount" name="discount_amount" type="number">
  <p id="discountError" class="hidden"></p><div id="discountRow" class="hidden"><strong id="discountValue"></strong></div>
  <span id="grandTotal"></span><button id="submitBtn" type="submit"><span id="submitLabel"></span></button></form>`;

const LABELS = {
  discount_percent_max: 'Diskon persen tidak boleh lebih dari 100.',
  discount_too_large_row: 'Diskon :discount melebihi subtotal :subtotal.',
  cart_empty_toast: 'Kosong', fix_cart: 'Perbaiki', stock: 'Stok', lines: ':count baris',
};

/** Boot the terminal with one product in the cart: 2 x 50,000 (subtotal 100,000). */
function boot(seed = [{ id: 1, code: 'P1', name: 'Urea', unit: 'pcs', stock: 100, stock_label: '100 pcs', prices: [], qty: 2, price: 50000 }]) {
  const dom = new JSDOM(`<!doctype html><body>${html}</body>`, { runScripts: 'outside-only', url: 'http://localhost/' });
  const w = dom.window;
  w.POS_LABELS = LABELS;
  w.POS_SEARCH_URL = '/x';
  w.POS_CART_SEED = seed;
  const toasts = [];
  w.showToast = (m) => toasts.push(m);
  w.eval(script);
  const $ = (id) => w.document.getElementById(id);
  const type = (id, value) => { $(id).value = String(value); $(id).dispatchEvent(new w.Event('input', { bubbles: true })); };
  const submit = () => { const e = new w.Event('submit', { cancelable: true, bubbles: true }); $('posNewForm').dispatchEvent(e); return e.defaultPrevented; };
  return { w, $, type, submit, toasts };
}

(async () => {
  await ok('no discount: total equals subtotal and the discount row stays hidden', () => {
    const { $ } = boot();
    assert.strictEqual($('subtotalValue').textContent, '100,000.00');
    assert.strictEqual($('grandTotal').textContent, '100,000.00');
    assert.ok($('discountRow').classList.contains('hidden'));
  });

  await ok('percentage only', () => {
    const { $, type } = boot();
    type('discount_percent', 10);
    assert.strictEqual($('discountValue').textContent, '-10,000.00');
    assert.strictEqual($('grandTotal').textContent, '90,000.00');
    assert.ok(!$('discountRow').classList.contains('hidden'));
  });

  await ok('fixed amount only (Rp 10 thousand)', () => {
    const { $, type } = boot();
    type('discount_amount', 10000);
    assert.strictEqual($('discountValue').textContent, '-10,000.00');
    assert.strictEqual($('grandTotal').textContent, '90,000.00');
  });

  await ok('both together are taken from the same subtotal', () => {
    const { $, type } = boot();
    type('discount_percent', 10);
    type('discount_amount', 5000);
    assert.strictEqual($('discountValue').textContent, '-15,000.00');
    assert.strictEqual($('grandTotal').textContent, '85,000.00');
  });

  await ok('changing the cart changes a percentage discount', () => {
    const { $, type, w } = boot();
    type('discount_percent', 10);
    $('itemRows').querySelector('.qty-plus').dispatchEvent(new w.Event('click'));   // 3 x 50,000 = 150,000
    assert.strictEqual($('subtotalValue').textContent, '150,000.00');
    assert.strictEqual($('grandTotal').textContent, '135,000.00');
  });

  await ok('a discount larger than the subtotal shows an error and blocks sending', () => {
    const { $, type, submit, toasts } = boot();
    type('discount_amount', 100001);
    assert.ok(!$('discountError').classList.contains('hidden'));
    assert.ok($('discountError').textContent.includes('melebihi subtotal'));
    assert.strictEqual($('grandTotal').textContent, '100,000.00');   // nothing is taken off while it is invalid
    assert.strictEqual(submit(), true);
    assert.strictEqual(toasts.length, 1);
    assert.strictEqual(toasts[0], $('discountError').textContent);
  });

  await ok('percentage above 100 shows its own message', () => {
    const { $, type, submit } = boot();
    type('discount_percent', 101);
    assert.strictEqual($('discountError').textContent, LABELS.discount_percent_max);
    assert.ok($('discount_percent').classList.contains('is-invalid'));
    assert.strictEqual(submit(), true);
  });

  await ok('the error clears once the discount is acceptable, and the sale can be sent', () => {
    const { $, type, submit } = boot();
    type('discount_amount', 100001);
    type('discount_amount', 100000);   // exactly the subtotal is allowed
    assert.ok($('discountError').classList.contains('hidden'));
    assert.strictEqual($('grandTotal').textContent, '0.00');
    assert.strictEqual(submit(), false);
  });

  await ok('empty or junk boxes count as no discount', () => {
    const { $, type } = boot();
    type('discount_percent', '');
    type('discount_amount', -5);
    assert.strictEqual($('grandTotal').textContent, '100,000.00');
    assert.ok($('discountError').classList.contains('hidden'));
  });

  await ok('free lines do not count towards the subtotal', () => {
    const { $, type } = boot([
      { id: 1, code: 'P1', name: 'Urea', unit: 'pcs', stock: 100, stock_label: '100 pcs', prices: [], qty: 2, price: 50000 },
      { id: 1, code: 'P1', name: 'Urea', unit: 'pcs', stock: 100, stock_label: '100 pcs', prices: [], qty: 1, price: 0 },
    ]);
    type('discount_percent', 10);
    assert.strictEqual($('subtotalValue').textContent, '100,000.00');
    assert.strictEqual($('grandTotal').textContent, '90,000.00');
  });

  await ok('values restored from a refused sale are applied on load', () => {
    const dom = new JSDOM(`<!doctype html><body>${html.replace('id="discount_percent" name="discount_percent" type="number"', 'id="discount_percent" name="discount_percent" type="number" value="12.5"').replace('id="discount_amount" name="discount_amount" type="number"', 'id="discount_amount" name="discount_amount" type="number" value="2000"')}</body>`, { runScripts: 'outside-only', url: 'http://localhost/' });
    const w = dom.window;
    w.POS_LABELS = LABELS; w.POS_SEARCH_URL = '/x'; w.showToast = () => {};
    w.POS_CART_SEED = [{ id: 1, code: 'P1', name: 'Urea', unit: 'pcs', stock: 100, stock_label: '100 pcs', prices: [], qty: 2, price: 50000 }];
    w.eval(script);
    // 12.5% of 100,000 = 12,500 + 2,000
    assert.strictEqual(w.document.getElementById('discountValue').textContent, '-14,500.00');
    assert.strictEqual(w.document.getElementById('grandTotal').textContent, '85,500.00');
  });

  console.log(`\n${passed} passed`);
})();
