@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

    {{-- Stat cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">

        <div class="stat-card card-gradient text-white p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs opacity-80 tracking-wide">TODAY'S SALES</p>
                <i class="fa-solid fa-sack-dollar opacity-80"></i>
            </div>
            <p class="text-3xl font-bold mt-3">${{ number_format($todaySales, 2) }}</p>
            <p class="text-xs mt-2 opacity-90">Total sales recorded today</p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">ORDERS TODAY</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--brand-100)] text-[var(--brand-600)] flex items-center justify-center">
                    <i class="fa-solid fa-receipt text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">{{ $ordersToday }}</p>
            <p class="text-xs mt-2 text-[var(--ink-400)] font-medium">Sales transactions today</p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">AVG. ORDER VALUE</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--warn-100)] text-[var(--warn-600)] flex items-center justify-center">
                    <i class="fa-solid fa-tag text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">${{ number_format($avgOrderValue, 2) }}</p>
            <p class="text-xs mt-2 text-[var(--ink-400)] font-medium">Today's sales &divide; orders</p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">LOW STOCK ITEMS</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">{{ $lowStockCount }}</p>
            <p class="text-xs mt-2 text-[var(--ink-400)] font-medium">Needs reordering</p>
        </div>
    </div>

    {{-- Chart + top products --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">

        <div class="xl:col-span-2 bg-white rounded-3xl p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="font-semibold text-lg text-[var(--ink-900)]">Sales Overview</h3>
                    <p class="text-xs text-[var(--ink-400)] mt-0.5">This week vs last week</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-medium">
                    <span class="flex items-center gap-1.5 text-[var(--ink-700)]"><span class="w-2.5 h-2.5 rounded-full bg-[var(--brand-600)]"></span> This week</span>
                    <span class="flex items-center gap-1.5 text-[var(--ink-400)]"><span class="w-2.5 h-2.5 rounded-full bg-[var(--ink-200)]"></span> Last week</span>
                </div>
            </div>

            @php
                $maxVal = max(1, collect($days)->flatMap(fn($d) => [$d['this'], $d['last']])->max());
                $scale = 150 / $maxVal;
            @endphp
            <div class="mt-6 flex items-end justify-between gap-3 h-[200px]" role="img" aria-label="Bar chart comparing daily sales this week to last week">
                @foreach($days as $day)
                    <div class="flex flex-col items-center gap-2 flex-1">
                        <div class="w-full flex items-end justify-center gap-1 h-40">
                            <div class="bar w-3 bg-[var(--ink-200)] rounded-md" style="height: {{ max(2, $day['last'] * $scale) }}px" title="Last week: ${{ number_format($day['last'], 2) }}"></div>
                            <div class="bar w-3 bg-[var(--brand-600)] rounded-md" style="height: {{ max(2, $day['this'] * $scale) }}px" title="This week: ${{ number_format($day['this'], 2) }}"></div>
                        </div>
                        <span class="text-[10px] text-[var(--ink-400)]">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6">
            <h3 class="font-semibold text-lg text-[var(--ink-900)]">Top Products</h3>
            <p class="text-xs text-[var(--ink-400)] mt-0.5">By units sold today</p>

            <div class="mt-5 space-y-1">
                @forelse($topProducts as $product)
                    <div class="product-row flex items-center gap-3 p-2 rounded-xl">
                        <div class="w-10 h-10 rounded-xl bg-[var(--{{ $product['bg'] }}-100)] text-[var(--{{ $product['bg'] }}-600)] flex items-center justify-center">
                            <i class="fa-solid {{ $product['icon'] }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-[var(--ink-900)] truncate">{{ $product['name'] }}</p>
                            <p class="text-xs text-[var(--ink-400)]">{{ $product['sold'] }} sold</p>
                        </div>
                        <span class="text-sm font-semibold text-[var(--ink-900)]">${{ number_format($product['revenue'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-[var(--ink-400)] py-6 text-center">No sales recorded yet today.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent orders --}}
    <div class="flex items-center justify-between mt-10 flex-wrap gap-4">
        <h3 class="text-2xl font-bold text-[var(--ink-900)]">Recent Orders</h3>
    </div>

    <div class="bg-white rounded-3xl mt-5 overflow-x-auto">
        <table class="w-full text-sm min-w-[720px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">Invoice</th>
                    <th class="font-semibold">Customer</th>
                    <th class="font-semibold">Items</th>
                    <th class="font-semibold">Source</th>
                    <th class="font-semibold">Date</th>
                    <th class="font-semibold">Total</th>
                    <th class="font-semibold text-right pr-5">Receipt</th>
                </tr>
            </thead>

            <tbody id="orderBody">
                @forelse($orders as $order)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $order['id'] }}</td>
                        <td class="text-[var(--ink-700)]">{{ $order['customer'] }}</td>
                        <td class="text-[var(--ink-400)]">{{ $order['items'] }} items</td>
                        <td class="text-[var(--ink-700)]">{{ ucfirst($order['source']) }}</td>
                        <td class="text-[var(--ink-400)]">{{ $order['date'] }} &middot; {{ $order['time'] }}</td>
                        <td class="font-semibold text-[var(--ink-900)]">${{ number_format($order['total'], 2) }}</td>
                        <td class="text-right pr-5">
                            <button class="dl-btn border border-gray-200 text-[var(--ink-700)] rounded-full px-4 py-2 text-xs font-medium hover:border-[var(--brand-600)] hover:text-[var(--brand-600)] transition-colors">Print</button>
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>

        <p id="emptyState" class="{{ count($orders) ? 'hidden' : '' }} text-center py-14 text-[var(--ink-400)] text-sm">
            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
            No orders match this view.
        </p>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
