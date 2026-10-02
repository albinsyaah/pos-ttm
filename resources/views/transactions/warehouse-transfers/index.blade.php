@extends('layouts.app')

@section('title', __('app.warehouse_transfers.title'))
@section('page-title', __('app.warehouse_transfers.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('transactions.warehouse-transfers.index') }}" method="GET" class="relative" data-live-search="auto">
            <label class="sr-only" for="warehouseTransferSearch">{{ __('app.warehouse_transfers.search_warehouse_transfers') }}</label>
            <input
                id="warehouseTransferSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.warehouse_transfers.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('transactions.warehouse-transfers.manage')
            <button
                id="addWarehouseTransferBtn"
                type="button"
                data-action="{{ route('transactions.warehouse-transfers.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.warehouse_transfers.add_warehouse_transfer') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[900px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.warehouse_transfers.mutation_number') }}</th>
                    <th class="font-semibold">{{ __('app.warehouse_transfers.mutation_date') }}</th>
                    <th class="font-semibold">{{ __('app.warehouse_transfers.from_warehouse') }}</th>
                    <th class="font-semibold">{{ __('app.warehouse_transfers.to_warehouse') }}</th>
                    <th class="font-semibold">{{ __('app.warehouse_transfers.requested_by') }}</th>
                    <th class="font-semibold">{{ __('app.warehouse_transfers.status') }}</th>
                    <th class="font-semibold">{{ __('app.warehouse_transfers.items') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($warehouseTransfers as $warehouseTransfer)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $warehouseTransfer->mutation_number }}</td>
                        <td class="text-[var(--ink-400)]">{{ \Illuminate\Support\Carbon::parse($warehouseTransfer->mutation_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $warehouseTransfer->fromWarehouse?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-700)]">{{ $warehouseTransfer->toWarehouse?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $warehouseTransfer->requestedBy?->name ?: '—' }}</td>
                        <td>
                            <span class="badge-{{ $warehouseTransfer->status === 'rejected' ? 'bad' : ($warehouseTransfer->status === 'completed' ? 'good' : 'neutral') }} inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ $warehouseTransfer->status }}
                            </span>
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $warehouseTransfer->internalMutationDetails->count() }}</td>
                        <td class="text-right pr-5">
                            @can('transactions.warehouse-transfers.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-warehouse-transfer-btn icon-btn"
                                        aria-label="Edit {{ $warehouseTransfer->mutation_number }}"
                                        data-action="{{ route('transactions.warehouse-transfers.update', $warehouseTransfer) }}"
                                        data-mutation-number="{{ $warehouseTransfer->mutation_number }}"
                                        data-mutation-date="{{ \Illuminate\Support\Carbon::parse($warehouseTransfer->mutation_date)->format('Y-m-d') }}"
                                        data-from-warehouse-id="{{ $warehouseTransfer->from_warehouse_id }}"
                                        data-to-warehouse-id="{{ $warehouseTransfer->to_warehouse_id }}"
                                        data-requested-by="{{ $warehouseTransfer->requested_by }}"
                                        data-status="{{ $warehouseTransfer->status }}"
                                        data-items="{{ $warehouseTransfer->internalMutationDetails->map(fn ($d) => ['product_id' => $d->product_id, 'qty' => $d->qty, 'notes' => $d->notes])->toJson() }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-warehouse-transfer-btn icon-btn"
                                        aria-label="Delete {{ $warehouseTransfer->mutation_number }}"
                                        data-action="{{ route('transactions.warehouse-transfers.destroy', $warehouseTransfer) }}"
                                        data-name="{{ $warehouseTransfer->mutation_number }}"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.warehouse_transfers.no_warehouse_transfers_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $warehouseTransfers->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="warehouseTransferModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="warehouseTransferModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-5">
                <h3 id="warehouseTransferModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.warehouse_transfers.add_warehouse_transfer') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="warehouseTransferForm" method="POST" action="{{ route('transactions.warehouse-transfers.store') }}">
                @csrf
                <div id="warehouseTransferFormMethod"></div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="mutation_number" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.warehouse_transfers.mutation_number') }}</label>
                        <input id="mutation_number" name="mutation_number" type="text" required maxlength="100"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="mutation_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.warehouse_transfers.mutation_date') }}</label>
                        <input id="mutation_date" name="mutation_date" type="date" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="from_warehouse_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.warehouse_transfers.from_warehouse') }}</label>
                        <select id="from_warehouse_id" name="from_warehouse_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="to_warehouse_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.warehouse_transfers.to_warehouse') }}</label>
                        <select id="to_warehouse_id" name="to_warehouse_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="requested_by" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.warehouse_transfers.requested_by') }}</label>
                        <select id="requested_by" name="requested_by"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->code }} — {{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.warehouse_transfers.status') }}</label>
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
                        <label class="block text-xs font-medium text-[var(--ink-700)]">{{ __('app.warehouse_transfers.line_items') }}</label>
                        <button type="button" id="addItemRowBtn" class="text-xs font-semibold text-[var(--brand-600)] hover:text-[var(--brand-700)]">
                            <i class="fa-solid fa-plus"></i> {{ __('app.warehouse_transfers.add_item') }}
                        </button>
                    </div>

                    <div class="rounded-2xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-[var(--surface)] text-[var(--ink-400)] uppercase tracking-wide">
                                <tr class="text-left">
                                    <th class="p-3 font-semibold">{{ __('app.warehouse_transfers.product') }}</th>
                                    <th class="p-3 font-semibold w-24">{{ __('app.warehouse_transfers.qty') }}</th>
                                    <th class="p-3 font-semibold">{{ __('app.warehouse_transfers.notes') }}</th>
                                    <th class="p-3 w-10"></th>
                                </tr>
                            </thead>
                            <tbody id="itemRows"></tbody>
                        </table>
                        <p id="noItemsMessage" class="text-center text-xs text-[var(--ink-400)] py-6">{{ __('app.warehouse_transfers.no_items_added') }}</p>
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
                <input type="text" name="items[__INDEX__][notes]" maxlength="500" class="item-notes w-full rounded-lg bg-[var(--surface)] py-2 px-2.5 text-xs outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
            </td>
            <td class="p-2 text-right">
                <button type="button" class="remove-item-btn icon-btn" aria-label="{{ __('app.warehouse_transfers.remove') }}">
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.warehouse_transfers.delete_warehouse_transfer') }}</h3>
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
    <script src="{{ asset('js/transactions-warehouse-transfers.js') }}"></script>
@endpush
