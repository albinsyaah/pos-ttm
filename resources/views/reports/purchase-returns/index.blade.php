@extends('layouts.app')

@section('title', __('app.reports.purchase_return.title'))
@section('page-title', __('app.reports.purchase_return.title'))

@section('content')

    <form id="purchaseReturnReportFilterForm" action="{{ route('reports.purchase-returns') }}" method="GET" class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="purchaseReturnReportSearch">{{ __('app.reports.purchase_return.search_label') }}</label>
            <input
                id="purchaseReturnReportSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.reports.purchase_return.search_placeholder') }}"
                autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if($search || $supplierId || $dateFrom || $dateTo)
                <a href="{{ route('reports.purchase-returns') }}" class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.purchase_return.reset_filters') }}
                </a>
            @endif

            <select id="purchaseReturnReportSupplier" name="supplier_id"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.purchase_return.all_suppliers') }}</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>

            <input id="purchaseReturnReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.purchase_return.date_to') }}</span>
            <input id="purchaseReturnReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                   class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="prReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.purchase_return.print') }}
            </button>
            <button type="button" id="prReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.purchase_return.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('app.reports.purchase_return.summary_total_returns') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalReturns) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('app.reports.purchase_return.summary_total_amount') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format((float) $totalAmount) }}</p>
        </div>
    </div>

    <div id="prReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.purchase_return.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($supplierId)
                &middot; {{ $suppliers->firstWhere('id', $supplierId)?->name }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="purchaseReturnReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="purchaseReturnReportLoading" class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[820px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.purchase_returns.return_number') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_returns.return_date') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_returns.purchase') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_returns.items') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_returns.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseReturns as $purchaseReturn)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $purchaseReturn->return_number }}</td>
                        <td class="text-[var(--ink-400)]">{{ \Illuminate\Support\Carbon::parse($purchaseReturn->return_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">
                            {{ $purchaseReturn->purchase?->invoice_number ?: '—' }}
                            <span class="text-[var(--ink-400)]">{{ $purchaseReturn->purchase?->supplier ? '· ' . $purchaseReturn->purchase->supplier->name : '' }}</span>
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $purchaseReturn->purchaseReturnDetails->count() }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format((float) $purchaseReturn->total_amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.purchase_return.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $purchaseReturns->links() }}
    </div>

@endsection

@push('styles')
    <style>
        @media print {
            #sidebar, #toastHost, .pagination, form#purchaseReturnReportFilterForm, nav[role="navigation"] { display: none !important; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-purchase-returns.js') }}"></script>
@endpush
