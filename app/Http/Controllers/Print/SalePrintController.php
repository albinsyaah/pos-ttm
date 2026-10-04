<?php

namespace App\Http\Controllers\Print;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\StockService;
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
        $sale = $this->load($sale);

        return view('prints.delivery-note', [
            'sale' => $sale,
            'store' => config('store'),
            'sources' => $this->warehouseSources($sale),
        ]);
    }

    /**
     * Where the goods of a sale were taken from, per warehouse, for the delivery note:
     * [['warehouse' => name, 'items' => [['name' => ..., 'qty' => ...], ...]], ...],
     * warehouses and products in alphabetical order.
     *
     * A sale made on a cashier terminal has no warehouse of its own and may have been
     * served from several, so this reads what the inventory ledger recorded for it. A sale
     * that names its warehouse and has no ledger rows (older data) is listed under that
     * warehouse with its lines.
     *
     * @return array<int, array{warehouse: string, items: array<int, array{name: string, qty: string}>}>
     */
    private function warehouseSources(Sale $sale): array
    {
        $moved = app(StockService::class)->movedBySource($sale);

        if ($moved === [] && $sale->warehouse_id !== null) {
            foreach ($sale->saleDetails as $detail) {
                $moved[(int) $detail->product_id][(int) $sale->warehouse_id] = ($moved[(int) $detail->product_id][(int) $sale->warehouse_id] ?? 0) + (int) $detail->qty;
            }
        }

        if ($moved === []) {
            return [];
        }

        $perWarehouse = [];
        foreach ($moved as $productId => $byWarehouse) {
            foreach ($byWarehouse as $warehouseId => $qty) {
                $perWarehouse[$warehouseId][$productId] = $qty;
            }
        }

        $warehouses = Warehouse::whereIn('id', array_keys($perWarehouse))->pluck('name', 'id');
        $products = Product::whereIn('id', array_keys($moved))->get()->keyBy('id');

        $groups = [];
        foreach ($perWarehouse as $warehouseId => $items) {
            $rows = [];
            foreach ($items as $productId => $qty) {
                $product = $products->get($productId);
                $rows[] = [
                    'name' => $product?->name ?? '#'.$productId,
                    'qty' => $product ? $product->formatQuantity((int) $qty) : (string) $qty,
                ];
            }
            usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            $groups[] = ['warehouse' => (string) ($warehouses[$warehouseId] ?? '#'.$warehouseId), 'items' => $rows];
        }

        usort($groups, fn ($a, $b) => strcasecmp($a['warehouse'], $b['warehouse']));

        return $groups;
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
