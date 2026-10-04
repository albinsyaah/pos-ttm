// Run: npm i -D jsdom && node tests/js/payment-balance.test.js
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const root = path.join(__dirname, '../../public/js/');

const tick = () => new Promise((r) => setTimeout(r, 0));
let passed = 0;
const ok = (name, fn) => Promise.resolve().then(fn).then(() => { passed++; console.log('ok  -', name); }).catch((e) => { console.log('FAIL-', name, '\n', e.message); process.exitCode = 1; });

const panelHtml = `<div id="balancePanel" class="hidden">
  <div data-bal="invoice-block"><b data-bal="total"></b><i data-bal="pay"></i><b data-bal="after"></b><p data-bal="over" class="hidden"></p></div>
  <b data-bal="supplier-total"></b><b data-bal="supplier-after"></b>
  <table><tbody data-bal="rows"></tbody></table></div>`;

function boot(html) {
  const dom = new JSDOM(`<!doctype html><body>${html}</body>`, { runScripts: 'outside-only', url: 'http://localhost/' });
  const w = dom.window;
  w.eval(fs.readFileSync(root + 'payment-balance.js', 'utf8'));
  return w;
}
const text = (w, name) => w.document.querySelector(`[data-bal="${name}"]`).textContent;

(async () => {
  await ok('money: Rp prefix with two decimals', () => {
    const w = boot('');
    assert.strictEqual(w.PaymentBalance.money(1250.5), 'Rp1,250.50');
    assert.strictEqual(w.PaymentBalance.money(0), 'Rp0.00');
    assert.strictEqual(w.PaymentBalance.money(undefined), 'Rp0.00');
  });

  await ok('summarize: reduction, floor at zero, overpayment reported', () => {
    const { summarize } = boot('').PaymentBalance;
    assert.deepStrictEqual(JSON.parse(JSON.stringify(summarize(1000, 400))), { before: 1000, pay: 400, after: 600, over: 0 });
    assert.deepStrictEqual(JSON.parse(JSON.stringify(summarize(1000, 1000))), { before: 1000, pay: 1000, after: 0, over: 0 });
    assert.deepStrictEqual(JSON.parse(JSON.stringify(summarize(1000, 1250.25))), { before: 1000, pay: 1250.25, after: 0, over: 250.25 });
    assert.strictEqual(summarize(1000, -5).pay, 0);
    assert.strictEqual(summarize(0.1 + 0.2, 0.3).after, 0); // no floating point crumbs
  });

  await ok('allocate: pays the oldest invoice first and carries the rest on', () => {
    const { allocate } = boot('').PaymentBalance;
    const rows = [{ outstanding: 500 }, { outstanding: 300 }, { outstanding: 200 }];
    const out = allocate(rows, 650).map((r) => [r.applied, r.after]);
    assert.deepStrictEqual(out, [[500, 0], [150, 150], [0, 200]]);
    assert.deepStrictEqual(allocate(rows, 0).map((r) => r.applied), [0, 0, 0]);
    assert.deepStrictEqual(allocate(rows, 99999).map((r) => r.after), [0, 0, 0]);
  });

  await ok('render: shows total, payment, what is left, and the invoice breakdown', () => {
    const w = boot(panelHtml);
    const panel = w.document.getElementById('balancePanel');
    w.PaymentBalance.render(panel, {
      total: 800, pay: 500, texts: { over: 'Lebih :amount' },
      rows: [{ invoice_number: 'N1', sale_date: '01 Sep 2026', outstanding: 500 }, { invoice_number: 'N2', sale_date: '10 Sep 2026', outstanding: 300 }],
    });
    assert(!panel.classList.contains('hidden'));
    assert.strictEqual(text(w, 'total'), 'Rp800.00');
    assert.strictEqual(text(w, 'pay'), '− Rp500.00');
    assert.strictEqual(text(w, 'after'), 'Rp300.00');
    const rows = [...w.document.querySelectorAll('[data-bal="rows"] tr')].map((tr) => [...tr.children].map((td) => td.textContent));
    assert.deepStrictEqual(rows, [['N1', '01 Sep 2026', 'Rp500.00', 'Rp0.00'], ['N2', '10 Sep 2026', 'Rp300.00', 'Rp300.00']]);
    assert(w.document.querySelector('[data-bal="over"]').classList.contains('hidden'));
  });

  await ok('render: paying more than owed shows the warning with the excess', () => {
    const w = boot(panelHtml);
    w.PaymentBalance.render(w.document.getElementById('balancePanel'), { total: 100, pay: 130, texts: { over: 'Lebih :amount.' }, rows: [] });
    assert.strictEqual(text(w, 'after'), 'Rp0.00');
    assert.strictEqual(text(w, 'over'), 'Lebih Rp30.00.');
    assert(!w.document.querySelector('[data-bal="over"]').classList.contains('hidden'));
  });

  await ok('render: no payment typed leaves the balance untouched', () => {
    const w = boot(panelHtml);
    w.PaymentBalance.render(w.document.getElementById('balancePanel'), { total: 100, pay: 0, texts: { over: '' }, rows: [{ invoice_number: 'N1', sale_date: '', outstanding: 100 }] });
    assert.strictEqual(text(w, 'pay'), 'Rp0.00');
    assert.strictEqual(text(w, 'after'), 'Rp100.00');
  });

  await ok('render: works when the panel has no invoice table (payable page)', () => {
    const w = boot(`<div id="p" class="hidden"><b data-bal="total"></b><i data-bal="pay"></i><b data-bal="after"></b></div>`);
    w.PaymentBalance.render(w.document.getElementById('p'), { total: 10, pay: 4, texts: { over: '' } });
    assert.strictEqual(text(w, 'after'), 'Rp6.00');
  });

  // ---------- the receivable page glue ----------
  const pageHtml = `<form id="receivablePaymentForm" data-outstanding-url="/ar/outstanding" data-text-over="Lebih :amount" data-text-failed="Gagal">
    <div id="receivablePaymentFormMethod"></div><input id="payment_number"><input id="payment_date">
    <select id="customer_id"><option value=""></option><option value="5">C5</option><option value="6">C6</option></select>
    <select id="payment_method"><option value="1">Tunai</option></select><input id="amount" type="number">
    ${panelHtml}</form><div id="receivablePaymentModal" class="hidden"><h3 id="receivablePaymentModalTitle"></h3></div><div id="deleteModal" class="hidden"></div>
    <form id="deleteForm"></form><p id="deleteModalText"></p>
    <button id="addReceivablePaymentBtn" data-action="/ar"></button>
    <button class="edit-receivable-payment-btn" data-action="/ar/9" data-customer-id="5" data-payment-id="9" data-payment-method-id="1" data-amount="400"></button>`;

  function page(fetchImpl) {
    const dom = new JSDOM(`<!doctype html><body>${pageHtml}</body>`, { runScripts: 'outside-only', url: 'http://localhost/' });
    const w = dom.window;
    const calls = []; const toasts = [];
    w.__t = (k) => k;
    w.showToast = (m) => toasts.push(m);
    w.fetch = fetchImpl || (async (url) => { calls.push(url); return { ok: true, json: async () => ({ total: 800, invoices: [{ invoice_number: 'N1', sale_date: '01 Sep 2026', outstanding: 500 }, { invoice_number: 'N2', sale_date: '10 Sep 2026', outstanding: 300 }] }) }; });
    w.eval(fs.readFileSync(root + 'payment-balance.js', 'utf8'));
    w.eval(fs.readFileSync(root + 'transactions-receivable-payments.js', 'utf8'));
    const d = w.document;
    const choose = async (id) => { d.getElementById('customer_id').value = id; d.getElementById('customer_id').dispatchEvent(new w.Event('change')); await tick(); await tick(); };
    const type = (v) => { const a = d.getElementById('amount'); a.value = v; a.dispatchEvent(new w.Event('input')); };
    return { w, d, calls, toasts, choose, type, panel: d.getElementById('balancePanel') };
  }

  await ok('receivable page: choosing a customer shows the total owed and the invoices', async () => {
    const p = page(); await p.choose('5');
    assert(!p.panel.classList.contains('hidden'));
    assert.strictEqual(text(p.w, 'total'), 'Rp800.00');
    assert.strictEqual(text(p.w, 'after'), 'Rp800.00');
    assert.strictEqual(p.d.querySelectorAll('[data-bal="rows"] tr').length, 2);
    assert(p.calls[0].startsWith('/ar/outstanding?customer_id=5'));
  });

  await ok('receivable page: typing an amount shows the reduction on the total and on each invoice', async () => {
    const p = page(); await p.choose('5'); p.type('650');
    assert.strictEqual(text(p.w, 'pay'), '− Rp650.00');
    assert.strictEqual(text(p.w, 'after'), 'Rp150.00');
    const after = [...p.d.querySelectorAll('[data-bal="rows"] tr')].map((tr) => tr.children[3].textContent);
    assert.deepStrictEqual(after, ['Rp0.00', 'Rp150.00']);
  });

  await ok('receivable page: paying more than owed warns', async () => {
    const p = page(); await p.choose('5'); p.type('1000');
    assert.strictEqual(text(p.w, 'over'), 'Lebih Rp200.00');
    assert.strictEqual(text(p.w, 'after'), 'Rp0.00');
  });

  await ok('receivable page: clearing the customer hides the panel', async () => {
    const p = page(); await p.choose('5'); await p.choose('');
    assert(p.panel.classList.contains('hidden'));
  });

  await ok('receivable page: editing a payment asks the server to leave that payment out', async () => {
    const p = page();
    p.d.querySelector('.edit-receivable-payment-btn').click(); await tick(); await tick();
    assert(p.calls[0].includes('customer_id=5') && p.calls[0].includes('payment_id=9'), p.calls[0]);
    assert.strictEqual(p.d.getElementById('amount').value, '400');
    assert.strictEqual(text(p.w, 'pay'), '− Rp400.00');   // the edited amount shows as this payment
    assert.strictEqual(text(p.w, 'after'), 'Rp400.00');
  });

  await ok('receivable page: a failed request tells the person and hides the panel', async () => {
    const p = page(async () => ({ ok: false, status: 500, json: async () => ({}) })); await p.choose('5');
    assert.deepStrictEqual(p.toasts, ['Gagal']);
    assert(p.panel.classList.contains('hidden'));
  });

  await ok('receivable page: a slow answer for an earlier customer does not overwrite the later one', async () => {
    let release; const slow = new Promise((r) => { release = r; });
    const p = page(async (url) => {
      if (url.includes('customer_id=5')) await slow;
      return { ok: true, json: async () => ({ total: url.includes('customer_id=5') ? 111 : 222, invoices: [] }) };
    });
    const first = p.choose('5'); const second = p.choose('6');
    await second; release(); await first; await tick();
    assert.strictEqual(text(p.w, 'total'), 'Rp222.00');
  });

  // ---------- the payable page glue ----------
  const apHtml = `<form id="payablePaymentForm" data-invoices-url="/ap/invoices" data-text-choose-supplier="Pilih supplier" data-text-choose-invoice="Pilih faktur" data-text-no-open="Tidak ada" data-text-no-invoice="Tanpa faktur" data-text-due="Jatuh tempo" data-text-outstanding="Sisa" data-text-failed="Gagal" data-text-over="Lebih :amount">
    <div id="payablePaymentFormMethod"></div><input id="payment_number"><input id="payment_date">
    <select id="supplier_id"><option value=""></option><option value="3">S3</option></select>
    <select id="purchase_id" disabled></select><p id="invoiceHint"></p>
    <select id="payment_method"><option value="1">Tunai</option></select><input id="amount" type="number">
    ${panelHtml}</form><div id="payablePaymentModal" class="hidden"><h3 id="payablePaymentModalTitle"></h3></div><div id="deleteModal" class="hidden"></div>
    <form id="deleteForm"></form><p id="deleteModalText"></p><button id="addPayablePaymentBtn" data-action="/ap"></button>`;

  function apPage() {
    const dom = new JSDOM(`<!doctype html><body>${apHtml}</body>`, { runScripts: 'outside-only', url: 'http://localhost/' });
    const w = dom.window; w.__t = (k) => k; w.showToast = () => {};
    w.fetch = async () => ({ ok: true, json: async () => ({ invoices: [
      { id: 11, invoice_number: 'B1', due_date: '01 Nov 2026', days_left: 20, net: 1000, paid: 0, outstanding: 1000 },
      { id: 12, invoice_number: 'B2', due_date: '15 Nov 2026', days_left: 30, net: 500, paid: 0, outstanding: 500 }] }) });
    w.eval(fs.readFileSync(root + 'payment-balance.js', 'utf8'));
    w.eval(fs.readFileSync(root + 'transactions-payable-payments.js', 'utf8'));
    return w;
  }
  const apChoose = async (w, supplier) => { const s = w.document.getElementById('supplier_id'); s.value = supplier; s.dispatchEvent(new w.Event('change')); await tick(); await tick(); };
  const apInvoice = (w, id) => { const s = w.document.getElementById('purchase_id'); s.value = id; s.dispatchEvent(new w.Event('change')); };
  const apType = (w, v) => { const a = w.document.getElementById('amount'); a.value = v; a.dispatchEvent(new w.Event('input')); };
  const hidden = (w, name) => w.document.querySelector(`[data-bal="${name}"]`).closest('[data-bal="invoice-block"]')?.classList.contains('hidden');

  await ok('payable page: choosing a supplier shows the total owed to them; the invoice block waits for an invoice', async () => {
    const w = apPage(); await apChoose(w, '3');
    assert(!w.document.getElementById('balancePanel').classList.contains('hidden'));
    assert.strictEqual(text(w, 'supplier-total'), 'Rp1,500.00');
    assert.strictEqual(text(w, 'supplier-after'), 'Rp1,500.00');
    assert(w.document.querySelector('[data-bal="invoice-block"]').classList.contains('hidden'));
  });

  await ok('payable page: choosing an invoice shows its balance and, with an amount, the reduction on both', async () => {
    const w = apPage(); await apChoose(w, '3'); apInvoice(w, '11');
    assert(!w.document.querySelector('[data-bal="invoice-block"]').classList.contains('hidden'));
    assert.strictEqual(text(w, 'total'), 'Rp1,000.00');
    assert.strictEqual(w.document.getElementById('amount').value, '1000'); // suggests the full balance
    apType(w, '250');
    assert.strictEqual(text(w, 'pay'), '− Rp250.00');
    assert.strictEqual(text(w, 'after'), 'Rp750.00');
    assert.strictEqual(text(w, 'supplier-after'), 'Rp1,250.00');
  });

  await ok('payable page: more than the invoice balance warns', async () => {
    const w = apPage(); await apChoose(w, '3'); apInvoice(w, '12'); apType(w, '800');
    assert.strictEqual(text(w, 'over'), 'Lebih Rp300.00');
  });

  await ok('payable page: opening "add" hides the panel', async () => {
    const w = apPage(); await apChoose(w, '3');
    w.document.getElementById('addPayablePaymentBtn').click();
    assert(w.document.getElementById('balancePanel').classList.contains('hidden'));
  });

  console.log(`\n${passed} passed`);
})();
