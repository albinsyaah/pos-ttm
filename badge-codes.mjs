// One-off script: wraps code / document-number cells in <span class="code-badge">.
// Usage:  node badge-codes.mjs --dry   (preview)
//         node badge-codes.mjs         (apply)
import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'resources/views';
const DRY = process.argv.includes('--dry');

const FIELDS = [
  'code', 'asset_code', 'account_code', 'product_code',
  'so_number', 'po_number', 'invoice_number', 'mutation_number',
  'return_number', 'payment_number', 'reference_number', 'document_number',
];
const f = FIELDS.join('|');
// $x->code, $x->purchase?->invoice_number, $row['document_number']
const FIELD_EXPR = new RegExp(`(->|\\?->)(${f})\\s*(\\?:\\s*'[^']*')?$|\\[['"](${f})['"]\\]\\s*(\\?:\\s*'[^']*')?$`);

// <td class="..."> {{ expr }} </td>   (single line, nothing else inside)
const TD = /<td class="([^"]*)">(\s*)\{\{\s*([^{}]+?)\s*\}\}(\s*)<\/td>/g;

function walk(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => {
    const p = path.join(dir, e.name);
    return e.isDirectory() ? walk(p) : p.endsWith('.blade.php') ? [p] : [];
  });
}

let totalFiles = 0, totalCells = 0;
for (const file of walk(ROOT)) {
  const src = fs.readFileSync(file, 'utf8');
  let count = 0;
  const out = src.replace(TD, (m, cls, sp1, expr, sp2) => {
    if (!FIELD_EXPR.test(expr.trim())) return m;
    count++;
    // badge carries its own colour/weight -> drop them from the <td>
    const tdCls = cls.split(/\s+/)
      .filter(c => c && c !== 'font-medium' && !/^text-\[var\(--ink-\d+\)\]$/.test(c))
      .join(' ');
    const open = tdCls ? `<td class="${tdCls}">` : '<td>';
    const fallback = expr.match(/^(.*?)\s*\?:\s*('[^']*')\s*$/);
    if (fallback) {
      const base = fallback[1];
      const dash = fallback[2].slice(1, -1);
      return `${open}@if(${base})<span class="code-badge">{{ ${base} }}</span>@else<span class="text-[var(--ink-400)]">${dash}</span>@endif</td>`;
    }
    return `${open}<span class="code-badge">{{ ${expr} }}</span></td>`;
  });
  if (count) {
    totalFiles++; totalCells += count;
    console.log(`${DRY ? '[dry] ' : ''}${file}  (${count})`);
    if (!DRY) fs.writeFileSync(file, out);
  }
}
console.log(`\n${totalCells} cells in ${totalFiles} files ${DRY ? '(nothing written)' : 'updated'}.`);
