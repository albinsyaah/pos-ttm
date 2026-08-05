<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\InternalMutation;
use App\Models\InternalMutationDetail;
use App\Models\Product;
use App\Models\Warehouse;
use Database\Seeders\Concerns\ManagesInventoryLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InternalMutationSeeder extends Seeder
{
    use ManagesInventoryLedger;

    public function run(): void
    {
        $warehouses = Warehouse::pluck('id')->all();

        if (count($warehouses) < 2) {
            return;
        }

        $products = Product::all();
        $requesters = Employee::whereIn('position', ['Kepala Gudang', 'Staff Gudang'])->pluck('id')->all();
        $startDate = Carbon::create(2024, 2, 1);

        for ($i = 1; $i <= 12; $i++) {
            $mutationDate = (clone $startDate)->addDays(($i - 1) * 5);
            [$fromWarehouseId, $toWarehouseId] = fake()->randomElements($warehouses, 2);
            $status = $i <= 10 ? 'Completed' : 'Pending';

            $mutation = InternalMutation::create([
                'mutation_number' => 'MUT-'.$mutationDate->format('Ym').'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'type' => 'Transfer Antar Gudang',
                'mutation_date' => $mutationDate,
                'status' => $status,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'requested_by' => fake()->randomElement($requesters),
            ]);

            $lineItems = $products->random(fake()->numberBetween(1, 4));

            foreach ($lineItems as $product) {
                $available = $this->currentStock($product->id, $fromWarehouseId);
                if ($available < 1) {
                    continue;
                }

                $qty = min($available, fake()->numberBetween(5, 30));

                InternalMutationDetail::create([
                    'qty' => $qty,
                    'notes' => 'Distribusi stok antar gudang',
                    'internal_mutation_id' => $mutation->id,
                    'product_id' => $product->id,
                ]);

                if ($status === 'Completed') {
                    $this->moveStock(
                        productId: $product->id,
                        warehouseId: $fromWarehouseId,
                        type: 'TRANSFER_OUT',
                        qty: -$qty,
                        referenceNumber: $mutation->mutation_number,
                        date: $mutationDate,
                    );

                    $this->moveStock(
                        productId: $product->id,
                        warehouseId: $toWarehouseId,
                        type: 'TRANSFER_IN',
                        qty: $qty,
                        referenceNumber: $mutation->mutation_number,
                        date: $mutationDate,
                    );
                }
            }
        }
    }
}
