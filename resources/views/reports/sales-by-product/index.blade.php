@extends('layouts.app')

@section('title', __('insight.by_product.title'))
@section('page-title', __('insight.by_product.title'))

@section('content')
@php use App\Support\Money; @endphp

    <form id="insightFilterForm" action="{{ route('reports.sales-by-product') }}" method="GET"
        class="report-filter-form flex items-center justify-between flex-wrap gap-4" data-live-search="custom">
        <div class="relative">
            <label class="sr-only" for="insightSearch">{{ __('insight.by_product.search_label') }}</label>
            <input id="insightSearch" name="q" type="search" value="{{ $search }}"
                placeholder="{{ __('insight.by_product.search_placeholder') }}" autocomplete="off"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            @if ($search || $warehouseId || $dateFrom || $dateTo)
                <a href="{{ route('reports.sales-by-product') }}"
                    class="text-xs font-semibold text-[var(--ink-400)] hover:text-[var(--ink-900)] transition-colors">
                    <i class="fa-solid fa-xmark"></i> {{ __('insight.common.reset_filters') }}
                </a>
            @endif

            <select name="warehouse_id" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                <option value="">{{ __('insight.by_product.all_warehouses') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>

            <input name="date_from" type="date" value="{{ $dateFrom }}" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            <span class="text-[var(--ink-400)] text-xs">{{ __('insight.common.date_to') }}</span>
            <input name="date_to" type="date" value="{{ $dateTo }}" class="rounded-full bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />

            @include('partials.insight-actions', ['filename' => 'sales-by-product-report'])
        </div>
    </form>

    <div class="grid sm:grid-cols-2 gap-4 mt-6 report-summary">
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('insight.by_product.total_qty') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ number_format($totalQty) }}</p>
        </div>
        <div class="bg-white rounded-3xl p-5">
            <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">{{ __('insight.common.total_revenue') }}</p>
            <p class="text-2xl font-extrabold text-[var(--ink-900)] mt-1">{{ Money::rupiah($totalRevenue) }}</p>
        </div>
    </div>

    <div class="report-print-header">
        <h1>{{ __('insight.by_product.title') }}</h1>
        <p>@if ($dateFrom || $dateTo){{ $dateFrom ?: '…' }} &ndash; {{ $dateTo ?: '…' }}@endif</p>
        <p class="generated">{{ __('app.reports.generated_at') }}: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div id="insightTableWrap" class="report-table-wrap relative bg-white rounded-3xl mt-6 overflow-x-auto">
        <div id="insightLoading" class="hidden absolute inset-0 z-10 flex items-start justify-center pt-16 bg-white/70 backdrop-blur-[1px] rounded-3xl">
            <i class="fa-solid fa-circle-notch fa-spin text-xl text-[var(--brand-600)]"></i>
        </div>

        <table class="w-full text-sm min-w-[900px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('insight.common.product') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.by_product.price') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.by_product.qty') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.common.revenue') }}</th>
                    <th class="font-semibold text-right">{{ __('insight.by_product.sales_count') }}</th>
                    <th class="font-semibold pl-6">{{ __('insight.by_product.period') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $product = $products->get($row->product_id); @endphp
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">
                            {{ $row->product_name }}
                            @if (in_array($row->product_id, $priceChanged) && (float) $row->unit_price > 0)
                                <span class="chip bg-[var(--warn-100)] text-[var(--warn-600)] ml-1">{{ __('insight.by_product.price_changed') }}</span>
                            @endif
                        </td>
                        <td class="text-right text-[var(--ink-700)]">
                            @if ((float) $row->unit_price > 0)
                                {{ Money::rupiah($row->unit_price) }}
                            @else
                                {{ __('insight.common.free') }}
                            @endif
                        </td>
                        <td class="text-right text-[var(--ink-700)]">{{ $product ? $product->formatQuantity((int) $row->qty) : number_format($row->qty) }}</td>
                        <td class="text-right text-[var(--ink-900)] font-semibold">{{ Money::rupiah($row->revenue) }}</td>
                        <td class="text-right text-[var(--ink-400)]">{{ number_format($row->sales_count) }}</td>
                        <td class="pl-6 text-[var(--ink-400)] whitespace-nowrap">
                            {{ \Illuminate\Support\Carbon::parse($row->first_date)->format('d M Y') }}
                            @if (\Illuminate\Support\Carbon::parse($row->first_date)->ne(\Illuminate\Support\Carbon::parse($row->last_date)))
                                &ndash; {{ \Illuminate\Support\Carbon::parse($row->last_date)->format('d M Y') }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('insight.by_product.no_data') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rows->links() }}
    </div>

@endsection

@push('styles')
    <style>
        @media print {
            #sidebar, #toastHost, .pagination, form#insightFilterForm, nav[role="navigation"] { display: none !important; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/insight-reports.js') }}"></script>
@endpush
