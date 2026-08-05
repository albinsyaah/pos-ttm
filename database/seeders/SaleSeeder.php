<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Database\Seeders\Concerns\ManagesInventoryLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SaleSeeder extends Seeder
{
    use ManagesInventoryLedger;

    public function run(): void
    {
        $warehouses = Warehouse::pluck('id')->all();
        $salesmen = Employee::whereIn('position', ['Sales Lapangan', 'Kasir'])->pluck('id')->all();
        $sequence = 1;

        // 1) Sales fulfilled from a sales order.
        SalesOrder::where('status', 'Fulfilled')
            ->with('salesOrderDetails')
            ->orderBy('id')
            ->get()
            ->each(function (SalesOrder $so, int $index) use ($warehouses, $salesmen, &$sequence) {
                if (Sale::where('sales_order_id', $so->id)->exists()) {
                    return;
                }

                $saleDate = Carbon::parse($so->order_date)->addDays(fake()->numberBetween(1, 4));
                $warehouseId = $warehouses[$index % count($warehouses)];
                $totalAmount = $so->salesOrderDetails->sum(fn ($d) => $d->qty * $d->price);

                $sale = Sale::create([
                    'invoice_number' => 'INV-'.$saleDate->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                    'sale_date' => $saleDate,
                    'total_amount' => $totalAmount,
                    'source' => 'Sales Order',
                    'sales_order_id' => $so->id,
                    'customer_id' => $so->customer_id,
                    'salesman_id' => fake()->randomElement($salesmen),
                    'warehouse_id' => $warehouseId,
                ]);
                $sequence++;

                foreach ($so->salesOrderDetails as $soDetail) {
                    $qty = min($soDetail->qty, max($this->currentStock($soDetail->product_id, $warehouseId), 1));

                    SaleDetail::create([
                        'qty' => $qty,
                        'price' => $soDetail->price,
                        'sale_id' => $sale->id,
                        'product_id' => $soDetail->product_id,
                    ]);

                    $this->moveStock(
                        productId: $soDetail->product_id,
                        warehouseId: $warehouseId,
                        type: 'OUT',
                        qty: -$qty,
                        referenceNumber: $sale->invoice_number,
                        date: $saleDate,
                    );
                }
            });

        // 2) Direct walk-in POS sales, not tied to a sales order.
        $customers = Customer::pluck('id')->all();
        $products = Product::with('priceSetups')->get()
            ->filter(fn (Product $p) => $p->priceSetups->firstWhere('price_category', 'Retail'));
        $startDate = Carbon::create(2024, 1, 10);

        for ($i = 1; $i <= 30; $i++) {
            $saleDate = (clone $startDate)->addDays($i * 2);
            $warehouseId = fake()->randomElement($warehouses);
            $lineItems = $products->random(fake()->numberBetween(1, 4));

            $details = [];
            $totalAmount = 0;

            foreach ($lineItems as $product) {
                $available = $this->currentStock($product->id, $warehouseId);
                if ($available < 1) {
                    continue;
                }

                $price = $product->priceSetups->firstWhere('price_category', 'Retail')->amount;
                $qty = min($available, fake()->numberBetween(1, 10));
                $totalAmount += $qty * $price;

                $details[] = ['product_id' => $product->id, 'qty' => $qty, 'price' => $price];
            }

            if (empty($details)) {
                continue;
            }

            $sale = Sale::create([
                'invoice_number' => 'INV-'.$saleDate->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'sale_date' => $saleDate,
                'total_amount' => $totalAmount,
                'source' => 'POS',
                'sales_order_id' => null,
                // Roughly a third of walk-in sales are anonymous cash customers.
                'customer_id' => fake()->boolean(65) ? fake()->randomElement($customers) : null,
                'salesman_id' => fake()->randomElement($salesmen),
                'warehouse_id' => $warehouseId,
            ]);
            $sequence++;

            foreach ($details as $detail) {
                SaleDetail::create([
                    'qty' => $detail['qty'],
                    'price' => $detail['price'],
                    'sale_id' => $sale->id,
                    'product_id' => $detail['product_id'],
                ]);

                $this->moveStock(
                    productId: $detail['product_id'],
                    warehouseId: $warehouseId,
                    type: 'OUT',
                    qty: -$detail['qty'],
                    referenceNumber: $sale->invoice_number,
                    date: $saleDate,
                );
            }
        }
    }
}
