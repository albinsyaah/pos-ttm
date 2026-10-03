@extends('layouts.app')

@section('title', __('insight.payment_methods.title'))
@section('page-title', __('insight.payment_methods.title'))

@section('content')
@php use App\Support\Money; @endphp

    <form id="insightFilterForm" action="{{ route('reports.payment-methods') }}" method="GET"
        class="report-filter-form flex items-center justify-end flex-wrap gap-3">
        @if ($methodId || $dateFrom || $dateTo)
            <a href="{{ route('reports.payment-methods') }}"
                class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                <i class="fa-solid fa-xmark"></i> {{ __('insight.common.reset_filters') }}
            </a>
        @endif

        <select name="payment_method_id" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
            <option value="">{{ __('insight.payment_methods.all_methods') }}</option>
            @foreach ($methods as $method)
                <option value="{{ $method->id }}" @selected((string) $methodId === (string) $method->id)>{{ $method->name }}</option>
            @endforeach
        </select>

        <input name="date_from" type="date" value="{{ $dateFrom }}" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
        <span class="text-[var(--ink-400)] text-xs">{{ __('insight.common.date_to') }}</span>
        <input name="date_to" type="date" value="{{ $dateTo }}" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

        @include('partials.insight-actions', ['filename' => 'payment-method-report'])
    </form>

    <div class="grid sm:grid-cols-3 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('insight.payment_methods.total_income') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1" id="totalIncome">{{ Money::rupiah($totals['income']) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('insight.payment_methods.cash_sales') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ Money::rupiah($totals['cash_sales']) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('insight.payment_methods.receivable_payments') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ Money::rupiah($totals['ar_received']) }}</p>
        </div>
    </div>

    <div class="report-print-header">
        <h1>{{ __('insight.payment_methods.title') }}</h1>
        <p>@if ($dateFrom || $dateTo){{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}@endif</p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="insightTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">
        <div id="insightLoading" class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[940px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('insight.payment_methods.method') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.payment_methods.cash_sales_count') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.payment_methods.cash_sales') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.payment_methods.receivable_count') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.payment_methods.receivable_payments') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.payment_methods.refunds') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.payment_methods.income') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('insight.payment_methods.supplier_paid') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">
                            {{ $row['name'] }}
                            @unless ($row['is_active'])<span class="text-xs text-[var(--ink-400)]">({{ __('insight.common.inactive') }})</span>@endunless
                        </td>
                        <td class="text-right text-[var(--ink-400)]">{{ number_format($row['sales_count']) }}</td>
                        <td class="text-right text-[var(--ink-700)]">{{ Money::rupiah($row['cash_sales']) }}</td>
                        <td class="text-right text-[var(--ink-400)]">{{ number_format($row['ar_count']) }}</td>
                        <td class="text-right text-[var(--ink-700)]">{{ Money::rupiah($row['ar_received']) }}</td>
                        <td class="text-right {{ $row['returns'] > 0 ? 'text-[var(--bad-600)]' : 'text-[var(--ink-400)]' }}">{{ $row['returns'] > 0 ? '-'.Money::rupiah($row['returns']) : '—' }}</td>
                        <td class="text-right font-semibold text-[var(--ink-900)]">{{ Money::rupiah($row['income']) }}</td>
                        <td class="text-right pr-5 text-[var(--ink-700)]">{{ Money::rupiah($row['ap_paid']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('insight.payment_methods.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($rows))
                <tfoot>
                    <tr class="border-t border-gray-200 font-semibold text-[var(--ink-900)]">
                        <td class="p-5">{{ __('insight.common.total') }}</td>
                        <td class="text-right">{{ number_format($totals['sales_count']) }}</td>
                        <td class="text-right">{{ Money::rupiah($totals['cash_sales']) }}</td>
                        <td class="text-right">{{ number_format($totals['ar_count']) }}</td>
                        <td class="text-right">{{ Money::rupiah($totals['ar_received']) }}</td>
                        <td class="text-right">{{ $totals['returns'] > 0 ? '-'.Money::rupiah($totals['returns']) : '—' }}</td>
                        <td class="text-right">{{ Money::rupiah($totals['income']) }}</td>
                        <td class="text-right pr-5">{{ Money::rupiah($totals['ap_paid']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <p class="text-xs text-[var(--ink-400)] mt-4">{{ __('insight.payment_methods.note') }}</p>

@endsection

@push('styles')
    <style>
        @media print {
            #sidebar, #toastHost, form#insightFilterForm, nav[role="navigation"] { display: none !important; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/insight-reports.js') }}"></script>
@endpush
