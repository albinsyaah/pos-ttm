/*
 * Pre-fills the unit price of an item row with the product's selling price
 * (harga jual, category "Retail") that applies on the form's date.
 *
 * The page passes the dated price book as JSON:
 *   { "<product_id>": [{ "date": "YYYY-MM-DD", "amount": 25000 }, ...] }   newest first
 *
 *   SellingPrice.suggest(row, book, date)
 *     Fills .item-price of the row from its .item-product. A price the user typed
 *     (or one saved on an existing line) is never overwritten: only an empty price,
 *     or one this helper filled in earlier, is replaced. Returns true if it changed.
 */
(function () {
  function priceOn(book, productId, date) {
    const day = date || new Date().toISOString().slice(0, 10);
    const hit = (book[productId] || []).find((entry) => entry.date <= day);
    return hit ? String(hit.amount) : '';
  }

  function suggest(row, book, date) {
    const product = row.querySelector('.item-product');
    const price = row.querySelector('.item-price');
    if (!product || !price) return false;

    const isAuto = price.value === '' || price.value === price.dataset.autoPrice;
    if (!isAuto) return false;

    const next = product.value === '' ? '' : priceOn(book, product.value, date);
    if (next === price.value) return false;

    price.value = next;
    price.dataset.autoPrice = next;
    return true;
  }

  window.SellingPrice = { suggest };
})();
