<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ItemType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ItemTypeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.item-types.view', only: ['index']),
            new Middleware('permission:inventory.item-types.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $itemTypes = ItemType::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.item-types.index', [
            'itemTypes' => $itemTypes,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateItemType($request);

        ItemType::create($data);

        return redirect()->route('inventory.item-types.index')->with('success', 'Item type added successfully.');
    }

    public function update(Request $request, ItemType $itemType): RedirectResponse
    {
        $data = $this->validateItemType($request);

        $itemType->update($data);

        return redirect()->route('inventory.item-types.index')->with('success', 'Item type updated successfully.');
    }

    public function destroy(ItemType $itemType): RedirectResponse
    {
        if ($itemType->products()->exists()) {
            return back()->with('error', 'This item type is still used by one or more products.');
        }

        $itemType->delete();

        return redirect()->route('inventory.item-types.index')->with('success', 'Item type deleted successfully.');
    }

    protected function validateItemType(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);
    }
}
