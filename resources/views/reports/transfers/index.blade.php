@extends('layouts.app')

@section('title', __('app.reports.transfer.title'))
@section('page-title', __('app.reports.transfer.title'))

@section('content')

    <form id="transferReportFilterForm" action="{{ route('reports.transfers') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="transferReportSearch">{{ __('app.reports.transfer.search_label') }}</label>
            <input id="transferReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.transfer.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $fromWarehouseId || $toWarehouseId || $status || $dateFrom || $dateTo)
                <a href="{{ route('reports.transfers') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.transfer.reset_filters') }}
                </a>
            @endif

            <select id="transferReportFromWarehouse" name="from_warehouse_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.transfer.all_from_warehouses') }}</option>
                @foreach ($warehouses as $warehouseOption)
                    <option value="{{ $warehouseOption->id }}" @selected((string) $fromWarehouseId === (string) $warehouseOption->id)>
                        {{ $warehouseOption->name }}</option>
                @endforeach
            </select>

            <select id="transferReportToWarehouse" name="to_warehouse_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.transfer.all_to_warehouses') }}</option>
                @foreach ($warehouses as $warehouseOption)
                    <option value="{{ $warehouseOption->id }}" @selected((string) $toWarehouseId === (string) $warehouseOption->id)>
                        {{ $warehouseOption->name }}</option>
                @endforeach
            </select>

            <select id="transferReportStatus" name="status"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.transfer.all_statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>

            <input id="transferReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.transfer.date_to') }}</span>
            <input id="transferReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="transferReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.transfer.print') }}
            </button>
            <button type="button" id="transferReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.transfer.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.transfer.summary_total_transfers') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalTransfers) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.transfer.summary_total_qty') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalQty) }}</p>
        </div>
    </div>

    <div id="transferReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.transfer.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($fromWarehouseId)
                &middot; {{ __('app.reports.transfer.from') }}: {{ $warehouses->firstWhere('id', $fromWarehouseId)?->name }}
            @endif
            @if ($toWarehouseId)
                &middot; {{ __('app.reports.transfer.to') }}: {{ $warehouses->firstWhere('id', $toWarehouseId)?->name }}
            @endif
            @if ($status)
                &middot; {{ ucfirst($status) }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="transferReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="transferReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[900px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.reports.transfer.mutation_number') }}</th>
                    <th class="font-semibold">{{ __('app.reports.transfer.mutation_date') }}</th>
                    <th class="font-semibold">{{ __('app.reports.transfer.from') }}</th>
                    <th class="font-semibold">{{ __('app.reports.transfer.to') }}</th>
                    <th class="font-semibold">{{ __('app.reports.transfer.status') }}</th>
                    <th class="font-semibold">{{ __('app.reports.transfer.items') }}</th>
                    <th class="font-semibold text-right">{{ __('app.reports.transfer.qty') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                    @php
                        $lineQty = $transfer->internalMutationDetails->sum('qty');
                    @endphp
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $transfer->mutation_number }}</td>
                        <td class="text-[var(--ink-400)]">
                            {{ \Illuminate\Support\Carbon::parse($transfer->mutation_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $transfer->fromWarehouse?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-700)]">{{ $transfer->toWarehouse?->name ?: '—' }}</td>
                        <td>
                            <span
                                class="badge-{{ $transfer->status === 'rejected' ? 'bad' : ($transfer->status === 'completed' ? 'good' : 'neutral') }} inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ $transfer->status }}
                            </span>
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $transfer->internalMutationDetails->count() }}</td>
                        <td class="text-right font-medium text-[var(--ink-900)]">{{ number_format($lineQty) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.transfer.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $transfers->links() }}
    </div>

@endsection

@push('styles')
    <style>
        .badge-neutral {
            background: var(--surface);
            color: var(--ink-700);
        }

        @media print {

            #sidebar,
            #toastHost,
            .pagination,
            form#transferReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-transfers.js') }}"></script>
@endpush
