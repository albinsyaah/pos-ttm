@extends('layouts.app')

@section('title', __('app.purchase_orders.title'))
@section('page-title', __('app.purchase_orders.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('transactions.purchase-orders.index') }}" method="GET" class="relative">
            <label class="sr-only" for="purchaseOrderSearch">{{ __('app.purchase_orders.search_purchase_orders') }}</label>
            <input
                id="purchaseOrderSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.purchase_orders.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('transactions.manage')
            <button
                id="addPurchaseOrderBtn"
                type="button"
                data-action="{{ route('transactions.purchase-orders.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.purchase_orders.add_purchase_order') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[860px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.purchase_orders.po_number') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.order_date') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.supplier') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.status') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.items') }}</th>
                    <th class="font-semibold">{{ __('app.purchase_orders.total') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $purchaseOrder)
                    @php
                        $lineTotal = $purchaseOrder->purchaseOrderDetails->sum(fn ($d) => $d->qty * $d->price);
                    @endphp
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $purchaseOrder->po_number }}</td>
                        <td class="text-[var(--ink-400)]">{{ \Illuminate\Support\Carbon::parse($purchaseOrder->order_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $purchaseOrder->supplier?->name ?: '—' }}</td>
                        <td>
                            <span class="badge-{{ $purchaseOrder->status === 'cancelled' ? 'bad' : ($purchaseOrder->status === 'completed' ? 'good' : 'neutral') }} inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ $purchaseOrder->status }}
                            </span>
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $purchaseOrder->purchaseOrderDetails->count() }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format($lineTotal, 2) }}</td>
                        <td class="text-right pr-5">
                            @can('transactions.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-purchase-order-btn icon-btn"
                                        aria-label="Edit {{ $purchaseOrder->po_number }}"
                                        data-action="{{ route('transactions.purchase-orders.update', $purchaseOrder) }}"
                                        data-po-number="{{ $purchaseOrder->po_number }}"
                                        data-order-date="{{ \Illuminate\Support\Carbon::parse($purchaseOrder->order_date)->format('Y-m-d') }}"
                                        data-supplier-id="{{ $purchaseOrder->supplier_id }}"
                                        data-status="{{ $purchaseOrder->status }}"
                                        data-items="{{ $purchaseOrder->purchaseOrderDetails->map(fn ($d) => ['product_id' => $d->product_id, 'qty' => $d->qty, 'price' => $d->price])->toJson() }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-purchase-order-btn icon-btn"
                                        aria-label="Delete {{ $purchaseOrder->po_number }}"
                                        data-action="{{ route('transactions.purchase-orders.destroy', $purchaseOrder) }}"
                                        data-name="{{ $purchaseOrder->po_number }}"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.purchase_orders.no_purchase_orders_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $purchaseOrders->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="purchaseOrderModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="purchaseOrderModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-5">
                <h3 id="purchaseOrderModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.purchase_orders.add_purchase_order') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="purchaseOrderForm" method="POST" action="{{ route('transactions.purchase-orders.store') }}">
                @csrf
                <div id="purchaseOrderFormMethod"></div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="po_number" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.purchase_orders.po_number') }}</label>
                        <input id="po_number" name="po_number" type="text" required maxlength="100"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="order_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.purchase_orders.order_date') }}</label>
                        <input id="order_date" name="order_date" type="date" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="supplier_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.purchase_orders.supplier') }}</label>
                        <select id="supplier_id" name="supplier_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.purchase_orders.status') }}</label>
                        <select id="status" name="status" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-medium text-[var(--ink-700)]">{{ __('app.purchase_orders.line_items') }}</label>
                        <button type="button" id="addItemRowBtn" class="text-xs font-semibold text-[var(--brand-600)] hover:text-[var(--brand-700)]">
                            <i class="fa-solid fa-plus"></i> {{ __('app.purchase_orders.add_item') }}
                        </button>
                    </div>

                    <div class="rounded-2xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-[var(--surface)] text-[var(--ink-400)] uppercase tracking-wide">
                                <tr class="text-left">
                                    <th class="p-3 font-semibold">{{ __('app.purchase_orders.product') }}</th>
                                    <th class="p-3 font-semibold w-24">{{ __('app.purchase_orders.qty') }}</th>
                                    <th class="p-3 font-semibold w-32">{{ __('app.purchase_orders.price') }}</th>
                                    <th class="p-3 font-semibold w-32">{{ __('app.purchase_orders.line_total') }}</th>
                                    <th class="p-3 w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="itemRows"></tbody>
                        </table>
                        <p id="noItemsMessage" class="text-center text-xs text-[var(--ink-400)] py-6">{{ __('app.purchase_orders.no_items_added') }}</p>
                    </div>

                    <div class="flex items-center justify-end mt-3 text-sm">
                        <span class="text-[var(--ink-400)] mr-2">{{ __('app.purchase_orders.total') }}:</span>
                        <span id="grandTotal" class="font-semibold text-[var(--ink-900)]">0.00</span>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="mt-4 text-xs text-[var(--bad-600)] space-y-1">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button type="button" class="modal-close text-sm font-medium text-[var(--ink-700)] px-4 py-2.5">{{ __('app.common.cancel') }}</button>
                    <button type="submit" class="bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">{{ __('app.common.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Row template for a line item --}}
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
            </td>
            <td class="p-2 item-line-total text-[var(--ink-700)] font-medium">0.00</td>
            <td class="p-2 text-right">
                <button type="button" class="remove-item-btn icon-btn" aria-label="{{ __('app.purchase_orders.remove') }}">
                    <i class="fa-solid fa-trash text-xs"></i>
                </button>
            </td>
        </tr>
    </template>

    {{-- Delete confirmation modal --}}
    <div id="deleteModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-sm">
            <div class="w-12 h-12 rounded-2xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center mb-4">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.purchase_orders.delete_purchase_order') }}</h3>
            <p id="deleteModalText" class="text-sm text-[var(--ink-400)] mt-1.5">{{ __('app.common.this_action_cannot_be_undone') }}</p>

            <form id="deleteForm" method="POST" class="mt-6 flex items-center justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" class="modal-close text-sm font-medium text-[var(--ink-700)] px-4 py-2.5">{{ __('app.common.cancel') }}</button>
                <button type="submit" class="bg-[var(--bad-600)] hover:opacity-90 text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">{{ __('app.common.delete') }}</button>
            </form>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(26, 33, 56, .45);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            z-index: 50;
        }
        .modal-overlay.hidden { display: none; }
        .modal-card { box-shadow: 0 24px 48px -16px rgba(26,33,56,.35); }
        .badge-neutral { background: var(--surface); color: var(--ink-700); }
    </style>
@endpush

@push('scripts')
    <script>
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success'))));
        @endif
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'fa-triangle-exclamation', 'var(--bad-600)'));
        @endif
    </script>
    <script src="{{ asset('js/transactions-purchase-orders.js') }}"></script>
@endpush
