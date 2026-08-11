@extends('layouts.app')

@section('title', __('app.reports.inventory.title'))
@section('page-title', __('app.reports.inventory.title'))

@section('content')

    <form id="inventoryReportFilterForm" action="{{ route('reports.inventory') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="inventoryReportSearch">{{ __('app.reports.inventory.search_label') }}</label>
            <input id="inventoryReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.inventory.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $brandId || $itemTypeId || $productGroupId)
                <a href="{{ route('reports.inventory') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.inventory.reset_filters') }}
                </a>
            @endif

            <select id="inventoryReportBrand" name="brand_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.inventory.all_brands') }}</option>
                @foreach ($brands as $brandOption)
                    <option value="{{ $brandOption->id }}" @selected((string) $brandId === (string) $brandOption->id)>
                        {{ $brandOption->name }}</option>
                @endforeach
            </select>

            <select id="inventoryReportItemType" name="item_type_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.inventory.all_item_types') }}</option>
                @foreach ($itemTypes as $itemTypeOption)
                    <option value="{{ $itemTypeOption->id }}" @selected((string) $itemTypeId === (string) $itemTypeOption->id)>
                        {{ $itemTypeOption->name }}</option>
                @endforeach
            </select>

            <select id="inventoryReportProductGroup" name="product_group_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.inventory.all_product_groups') }}</option>
                @foreach ($productGroups as $productGroupOption)
                    <option value="{{ $productGroupOption->id }}" @selected((string) $productGroupId === (string) $productGroupOption->id)>
                        {{ $productGroupOption->name }}</option>
                @endforeach
            </select>

            <button type="button" id="inventoryReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.inventory.print') }}
            </button>
            <button type="button" id="inventoryReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.inventory.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-3 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.inventory.summary_total_products') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalProducts) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.inventory.summary_total_stock') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalStock) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.inventory.summary_total_value') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format((float) $totalValue) }}</p>
        </div>
    </div>

    <div id="inventoryReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.inventory.title') }}</h1>
        <p>
            @if ($brandId)
                {{ $brands->firstWhere('id', $brandId)?->name }}
            @endif
            @if ($itemTypeId)
                &middot; {{ $itemTypes->firstWhere('id', $itemTypeId)?->name }}
            @endif
            @if ($productGroupId)
                &middot; {{ $productGroups->firstWhere('id', $productGroupId)?->name }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="inventoryReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="inventoryReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[900px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.reports.inventory.product_code') }}</th>
                    <th class="font-semibold">{{ __('app.reports.inventory.product_name') }}</th>
                    <th class="font-semibold">{{ __('app.reports.inventory.brand') }}</th>
                    <th class="font-semibold">{{ __('app.reports.inventory.item_type') }}</th>
                    <th class="font-semibold">{{ __('app.reports.inventory.product_group') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.inventory.total_stock') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.inventory.unit_price') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.inventory.stock_value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $stock = $stockByProduct[$product->id] ?? 0;
                        $price = $priceByProduct[$product->id] ?? 0;
                    @endphp
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 text-[var(--ink-400)]">{{ $product->code }}</td>
                        <td class="font-medium text-[var(--ink-900)]">{{ $product->name }}</td>
                        <td class="text-[var(--ink-700)]">{{ $product->brand?->name ?? '-' }}</td>
                        <td class="text-[var(--ink-700)]">{{ $product->itemType?->name ?? '-' }}</td>
                        <td class="text-[var(--ink-700)]">{{ $product->productGroup?->name ?? '-' }}</td>
                        <td class=" font-medium text-[var(--ink-900)]">{{ number_format($stock) }}</td>
                        <td class=" text-[var(--ink-700)]">Rp{{ number_format((float) $price) }}</td>
                        <td class=" font-medium text-[var(--ink-900)]">Rp{{ number_format((float) ($stock * $price)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.inventory.no_data') }}
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

@push('styles')
    <style>
        @media print {

            #sidebar,
            #toastHost,
            .pagination,
            form#inventoryReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-inventory.js') }}"></script>
@endpush
