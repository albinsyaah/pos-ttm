<?php

namespace App\Http\Controllers\Print;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

/**
 * Printable documents for one sale. Works for every sale regardless of the
 * page that created it (Sales, Point of Sale, Point of Sale New, Sales SPG).
 *
 *  - small receipt  : 100 x 150 mm label/thermal paper, for the cashier
 *  - large receipt  : A4 invoice, for the main cashier (kasir induk)
 *  - delivery note  : A4 surat jalan, main cashier only
 *
 * Who may print what is decided purely by permission, so which role counts as
 * "kasir" or "kasir induk" is set on the Role screen.
 */
class SalePrintController extends Controller implements HasMiddleware
{
    /** Small receipt paper size in millimetres (width x height). */
    public const SMALL_RECEIPT_WIDTH = 100;
    public const SMALL_RECEIPT_HEIGHT = 150;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:print.receipt-small', only: ['receiptSmall']),
            new Middleware('permission:print.receipt-large', only: ['receiptLarge']),
            new Middleware('permission:print.delivery-note', only: ['deliveryNote']),
        ];
    }

    public function receiptSmall(Sale $sale)
    {
        return view('prints.receipt-small', [
            'sale' => $this->load($sale),
            'width' => self::SMALL_RECEIPT_WIDTH,
            'height' => self::SMALL_RECEIPT_HEIGHT,
            'store' => config('store'),
        ]);
    }

    public function receiptLarge(Sale $sale)
    {
        return view('prints.receipt-large', [
            'sale' => $this->load($sale),
            'store' => config('store'),
        ]);
    }

    public function deliveryNote(Sale $sale)
    {
        return view('prints.delivery-note', [
            'sale' => $this->load($sale),
            'store' => config('store'),
        ]);
    }

    /**
     * Load what the documents need. A sale that predates the token column
     * (or was inserted without the model) gets its token here, so the
     * barcode always has a link to point to.
     */
    private function load(Sale $sale): Sale
    {
        if (blank($sale->public_token)) {
            $sale->forceFill(['public_token' => Str::random(32)])->save();
        }

        return $sale->load(['customer', 'warehouse', 'salesman', 'paymentMethod', 'saleDetails.product']);
    }
}
