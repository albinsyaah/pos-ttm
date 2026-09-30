<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the purchase-return and sales-return controllers.
 *
 * A return may only hand back products that are on the original purchase or
 * sale, and never more than was bought / sold minus what earlier returns of
 * the same document already took back. Without this a sales return could put
 * stock into the warehouse that was never sold, or a purchase return could
 * take out stock that never came in.
 */
trait ChecksReturnLimits
{
    /**
     * @param  string  $source  Invoice number shown in the messages.
     * @param  array<int, array{product_id: int|string, qty: int|string}>  $items  The return's lines.
     * @param  array<int, int>  $original  product_id => qty on the purchase / sale.
     * @param  array<int, int>  $alreadyReturned  product_id => qty on OTHER returns of the same document.
     *
     * @throws ValidationException  Messages under the "items" key.
     */
    protected function assertWithinReturnLimits(string $source, array $items, array $original, array $alreadyReturned): void
    {
        // The same product may be listed on more than one line; add it up.
        $requested = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $requested[$productId] = ($requested[$productId] ?? 0) + (int) $item['qty'];
        }

        $messages = [];
        foreach ($requested as $productId => $qty) {
            $product = Product::find($productId);
            $returned = (int) ($alreadyReturned[$productId] ?? 0);
            $params = [
                'product' => $product?->name ?? '#'.$productId,
                'code' => $product?->code ?? '-',
                'source' => $source,
                'requested' => $qty,
                'original' => (int) ($original[$productId] ?? 0),
                'returned' => $returned,
                'remaining' => max(0, (int) ($original[$productId] ?? 0) - $returned),
            ];

            if (! isset($original[$productId])) {
                $messages[] = __('stock.return_product_not_on_source', $params);
            } elseif ($qty > $params['remaining']) {
                $messages[] = __('stock.return_exceeds', $params);
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages(['items' => $messages]);
        }
    }
}
