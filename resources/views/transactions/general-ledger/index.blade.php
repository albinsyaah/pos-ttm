@extends('layouts.app')

@section('title', __('app.transactions_general_ledger.title'))
@section('page-title', __('app.transactions_general_ledger.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('transactions.general-ledger.index') }}" method="GET" class="relative">
            <label class="sr-only" for="txGeneralLedgerSearch">{{ __('app.transactions_general_ledger.search_ledgers') }}</label>
            <input
                id="txGeneralLedgerSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.transactions_general_ledger.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('transactions.manage')
            <button
                id="addTxLedgerBtn"
                type="button"
                data-action="{{ route('transactions.general-ledger.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.transactions_general_ledger.add_ledger') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[780px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.transactions_general_ledger.transaction_date') }}</th>
                    <th class="font-semibold">{{ __('app.transactions_general_ledger.account') }}</th>
                    <th class="font-semibold">{{ __('app.transactions_general_ledger.reference_number') }}</th>
                    <th class="font-semibold">{{ __('app.transactions_general_ledger.debit') }}</th>
                    <th class="font-semibold">{{ __('app.transactions_general_ledger.credit') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ledgers as $ledger)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ \Illuminate\Support\Carbon::parse($ledger->transaction_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $ledger->account?->account_name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $ledger->reference_number ?: '—' }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format((float) $ledger->debit, 2) }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format((float) $ledger->credit, 2) }}</td>
                        <td class="text-right pr-5">
                            @can('transactions.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-tx-ledger-btn icon-btn"
                                        aria-label="Edit ledger entry"
                                        data-action="{{ route('transactions.general-ledger.update', $ledger) }}"
                                        data-transaction-date="{{ \Illuminate\Support\Carbon::parse($ledger->transaction_date)->format('Y-m-d') }}"
                                        data-account-id="{{ $ledger->account_id }}"
                                        data-reference-number="{{ $ledger->reference_number }}"
                                        data-debit="{{ $ledger->debit }}"
                                        data-credit="{{ $ledger->credit }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-tx-ledger-btn icon-btn"
                                        aria-label="Delete ledger entry"
                                        data-action="{{ route('transactions.general-ledger.destroy', $ledger) }}"
                                        data-name="{{ $ledger->reference_number ?: $ledger->account?->account_name }}"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.transactions_general_ledger.no_ledgers_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $ledgers->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="txLedgerModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="txLedgerModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="txLedgerModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.transactions_general_ledger.add_ledger') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="txLedgerForm" method="POST" action="{{ route('transactions.general-ledger.store') }}">
                @csrf
                <div id="txLedgerFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="transaction_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.transactions_general_ledger.transaction_date') }}</label>
                        <input id="transaction_date" name="transaction_date" type="date" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="account_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.transactions_general_ledger.account') }}</label>
                        <select id="account_id" name="account_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reference_number" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.transactions_general_ledger.reference_number') }}</label>
                        <input id="reference_number" name="reference_number" type="text" maxlength="150"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="debit" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.transactions_general_ledger.debit') }}</label>
                            <input id="debit" name="debit" type="number" step="0.01" min="0" required value="0"
                                   class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                        </div>
                        <div>
                            <label for="credit" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.transactions_general_ledger.credit') }}</label>
                            <input id="credit" name="credit" type="number" step="0.01" min="0" required value="0"
                                   class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                        </div>
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

    {{-- Delete confirmation modal --}}
    <div id="deleteModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-sm">
            <div class="w-12 h-12 rounded-2xl bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center mb-4">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.transactions_general_ledger.delete_ledger') }}</h3>
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
    </style>
@endpush

@push('scripts')
    <script>
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success'))));
        @endif
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error'))));
        @endif
    </script>
    <script src="{{ asset('js/transactions-general-ledger.js') }}"></script>
@endpush
