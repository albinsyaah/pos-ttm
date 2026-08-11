@extends('layouts.app')

@section('title', __('app.reports.position.title'))
@section('page-title', __('app.reports.position.title'))

@section('content')

    <form id="positionReportFilterForm" action="{{ route('reports.position') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="positionReportSearch">{{ __('app.reports.position.search_label') }}</label>
            <input id="positionReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.position.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $warehouseId || $lowStockOnly)
                <a href="{{ route('reports.position') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.position.reset_filters') }}
                </a>
            @endif

            <select id="positionReportWarehouse" name="warehouse_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.position.all_warehouses') }}</option>
                @foreach ($warehouses as $warehouseOption)
                    <option value="{{ $warehouseOption->id }}" @selected((string) $warehouseId === (string) $warehouseOption->id)>
                        {{ $warehouseOption->name }}</option>
                @endforeach
            </select>

            <label
                class="flex items-center gap-2 rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm cursor-pointer select-none">
                <input id="positionReportLowStockOnly" name="low_stock_only" type="checkbox" value="1"
                    @checked($lowStockOnly) class="rounded border-gray-300 text-[var(--brand-600)] focus:ring-[var(--brand-600)]" />
                {{ __('app.reports.position.low_stock_only') }}
            </label>

            <button type="button" id="positionReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.position.print') }}
            </button>
            <button type="button" id="positionReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.position.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-3 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.position.summary_total_products') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalProducts) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.position.summary_total_balance') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalBalance) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.position.summary_low_stock') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($lowStockCount) }}</p>
        </div>
    </div>

    <div id="positionReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.position.title') }}</h1>
        <p>
            @if ($warehouseId)
                {{ $warehouses->firstWhere('id', $warehouseId)?->name }}
            @endif
            @if ($lowStockOnly)
                &middot; {{ __('app.reports.position.low_stock_only') }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="positionReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="positionReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.reports.position.product_code') }}</th>
                    <th class="font-semibold">{{ __('app.reports.position.product_name') }}</th>
                    <th class="font-semibold">{{ __('app.reports.position.warehouse') }}</th>
                    <th class="font-semibold text-right">{{ __('app.reports.position.balance') }}</th>
                    <th class="font-semibold">{{ __('app.reports.position.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $position)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 text-[var(--ink-400)]">{{ $position->product_code }}</td>
                        <td class="font-medium text-[var(--ink-900)]">{{ $position->product_name }}</td>
                        <td class="text-[var(--ink-700)]">{{ $position->warehouse_name }}</td>
                        <td class="text-right font-medium text-[var(--ink-900)]">
                            {{ number_format($position->balance) }}</td>
                        <td>
                            @if ($position->balance < $lowStockThreshold)
                                <span
                                    class="badge-bad inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    {{ __('app.reports.position.low_stock') }}
                                </span>
                            @else
                                <span
                                    class="badge-good inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    {{ __('app.reports.position.in_stock') }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.position.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $positions->links() }}
    </div>

@endsection

@push('styles')
    <style>
        @media print {

            #sidebar,
            #toastHost,
            .pagination,
            form#positionReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-position.js') }}"></script>
@endpush
