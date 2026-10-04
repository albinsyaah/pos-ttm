<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Validation\ValidationException;

/**
 * Works out the discount of a sale.
 *
 * A sale can carry a percentage and a fixed rupiah amount at the same time. Both are
 * taken from the same base, the sum of the lines (the subtotal):
 *
 *     discount = subtotal x percent / 100 + fixed amount
 *     total    = subtotal - discount
 *
 * The two never act on each other, so the order they are typed in does not matter.
 * A discount larger than the subtotal is refused (a sale cannot be worth less than nothing).
 * Everything is rounded to cents.
 */
class SaleDiscountService
{
    /**
     * @param  string  $errorKey  where the refusal message is shown on the form
     * @return array{subtotal: float, discount_percent: float, discount_amount: float, discount_total: float, total_amount: float}
     *
     * @throws ValidationException
     */
    public function calculate(float $subtotal, float $percent, float $amount, string $errorKey = 'discount_amount'): array
    {
        $subtotal = round($subtotal, 2);
        $percent = round(max(0.0, $percent), 2);
        $amount = round(max(0.0, $amount), 2);

        $discount = round($subtotal * $percent / 100 + $amount, 2);

        if ($percent > 100) {
            throw ValidationException::withMessages([$errorKey => __('app.point_of_sale_new.discount_percent_max')]);
        }

        if ($discount > $subtotal + 0.004) {
            throw ValidationException::withMessages([
                $errorKey => __('app.point_of_sale_new.discount_too_large', [
                    'discount' => number_format($discount, 0, ',', '.'),
                    'subtotal' => number_format($subtotal, 0, ',', '.'),
                ]),
            ]);
        }

        // Guard against a sum a hair over the subtotal from rounding.
        $discount = min($discount, $subtotal);

        return [
            'subtotal' => $subtotal,
            'discount_percent' => $percent,
            'discount_amount' => $amount,
            'discount_total' => $discount,
            'total_amount' => round($subtotal - $discount, 2),
        ];
    }

    /**
     * An existing sale being edited on a page that has no discount field (Sales, Point of
     * Sale, SPG): the lines may change, so the discount it was given is worked out again
     * from the same percentage and fixed amount against the new subtotal. Without this an
     * edit would quietly drop the discount and raise what the customer owes.
     *
     * @return array{subtotal: float, discount_percent: float, discount_amount: float, discount_total: float, total_amount: float}
     *
     * @throws ValidationException when the new lines no longer cover the discount
     */
    public function recalculateFor(Sale $sale, float $newSubtotal): array
    {
        return $this->calculate(
            $newSubtotal,
            (float) $sale->discount_percent,
            (float) $sale->discount_amount,
            'items',
        );
    }
}
