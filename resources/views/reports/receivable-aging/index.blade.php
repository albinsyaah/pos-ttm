@extends('layouts.app')

@section('title', __('app.reports.receivable_aging.title'))
@section('page-title', __('app.reports.receivable_aging.title'))

@section('content')

    <form id="arAgingReportFilterForm" action="{{ route('reports.receivable-aging') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="arAgingReportSearch">{{ __('app.reports.receivable_aging.search_label') }}</label>
            <input id="arAgingReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.receivable_aging.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $customerId || $asOf !== now()->toDateString())
                <a href="{{ route('reports.receivable-aging') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.receivable_aging.reset_filters') }}
                </a>
            @endif

            <select id="arAgingReportCustomer" name="customer_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.receivable_aging.all_customers') }}</option>
                @foreach ($customers as $customerOption)
                    <option value="{{ $customerOption->id }}" @selected((string) $customerId === (string) $customerOption->id)>
                        {{ $customerOption->name }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.receivable_aging.as_of') }}</span>
                <input id="arAgingReportAsOf" name="as_of" type="date" value="{{ $asOf }}"
                    class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            </div>

            <button type="button" id="arAgingReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.receivable_aging.print') }}
            </button>
            <button type="button" id="arAgingReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.receivable_aging.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receivable_aging.bucket_current') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($grandTotals['current']) }}
            </p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receivable_aging.bucket_31_60') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">
                Rp{{ number_format($grandTotals['days_31_60']) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receivable_aging.bucket_61_90') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">
                Rp{{ number_format($grandTotals['days_61_90']) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receivable_aging.bucket_over_90') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($grandTotals['over_90']) }}
            </p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receivable_aging.summary_total_outstanding') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($grandTotals['total']) }}
            </p>
        </div>
    </div>

    <div id="arAgingReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.receivable_aging.title') }}</h1>
        <p>
            {{ __('app.reports.receivable_aging.as_of') }} {{ \Illuminate\Support\Carbon::parse($asOf)->format('d M Y') }}
            @if ($customerId)
                &middot; {{ $customers->firstWhere('id', $customerId)?->name }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="arAgingReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="arAgingReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[880px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.reports.receivable_aging.customer') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.receivable_aging.bucket_current') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.receivable_aging.bucket_31_60') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.receivable_aging.bucket_61_90') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.receivable_aging.bucket_over_90') }}</th>
                    <th class="font-semibold ">{{ __('app.reports.receivable_aging.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">
                            {{ $row['customer']->name }}
                            @if ($row['customer']->code)
                                <span class="text-[var(--ink-400)] font-normal">({{ $row['customer']->code }})</span>
                            @endif
                        </td>
                        <td class=" text-[var(--ink-700)]">
                            {{ $row['current'] > 0 ? 'Rp' . number_format($row['current']) : '—' }}</td>
                        <td class=" text-[var(--ink-700)]">
                            {{ $row['days_31_60'] > 0 ? 'Rp' . number_format($row['days_31_60']) : '—' }}</td>
                        <td class=" text-[var(--ink-700)]">
                            {{ $row['days_61_90'] > 0 ? 'Rp' . number_format($row['days_61_90']) : '—' }}</td>
                        <td class=" {{ $row['over_90'] > 0 ? 'text-red-600 font-semibold' : 'text-[var(--ink-700)]' }}">
                            {{ $row['over_90'] > 0 ? 'Rp' . number_format($row['over_90']) : '—' }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">Rp{{ number_format($row['total']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-smile text-2xl block mb-2"></i>
                            {{ __('app.reports.receivable_aging.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="border-t border-gray-100">
                        <td class="p-5 font-semibold text-[var(--ink-700)]">{{ __('app.reports.receivable_aging.grand_total') }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">
                            Rp{{ number_format($grandTotals['current']) }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">
                            Rp{{ number_format($grandTotals['days_31_60']) }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">
                            Rp{{ number_format($grandTotals['days_61_90']) }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">
                            Rp{{ number_format($grandTotals['over_90']) }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">
                            Rp{{ number_format($grandTotals['total']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

@endsection

@push('styles')
    <style>
        @media print {

            #sidebar,
            #toastHost,
            form#arAgingReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-receivable-aging.js') }}"></script>
@endpush
