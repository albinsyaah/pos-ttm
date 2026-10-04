@extends('layouts.app')

@section('title', __('app.reports.receivable_payment.title'))
@section('page-title', __('app.reports.receivable_payment.title'))

@section('content')

    <form id="arReportFilterForm" action="{{ route('reports.receivable-payments') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4" data-live-search="custom">
        <div class="relative">
            <label class="sr-only" for="arReportSearch">{{ __('app.reports.receivable_payment.search_label') }}</label>
            <input id="arReportSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('app.reports.receivable_payment.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i
                class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $customerId || $paymentMethod || $dateFrom || $dateTo)
                <a href="{{ route('reports.receivable-payments') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('app.reports.receivable_payment.reset_filters') }}
                </a>
            @endif

            <select id="arReportCustomer" name="customer_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.receivable_payment.all_customers') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) $customerId === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>

            <select id="arReportMethod" name="payment_method_id"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('app.reports.receivable_payment.all_methods') }}</option>
                @foreach ($paymentMethods as $methodOption)
                    <option value="{{ $methodOption->id }}" @selected((string) $paymentMethod === (string) $methodOption->id)>
                        {{ $methodOption->name }}</option>
                @endforeach
            </select>

            <input id="arReportDateFrom" name="date_from" type="date" value="{{ $dateFrom }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('app.reports.receivable_payment.date_to') }}</span>
            <input id="arReportDateTo" name="date_to" type="date" value="{{ $dateTo }}"
                class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            <button type="button" id="arReportPrintBtn"
                class="flex items-center gap-2 border border-[var(--brand-600)] text-[var(--brand-600)] hover:bg-[var(--brand-600)] hover:text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-print"></i> {{ __('app.reports.receivable_payment.print') }}
            </button>
            <button type="button" id="arReportExportBtn"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-file-excel"></i> {{ __('app.reports.receivable_payment.export_excel') }}
            </button>
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receivable_payment.summary_total_payments') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalPayments) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                {{ __('app.reports.receivable_payment.summary_total_amount') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">Rp{{ number_format((float) $totalAmount) }}
            </p>
        </div>
    </div>

    <div id="arReportPrintHeader" class="report-print-header">
        <h1>{{ __('app.reports.receivable_payment.title') }}</h1>
        <p>
            @if ($dateFrom || $dateTo)
                {{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}
            @endif
            @if ($customerId)
                &middot; {{ $customers->firstWhere('id', $customerId)?->name }}
            @endif
            @if ($paymentMethod)
                &middot; {{ $paymentMethods->firstWhere('id', $paymentMethod)?->name }}
            @endif
        </p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="arReportTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">

        <div id="arReportLoading"
            class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.receivable_payments.payment_number') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.payment_date') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.customer') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.payment_method') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivablePayments as $payment)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $payment->payment_number }}</td>
                        <td class="text-[var(--ink-400)]">
                            {{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $payment->customer?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $payment->paymentMethod?->name ?? '-' }}</td>
                        <td class="text-[var(--ink-700)]">Rp{{ number_format((float) $payment->amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.reports.receivable_payment.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $receivablePayments->links() }}
    </div>

@endsection

@push('styles')
    <style>
        .badge-neutral {
            background: var(--surface);
            color: var(--ink-700);
        }

        @media print {
            #sidebar, #toastHost, .pagination, form#arReportFilterForm, nav[role="navigation"] { display: none !important; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/reports-receivable-payments.js') }}"></script>
@endpush
