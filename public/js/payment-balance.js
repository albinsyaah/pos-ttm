/*
 * Balance panel of the payment forms (receivable and payable): what is still
 * owed, what this payment takes off, and what is left afterwards.
 *
 *   PaymentBalance.money(n)              "Rp1,250.00"
 *   PaymentBalance.allocate(rows, pay)   pays the rows oldest first
 *   PaymentBalance.summarize(total, pay) { before, pay, after, over }
 *   PaymentBalance.render(panel, state)  fills the panel (see below)
 *
 * The panel is plain markup with data-bal="..." parts, so the same code
 * serves both pages: total, pay, after, over, rows (the invoice list).
 */
(function () {
  const round2 = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;

  function money(n) {
    return 'Rp' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /** Pay the invoices in the order given. Each row gains applied and after. */
  function allocate(rows, pay) {
    let left = Math.max(0, round2(pay));

    return rows.map((row) => {
      const applied = round2(Math.min(row.outstanding, left));
      left = round2(left - applied);

      return { ...row, applied, after: round2(row.outstanding - applied) };
    });
  }

  function summarize(total, pay) {
    const before = round2(total);
    const paid = Math.max(0, round2(pay));

    return {
      before,
      pay: paid,
      after: Math.max(0, round2(before - paid)),
      over: Math.max(0, round2(paid - before)),
    };
  }

  const part = (panel, name) => panel.querySelector(`[data-bal="${name}"]`);

  /**
   * state: { total, pay, texts: { over }, rows? }
   *   rows: open invoices [{ invoice_number, sale_date, outstanding }], shown with
   *         the amount left on each after this payment.
   */
  function render(panel, state) {
    if (!panel) return;

    const sum = summarize(state.total, state.pay);

    part(panel, 'total').textContent = money(sum.before);
    part(panel, 'pay').textContent = sum.pay > 0 ? `− ${money(sum.pay)}` : money(0);
    part(panel, 'after').textContent = money(sum.after);

    const over = part(panel, 'over');
    if (over) {
      over.textContent = sum.over > 0 ? String(state.texts.over).replace(':amount', money(sum.over)) : '';
      over.classList.toggle('hidden', sum.over <= 0);
    }

    const list = part(panel, 'rows');
    if (list) {
      list.innerHTML = '';
      const rows = allocate(state.rows || [], sum.pay);

      rows.forEach((row) => {
        const tr = document.createElement('tr');
        tr.className = 'border-t border-gray-100';

        [
          [row.invoice_number, ''],
          [row.sale_date || '', 'text-[var(--ink-400)]'],
          [money(row.outstanding), 'text-right'],
          [money(row.after), `text-right font-medium${row.after === 0 && row.applied > 0 ? ' text-[var(--good-600)]' : ''}`],
        ].forEach(([text, cls]) => {
          const td = document.createElement('td');
          td.className = `py-1 px-2 ${cls}`.trim();
          td.textContent = text;
          tr.appendChild(td);
        });

        list.appendChild(tr);
      });

      const table = list.closest('table');
      if (table) table.classList.toggle('hidden', rows.length === 0);
    }

    panel.classList.remove('hidden');
  }

  function hide(panel) {
    if (panel) panel.classList.add('hidden');
  }

  window.PaymentBalance = { money, allocate, summarize, render, hide };
})();
