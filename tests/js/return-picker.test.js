const { JSDOM } = require('jsdom');
const fs = require('fs');
const assert = require('assert');
const root = require('path').join(__dirname, '../../public/js/');

function boot(html) {
  const dom = new JSDOM(`<!doctype html><body>${html}</body>`, { runScripts: 'outside-only', pretendToBeVisual: true, url: 'http://localhost/' });
  const w = dom.window;
  w.__t = (k) => ({ 'Max': 'Maks.', 'On invoice': 'Di nota', 'Returned': 'Sudah diretur', 'Select the invoice first': 'Pilih nota dulu', 'Loading...': 'Memuat...', 'Select': 'Pilih', 'Search product by code or name': 'Cari barang' }[k] || k);
  w.Element.prototype.scrollIntoView = () => {};
  return w;
}
const load = (w, f) => w.eval(fs.readFileSync(root + f, 'utf8'));
const tick = () => new Promise((r) => setTimeout(r, 0));
let passed = 0;
const ok = (name, fn) => Promise.resolve().then(fn).then(() => { passed++; console.log('ok  -', name); }).catch((e) => { console.log('FAIL-', name, '\n', e.message); process.exitCode = 1; });

(async () => {
  // ---- ProductPicker
  const pickerHtml = `<form id="f"><table><tbody><tr><td><select id="s" name="p" required class="item-product"><option value="">— Pilih —</option>
    <option value="1">000001 — Pupuk Urea</option><option value="2">000002 — Pupuk NPK Phonska</option><option value="3">000003 — Benih Padi</option></select></td></tr></tbody></table></form>`;
  await ok('picker: hides select, shows search box with hint', () => {
    const w = boot(pickerHtml); load(w, 'product-picker.js');
    const s = w.document.getElementById('s'); w.ProductPicker.enhance(s);
    const input = w.document.querySelector('.product-picker input');
    assert(input, 'input exists'); assert.strictEqual(input.placeholder, 'Cari barang'); assert.strictEqual(input.value, '');
    assert(s.style.opacity === '0');
  });
  await ok('picker: shows existing value as text', () => {
    const w = boot(pickerHtml); load(w, 'product-picker.js');
    const s = w.document.getElementById('s'); s.value = '2'; w.ProductPicker.enhance(s);
    assert.strictEqual(w.document.querySelector('.product-picker input').value, '000002 — Pupuk NPK Phonska');
  });
  await ok('picker: typing filters by name and code, all words must match', () => {
    const w = boot(pickerHtml); load(w, 'product-picker.js');
    const s = w.document.getElementById('s'); w.ProductPicker.enhance(s);
    const input = w.document.querySelector('.product-picker input');
    input.dispatchEvent(new w.Event('focus'));
    const typed = (q) => { input.value = q; input.dispatchEvent(new w.Event('input')); return [...w.document.querySelectorAll('.product-picker-list [role=option]')].map((e) => e.dataset.value); };
    assert.deepStrictEqual(typed('pupuk'), ['1', '2']);
    assert.deepStrictEqual(typed('npk pup'), ['2']);
    assert.deepStrictEqual(typed('000003'), ['3']);
    assert.deepStrictEqual(typed('zzz'), []);
    assert(w.document.querySelector('.product-picker-list').textContent.includes('No product found'));
  });
  await ok('picker: choosing sets the select and fires change; box shows the label', () => {
    const w = boot(pickerHtml); load(w, 'product-picker.js');
    const s = w.document.getElementById('s'); w.ProductPicker.enhance(s);
    let changes = 0; s.addEventListener('change', () => changes++);
    const input = w.document.querySelector('.product-picker input');
    input.dispatchEvent(new w.Event('focus')); input.value = 'benih'; input.dispatchEvent(new w.Event('input'));
    w.document.querySelector('.product-picker-list [role=option]').dispatchEvent(new w.MouseEvent('mousedown', { bubbles: true, cancelable: true }));
    assert.strictEqual(s.value, '3'); assert.strictEqual(changes, 1);
    assert.strictEqual(input.value, '000003 — Benih Padi');
    assert.strictEqual(w.document.querySelector('.product-picker-list'), null, 'list closed');
  });
  await ok('picker: keyboard - arrows then Enter picks, Enter never submits, Escape does not reach the dialog', () => {
    const w = boot(pickerHtml); load(w, 'product-picker.js');
    const s = w.document.getElementById('s'); w.ProductPicker.enhance(s);
    const input = w.document.querySelector('.product-picker input');
    let reachedDialog = false; w.document.addEventListener('keydown', (e) => { if (e.key === 'Escape') reachedDialog = true; });
    input.dispatchEvent(new w.Event('focus'));
    const key = (k) => { const e = new w.KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }); input.dispatchEvent(e); return e; };
    key('ArrowDown'); // 1 -> 2
    const enter = key('Enter');
    assert.strictEqual(enter.defaultPrevented, true); assert.strictEqual(s.value, '2');
    input.dispatchEvent(new w.Event('focus')); key('Escape');
    assert.strictEqual(reachedDialog, false);
    assert.strictEqual(w.document.querySelector('.product-picker-list'), null);
  });
  await ok('picker: emptying the box clears the product; stray text is put back', () => {
    const w = boot(pickerHtml); load(w, 'product-picker.js');
    const s = w.document.getElementById('s'); s.value = '1'; w.ProductPicker.enhance(s);
    const input = w.document.querySelector('.product-picker input');
    input.dispatchEvent(new w.Event('focus')); input.value = 'xyz'; input.dispatchEvent(new w.Event('input')); input.dispatchEvent(new w.Event('blur'));
    assert.strictEqual(s.value, '1'); assert.strictEqual(input.value, '000001 — Pupuk Urea');
    input.dispatchEvent(new w.Event('focus')); input.value = ''; input.dispatchEvent(new w.Event('input')); input.dispatchEvent(new w.Event('blur'));
    assert.strictEqual(s.value, ''); assert.strictEqual(input.value, '');
  });
  await ok('picker: enhance twice is harmless; refresh picks up new options', () => {
    const w = boot(pickerHtml); load(w, 'product-picker.js');
    const s = w.document.getElementById('s'); w.ProductPicker.enhance(s); w.ProductPicker.enhance(s);
    assert.strictEqual(w.document.querySelectorAll('.product-picker input').length, 1);
    s.add(new w.Option('000009 — Baru', '9')); w.ProductPicker.refresh(s);
    const input = w.document.querySelector('.product-picker input');
    input.dispatchEvent(new w.Event('focus')); input.value = 'baru'; input.dispatchEvent(new w.Event('input'));
    assert.strictEqual(w.document.querySelectorAll('.product-picker-list [role=option]').length, 1);
  });

  // ---- ReturnLines
  const returnHtml = `<select id="sale_id" data-lines-url="/transactions/sales-returns/__ID__/lines"><option value="">-</option><option value="10">N10</option><option value="11">N11</option><option value="12">N12</option></select>
    <table><tbody id="itemRows"></tbody></table>
    <template id="tpl"><tr class="item-row"><td><select class="item-product"><option value="">— Pilih —</option></select></td><td><input type="number" class="item-qty" min="1"><div class="item-hint hidden"></div></td></tr></template>`;
  const payloads = {
    '10': { returnable: true, lines: [{ product_id: 1, code: '000001', name: 'Pupuk Urea', original: 10, returned: 4, remaining: 6 }, { product_id: 2, code: '000002', name: 'Benih Padi', original: 3, returned: 3, remaining: 0 }] },
    '11': { returnable: true, lines: [{ product_id: 5, code: '000005', name: 'Cangkul', original: 2, returned: 0, remaining: 2 }] },
    '12': { returnable: false, message: 'Not received', lines: [] },
  };
  function returnPage(fetchImpl) {
    const w = boot(returnHtml);
    const calls = []; const toasts = [];
    w.showToast = (m) => toasts.push(m);
    w.fetch = fetchImpl || (async (url) => { calls.push(url); const id = url.match(/returns\/(\d+)\/lines/)[1]; return { ok: true, json: async () => payloads[id] }; });
    load(w, 'product-picker.js'); load(w, 'return-lines.js');
    const d = w.document;
    const lines = w.ReturnLines.create({ sourceSelect: d.getElementById('sale_id'), rowsBody: d.getElementById('itemRows') });
    const addRow = (values) => { const tb = d.createElement('tbody'); tb.innerHTML = d.getElementById('tpl').innerHTML.trim(); const row = tb.firstElementChild; d.getElementById('itemRows').appendChild(row); lines.attachRow(row, values); return row; };
    return { w, d, lines, addRow, calls, toasts };
  }
  const labels = (row) => [...row.querySelectorAll('.item-product option')].map((o) => o.textContent);

  await ok('returns: before an invoice is chosen the product box is disabled with a hint', () => {
    const p = returnPage(); const row = p.addRow();
    assert.strictEqual(row.querySelector('.item-product').disabled, true);
    assert.strictEqual(row.querySelector('.product-picker input').placeholder, 'Pilih nota dulu');
    assert.strictEqual(row.querySelector('.product-picker input').disabled, true);
  });
  await ok('returns: choosing an invoice lists only that invoice\'s products', async () => {
    const p = returnPage(); const row = p.addRow();
    await p.lines.load('10', null);
    assert.deepStrictEqual(labels(row), ['— Pilih —'.replace('— Pilih —', 'Pilih'), '000001 — Pupuk Urea', '000002 — Benih Padi']);
    assert.strictEqual(row.querySelector('.item-product').disabled, false);
    assert.strictEqual(p.calls[0], '/transactions/sales-returns/10/lines');
  });
  await ok('returns: picking a product limits the quantity to what may still go back and shows the numbers', async () => {
    const p = returnPage(); const row = p.addRow(); await p.lines.load('10', null);
    const s = row.querySelector('.item-product'); s.value = '1'; s.dispatchEvent(new p.w.Event('change', { bubbles: true }));
    const qty = row.querySelector('.item-qty');
    assert.strictEqual(qty.max, '6');
    assert.strictEqual(row.querySelector('.item-hint').textContent, 'Maks. 6 · Di nota 10 · Sudah diretur 4');
    qty.value = '9'; qty.dispatchEvent(new p.w.Event('input')); assert.strictEqual(qty.value, '6');
  });
  await ok('returns: a product with nothing left clears the quantity (max 0)', async () => {
    const p = returnPage(); const row = p.addRow(); await p.lines.load('10', null);
    row.querySelector('.item-qty').value = '2';
    const s = row.querySelector('.item-product'); s.value = '2'; s.dispatchEvent(new p.w.Event('change', { bubbles: true }));
    assert.strictEqual(row.querySelector('.item-qty').max, '0'); assert.strictEqual(row.querySelector('.item-qty').value, '');
  });
  await ok('returns: switching invoice keeps a product that is on both, clears one that is not', async () => {
    const p = returnPage(); const row = p.addRow(); await p.lines.load('10', null);
    const s = row.querySelector('.item-product'); s.value = '1'; s.dispatchEvent(new p.w.Event('change', { bubbles: true }));
    await p.lines.load('11', null);
    assert.strictEqual(s.value, ''); assert.deepStrictEqual(labels(row), ['Pilih', '000005 — Cangkul']);
    assert.strictEqual(row.querySelector('.item-qty').hasAttribute('max'), false);
  });
  await ok('returns: edit mode - rows come back with their product and quantity, exclude id is sent', async () => {
    const p = returnPage();
    await p.lines.load('10', '77');
    const row = p.addRow({ product_id: 1, qty: 3 });
    assert(p.calls[0].endsWith('/10/lines?exclude=77'), p.calls[0]);
    assert.strictEqual(row.querySelector('.item-product').value, '1');
    assert.strictEqual(row.querySelector('.item-qty').value, '3');
    assert.strictEqual(row.querySelector('.product-picker input').value, '000001 — Pupuk Urea');
  });
  await ok('returns: an invoice that cannot be returned shows the server message and disables the rows', async () => {
    const p = returnPage(); const row = p.addRow(); await p.lines.load('12', null);
    assert.deepStrictEqual(p.toasts, ['Not received']);
    assert.strictEqual(row.querySelector('.item-product').disabled, true);
    assert.strictEqual(row.querySelector('.product-picker input').placeholder, 'This invoice cannot be returned');
  });
  await ok('returns: a failed request is reported and leaves the box disabled', async () => {
    const p = returnPage(async () => ({ ok: false, status: 500, json: async () => ({}) })); const row = p.addRow();
    await p.lines.load('10', null);
    assert.strictEqual(p.toasts.length, 1); assert.strictEqual(row.querySelector('.item-product').disabled, true);
  });
  await ok('returns: a slow answer for an earlier invoice does not overwrite the later choice', async () => {
    let release; const slow = new Promise((r) => { release = r; });
    const p = returnPage(async (url) => { const id = url.match(/returns\/(\d+)\/lines/)[1]; if (id === '10') await slow; return { ok: true, json: async () => payloads[id] }; });
    const row = p.addRow();
    const first = p.lines.load('10', null); const second = p.lines.load('11', null);
    await second; release(); await first; await tick();
    assert.deepStrictEqual(labels(row), ['Pilih', '000005 — Cangkul']);
  });
  await ok('returns: clearing the invoice goes back to the "choose invoice" state', async () => {
    const p = returnPage(); const row = p.addRow(); await p.lines.load('10', null); await p.lines.load('', null);
    assert.strictEqual(row.querySelector('.item-product').disabled, true); assert.strictEqual(row.querySelector('.item-product').value, '');
  });

  console.log(`\n${passed} passed`);
})();
