@extends('layouts.app')

@section('title', __('app.inquiry.title'))
@section('page-title', __('app.inquiry.title'))

@section('content')

    <form action="{{ route('inquiry.index') }}" method="GET" class="flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="inquirySearch">{{ __('app.inquiry.search_products') }}</label>
            <input
                id="inquirySearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.inquiry.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <select name="brand_id" onchange="this.form.submit()"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.inquiry.all_brands') }}</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) $brandId === (string) $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>

            <select name="item_type_id" onchange="this.form.submit()"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.inquiry.all_item_types') }}</option>
                @foreach($itemTypes as $itemType)
                    <option value="{{ $itemType->id }}" @selected((string) $itemTypeId === (string) $itemType->id)>{{ $itemType->name }}</option>
                @endforeach
            </select>

            <select name="warehouse_id" onchange="this.form.submit()"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.inquiry.all_warehouses') }}</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[860px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.common.code') }}</th>
                    <th class="font-semibold">{{ __('app.common.name') }}</th>
                    <th class="font-semibold">{{ __('app.products.brand') }}</th>
                    <th class="font-semibold">{{ __('app.products.item_type') }}</th>
                    <th class="font-semibold">{{ __('app.inquiry.stock') }}</th>
                    <th class="font-semibold">{{ __('app.inquiry.price') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $stock = $stockByProduct[$product->id] ?? null;
                        $prices = $pricesByProduct[$product->id] ?? collect();
                    @endphp
                    <tr class="table-row border-b border-gray-50 align-top">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $product->code }}</td>
                        <td class="text-[var(--ink-700)]">{{ $product->name }}</td>
                        <td class="text-[var(--ink-400)]">{{ $product->brand?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $product->itemType?->name ?: '—' }}</td>
                        <td>
                            @if($stock)
                                <span class="font-semibold text-[var(--ink-900)]">{{ number_format($stock['total']) }}</span>
                                <div class="mt-1 flex flex-col gap-1">
                                    @foreach($stock['warehouses'] as $row)
                                        <span class="inline-flex items-center rounded-full bg-[var(--surface)] px-2.5 py-1 text-xs text-[var(--ink-400)]">
                                            {{ $row['name'] }}: {{ number_format($row['balance']) }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-[var(--ink-400)] text-xs">{{ __('app.inquiry.no_stock_data') }}</span>
                            @endif
                        </td>
                        <td>
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
