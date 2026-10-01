<?php

namespace App\Http\Controllers;

use App\Models\Sale;

/**
 * Public digital receipt, opened by scanning the barcode on the printed
 * receipt. No sign-in: the unguessable token in the link is the only key,
 * and the page shows only what the customer already holds on paper.
 */
class DigitalReceiptController extends Controller
{
    public function show(string $token)
    {
        $sale = Sale::where('public_token', $token)
            ->with(['customer', 'paymentMethod', 'saleDetails.product'])
            ->firstOrFail();

        return response()
            ->view('receipts.show', [
                'sale' => $sale,
                'store' => config('store'),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }
}
