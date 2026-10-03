<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class AssetController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:assets.view', only: ['index']),
            new Middleware('permission:assets.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $assets = Asset::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('assets.index', [
            'assets' => $assets,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAsset($request);

        Asset::create($data);

        return redirect()->route('assets.index')->with('success', __('Asset added successfully.'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $data = $this->validateAsset($request, $asset->id);

        $asset->update($data);

        return redirect()->route('assets.index')->with('success', __('Asset updated successfully.'));
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return redirect()->route('assets.index')->with('success', __('Asset deleted successfully.'));
    }

    protected function validateAsset(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'asset_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('assets', 'asset_code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'purchase_date' => ['required', 'date'],
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);
    }
}
