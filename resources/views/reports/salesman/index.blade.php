@extends('layouts.app')

@section('title', __('insight.salesman.title'))
@section('page-title', __('insight.salesman.title'))

@section('content')
@php use App\Support\Money; @endphp

    <form id="insightFilterForm" action="{{ route('reports.salesman') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4" data-live-search="custom">
        <div class="relative">
            <label class="sr-only" for="insightSearch">{{ __('insight.salesman.search_label') }}</label>
            <input id="insightSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('insight.salesman.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $salesmanId || $dateFrom || $dateTo)
                <a href="{{ route('reports.salesman') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('insight.common.reset_filters') }}
                </a>
            @endif

            <select name="salesman_id" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('insight.salesman.all_salesmen') }}</option>
                @foreach ($salesmen as $salesman)
                    <option value="{{ $salesman->id }}" @selected((string) $salesmanId === (string) $salesman->id)>{{ $salesman->name }}</option>
                @endforeach
            </select>

            <input name="date_from" type="date" value="{{ $dateFrom }}" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('insight.common.date_to') }}</span>
            <input name="date_to" type="date" value="{{ $dateTo }}" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            @include('partials.insight-actions', ['filename' => 'salesman-report'])
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('insight.salesman.salesmen_count') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($groups->count()) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('insight.common.total_revenue') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ Money::rupiah($grandTotal) }}</p>
        </div>
    </div>

    <div class="report-print-header">
        <h1>{{ __('insight.salesman.title') }}</h1>
        <p>@if ($dateFrom || $dateTo){{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}@endif</p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="insightTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">
        <div id="insightLoading" class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('insight.salesman.salesman') }}</th>
                    <th class="font-semibold">{{ __('insight.common.product') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.salesman.paid_qty') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.salesman.free_qty') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.returns.returned_qty') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('insight.common.revenue') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr class="bg-[var(--surface)] font-semibold text-[var(--ink-900)]" data-salesman-row="{{ $group['salesman']->id }}">
                        <td class="p-4" colspan="2">
                            {{ $group['salesman']->name }}
                            <span class="text-xs font-normal text-[var(--ink-400)]">({{ $group['salesman']->code }}) &middot; {{ number_format($group['sales_count']) }} {{ __('insight.salesman.sales') }}</span>
                        </td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="text-right pr-5">{{ Money::rupiah($group['revenue']) }}</td>
                    </tr>
                    @foreach ($group['rows'] as $row)
                        <tr class="table-row border-b border-gray-50">
                            <td class="p-3"></td>
                            <td class="text-[var(--ink-700)]">{{ $row['product']->name }}</td>
                            <td class="text-right text-[var(--ink-700)]">{{ $row['paid_qty'] > 0 ? $row['product']->formatQuantity($row['paid_qty']) : '—' }}</td>
                            <td class="text-right text-[var(--ink-400)]">{{ $row['free_qty'] > 0 ? $row['product']->formatQuantity($row['free_qty']) : '—' }}</td>
                            <td class="text-right {{ $row['returned_qty'] > 0 ? 'text-[var(--bad-600)] font-semibold' : 'text-[var(--ink-400)]' }}">{{ $row['returned_qty'] > 0 ? $row['product']->formatQuantity($row['returned_qty']) : '—' }}</td>
                            <td class="text-right pr-5 text-[var(--ink-700)]">{{ Money::rupiah($row['revenue']) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('insight.salesman.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
