<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.view', only: ['index']),
            new Middleware('permission:transactions.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $purchaseReturns = PurchaseReturn::query()
            ->with(['purchase.supplier', 'purchaseReturnDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('purchase', function ($purchaseQuery) use ($search) {
                            $purchaseQuery->where('invoice_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('return_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.purchase-returns.index', [
            'purchaseReturns' => $purchaseReturns,
            'search' => $search,
            'purchases' => Purchase::with('supplier')->orderByDesc('purchase_date')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePurchaseReturn($request);

        DB::transaction(function () use ($data) {
            $purchaseReturn = PurchaseReturn::create([
                'return_number' => $data['return_number'],
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'purchase_id' => $data['purchase_id'],
            ]);

            $purchaseReturn->purchaseReturnDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.purchase-returns.index')->with('success', 'Purchase return added successfully.');
    }

    public function update(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $data = $this->validatePurchaseReturn($request, $purchaseReturn->id);

        DB::transaction(function () use ($data, $purchaseReturn) {
            $purchaseReturn->update([
                'return_number' => $data['return_number'],
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'purchase_id' => $data['purchase_id'],
            ]);

            $purchaseReturn->purchaseReturnDetails()->delete();
            $purchaseReturn->purchaseReturnDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.purchase-returns.index')->with('success', 'Purchase return updated successfully.');
    }

    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        DB::transaction(function () use ($purchaseReturn) {
            $purchaseReturn->purchaseReturnDetails()->delete();
            $purchaseReturn->delete();
        });

        return redirect()->route('transactions.purchase-returns.index')->with('success', 'Purchase return deleted successfully.');
    }

    protected function validatePurchaseReturn(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'return_number' => [
                'required', 'string', 'max:100',
                Rule::unique('purchase_returns', 'return_number')->ignore($ignoreId),
            ],
            'return_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'purchase_id' => ['required', 'exists:purchases,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $data['items'] = array_map(fn ($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'reason' => $item['reason'] ?? null,
        ], $data['items']);

        return $data;
    }
}
