@extends('layouts.app')

@section('title', __('app.reports.receipt.title'))
@section('page-title', __('app.reports.receipt.title'))

@section('content')

    <form id="receiptReportFilterForm" action="{{ route('reports.receipt') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4">
        <div class="relative">
            <label class="sr-only" for="receiptReportSearch">{{ __('app.reports.receipt.search_label') }}</label>
            <input id="receiptReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.receipt.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $accountId || $dateFrom || $dateTo)
                <a href="{{ route('reports.receipt') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.receipt.reset_filters') }}
                </a>
            @endif

            <select id="receiptReportAccount" name="account_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.receipt.all_accounts') }}</option>
                @foreach ($accounts as $accountOption)
                    <option value="{{ $accountOption->id }}" @selected((string) $accountId === (string) $accountOption->id)>
                        {{ $accountOption->account_code }} &ndash; {{ $accountOption->account_name }}</option>
                @endforeach
            </select>

            <input id="receiptReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.receipt.date_to') }}</span>
            <input id="receiptReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="receiptReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.receipt.print') }}
            </button>
            <button type="button" id="receiptReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.receipt.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receipt.summary_total_transactions') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalTransactions) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receipt.summary_total_amount') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format((float) $totalAmount) }}
            </p>
        </div>
    </div>

    <div id="receiptReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.receipt.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($accountId)
                &middot; {{ $accounts->firstWhere('id', $accountId)?->account_name }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="receiptReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="receiptReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.reports.receipt.transaction_date') }}</th>
                    <th class="font-semibold">{{ __('app.reports.receipt.account') }}</th>
                    <th class="font-semibold">{{ __('app.reports.receipt.description') }}</th>
                    <th class="font-semibold text-right">{{ __('app.reports.receipt.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $receipt)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 text-[var(--ink-400)]">
                            {{ \Illuminate\Support\Carbon::parse($receipt->transaction_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">
                            @if ($receipt->account)
                                {{ $receipt->account->account_code }} &ndash; {{ $receipt->account->account_name }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-[var(--ink-700)]">{{ $receipt->description ?: '—' }}</td>
                        <td class="text-right font-medium text-[var(--ink-900)]">
                            Rp{{ number_format((float) $receipt->amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.receipt.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $receipts->links() }}
    </div>

@endsection

@push('styles')
    <style>
        @media print {

            #sidebar,
            #toastHost,
            .pagination,
            form#receiptReportFilterForm,
            nav[role="navigation"] {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-receipt.js') }}"></script>
@endpush
