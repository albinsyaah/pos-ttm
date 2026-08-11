@extends('layouts.app')

@section('title', __('app.reports.stock_card.title'))
@section('page-title', __('app.reports.stock_card.title'))

@section('content')

    <form id="stockCardReportFilterForm" action="{{ route('reports.stock-card') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3 flex-wrap">
            <select id="stockCardReportProduct" name="product_id"
                class="w-64 sm:w-72 rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.stock_card.select_product') }}</option>
                @foreach ($products as $productOption)
                    <option value="{{ $productOption->id }}" @selected((string) $productId === (string) $productOption->id)>
                        {{ $productOption->name }}</option>
                @endforeach
            </select>

            <select id="stockCardReportWarehouse" name="warehouse_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.stock_card.all_warehouses') }}</option>
                @foreach ($warehouses as $warehouseOption)
                    <option value="{{ $warehouseOption->id }}" @selected((string) $warehouseId === (string) $warehouseOption->id)>
                        {{ $warehouseOption->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($productId || $warehouseId || $dateFrom || $dateTo)
                <a href="{{ route('reports.stock-card') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.stock_card.reset_filters') }}
                </a>
            @endif

            <input id="stockCardReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.stock_card.date_to') }}</span>
            <input id="stockCardReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="stockCardReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.stock_card.print') }}
            </button>
            <button type="button" id="stockCardReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.stock_card.export_excel') }}
            </button>
        </div>
    </form>

    @if ($product)
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 report-summary">
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.stock_card.summary_opening_balance') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($openingBalance) }}</p>
            </div>
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.stock_card.summary_total_in') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalIn) }}</p>
            </div>
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.stock_card.summary_total_out') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalOut) }}</p>
            </div>
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.stock_card.summary_ending_balance') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($endingBalance) }}</p>
            </div>
        </div>
    @endif

    <div id="stockCardReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.stock_card.title') }}</h1>
        <p>
            @if ($product)
                {{ $product->name }}@if ($product->code)
                    ({{ $product->code }})
                @endif
            @endif
            @if ($warehouseId)
                &middot; {{ $warehouses->firstWhere('id', $warehouseId)?->name }}
            @endif
            @if ($dateFrom || $dateTo)
                &middot; {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="stockCardReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="stockCardReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        @if (!$product)
            <div class="text-center py-14 text-[var(--ink-400)] text-sm">
                <i class="fa-regular fa-hand-pointer text-2xl block mb-2"></i>
                {{ __('app.reports.stock_card.no_product_selected') }}
            </div>
        @else
            <table class="w-full text-sm min-w-[880px]">
                <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                    <tr class="text-left border-b border-gray-100">
                        <th class="p-5 font-semibold">{{ __('app.reports.stock_card.date') }}</th>
                        <th class="font-semibold">{{ __('app.reports.stock_card.reference_number') }}</th>
                        <th class="font-semibold">{{ __('app.reports.stock_card.warehouse') }}</th>
                        <th class="font-semibold">{{ __('app.reports.stock_card.type') }}</th>
                        <th class="font-semibold ">{{ __('app.reports.stock_card.in') }}</th>
                        <th class="font-semibold ">{{ __('app.reports.stock_card.out') }}</th>
                        <th class="font-semibold ">{{ __('app.reports.stock_card.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-row border-b border-gray-50 bg-[var(--surface)]">
                        <td class="p-5 font-semibold text-[var(--ink-700)]" colspan="6">
                            {{ __('app.reports.stock_card.opening_balance') }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">
                            {{ number_format($openingBalance) }}</td>
                    </tr>
                    @forelse($rows as $row)
                        <tr class="table-row border-b border-gray-50">
                            <td class="p-5 text-[var(--ink-400)]">
                                {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="font-medium text-[var(--ink-900)]">{{ $row['reference_number'] }}</td>
                            <td class="text-[var(--ink-700)]">{{ $row['warehouse'] ?: '—' }}</td>
                            <td class="text-[var(--ink-700)]">{{ $row['type_label'] }}</td>
                            <td class=" text-[var(--ink-700)]">
                                {{ $row['qty'] > 0 ? number_format($row['qty']) : '—' }}</td>
                            <td class=" text-[var(--ink-700)]">
                                {{ $row['qty'] < 0 ? number_format(abs($row['qty'])) : '—' }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                {{ number_format($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-14 text-[var(--ink-400)] text-sm">
                                <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                                {{ __('app.reports.stock_card.no_data') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t border-gray-100">
                            <td class="p-5 font-semibold text-[var(--ink-700)]" colspan="4">
                                {{ __('app.reports.stock_card.ending_balance') }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                {{ number_format($totalIn) }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                {{ number_format($totalOut) }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                {{ number_format($endingBalance) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        @endif
    </div>

@endsection

@push('styles')
    <style>
        @media print {

            #sidebar,
            #toastHost,
            form#stockCardReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-stock-card.js') }}"></script>
@endpush
