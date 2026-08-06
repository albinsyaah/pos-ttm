@extends('layouts.app')

@section('title', __('app.inquiry.title'))
@section('page-title', __('app.inquiry.title'))

@section('content')

    <form id="inquiryFilterForm" action="{{ route('inquiry.index') }}" method="GET" class="flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="inquirySearch">{{ __('app.inquiry.search_products') }}</label>
            <input
                id="inquirySearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.inquiry.search_placeholder') }}"
                autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
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
        </div>
    </form>

    <div id="inquiryTableWrap" class="relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="inquiryLoading" class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[900px] table-fixed">
            <colgroup>
                <col style="width: 9%">
                <col style="width: 19%">
                <col style="width: 16%">
                <col style="width: 10%">
                <col style="width: 28%">
                <col style="width: 18%">
            </colgroup>
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="px-5 py-4 font-semibold whitespace-nowrap">{{ __('app.common.code') }}</th>
                    <th class="px-5 py-4 font-semibold">{{ __('app.common.name') }}</th>
                    <th class="px-5 py-4 font-semibold">{{ __('app.products.brand') }}</th>
                    <th class="px-5 py-4 font-semibold">{{ __('app.products.item_type') }}</th>
                    <th class="px-5 py-4 font-semibold">{{ __('app.inquiry.stock') }}</th>
                    <th class="px-5 py-4 font-semibold">{{ __('app.inquiry.price') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $stock = $stockByProduct[$product->id] ?? null;
                        $prices = $pricesByProduct[$product->id] ?? collect();
                    @endphp
                    <tr class="table-row border-b border-gray-50 align-top">
                        <td class="px-5 py-4 font-medium text-[var(--ink-900)] whitespace-nowrap">{{ $product->code }}</td>
                        <td class="px-5 py-4 text-[var(--ink-700)]">{{ $product->name }}</td>
                        <td class="px-5 py-4 text-[var(--ink-400)]">{{ $product->brand?->name ?: '—' }}</td>
                        <td class="px-5 py-4 text-[var(--ink-400)]">{{ $product->itemType?->name ?: '—' }}</td>
                        <td class="px-5 py-4">
                            @if($stock)
                                <span class="font-semibold text-[var(--ink-900)]">{{ number_format($stock['total']) }}</span>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    @foreach($stock['warehouses'] as $row)
                                        <span class="inline-flex items-center rounded-full bg-[var(--surface)] px-2.5 py-1 text-xs text-[var(--ink-400)] whitespace-nowrap">
                                            {{ $row['name'] }}: {{ number_format($row['balance']) }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-[var(--ink-400)] text-xs">{{ __('app.inquiry.no_stock_data') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @forelse($prices as $price)
                                <div class="text-[var(--ink-700)] whitespace-nowrap">
                                    <span class="text-[var(--ink-400)] text-xs">{{ $price->price_category }}:</span>
                                    {{ number_format((float) $price->amount, 2) }}
                                </div>
                            @empty
                                <span class="text-[var(--ink-400)] text-xs">{{ __('app.inquiry.no_price_data') }}</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
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

@endsection

@push('scripts')
    <script>
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success'))));
        @endif
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'fa-circle-exclamation', 'var(--bad-600)'));
        @endif
    </script>
    <script src="{{ asset('js/inquiry.js') }}"></script>
@endpush
