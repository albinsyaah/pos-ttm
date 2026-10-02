@extends('layouts.app')

@section('title', __('app.reports.sales_order.title'))
@section('page-title', __('app.reports.sales_order.title'))

@section('content')

    <form id="soReportFilterForm" action="{{ route('reports.sales-orders') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4" data-live-search="custom">
        <div class="relative">
            <label class="sr-only" for="soReportSearch">{{ __('app.reports.sales_order.search_label') }}</label>
            <input id="soReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.sales_order.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $customerId || $status || $dateFrom || $dateTo)
                <a href="{{ route('reports.sales-orders') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.sales_order.reset_filters') }}
                </a>
            @endif

            <select id="soReportCustomer" name="customer_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.sales_order.all_customers') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) $customerId === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>

            <select id="soReportStatus" name="status"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.sales_order.all_statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>
                        {{ __('app.sales_orders.status_' . $statusOption) }}</option>
                @endforeach
            </select>

            <input id="soReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.sales_order.date_to') }}</span>
            <input id="soReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="soReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.sales_order.print') }}
            </button>
            <button type="button" id="soReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.sales_order.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.sales_order.summary_total_orders') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalOrders) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.sales_order.summary_total_amount') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($totalAmount) }}</p>
        </div>
    </div>

    <div id="soReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.sales_order.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($customerId)
                &middot; {{ $customers->firstWhere('id', $customerId)?->name }}
            @endif
            @if ($status)
                &middot; {{ $status }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="soReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="soReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[900px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.sales_orders.so_number') }}</th>
                    <th class="font-semibold">{{ __('app.sales_orders.order_date') }}</th>
                    <th class="font-semibold">{{ __('app.sales_orders.customer') }}</th>
                    <th class="font-semibold">{{ __('app.sales_orders.status') }}</th>
                    <th class="font-semibold">{{ __('app.sales_orders.items') }}</th>
                    <th class="font-semibold">{{ __('app.sales_orders.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salesOrders as $salesOrder)
                    @php
                        $lineTotal = $salesOrder->salesOrderDetails->sum(fn($d) => $d->qty * $d->price);
                    @endphp
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $salesOrder->so_number }}</td>
                        <td class="text-[var(--ink-400)]">
                            {{ \Illuminate\Support\Carbon::parse($salesOrder->order_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $salesOrder->customer?->name ?: '—' }}</td>
                        <td>
                            <span
                                class="badge-{{ $salesOrder->status === 'cancelled' ? 'bad' : ($salesOrder->status === 'completed' ? 'good' : 'neutral') }} inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ $salesOrder->status }}
                            </span>
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $salesOrder->salesOrderDetails->count() }}</td>
                        <td class="text-[var(--ink-700)]">Rp{{ number_format($lineTotal) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.sales_order.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $salesOrders->links() }}
    </div>

@endsection

@push('styles')
    <style>
        .badge-neutral {
            background: var(--surface);
            color: var(--ink-700);
        }

        @media print {
            #sidebar, #toastHost, .pagination, form#soReportFilterForm, nav[role="navigation"] { display: none !important; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-sales-orders.js') }}"></script>
@endpush
