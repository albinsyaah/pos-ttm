<?php

namespace Database\Seeders;

use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Database\Seeders\Concerns\ManagesInventoryLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PurchaseSeeder extends Seeder
{
    use ManagesInventoryLedger;

    public function run(): void
    {
        $warehouses = Warehouse::pluck('id')->all();

        PurchaseOrder::where('status', 'Received')
            ->with('purchaseOrderDetails')
            ->orderBy('id')
            ->get()
            ->each(function (PurchaseOrder $po, int $index) use ($warehouses) {
                if (Purchase::where('purchase_order_id', $po->id)->exists()) {
                    return;
                }

                $purchaseDate = Carbon::parse($po->order_date)->addDays(fake()->numberBetween(2, 6));
                $warehouseId = $warehouses[$index % count($warehouses)];

                $totalAmount = $po->purchaseOrderDetails->sum(fn ($detail) => $detail->qty * $detail->price);

                $purchase = Purchase::create([
                    'invoice_number' => 'PUR-'.$purchaseDate->format('Ym').'-'.str_pad((string) ($po->id), 4, '0', STR_PAD_LEFT),
                    'purchase_date' => $purchaseDate,
                    'total_amount' => $totalAmount,
                    'status' => 'Completed',
                    'purchase_order_id' => $po->id,
                    'supplier_id' => $po->supplier_id,
                    'warehouse_id' => $warehouseId,
                ]);

                foreach ($po->purchaseOrderDetails as $poDetail) {
                    PurchaseDetail::create([
                        'qty' => $poDetail->qty,
                        'price' => $poDetail->price,
                        'purchase_id' => $purchase->id,
                        'product_id' => $poDetail->product_id,
                    ]);

                    $this->moveStock(
                        productId: $poDetail->product_id,
                        warehouseId: $warehouseId,
                        type: 'IN',
                        qty: $poDetail->qty,
                        referenceNumber: $purchase->invoice_number,
                        date: $purchaseDate,
                    );
                }
            });
    }
}
