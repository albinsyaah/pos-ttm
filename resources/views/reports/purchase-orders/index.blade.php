@extends('layouts.app')

@section('title', __('app.reports.purchase_order.title'))
@section('page-title', __('app.reports.purchase_order.title'))

@section('content')

    <form id="poReportFilterForm" action="{{ route('reports.purchase-orders') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="poReportSearch">{{ __('app.reports.purchase_order.search_label') }}</label>
            <input id="poReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.purchase_order.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $supplierId || $status || $dateFrom || $dateTo)
                <a href="{{ route('reports.purchase-orders') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.purchase_order.reset_filters') }}
                </a>
            @endif

            <select id="poReportSupplier" name="supplier_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.purchase_order.all_suppliers') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>

            {{-- <select id="poReportStatus" name="status"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.purchase_order.all_statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select> --}}

            <input id="poReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.purchase_order.date_to') }}</span>
            <input id="poReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="poReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.purchase_order.print') }}
            </button>
            <button type="button" id="poReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.purchase_order.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.purchase_order.summary_total_orders') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalOrders) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.purchase_order.summary_total_amount') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($totalAmount) }}</p>
        </div>
    </div>

    <div id="poReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.purchase_order.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($supplierId)
                &middot; {{ $suppliers->firstWhere('id', $supplierId)?->name }}
            @endif
            @if ($status)
                &middot; {{ ucfirst($status) }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="poReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="poReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[900px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.purchase_orders.po_number') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.order_date') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.supplier') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.status') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.items') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $purchaseOrder)
                    @php
                        $lineTotal = $purchaseOrder->purchaseOrderDetails->sum(fn($d) => $d->qty * $d->price);
                    @endphp
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $purchaseOrder->po_number }}</td>
                        <td class="text-[var(--ink-400)]">
                            {{ \Illuminate\Support\Carbon::parse($purchaseOrder->order_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $purchaseOrder->supplier?->name ?: '—' }}</td>
                        <td>
                            <span
                                class="badge-{{ $purchaseOrder->status === 'cancelled' ? 'bad' : ($purchaseOrder->status === 'completed' ? 'good' : 'neutral') }} inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ $purchaseOrder->status }}
                            </span>
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $purchaseOrder->purchaseOrderDetails->count() }}</td>
                        <td class="text-[var(--ink-700)]">Rp{{ number_format($lineTotal) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.purchase_order.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $purchaseOrders->links() }}
    </div>

@endsection

@push('styles')
    <style>
        .badge-neutral {
            background: var(--surface);
            color: var(--ink-700);
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-purchase-orders.js') }}"></script>
@endpush
