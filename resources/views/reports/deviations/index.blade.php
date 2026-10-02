@extends('layouts.app')

@section('title', __('app.reports.deviation.title'))
@section('page-title', __('app.reports.deviation.title'))

@section('content')

    <form id="deviationReportFilterForm" action="{{ route('reports.deviations') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4" data-live-search="custom">
        <div class="relative">
            <label class="sr-only" for="deviationReportSearch">{{ __('app.reports.deviation.search_label') }}</label>
            <input id="deviationReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.deviation.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $warehouseId || $status || $dateFrom || $dateTo)
                <a href="{{ route('reports.deviations') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.deviation.reset_filters') }}
                </a>
            @endif

            <select id="deviationReportWarehouse" name="warehouse_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.deviation.all_warehouses') }}</option>
                @foreach ($warehouses as $warehouseOption)
                    <option value="{{ $warehouseOption->id }}" @selected((string) $warehouseId === (string) $warehouseOption->id)>
                        {{ $warehouseOption->name }}</option>
                @endforeach
            </select>

            <select id="deviationReportStatus" name="status"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.deviation.all_statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ ucfirst($statusOption) }}</option>
                @endforeach
            </select>

            <input id="deviationReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.deviation.date_to') }}</span>
            <input id="deviationReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="deviationReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.deviation.print') }}
            </button>
            <button type="button" id="deviationReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.deviation.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.deviation.summary_total_deviations') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalDeviations) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.deviation.summary_net_qty') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalNetQty) }}</p>
        </div>
    </div>

    <div id="deviationReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.deviation.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($warehouseId)
                &middot; {{ $warehouses->firstWhere('id', $warehouseId)?->name }}
            @endif
            @if ($status)
                &middot; {{ ucfirst($status) }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="deviationReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="deviationReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[820px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.reports.deviation.mutation_number') }}</th>
                    <th class="font-semibold">{{ __('app.reports.deviation.mutation_date') }}</th>
                    <th class="font-semibold">{{ __('app.reports.deviation.warehouse') }}</th>
                    <th class="font-semibold">{{ __('app.reports.deviation.status') }}</th>
                    <th class="font-semibold">{{ __('app.reports.deviation.items') }}</th>
                    <th class="font-semibold text-right">{{ __('app.reports.deviation.qty') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deviations as $deviation)
                    @php
                        $lineQty = $deviation->internalMutationDetails->sum('qty');
                    @endphp
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $deviation->mutation_number }}</td>
                        <td class="text-[var(--ink-400)]">
                            {{ \Illuminate\Support\Carbon::parse($deviation->mutation_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $deviation->fromWarehouse?->name ?: '—' }}</td>
                        <td>
                            <span
                                class="badge-{{ $deviation->status === 'rejected' ? 'bad' : ($deviation->status === 'completed' ? 'good' : 'neutral') }} inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ $deviation->status }}
                            </span>
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $deviation->internalMutationDetails->count() }}</td>
                        <td
                            class="text-right font-medium {{ $lineQty < 0 ? 'text-red-600' : 'text-[var(--ink-900)]' }}">
                            {{ $lineQty > 0 ? '+' : '' }}{{ number_format($lineQty) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.deviation.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $deviations->links() }}
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
            form#deviationReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-deviations.js') }}"></script>
@endpush
