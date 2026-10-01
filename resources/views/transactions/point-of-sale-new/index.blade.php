@extends('layouts.app')

@section('title', __('app.point_of_sale_new.title'))
@section('page-title', __('app.point_of_sale_new.title'))

@push('styles')
<style>
    /* Checkout terminal. Scoped "pos-" classes so the look does not depend on a Tailwind rebuild. */
    .pos-field { width: 100%; border-radius: .75rem; background: var(--surface); padding: .65rem 1rem; font-size: .875rem; border: 1px solid transparent; outline: none; transition: border-color .15s, background-color .15s; }
    .pos-field:focus { border-color: var(--brand-600); background: #fff; }
    .pos-label { display: block; font-size: .75rem; font-weight: 500; color: var(--ink-700); margin-bottom: .375rem; }

    .pos-search-wrap { position: relative; }
    .pos-search { width: 100%; border-radius: 1rem; background: var(--surface); padding: .95rem 1rem .95rem 2.9rem; font-size: 1rem; border: 2px solid transparent; outline: none; transition: border-color .15s, background-color .15s; }
    .pos-search:focus { border-color: var(--brand-600); background: #fff; }
    .pos-search:disabled { opacity: .6; cursor: not-allowed; }
    .pos-search-icon { position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: var(--ink-400); pointer-events: none; }

    .pos-results { margin-top: .5rem; border: 1px solid var(--ink-200); border-radius: 1rem; background: #fff; max-height: 20rem; overflow-y: auto; }
    .pos-status { padding: .9rem 1rem; font-size: .8rem; color: var(--ink-400); }
    .pos-result { display: flex; width: 100%; justify-content: space-between; align-items: center; gap: 1rem; padding: .7rem 1rem; text-align: left; background: #fff; border: 0; border-bottom: 1px solid var(--surface); cursor: pointer; }
    .pos-result:last-child { border-bottom: 0; }
    .pos-result:hover, .pos-result.is-active { background: var(--brand-100); }
    .pos-result.is-out { opacity: .55; }
    .pos-result-name { font-size: .875rem; font-weight: 600; color: var(--ink-900); }
    .pos-result-sub { font-size: .7rem; color: var(--ink-400); margin-top: .1rem; }
    .pos-result-side { text-align: right; flex-shrink: 0; font-size: .75rem; }
    .pos-stock-ok { color: var(--good-600); font-weight: 600; }
    .pos-stock-low { color: var(--warn-600); font-weight: 600; }
    .pos-stock-out { color: var(--bad-600); font-weight: 600; }

    .pos-cart-wrap { border: 1px solid var(--ink-200); border-radius: 1rem; overflow-x: auto; }
    .pos-row.pos-row-bad { background: var(--bad-100); }
    .pos-name { font-size: .8rem; font-weight: 600; color: var(--ink-900); }
    .pos-sub { font-size: .7rem; color: var(--ink-400); margin-top: .1rem; }
    .pos-error { color: var(--bad-600); font-size: .7rem; margin-top: .25rem; }
    .pos-link { margin-top: .35rem; font-size: .7rem; font-weight: 600; color: var(--brand-600); background: none; border: 0; padding: 0; cursor: pointer; }
    .pos-link:hover { color: var(--brand-700); }
    .pos-badge-free { display: inline-block; margin-left: .4rem; padding: .05rem .5rem; border-radius: 999px; background: var(--good-100); color: var(--good-600); font-size: .65rem; font-weight: 700; vertical-align: middle; }
    .pos-flash { animation: pos-flash .7s ease; }
    @keyframes pos-flash { from { background: var(--brand-100); } to { background: transparent; } }

    .pos-stepper { display: flex; align-items: center; gap: .25rem; }
    .pos-stepper button { width: 1.75rem; height: 1.75rem; border-radius: .5rem; background: var(--surface); border: 0; color: var(--ink-700); font-weight: 700; cursor: pointer; flex-shrink: 0; }
    .pos-stepper button:hover { background: var(--brand-100); color: var(--brand-700); }
    .pos-stepper input { width: 3.5rem; text-align: center; }

    .pos-seg { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem; padding: .25rem; background: var(--surface); border-radius: .875rem; }
    .pos-seg label { cursor: pointer; }
    .pos-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .pos-seg span { display: block; text-align: center; padding: .55rem .5rem; border-radius: .7rem; font-size: .8rem; font-weight: 600; color: var(--ink-700); transition: background-color .15s, color .15s; }
    .pos-seg input:checked + span { background: var(--brand-600); color: #fff; }
    .pos-seg input:focus-visible + span { outline: 2px solid var(--focus); outline-offset: 2px; }
    .pos-note { font-size: .7rem; color: var(--ink-400); margin-top: .35rem; }

    .pos-field-sm { padding: .45rem .5rem; font-size: .75rem; border-radius: .5rem; }
    .pos-label-inline { margin-bottom: 0; }
    .pos-stack > * + * { margin-top: 1rem; }
    .pos-summary { border-top: 1px solid var(--surface); margin-top: 1.5rem; padding-top: 1.25rem; }
    .pos-total-row { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 1.25rem; }
    .pos-total { font-size: 1.875rem; font-weight: 600; color: var(--ink-900); line-height: 1.1; }
    .pos-submit { width: 100%; display: flex; align-items: center; justify-content: center; gap: .5rem; background: var(--brand-600); color: #fff; font-size: .875rem; font-weight: 600; border: 0; border-radius: 999px; padding: .85rem 1.25rem; cursor: pointer; transition: background-color .15s; }
    .pos-submit:hover { background: var(--brand-700); }
    .pos-submit:disabled { opacity: .6; cursor: not-allowed; }
</style>
@endpush

@section('content')

    <p class="text-sm text-[var(--ink-400)] -mt-2 mb-6">{{ __('app.point_of_sale_new.subtitle') }}</p>

    <form id="posNewForm" method="POST" action="{{ route('transactions.point-of-sale-new.store') }}" class="grid lg:grid-cols-3 gap-6 items-start">
        @csrf

        {{-- Left: warehouse, product search and the cart --}}
        <div class="lg:col-span-2 bg-white rounded-3xl p-6">
            <div class="mb-5 sm:w-1/2">
                <label for="warehouse_id" class="pos-label">{{ __('app.point_of_sale_new.warehouse') }}</label>
                <select id="warehouse_id" name="warehouse_id" required class="pos-field">
                    <option value="">{{ __('app.common.select') }}</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pos-search-wrap">
                <i class="fa-solid fa-magnifying-glass pos-search-icon"></i>
                <input id="productSearch" type="search" autocomplete="off" spellcheck="false" disabled
                       class="pos-search"
                       placeholder="{{ __('app.point_of_sale_new.choose_warehouse') }}"
                       aria-label="{{ __('app.point_of_sale_new.search_label') }}"
                       aria-controls="searchResults" />
            </div>
            <div id="searchResults" class="pos-results hidden" role="listbox" aria-label="{{ __('app.point_of_sale_new.search_label') }}"></div>

            <div class="mt-6">
                <div class="flex items-center justify-between mb-2">
                    <label class="pos-label pos-label-inline">{{ __('app.point_of_sale_new.cart') }}</label>
                    <span id="cartCount" class="text-xs text-[var(--ink-400)]"></span>
                </div>

                <div class="pos-cart-wrap">
                    <table class="w-full text-xs">
                        <thead class="bg-[var(--surface)] text-[var(--ink-400)] uppercase tracking-wide">
                            <tr class="text-left">
                                <th class="p-3 font-semibold">{{ __('app.point_of_sale_new.product') }}</th>
                                <th class="p-3 font-semibold w-40">{{ __('app.point_of_sale_new.qty') }}</th>
                                <th class="p-3 font-semibold w-36">{{ __('app.point_of_sale_new.price') }}</th>
                                <th class="p-3 font-semibold w-32">{{ __('app.point_of_sale_new.line_total') }}</th>
                                <th class="p-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="itemRows"></tbody>
                    </table>
                    <p id="noItemsMessage" class="text-center text-xs text-[var(--ink-400)] py-10">{{ __('app.point_of_sale_new.no_items_added') }}</p>
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
            <tr class="item-row pos-row border-t border-gray-100">
                <td class="p-3 align-top">
                    <input type="hidden" name="items[__INDEX__][product_id]" class="item-product" />
                    <div class="pos-name"><span class="item-name"></span><span class="item-free-badge pos-badge-free hidden">{{ __('app.point_of_sale_new.free') }}</span></div>
                    <div class="pos-sub"><span class="item-code"></span> · <span class="item-stock"></span></div>
                    <div class="item-error pos-error hidden"></div>
                    <button type="button" class="add-free-btn pos-link hidden"><i class="fa-solid fa-gift"></i> {{ __('app.point_of_sale_new.add_free') }}</button>
                </td>
                <td class="p-2 align-top">
                    <div class="pos-stepper">
                        <button type="button" class="qty-minus" aria-label="{{ __('app.point_of_sale_new.qty_decrease') }}">−</button>
                        <input type="number" name="items[__INDEX__][qty]" min="1" step="1" required class="item-qty pos-field pos-field-sm" />
                        <button type="button" class="qty-plus" aria-label="{{ __('app.point_of_sale_new.qty_increase') }}">+</button>
                    </div>
                </td>
                <td class="p-2 align-top">
                    <input type="number" name="items[__INDEX__][price]" min="0" step="0.01" required class="item-price pos-field pos-field-sm" />
                    <span class="item-price-hint block text-[10px] mt-1 text-[var(--ink-400)]"></span>
                </td>
                <td class="p-3 align-top item-line-total text-[var(--ink-700)] font-medium">0.00</td>
                <td class="p-2 align-top text-right">
                    <button type="button" class="remove-item-btn icon-btn" aria-label="{{ __('app.point_of_sale_new.remove') }}">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                </td>
            </tr>
        </template>

        {{-- Right: transaction details, payment and total --}}
        <div class="bg-white rounded-3xl p-6 lg:sticky lg:top-6">
            <div class="pos-stack">
                <div>
                    <label for="invoice_number" class="pos-label">{{ __('app.point_of_sale_new.invoice_number') }}</label>
                    <input id="invoice_number" name="invoice_number" type="text" required maxlength="100" value="{{ old('invoice_number') }}" class="pos-field" />
                </div>
                <div>
                    <label for="sale_date" class="pos-label">{{ __('app.point_of_sale_new.sale_date') }}</label>
                    <input id="sale_date" name="sale_date" type="date" required value="{{ old('sale_date', now()->format('Y-m-d')) }}" class="pos-field" />
                </div>
                <div>
                    <label for="salesman_id" class="pos-label">{{ __('app.point_of_sale_new.salesman_optional') }}</label>
                    <select id="salesman_id" name="salesman_id" class="pos-field">
                        <option value="">{{ __('app.common.none') }}</option>
                        @foreach($salesmen as $salesman)
                            <option value="{{ $salesman->id }}" @selected(old('salesman_id') == $salesman->id)>{{ $salesman->code }} — {{ $salesman->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <span class="pos-label">{{ __('app.point_of_sale_new.payment') }}</span>
                    <div class="pos-seg" role="radiogroup" aria-label="{{ __('app.point_of_sale_new.payment') }}">
                        <label>
                            <input type="radio" name="payment_type" value="cash" @checked(old('payment_type', 'cash') !== 'credit') />
                            <span><i class="fa-solid fa-money-bill-wave"></i> {{ __('app.point_of_sale_new.payment_cash') }}</span>
                        </label>
                        <label>
                            <input type="radio" name="payment_type" value="credit" @checked(old('payment_type') === 'credit') />
                            <span><i class="fa-solid fa-file-invoice-dollar"></i> {{ __('app.point_of_sale_new.payment_credit') }}</span>
                        </label>
                    </div>
                    <p id="creditNote" class="pos-note hidden">{{ __('app.point_of_sale_new.credit_note') }}</p>
                </div>

                <div id="methodField">
                    <label for="payment_method_id" class="pos-label">{{ __('app.point_of_sale_new.payment_method') }}</label>
                    <select id="payment_method_id" name="payment_method_id" class="pos-field">
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->id }}" @selected($selectedMethodId === $method->id)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="customer_id" id="customerLabel" class="pos-label"
                           data-cash="{{ __('app.point_of_sale_new.customer_optional') }}"
                           data-credit="{{ __('app.point_of_sale_new.customer_credit_required') }}">{{ __('app.point_of_sale_new.customer_optional') }}</label>
                    <select id="customer_id" name="customer_id" class="pos-field">
                        <option value="">{{ __('app.common.none') }}</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="pos-summary">
                <div class="pos-total-row">
                    <span class="text-sm font-medium text-[var(--ink-400)]">{{ __('app.point_of_sale_new.total') }}</span>
                    <span id="grandTotal" class="pos-total">0.00</span>
                </div>
                <button id="submitBtn" type="submit" class="pos-submit">
                    <i class="fa-solid fa-cash-register"></i> <span id="submitLabel">{{ __('app.point_of_sale_new.complete_transaction') }}</span>
                </button>
                <a href="{{ route('transactions.point-of-sale.index') }}" class="mt-3 block text-center text-xs font-medium text-[var(--ink-400)] hover:text-[var(--ink-700)]">
                    {{ __('app.sidebar.point_of_sales') }}
                </a>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
@php
    // Built in plain PHP: @json() cannot compile a multi-line array that contains __().
    $labels = collect([
        'search_placeholder', 'choose_warehouse', 'searching', 'no_results', 'search_failed', 'stock',
        'out_of_stock', 'stock_short', 'duplicate_paid_row', 'duplicate_free_row', 'confirm_clear_cart',
        'cart_empty_toast', 'fix_cart', 'processing', 'complete_transaction', 'reference_price',
        'price_changed', 'no_reference_price', 'lines', 'free',
    ])->mapWithKeys(fn ($key) => [$key => __('app.point_of_sale_new.'.$key)])->all();
@endphp
    <script>
        window.POS_SEARCH_URL = @json(route('transactions.point-of-sale-new.products'));
        window.POS_LABELS = @json($labels);
        // Rows of a refused submission (e.g. not enough stock), so the cart is not lost.
        window.POS_CART_SEED = @json($cartSeed);
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success'))));
        @endif
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'fa-triangle-exclamation', 'var(--bad-600)'));
        @endif
    </script>
    <script src="{{ asset('js/transactions-point-of-sale-new.js') }}"></script>
@endpush
