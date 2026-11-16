@extends('layouts.app')

@section('title', __('app.inquiry.title'))
@section('page-title', __('app.inquiry.title'))

@section('content')
@php use App\Support\Money; use App\Services\PriceService; @endphp
@php
    $colspan = 7 + ($canSeePurchasePrice ? 1 : 0) + ($canManage ? 1 : 0);
    $widths = match (true) {
        $canSeePurchasePrice && $canManage => [7, 15, 12, 9, 9, 13, 13, 15, 6],
        $canSeePurchasePrice => [8, 17, 13, 9, 9, 14, 14, 16],
        $canManage => [8, 17, 14, 9, 9, 14, 17, 6],
        default => [9, 19, 16, 10, 10, 15, 17],
    };
    $inputClass = 'w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors';
@endphp

    <form id="inquiryFilterForm" action="{{ route('inquiry.index') }}" method="GET" class="flex items-center justify-between flex-wrap gap-4" data-live-search="custom">
        <div class="relative">
            <label class="sr-only" for="inquirySearch">{{ __('app.inquiry.search_products') }}</label>
            <input
                id="inquirySearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.inquiry.search_placeholder') }}"
                autocomplete="off"
                class="w-64 sm:w-96 rounded-xl bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap ">
            @if($search || $brandId || $itemTypeId || $warehouseId)
                <a href="{{ route('inquiry.index') }}" class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> Reset Filters
                </a>
            @endif

            <select id="inquiryBrand" name="brand_id"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.inquiry.all_brands') }}</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) $brandId === (string) $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>

            <select id="inquiryItemType" name="item_type_id"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.inquiry.all_item_types') }}</option>
                @foreach($itemTypes as $itemType)
                    <option value="{{ $itemType->id }}" @selected((string) $itemTypeId === (string) $itemType->id)>{{ $itemType->name }}</option>
                @endforeach
            </select>

            <select id="inquiryWarehouse" name="warehouse_id"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.inquiry.all_warehouses') }}</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>

            @if($canManage)
                <button type="button" id="addInquiryProductBtn" data-action="{{ route('inquiry.store') }}"
                        class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                    <i class="fa-solid fa-plus"></i> {{ __('app.inquiry.add_product') }}
                </button>
            @endif
        </div>
    </form>

    <div id="inquiryTableWrap" class="relative bg-white rounded-xl mt-6 overflow-x-auto">

        <div id="inquiryLoading" class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[900px] table-fixed">
            <colgroup>
                @foreach($widths as $width)
                    <col style="width: {{ $width }}%">
                @endforeach
            </colgroup>
            <thead class="text-[var(--ink-900)] text-[14px] uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="px-5 py-4 font-bold whitespace-nowrap">{{ __('app.common.code') }}</th>
                    <th class="px-5 py-4 font-bold">{{ __('app.common.name') }}</th>
                    <th class="px-5 py-4 font-bold">{{ __('app.products.brand') }}</th>
                    <th class="px-5 py-4 font-bold">{{ __('app.products.item_type') }}</th>
                    <th class="px-5 py-4 font-bold">{{ __('app.inquiry.stock') }}</th>
                    @if($canSeePurchasePrice)
                        <th class="px-5 py-4 font-bold">{{ __('app.inquiry.price_purchase') }}</th>
                    @endif
                    <th class="px-5 py-4 font-bold">{{ __('app.inquiry.price_retail') }}</th>
                    <th class="px-5 py-4 font-bold">{{ __('app.inquiry.price_wholesale') }}</th>
                    @if($canManage)
                        <th class="px-3 py-4"><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $stock = $stockByProduct[$product->id] ?? null;
                        $prices = collect($pricesByProduct[$product->id] ?? []);
                        $purchasePrice = $prices->first(fn ($p) => PriceService::canonicalCategory($p->price_category) === PriceService::PURCHASE);
                        $retailPrice = $prices->first(fn ($p) => PriceService::canonicalCategory($p->price_category) === PriceService::RETAIL);
                        $wholesalePrice = $prices->first(fn ($p) => PriceService::canonicalCategory($p->price_category) === PriceService::WHOLESALE);
                    @endphp
                    <tr class="table-row align-top {{ $loop->even ? 'is-zebra' : '' }}">
                        <td class="px-5 py-4 whitespace-nowrap"><span class="code-badge">{{ $product->code }}</span></td>
                        <td class="px-5 py-4 text-[var(--ink-700)]">{{ $product->name }}</td>
                        <td class="px-5 py-4 text-[var(--ink-400)]">{{ $product->brand?->name ?: '—' }}</td>
                        <td class="px-5 py-4 text-[var(--ink-400)]">{{ $product->itemType?->name ?: '—' }}</td>
                        <td class="px-5 py-4">
                            @if($stock)
                                <span class="font-semibold text-[var(--ink-900)]">{{ $product->formatQuantity($stock['total']) }}</span>
                                {{-- @if($stock['total'] > 0)
                                    <span class="chip bg-[var(--good-100)] text-[var(--good-600)] ml-1" data-availability="available">{{ __('insight.inquiry.available') }}</span>
                                @else
                                    <span class="chip bg-[var(--bad-100)] text-[var(--bad-600)] ml-1" data-availability="out">{{ __('insight.inquiry.out_of_stock') }}</span>
                                @endif --}}
                            @else
                                <span class="text-[var(--ink-400)] text-xs">{{ __('app.inquiry.no_stock_data') }}</span>
                            @endif
                        </td>
                        @if($canSeePurchasePrice)
                            <td class="px-5 py-4">
                                @if($purchasePrice)
                                    <div class="text-[var(--ink-700)] whitespace-nowrap">
                                        <span class="font-semibold">{{ Money::rupiah($purchasePrice->amount) }}</span>
                                        <div class="text-[var(--ink-400)] text-[11px]">({{ __('insight.inquiry.effective') }} {{ \Illuminate\Support\Carbon::parse($purchasePrice->effective_date)->format('d M Y') }})</div>
                                    </div>
                                @else
                                    <span class="text-[var(--ink-400)] text-xs">{{ __('app.inquiry.no_price_data') }}</span>
                                @endif
                            </td>
                        @endif
                        <td class="px-5 py-4">
                            @if($retailPrice)
                                <div class="text-[var(--ink-700)] whitespace-nowrap">
                                    <span class="font-semibold">{{ Money::rupiah($retailPrice->amount) }}</span>
                                    <div class="text-[var(--ink-400)] text-[11px]">({{ __('insight.inquiry.effective') }} {{ \Illuminate\Support\Carbon::parse($retailPrice->effective_date)->format('d M Y') }})</div>
                                </div>
                            @else
                                <span class="text-[var(--ink-400)] text-xs">{{ __('app.inquiry.no_price_data') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if($wholesalePrice)
                                <div class="text-[var(--ink-700)] whitespace-nowrap">
                                    <span class="font-semibold">{{ Money::rupiah($wholesalePrice->amount) }}</span>
                                    <div class="text-[var(--ink-400)] text-[11px]">({{ __('insight.inquiry.effective') }} {{ \Illuminate\Support\Carbon::parse($wholesalePrice->effective_date)->format('d M Y') }})</div>
                                </div>
                            @else
                                <span class="text-[var(--ink-400)] text-xs">{{ __('app.inquiry.no_price_data') }}</span>
                            @endif
                            <button type="button" data-history-toggle="{{ $product->id }}" aria-expanded="false"
                                    class="mt-2 block text-xs font-semibold text-[var(--brand-600)] hover:underline">
                                {{ __('insight.inquiry.price_history') }}
                            </button>
                        </td>
                        @if($canManage)
                            <td class="px-3 py-4 text-center">
                                <button type="button" class="edit-inquiry-btn icon-btn-edit"
                                        aria-label="{{ __('app.inquiry.edit') }} {{ $product->name }}"
                                        data-action="{{ route('inquiry.update', $product) }}"
                                        data-product-id="{{ $product->id }}"
                                        data-code="{{ $product->code }}"
                                        data-unit-name="{{ $product->unit_name }}"
                                        data-brand-id="{{ $product->brand_id }}"
                                        data-item-type-id="{{ $product->item_type_id }}"
                                        data-price-purchase="{{ $purchasePrice ? (float) $purchasePrice->amount : '' }}"
                                        data-price-retail="{{ $retailPrice ? (float) $retailPrice->amount : '' }}"
                                        data-price-wholesale="{{ $wholesalePrice ? (float) $wholesalePrice->amount : '' }}"
                                        data-stock="{{ json_encode((object) ($warehouseStockByProduct[$product->id] ?? [])) }}">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                            </td>
                        @endif
                    </tr>
                    <tr class="hidden bg-[var(--surface)]" data-history-row="{{ $product->id }}">
                        <td colspan="{{ $colspan }}" class="px-5 py-4">
                            @php $history = $historyByProduct[$product->id] ?? collect(); @endphp
                            @if($history->isEmpty())
                                <p class="text-xs text-[var(--ink-400)]">{{ __('insight.inquiry.no_price_history') }}</p>
                            @else
                                <table class="w-full text-xs">
                                    <thead class="text-[var(--ink-400)] uppercase tracking-wide">
                                        <tr class="text-left">
                                            <th class="py-1 pr-3 font-semibold">{{ __('insight.inquiry.when') }}</th>
                                            <th class="py-1 pr-3 font-semibold">{{ __('insight.inquiry.category') }}</th>
                                            <th class="py-1 pr-3 font-semibold">{{ __('insight.inquiry.change') }}</th>
                                            <th class="py-1 pr-3 font-semibold">{{ __('insight.inquiry.effective_date') }}</th>
                                            <th class="py-1 font-semibold">{{ __('insight.inquiry.by') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($history as $log)
                                            <tr class="border-t border-white">
                                                <td class="py-1.5 pr-3 whitespace-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                                                <td class="py-1.5 pr-3">{{ PriceService::label($log->price_category) }}</td>
                                                <td class="py-1.5 pr-3 whitespace-nowrap">
                                                    @if($log->action === \App\Models\PriceHistory::CREATED)
                                                        {{ __('insight.inquiry.set_to') }} <strong>{{ Money::rupiah($log->new_amount) }}</strong>
                                                    @elseif($log->action === \App\Models\PriceHistory::DELETED)
                                                        {{ __('insight.inquiry.removed') }} ({{ Money::rupiah($log->old_amount) }})
                                                    @else
                                                        {{ Money::rupiah($log->old_amount) }} &rarr; <strong>{{ Money::rupiah($log->new_amount) }}</strong>
                                                    @endif
                                                </td>
                                                <td class="py-1.5 pr-3 whitespace-nowrap">{{ ($log->new_effective_date ?? $log->old_effective_date)?->format('d M Y') ?: '—' }}</td>
                                                <td class="py-1.5">{{ $log->user?->username ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspan }}" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.inquiry.no_products_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $products->links() }}
    </div>

@if($canManage)
        {{-- Add / edit product: data, prices and stock --}}
        <div id="inquiryModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="inquiryModalTitle"
             data-update-url="{{ route('inquiry.update', '__ID__') }}"
             data-data-url="{{ route('inquiry.product-data', '__ID__') }}"
             data-title-add="{{ __('app.inquiry.add_product') }}"
             data-title-edit="{{ __('app.inquiry.edit_product') }}">
            <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-5">
                    <h3 id="inquiryModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.inquiry.add_product') }}</h3>
                    <button type="button" class="icon-btn modal-close" aria-label="Close">
                        <i class="fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>

                <form id="inquiryEditForm" method="POST" action="{{ route('inquiry.store') }}">
                    @csrf
                    <div id="inquiryFormMethod"></div>
                    <input type="hidden" name="_inquiry_form" value="1">
                    <input type="hidden" name="_product_id" id="inquiryProductId" value="">

                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ink-400)] mb-3">{{ __('app.inquiry.product_section') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="inquiry_product_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.name') }}</label>
                            <select id="inquiry_product_id" name="product_id" required class="{{ $inputClass }}">
                                <option value="">{{ __('app.common.select') }}</option>
                                @foreach($allProducts as $option)
                                    <option value="{{ $option->id }}">{{ $option->code }} — {{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="inquiry_code" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.code') }}</label>
                            <input id="inquiry_code" name="code" type="text" maxlength="50" placeholder="{{ __('app.inquiry.code_auto_hint') }}" class="{{ $inputClass }}" />
                        </div>
                        <div>
                            <label for="inquiry_unit_name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.inquiry.unit') }}</label>
                            <input id="inquiry_unit_name" name="unit_name" type="text" maxlength="30" required class="{{ $inputClass }}" />
                        </div>
                        <div>
                            <label for="inquiry_brand_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.brand') }}</label>
                            <select id="inquiry_brand_id" name="brand_id" class="{{ $inputClass }}">
                                <option value="">—</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="inquiry_item_type_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.products.item_type') }}</label>
                            <select id="inquiry_item_type_id" name="item_type_id" class="{{ $inputClass }}">
                                <option value="">—</option>
                                @foreach($itemTypes as $itemType)
                                    <option value="{{ $itemType->id }}">{{ $itemType->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ink-400)] mt-6 mb-1">{{ __('app.inquiry.price_section') }}</p>
                    <p class="text-xs text-[var(--ink-400)] mb-3">{{ __('app.inquiry.price_hint') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        @if($canSeePurchasePrice)
                            <div>
                                <label for="inquiry_price_purchase" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.inquiry.price_purchase') }}</label>
                                <input id="inquiry_price_purchase" name="prices[purchase]" type="number" step="0.01" min="0" class="{{ $inputClass }}" />
                            </div>
                        @endif
                        <div>
                            <label for="inquiry_price_retail" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.inquiry.price_retail') }}</label>
                            <input id="inquiry_price_retail" name="prices[retail]" type="number" step="0.01" min="0" class="{{ $inputClass }}" />
                        </div>
                        <div>
                            <label for="inquiry_price_wholesale" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.inquiry.price_wholesale') }}</label>
                            <input id="inquiry_price_wholesale" name="prices[wholesale]" type="number" step="0.01" min="0" class="{{ $inputClass }}" />
                        </div>
                        <div>
                            <label for="inquiry_price_effective_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.inquiry.effective_date') }}</label>
                            <input id="inquiry_price_effective_date" name="price_effective_date" type="date" value="{{ now()->toDateString() }}" class="{{ $inputClass }}" />
                        </div>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ink-400)] mt-6 mb-1">{{ __('app.inquiry.stock_section') }}</p>
                    <p class="text-xs text-[var(--ink-400)] mb-3">{{ __('app.inquiry.stock_hint') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        @foreach($warehouses as $warehouse)
                            <div>
                                <label for="inquiry_stock_{{ $warehouse->id }}" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ $warehouse->name }}</label>
                                <input id="inquiry_stock_{{ $warehouse->id }}" name="stock[{{ $warehouse->id }}]" type="number" step="1" min="0" class="{{ $inputClass }}" />
                            </div>
                        @endforeach
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
    @endif

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
        @if($canManage && $errors->any() && old('_inquiry_form'))
            window.INQUIRY_REOPEN = @json(collect(old())->except(['_token', '_method'])->all());
        @endif
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success'))));
        @endif
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'fa-circle-exclamation', 'var(--bad-600)'));
        @endif
    </script>
    @if($canManage)
        {{-- Searchable product select of the add / edit form --}}
        <script src="{{ asset('js/product-picker.js') }}"></script>
    @endif
    <script src="{{ asset('js/inquiry.js') }}"></script>
@endpush
