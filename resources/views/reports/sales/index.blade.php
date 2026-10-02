@extends('layouts.app')

@section('title', __('app.reports.sale.title'))
@section('page-title', __('app.reports.sale.title'))

@section('content')

    <form id="saleReportFilterForm" action="{{ route('reports.sales') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4" data-live-search="custom">
        <div class="relative">
            <label class="sr-only" for="saleReportSearch">{{ __('app.reports.sale.search_label') }}</label>
            <input id="saleReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.sale.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $customerId || $warehouseId || $dateFrom || $dateTo)
                <a href="{{ route('reports.sales') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.sale.reset_filters') }}
                </a>
            @endif

            <select id="saleReportCustomer" name="customer_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.sale.all_customers') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) $customerId === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>

            <select id="saleReportWarehouse" name="warehouse_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.sale.all_warehouses') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>

            <input id="saleReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.sale.date_to') }}</span>
            <input id="saleReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="saleReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.sale.print') }}
            </button>
            <button type="button" id="saleReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.sale.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.sale.summary_total_sales') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalSales) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.sale.summary_total_amount') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format((float) $totalAmount) }}
            </p>
        </div>
    </div>

    <div id="saleReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.sale.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($customerId)
                &middot; {{ $customers->firstWhere('id', $customerId)?->name }}
            @endif
            @if ($warehouseId)
                &middot; {{ $warehouses->firstWhere('id', $warehouseId)?->name }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="saleReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="saleReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[920px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.sales.invoice_number') }}</th>
                    <th class="font-semibold">{{ __('app.sales.sale_date') }}</th>
                    <th class="font-semibold">{{ __('app.sales.customer') }}</th>
                    <th class="font-semibold">{{ __('app.sales.warehouse') }}</th>
                    <th class="font-semibold">{{ __('app.sales.items') }}</th>
                    <th class="font-semibold">{{ __('app.sales.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $sale->invoice_number }}</td>
                        <td class="text-[var(--ink-400)]">
                            {{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $sale->customer?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $sale->warehouse?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $sale->saleDetails->count() }}</td>
                        <td class="text-[var(--ink-700)]">Rp{{ number_format((float) $sale->total_amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.sale.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $sales->links() }}
    </div>

@endsection

@push('styles')
    <style>
        .badge-neutral {
            background: var(--surface);
            color: var(--ink-700);
        }

        @media print {
            #sidebar, #toastHost, .pagination, form#saleReportFilterForm, nav[role="navigation"] { display: none !important; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-sales.js') }}"></script>
@endpush
