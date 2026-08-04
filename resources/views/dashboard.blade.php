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
            <p class="text-3xl font-bold mt-3">${{ number_format($todaySales ?? 4286.50, 2) }}</p>
            <p class="text-xs mt-2 opacity-90 flex items-center gap-1">
                <i class="fa-solid fa-arrow-trend-up"></i> +12.4% vs yesterday
            </p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">ORDERS TODAY</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--brand-100)] text-[var(--brand-600)] flex items-center justify-center">
                    <i class="fa-solid fa-receipt text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">{{ $ordersToday ?? 186 }}</p>
            <p class="text-xs mt-2 text-[var(--good-600)] font-medium flex items-center gap-1">
                <i class="fa-solid fa-arrow-trend-up"></i> +8 vs yesterday
            </p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">AVG. ORDER VALUE</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--warn-100)] text-[var(--warn-600)] flex items-center justify-center">
                    <i class="fa-solid fa-tag text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">${{ number_format($avgOrderValue ?? 23.05, 2) }}</p>
            <p class="text-xs mt-2 text-[var(--bad-600)] font-medium flex items-center gap-1">
                <i class="fa-solid fa-arrow-trend-down"></i> -1.2% vs yesterday
            </p>
        </div>

        <div class="stat-card p-6">
            <div class="flex items-center justify-between">
                <p class="text-xs text-[var(--ink-400)] tracking-wide font-medium">LOW STOCK ITEMS</p>
                <div class="w-9 h-9 rounded-xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
            </div>
            <p class="text-3xl font-bold mt-3 text-[var(--ink-900)]">{{ $lowStockCount ?? 7 }}</p>
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

            {{-- Replace this static bar chart with real data, e.g. via Chart.js fed by $weeklySales --}}
            <div class="mt-6 flex items-end justify-between gap-3 h-[200px]" role="img" aria-label="Bar chart comparing daily sales this week to last week, Saturday is the highest day at $980">
                @php
                    $days = [
                        ['label' => 'Mon', 'last' => 24, 'this' => 28],
                        ['label' => 'Tue', 'last' => 20, 'this' => 32],
                        ['label' => 'Wed', 'last' => 28, 'this' => 20],
                        ['label' => 'Thu', 'last' => 16, 'this' => 24],
                        ['label' => 'Fri', 'last' => 24, 'this' => 36],
                        ['label' => 'Sat', 'last' => 32, 'this' => 40],
                        ['label' => 'Sun', 'last' => 16, 'this' => 20],
                    ];
                @endphp
                @foreach($days as $day)
                    <div class="flex flex-col items-center gap-2 flex-1">
                        <div class="w-full flex items-end justify-center gap-1 h-40">
                            <div class="bar w-3 bg-[var(--ink-200)] rounded-md" style="height: {{ $day['last'] * 4 }}px"></div>
                            <div class="bar w-3 bg-[var(--brand-600)] rounded-md" style="height: {{ $day['this'] * 4 }}px"></div>
                        </div>
                        <span class="text-[10px] {{ $day['label'] === 'Sat' ? 'text-[var(--ink-700)] font-medium' : 'text-[var(--ink-400)]' }}">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6">
            <h3 class="font-semibold text-lg text-[var(--ink-900)]">Top Products</h3>
            <p class="text-xs text-[var(--ink-400)] mt-0.5">By units sold today</p>

            <div class="mt-5 space-y-1">
                @php
                    $topProducts = $topProducts ?? [
                        ['icon' => 'fa-mug-hot', 'bg' => 'brand', 'name' => 'Caramel Latte', 'sold' => 42, 'revenue' => 210],
                        ['icon' => 'fa-cookie-bite', 'bg' => 'warn', 'name' => 'Choco Cookie', 'sold' => 35, 'revenue' => 87.50],
                        ['icon' => 'fa-bottle-water', 'bg' => 'good', 'name' => 'Sparkling Water', 'sold' => 31, 'revenue' => 62],
                        ['icon' => 'fa-burger', 'bg' => 'bad', 'name' => 'Beef Slider', 'sold' => 27, 'revenue' => 121.50],
                        ['icon' => 'fa-ice-cream', 'bg' => 'brand', 'name' => 'Vanilla Cone', 'sold' => 24, 'revenue' => 48],
                    ];
                @endphp
                @foreach($topProducts as $product)
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
                @endforeach
            </div>
        </div>
    </div>

    {{-- Recent orders --}}
    <div class="flex items-center justify-between mt-10 flex-wrap gap-4">
        <h3 class="text-2xl font-bold text-[var(--ink-900)]">Recent Orders</h3>
    </div>

    <div class="flex gap-2 mt-5 border-b border-gray-100" role="tablist" aria-label="Filter orders">
        <button class="chip filter-tab bg-[var(--brand-600)] text-white py-2 px-4" data-filter="all" role="tab" aria-selected="true">All Orders</button>
        <button class="chip filter-tab text-[var(--ink-400)] hover:bg-[var(--surface)] py-2 px-4" data-filter="completed" role="tab" aria-selected="false">Completed</button>
        <button class="chip filter-tab text-[var(--ink-400)] hover:bg-[var(--surface)] py-2 px-4" data-filter="pending" role="tab" aria-selected="false">Pending</button>
        <button class="chip filter-tab text-[var(--ink-400)] hover:bg-[var(--surface)] py-2 px-4" data-filter="refunded" role="tab" aria-selected="false">Refunded</button>
    </div>

    <div class="bg-white rounded-3xl mt-5 overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">Order</th>
                    <th class="font-semibold">Customer</th>
                    <th class="font-semibold">Items</th>
                    <th class="font-semibold">Payment</th>
                    <th class="font-semibold">Time</th>
                    <th class="font-semibold">Total</th>
                    <th class="font-semibold">Status</th>
                    <th class="font-semibold text-right pr-5">Receipt</th>
                </tr>
            </thead>

            <tbody id="orderBody">
                @php
                    $statusStyle = [
                        'completed' => ['bg' => 'good', 'label' => 'Completed'],
                        'pending'   => ['bg' => 'warn', 'label' => 'Pending'],
                        'refunded'  => ['bg' => 'bad',  'label' => 'Refunded'],
                    ];
                    $paymentIcon = [
                        'card'  => 'fa-credit-card',
                        'cash'  => 'fa-money-bill',
                        'qris'  => 'fa-qrcode',
                    ];
                    $orders = $orders ?? [
                        ['id' => '#ORD-3021', 'customer' => 'Sarah Kim',   'items' => 3, 'payment' => 'card', 'time' => '10:42 AM', 'total' => 34.50, 'status' => 'completed'],
                        ['id' => '#ORD-3022', 'customer' => 'Marco Diaz',  'items' => 5, 'payment' => 'cash', 'time' => '10:47 AM', 'total' => 58.20, 'status' => 'pending'],
                        ['id' => '#ORD-3023', 'customer' => 'Aiko Tanaka', 'items' => 2, 'payment' => 'qris', 'time' => '11:03 AM', 'total' => 18.00, 'status' => 'completed'],
                        ['id' => '#ORD-3024', 'customer' => 'James Cole',  'items' => 1, 'payment' => 'card', 'time' => '11:15 AM', 'total' => 12.00, 'status' => 'refunded'],
                        ['id' => '#ORD-3025', 'customer' => 'Priya Nair',  'items' => 4, 'payment' => 'cash', 'time' => '11:22 AM', 'total' => 41.75, 'status' => 'completed'],
                    ];
                @endphp

                @foreach($orders as $order)
                    <tr class="table-row border-b border-gray-50" data-type="{{ $order['status'] }}">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $order['id'] }}</td>
                        <td class="text-[var(--ink-700)]">{{ $order['customer'] }}</td>
                        <td class="text-[var(--ink-400)]">{{ $order['items'] }} items</td>
                        <td class="text-[var(--ink-700)]">
                            <i class="fa-solid {{ $paymentIcon[$order['payment']] }} mr-1.5 text-[var(--ink-400)]"></i>{{ ucfirst($order['payment']) }}
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $order['time'] }}</td>
                        <td class="font-semibold text-[var(--ink-900)]">${{ number_format($order['total'], 2) }}</td>
                        <td>
                            @php($style = $statusStyle[$order['status']])
                            <span class="chip bg-[var(--{{ $style['bg'] }}-100)] text-[var(--{{ $style['bg'] }}-600)]">
                                <span class="chip-dot bg-[var(--{{ $style['bg'] }}-600)]"></span>{{ $style['label'] }}
                            </span>
                        </td>
                        <td class="text-right pr-5">
                            <button class="dl-btn border border-gray-200 text-[var(--ink-700)] rounded-full px-4 py-2 text-xs font-medium hover:border-[var(--brand-600)] hover:text-[var(--brand-600)] transition-colors">Print</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p id="emptyState" class="hidden text-center py-14 text-[var(--ink-400)] text-sm">
            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
            No orders match this view.
        </p>
    </div>

    <nav class="flex items-center justify-end gap-2 mt-6 text-sm" aria-label="Pagination">
        <button class="px-3 py-2 text-[var(--ink-400)] hover:text-[var(--brand-600)] disabled:opacity-40" disabled>Previous</button>
        <button class="w-9 h-9 rounded-xl bg-[var(--brand-600)] text-white font-semibold" aria-current="page">1</button>
        <button class="w-9 h-9 rounded-xl text-[var(--ink-700)] hover:bg-[var(--surface)]">2</button>
        <button class="w-9 h-9 rounded-xl text-[var(--ink-700)] hover:bg-[var(--surface)]">3</button>
        <button class="w-9 h-9 rounded-xl text-[var(--ink-700)] hover:bg-[var(--surface)]">4</button>
        <button class="px-3 py-2 text-[var(--ink-700)] hover:text-[var(--brand-600)]">Next</button>
    </nav>

@endsection

@push('scripts')
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
