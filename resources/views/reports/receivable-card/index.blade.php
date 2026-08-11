@extends('layouts.app')

@section('title', __('app.reports.receivable_card.title'))
@section('page-title', __('app.reports.receivable_card.title'))

@section('content')

    <form id="arCardReportFilterForm" action="{{ route('reports.receivable-card') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3 flex-wrap">
            <select id="arCardReportCustomer" name="customer_id"
                class="w-64 sm:w-72 rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.receivable_card.select_customer') }}</option>
                @foreach ($customers as $customerOption)
                    <option value="{{ $customerOption->id }}" @selected((string) $customerId === (string) $customerOption->id)>
                        {{ $customerOption->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($customerId || $dateFrom || $dateTo)
                <a href="{{ route('reports.receivable-card') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.receivable_card.reset_filters') }}
                </a>
            @endif

            <input id="arCardReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.receivable_card.date_to') }}</span>
            <input id="arCardReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="arCardReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.receivable_card.print') }}
            </button>
            <button type="button" id="arCardReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.receivable_card.export_excel') }}
            </button>
        </div>
    </form>

    @if ($customer)
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 report-summary">
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.receivable_card.summary_opening_balance') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($openingBalance) }}
                </p>
            </div>
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.receivable_card.summary_total_debit') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($totalDebit) }}</p>
            </div>
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.receivable_card.summary_total_credit') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($totalCredit) }}
                </p>
            </div>
            <div class="bg-white rounded-3xl p-5">
                <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                    {{ __('app.reports.receivable_card.summary_ending_balance') }}</p>
                <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format($endingBalance) }}
                </p>
            </div>
        </div>
    @endif

    <div id="arCardReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.receivable_card.title') }}</h1>
        <p>
            @if ($customer)
                {{ $customer->name }}@if ($customer->code)
                    ({{ $customer->code }})
                @endif
            @endif
            @if ($dateFrom || $dateTo)
                &middot; {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="arCardReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="arCardReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        @if (!$customer)
            <div class="text-center py-14 text-[var(--ink-400)] text-sm">
                <i class="fa-regular fa-hand-pointer text-2xl block mb-2"></i>
                {{ __('app.reports.receivable_card.no_customer_selected') }}
            </div>
        @else
            <table class="w-full text-sm min-w-[820px]">
                <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                    <tr class="text-left border-b border-gray-100">
                        <th class="p-5 font-semibold">{{ __('app.reports.receivable_card.date') }}</th>
                        <th class="font-semibold">{{ __('app.reports.receivable_card.document_number') }}</th>
                        <th class="font-semibold">{{ __('app.reports.receivable_card.type') }}</th>
                        <th class="font-semibold ">{{ __('app.reports.receivable_card.debit') }}</th>
                        <th class="font-semibold ">{{ __('app.reports.receivable_card.credit') }}</th>
                        <th class="font-semibold ">{{ __('app.reports.receivable_card.balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-row border-b border-gray-50 bg-[var(--surface)]">
                        <td class="p-5 font-semibold text-[var(--ink-700)]" colspan="5">
                            {{ __('app.reports.receivable_card.opening_balance') }}</td>
                        <td class=" font-semibold text-[var(--ink-900)]">
                            Rp{{ number_format($openingBalance) }}</td>
                    </tr>
                    @forelse($rows as $row)
                        <tr class="table-row border-b border-gray-50">
                            <td class="p-5 text-[var(--ink-400)]">
                                {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                            <td class="font-medium text-[var(--ink-900)]">{{ $row['document_number'] }}</td>
                            <td class="text-[var(--ink-700)]">{{ $row['type'] }}</td>
                            <td class=" text-[var(--ink-700)]">
                                {{ $row['debit'] > 0 ? 'Rp' . number_format($row['debit']) : '—' }}</td>
                            <td class=" text-[var(--ink-700)]">
                                {{ $row['credit'] > 0 ? 'Rp' . number_format($row['credit']) : '—' }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                Rp{{ number_format($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                                <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                                {{ __('app.reports.receivable_card.no_data') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t border-gray-100">
                            <td class="p-5 font-semibold text-[var(--ink-700)]" colspan="3">
                                {{ __('app.reports.receivable_card.ending_balance') }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                Rp{{ number_format($totalDebit) }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                Rp{{ number_format($totalCredit) }}</td>
                            <td class=" font-semibold text-[var(--ink-900)]">
                                Rp{{ number_format($endingBalance) }}</td>
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
            form#arCardReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-receivable-card.js') }}"></script>
@endpush
