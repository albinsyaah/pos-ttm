@extends('layouts.app')

@section('title', __('app.products.title'))
@section('page-title', __('app.products.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('inventory.products.index') }}" method="GET" class="relative" data-live-search="auto">
            <label class="sr-only" for="productSearch">{{ __('app.products.search_products') }}</label>
            <input
                id="productSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.products.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('inventory.products.manage')
            <button
                id="addProductBtn"
                type="button"
                data-action="{{ route('inventory.products.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.products.add_product') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[1100px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.common.code') }}</th>
                    <th class="font-semibold">{{ __('app.common.name') }}</th>
                    <th class="font-semibold">{{ __('app.products.packaging') }}</th>
                    <th class="font-semibold">{{ __('app.products.stock') }}</th>
                    <th class="font-semibold">{{ __('app.products.brand') }}</th>
                    <th class="font-semibold">{{ __('app.products.item_type') }}</th>
                    <th class="font-semibold">{{ __('app.products.product_group') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $product->code }}</td>
                        <td class="text-[var(--ink-700)]">{{ $product->name }}</td>
                        <td class="text-[var(--ink-400)]">
                            {{ $product->unit_name }}
                            @if($product->hasPack())
                                · 1 {{ $product->pack_name }} = {{ $product->pack_qty }} {{ $product->unit_name }}
                            @endif
                            @if($product->hasBox())
                                · 1 {{ $product->box_name }} = {{ $product->box_qty }} {{ $product->unit_name }}
                            @endif
                        </td>
                        <td class="font-medium text-[var(--ink-900)]">{{ $product->formatQuantity($stockByProduct[$product->id] ?? 0) }}</td>
                        <td class="text-[var(--ink-400)]">{{ $product->brand?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $product->itemType?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $product->productGroup?->name ?: '—' }}</td>
                        <td class="text-right pr-5">
                            @can('inventory.products.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-product-btn icon-btn"
                                        aria-label="Edit {{ $product->name }}"
                                        data-action="{{ route('inventory.products.update', $product) }}"
                                        data-code="{{ $product->code }}"
                                        data-name="{{ $product->name }}"
                                        data-unit-name="{{ $product->unit_name }}"
                                        data-pack-name="{{ $product->pack_name }}"
                                        data-pack-qty="{{ $product->pack_qty }}"
                                        data-box-name="{{ $product->box_name }}"
                                        data-box-qty="{{ $product->box_qty }}"
                                        data-brand-id="{{ $product->brand_id }}"
                                        data-item-type-id="{{ $product->item_type_id }}"
                                        data-product-group-id="{{ $product->product_group_id }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-product-btn icon-btn"
                                        aria-label="Delete {{ $product->name }}"
                                        data-action="{{ route('inventory.products.destroy', $product) }}"
                                        data-name="{{ $product->name }}"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.products.no_products_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $products->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="productModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-5">
                <h3 id="productModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.products.add_product') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="productForm" method="POST" action="{{ route('inventory.products.store') }}">
                @csrf
                <div id="productFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="code" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.code') }}</label>
                        <input id="code" name="code" type="text" readonly maxlength="50" placeholder="{{ __('app.auto_number') }}"
                               class="cursor-not-allowed text-[var(--ink-400)] w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.name') }}</label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div class="rounded-2xl border border-gray-100 p-4 space-y-4">
                        <div>
                            <p class="text-xs font-semibold text-[var(--ink-900)]">{{ __('app.products.packaging') }}</p>
                            <p class="text-xs text-[var(--ink-400)] mt-0.5">{{ __('app.products.packaging_hint') }}</p>
                        </div>
                        <div>
                            <label for="unit_name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.unit_name') }}</label>
                            <input id="unit_name" name="unit_name" type="text" required maxlength="30" value="pcs"
                                   class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="pack_name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.pack_name') }}</label>
                                <input id="pack_name" name="pack_name" type="text" maxlength="30" placeholder="pack"
                                       class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                            </div>
                            <div>
                                <label for="pack_qty" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.pack_qty') }}</label>
                                <input id="pack_qty" name="pack_qty" type="number" min="2" step="1" inputmode="numeric"
                                       class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                            </div>
                            <div>
                                <label for="box_name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.box_name') }}</label>
                                <input id="box_name" name="box_name" type="text" maxlength="30" placeholder="box"
                                       class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                            </div>
                            <div>
                                <label for="box_qty" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.box_qty') }}</label>
                                <input id="box_qty" name="box_qty" type="number" min="2" step="1" inputmode="numeric"
                                       class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="brand_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.brand') }}</label>
                        <select id="brand_id" name="brand_id"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.none') }}</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="item_type_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.item_type') }}</label>
                        <select id="item_type_id" name="item_type_id"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">— None —</option>
                            @foreach($itemTypes as $itemType)
                                <option value="{{ $itemType->id }}">{{ $itemType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="product_group_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.product_group') }}</label>
                        <select id="product_group_id" name="product_group_id"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">— None —</option>
                            @foreach($productGroups as $productGroup)
                                <option value="{{ $productGroup->id }}">{{ $productGroup->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-4 text-xs text-[var(--bad-600)] space-y-1">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button type="button" class="modal-close text-sm font-medium text-[var(--ink-700)] px-4 py-2.5">{{ __('app.common.cancel') }}</button>
                    <button type="submit" class="bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">{{ __('app.common.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    <div id="deleteModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-sm">
            <div class="w-12 h-12 rounded-2xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center mb-4">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.products.delete_product') }}</h3>
            <p id="deleteModalText" class="text-sm text-[var(--ink-400)] mt-1.5">{{ __('app.common.this_action_cannot_be_undone') }}</p>

            <form id="deleteForm" method="POST" class="mt-6 flex items-center justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" class="modal-close text-sm font-medium text-[var(--ink-700)] px-4 py-2.5">{{ __('app.common.cancel') }}</button>
                <button type="submit" class="bg-[var(--bad-600)] hover:opacity-90 text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">{{ __('app.common.delete') }}</button>
            </form>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(26, 33, 56, .45);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            z-index: 50;
        }
        .modal-overlay.hidden { display: none; }
        .modal-card { box-shadow: 0 24px 48px -16px rgba(26,33,56,.35); }
    </style>
@endpush

@push('scripts')
    <script>
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success'))));
        @endif
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error'))));
        @endif
    </script>
    <script src="{{ asset('js/inventory-products.js') }}"></script>
@endpush
