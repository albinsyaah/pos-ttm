@extends('layouts.app')

@section('title', __('app.layout.dashboard'))
@section('page-title', __('app.layout.dashboard'))

@section('content')
@php use App\Support\Money; @endphp

    {{-- Sales cards: day, week and month, each in its own colour --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="stat-card card-day text-white p-6" id="cardDaySales">
            <div class="flex items-center justify-between">
                <p class="text-xs opacity-90 tracking-wide font-semibold">{{ __('app.dashboard.todays_sales') }}</p>
                <i class="fa-solid fa-sun opacity-90"></i>
            </div>
            <p class="text-3xl font-bold mt-3">{{ Money::rupiah($todaySales) }}</p>
            <p class="text-xs mt-2 opacity-90">{{ $ordersToday }} {{ __('app.dashboard.orders') }} &middot; {{ __('app.dashboard.total_sales_today') }}</p>
        </div>

        <div class="stat-card card-week text-white p-6" id="cardWeekSales">
            <div class="flex items-center justify-between">
                <p class="text-xs opacity-90 tracking-wide font-semibold">{{ __('app.dashboard.weekly_sales') }}</p>
                <i class="fa-solid fa-calendar-week opacity-90"></i>
            </div>
            <p class="text-3xl font-bold mt-3">{{ Money::rupiah($weeklySales) }}</p>
            <p class="text-xs mt-2 opacity-90">{{ $weekCount }} {{ __('app.dashboard.orders') }} &middot; {{ __('app.dashboard.total_sales_week') }}</p>
        </div>

        <div class="stat-card card-month text-white p-6" id="cardMonthSales">
            <div class="flex items-center justify-between">
                <p class="text-xs opacity-90 tracking-wide font-semibold">{{ __('app.dashboard.monthly_sales') }}</p>
                <i class="fa-solid fa-calendar-days opacity-90"></i>
            </div>
            <p class="text-3xl font-bold mt-3">{{ Money::rupiah($monthSales) }}</p>
            <p class="text-xs mt-2 opacity-90">{{ $monthCount }} {{ __('app.dashboard.orders') }} &middot; {{ __('app.dashboard.total_sales_month') }}</p>
        </div>
    </div>

    {{-- Stock and money owed --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 mt-6">
        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">{{ __('app.dashboard.low_stock_items') }}</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">{{ $lowStockCount }}</p>
            <p class="text-xs mt-2 text-[var(--ink-400)] font-medium">{{ __('app.dashboard.needs_reordering') }}</p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">{{ __('app.dashboard.receivables_outstanding') }}</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--good-100)] text-[var(--good-600)] flex items-center justify-center">
                    <i class="fa-solid fa-hand-holding-dollar text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">{{ Money::rupiah($receivablesOutstanding) }}</p>
            <p class="text-xs mt-2 text-[var(--ink-400)] font-medium">{{ __('app.dashboard.sales_not_yet_paid') }}</p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">{{ __('app.dashboard.payables_outstanding') }}</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center">
                    <i class="fa-solid fa-money-check-dollar text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">{{ Money::rupiah($payablesOutstanding) }}</p>
            <p class="text-xs mt-2 text-[var(--ink-400)] font-medium">{{ __('app.dashboard.purchases_not_yet_paid') }}</p>
        </div>
    </div>

    {{-- Chart + top products --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">

        <div class="xl:col-span-2 bg-white rounded-3xl p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.dashboard.sales_overview') }}</h3>
                    <p class="text-xs text-[var(--ink-400)] mt-0.5">{{ __('app.dashboard.this_week_vs_last_week') }}</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-medium">
                    <span class="flex items-center gap-1.5 text-[var(--ink-700)]"><span class="w-2.5 h-2.5 rounded-full bg-[var(--brand-600)]"></span> {{ __('app.dashboard.this_week') }}</span>
                    <span class="flex items-center gap-1.5 text-[var(--ink-400)]"><span class="w-2.5 h-2.5 rounded-full bg-[var(--ink-200)]"></span> {{ __('app.dashboard.last_week') }}</span>
                </div>
            </div>

            @php
                $maxVal = max(1, collect($days)->flatMap(fn($d) => [$d['this'], $d['last']])->max());
                $scale = 150 / $maxVal;
            @endphp
            <div class="mt-6 flex items-end justify-between gap-3 h-[200px]" role="img" aria-label="{{ __('app.dashboard.bar_chart_aria') }}">
                @foreach($days as $day)
                    <div class="flex flex-col items-center gap-2 flex-1">
                        <div class="w-full flex items-end justify-center gap-1 h-40">
                            <div class="bar w-3 bg-[var(--ink-200)] rounded-md" style="height: {{ max(2, $day['last'] * $scale) }}px" title="{{ __('app.dashboard.last_week_tooltip') }}: {{ Money::rupiah($day['last']) }}"></div>
                            <div class="bar w-3 bg-[var(--brand-600)] rounded-md" style="height: {{ max(2, $day['this'] * $scale) }}px" title="{{ __('app.dashboard.this_week_tooltip') }}: {{ Money::rupiah($day['this']) }}"></div>
                        </div>
                        <span class="text-[10px] text-[var(--ink-400)]">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6" id="insights">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="font-semibold text-lg text-[var(--ink-900)]">{{ __('insight.dashboard.top_products') }}</h3>
                    <p class="text-xs text-[var(--ink-400)] mt-0.5">{{ __('insight.dashboard.by_units_sold') }}</p>
                </div>
                @include('partials.range-tabs', ['range' => $range])
            </div>

            <div class="mt-5 space-y-1" id="topProductsList">
                @php $badge = ['brand', 'warn', 'good', 'bad']; @endphp
                @forelse($topProducts as $i => $row)
                    @php $tone = $badge[$i % count($badge)]; @endphp
                    <div class="product-row flex items-center gap-3 p-2 rounded-xl">
                        <div class="w-8 h-8 rounded-xl bg-[var(--{{ $tone }}-100)] text-[var(--{{ $tone }}-600)] flex items-center justify-center text-xs font-bold">{{ $i + 1 }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[var(--ink-900)] truncate">{{ $row['product']->name }}</p>
                            <p class="text-xs text-[var(--ink-400)]">{{ $row['product']->formatQuantity($row['sold']) }} {{ __('app.dashboard.sold') }}</p>
                        </div>
                        <span class="text-sm font-semibold text-[var(--ink-900)]">{{ Money::rupiah($row['revenue']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-[var(--ink-400)] py-6 text-center">{{ __('insight.dashboard.no_sales_in_range') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Income per payment method (same range as the best sellers) --}}
    <div class="bg-white rounded-3xl p-6 mt-6" id="incomeByMethod">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h3 class="font-semibold text-lg text-[var(--ink-900)]">{{ __('insight.dashboard.income_by_method') }}</h3>
                <p class="text-xs text-[var(--ink-400)] mt-0.5">{{ __('insight.dashboard.income_by_method_hint') }}</p>
            </div>
            @include('partials.range-tabs', ['range' => $range])
        </div>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @forelse($incomeByMethod as $method)
                <div class="rounded-2xl bg-[var(--surface)] p-4">
                    <p class="text-xs text-[var(--ink-400)] font-semibold uppercase tracking-wide">
                        {{ $method['name'] }}
                        @unless($method['is_active'])<span class="normal-case">({{ __('insight.dashboard.inactive') }})</span>@endunless
                    </p>
                    <p class="text-xl font-extrabold text-[var(--ink-900)] mt-1">{{ Money::rupiah($method['income']) }}</p>
                    <p class="text-xs text-[var(--ink-400)] mt-1">
                        {{ __('insight.dashboard.cash_sales') }} {{ Money::rupiah($method['cash_sales']) }}
                        &middot; {{ __('insight.dashboard.receivable_payments') }} {{ Money::rupiah($method['ar_received']) }}
                    </p>
                </div>
            @empty
                <p class="text-sm text-[var(--ink-400)] py-6 text-center sm:col-span-2 xl:col-span-4">{{ __('insight.dashboard.no_income_in_range') }}</p>
            @endforelse
        </div>
        @if(count($incomeByMethod))
            <p class="text-sm font-semibold text-[var(--ink-900)] mt-4">
                {{ __('insight.dashboard.total_income') }}: {{ Money::rupiah(collect($incomeByMethod)->sum('income')) }}
            </p>
        @endif
    </div>

    {{-- Low stock + top customers --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mt-6">

        <div class="bg-white rounded-3xl p-6">
            <h3 class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.dashboard.low_stock_products') }}</h3>
            <p class="text-xs text-[var(--ink-400)] mt-0.5">{{ __('app.dashboard.balance_below_threshold') }}</p>

            <div class="mt-5 space-y-1">
                @forelse($lowStockProducts as $product)
                    <div class="product-row flex items-center gap-3 p-2 rounded-xl">
                        <div class="w-10 h-10 rounded-xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[var(--ink-900)] truncate">{{ $product->name }}</p>
                            <p class="text-xs text-[var(--ink-400)]">{{ $product->code }}</p>
                        </div>
                        <span class="chip bg-[var(--bad-100)] text-[var(--bad-600)]">{{ (int) $product->balance }} {{ __('app.dashboard.left') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-[var(--ink-400)] py-6 text-center">{{ __('app.dashboard.all_products_well_stocked') }}</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6">
            <h3 class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.dashboard.top_customers') }}</h3>
            <p class="text-xs text-[var(--ink-400)] mt-0.5">{{ __('app.dashboard.by_total_revenue') }}</p>

            <div class="mt-5 space-y-1">
                @forelse($topCustomers as $customer)
                    <div class="product-row flex items-center gap-3 p-2 rounded-xl">
                        <div class="w-10 h-10 rounded-xl bg-[var(--brand-100)] text-[var(--brand-600)] flex items-center justify-center">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[var(--ink-900)] truncate">{{ $customer->name }}</p>
                            <p class="text-xs text-[var(--ink-400)]">{{ $customer->orders }} {{ __('app.dashboard.orders') }}</p>
                        </div>
                        <span class="text-sm font-semibold text-[var(--ink-900)]">{{ Money::rupiah($customer->total) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-[var(--ink-400)] py-6 text-center">{{ __('app.dashboard.no_sales_recorded_yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent orders --}}
    <div class="flex items-center justify-between mt-10 flex-wrap gap-4">
        <h3 class="text-2xl font-bold text-[var(--ink-900)]">{{ __('app.dashboard.recent_orders') }}</h3>
    </div>

    <div class="bg-white rounded-3xl mt-5 overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.dashboard.invoice') }}</th>
                    <th class="font-semibold">{{ __('app.dashboard.customer') }}</th>
                    <th class="font-semibold">{{ __('app.dashboard.items') }}</th>
                    <th class="font-semibold">{{ __('app.dashboard.source') }}</th>
                    <th class="font-semibold">{{ __('app.dashboard.date') }}</th>
                    <th class="font-semibold">{{ __('app.dashboard.total') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.dashboard.receipt') }}</th>
                </tr>
            </thead>

            <tbody id="orderBody">
                @forelse($orders as $order)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $order['id'] }}</td>
                        <td class="text-[var(--ink-700)]">{{ $order['customer'] }}</td>
                        <td class="text-[var(--ink-400)]">{{ $order['items'] }} {{ __('app.dashboard.items_count') }}</td>
                        <td class="text-[var(--ink-700)]">{{ ucfirst($order['source']) }}</td>
                        <td class="text-[var(--ink-400)]">{{ $order['date'] }} &middot; {{ $order['time'] }}</td>
                        <td class="font-semibold text-[var(--ink-900)]">{{ Money::rupiah($order['total']) }}</td>
                        <td class="text-right pr-5">
                            <button class="dl-btn border border-gray-200 text-[var(--ink-700)] rounded-full px-4 py-2 text-xs font-medium hover:border-[var(--brand-600)] hover:text-[var(--brand-600)] transition-colors">{{ __('app.dashboard.print') }}</button>
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>

        <p id="emptyState" class="{{ count($orders) ? 'hidden' : '' }} text-center py-14 text-[var(--ink-400)] text-sm">
            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
            {{ __('app.dashboard.no_orders_match_view') }}
        </p>
    </div>

@endsection

@push('styles')
    <style>
        .card-day { background: linear-gradient(135deg, #3b4cf6 0%, #6c4cf6 100%); box-shadow: 0 20px 40px -14px rgba(59,76,246,.45); }
        .card-week { background: linear-gradient(135deg, #0e9f6e 0%, #12b76a 100%); box-shadow: 0 20px 40px -14px rgba(18,183,106,.45); }
        .card-month { background: linear-gradient(135deg, #f79009 0%, #f04438 100%); box-shadow: 0 20px 40px -14px rgba(247,144,9,.45); }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
