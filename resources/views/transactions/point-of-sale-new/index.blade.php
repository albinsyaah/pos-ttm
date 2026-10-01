@extends('layouts.app')

@section('title', __('app.point_of_sale_new.title'))
@section('page-title', __('app.point_of_sale_new.title'))

@section('content')

    <p class="text-sm text-[var(--ink-400)] -mt-2 mb-6">{{ __('app.point_of_sale_new.subtitle') }}</p>

    <form id="posNewForm" method="POST" action="{{ route('transactions.point-of-sale-new.store') }}" class="grid lg:grid-cols-3 gap-6 items-start">
        @csrf

        <div class="lg:col-span-2 bg-white rounded-3xl p-6">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="invoice_number" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.point_of_sale_new.invoice_number') }}</label>
                    <input id="invoice_number" name="invoice_number" type="text" required maxlength="100" value="{{ old('invoice_number') }}"
                           class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                </div>
                <div>
                    <label for="sale_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.point_of_sale_new.sale_date') }}</label>
                    <input id="sale_date" name="sale_date" type="date" required value="{{ old('sale_date', now()->format('Y-m-d')) }}"
                           class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                </div>
                <div>
                    <label for="customer_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.point_of_sale_new.customer_optional') }}</label>
                    <select id="customer_id" name="customer_id"
                           class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                        <option value="">{{ __('app.common.none') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="salesman_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.point_of_sale_new.salesman_optional') }}</label>
                    <select id="salesman_id" name="salesman_id"
                           class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                        <option value="">{{ __('app.common.none') }}</option>
                        @foreach($salesmen as $salesman)
                            <option value="{{ $salesman->id }}" @selected(old('salesman_id') == $salesman->id)>{{ $salesman->code }} — {{ $salesman->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="warehouse_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.point_of_sale_new.warehouse') }}</label>
                    <select id="warehouse_id" name="warehouse_id" required
                           class="w-full sm:w-1/2 rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                        <option value="">{{ __('app.common.select') }}</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-medium text-[var(--ink-700)]">{{ __('app.point_of_sale_new.cart') }}</label>
                    <button type="button" id="addItemRowBtn" class="text-xs font-semibold text-[var(--brand-600)] hover:text-[var(--brand-700)]">
                        <i class="fa-solid fa-plus"></i> {{ __('app.point_of_sale_new.add_item') }}
                    </button>
                </div>

                <div class="rounded-2xl border border-gray-100 overflow-hidden">
                    <table class="w-full text-xs">
                        <thead class="bg-[var(--surface)] text-[var(--ink-400)] uppercase tracking-wide">
                            <tr class="text-left">
                                <th class="p-3 font-semibold">{{ __('app.point_of_sale_new.product') }}</th>
                                <th class="p-3 font-semibold w-24">{{ __('app.point_of_sale_new.qty') }}</th>
                                <th class="p-3 font-semibold w-32">{{ __('app.point_of_sale_new.price') }}</th>
                                <th class="p-3 font-semibold w-32">{{ __('app.point_of_sale_new.line_total') }}</th>
                                <th class="p-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="itemRows"></tbody>
                    </table>
                    <p id="noItemsMessage" class="text-center text-xs text-[var(--ink-400)] py-8">{{ __('app.point_of_sale_new.no_items_added') }}</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="mt-4 text-xs text-[var(--bad-600)] space-y-1">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Row template for a cart item --}}
        <template id="itemRowTemplate">
            <tr class="item-row border-t border-gray-100">
                <td class="p-2">
                    <select name="items[__INDEX__][product_id]" required class="item-product w-full rounded-lg bg-[var(--surface)] py-2 px-2.5 text-xs outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                        <option value="">{{ __('app.common.select') }}</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="p-2">
                    <input type="number" name="items[__INDEX__][qty]" min="1" step="1" required class="item-qty w-full rounded-lg bg-[var(--surface)] py-2 px-2.5 text-xs outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                </td>
                <td class="p-2">
                    <input type="number" name="items[__INDEX__][price]" min="0" step="0.01" required class="item-price w-full rounded-lg bg-[var(--surface)] py-2 px-2.5 text-xs outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    <span class="item-price-hint block text-[10px] mt-1 text-[var(--ink-400)]"></span>
                </td>
                <td class="p-2 item-line-total text-[var(--ink-700)] font-medium">0.00</td>
                <td class="p-2 text-right">
                    <button type="button" class="remove-item-btn icon-btn" aria-label="{{ __('app.point_of_sale_new.remove') }}">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                </td>
            </tr>
        </template>

        {{-- Summary / checkout panel --}}
        <div class="bg-white rounded-3xl p-6 lg:sticky lg:top-6">
            <h3 class="font-semibold text-lg text-[var(--ink-900)] mb-4">{{ __('app.point_of_sale_new.total') }}</h3>
            <div class="flex items-center justify-between text-2xl font-semibold text-[var(--ink-900)] mb-6">
                <span class="text-sm font-medium text-[var(--ink-400)]">{{ __('app.point_of_sale_new.total') }}</span>
                <span id="grandTotal">0.00</span>
            </div>
            <button type="submit" class="w-full flex items-center justify-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-3 transition-colors">
                <i class="fa-solid fa-cash-register"></i> {{ __('app.point_of_sale_new.complete_transaction') }}
            </button>
            <a href="{{ route('transactions.point-of-sale.index') }}" class="mt-3 block text-center text-xs font-medium text-[var(--ink-400)] hover:text-[var(--ink-700)]">
                {{ __('app.sidebar.point_of_sales') }}
            </a>
        </div>
    </form>

@endsection

@push('scripts')
@php
    $priceLabels = [
        'reference' => __('app.point_of_sale_new.reference_price'),
        'changed' => __('app.point_of_sale_new.price_changed'),
        'none' => __('app.point_of_sale_new.no_reference_price'),
    ];
@endphp
    <script>
        // Reference selling prices (harga patokan): product id => dated prices, newest first.
        window.POS_PRICE_BOOK = @json($priceBook);
        window.POS_PRICE_LABELS = @json($priceLabels);
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success'))));
        @endif
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'fa-triangle-exclamation', 'var(--bad-600)'));
        @endif
    </script>
    <script src="{{ asset('js/transactions-point-of-sale-new.js') }}"></script>
@endpush
