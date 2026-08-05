<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:warehouses.view', only: ['index']),
            new Middleware('permission:warehouses.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $warehouses = Warehouse::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('warehouses.index', [
            'warehouses' => $warehouses,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateWarehouse($request);

        Warehouse::create($data);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse added successfully.');
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $this->validateWarehouse($request, $warehouse->id);

        $warehouse->update($data);

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if (
            $warehouse->purchases()->exists()
            || $warehouse->sales()->exists()
            || $warehouse->fromWarehouseInternalMutations()->exists()
            || $warehouse->toWarehouseInternalMutations()->exists()
            || $warehouse->inventoryLedgers()->exists()
        ) {
            return back()->with('error', 'This warehouse still has transaction records and cannot be deleted.');
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted successfully.');
    }

    protected function validateWarehouse(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('warehouses', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
