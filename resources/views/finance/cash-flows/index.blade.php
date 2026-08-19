@extends('layouts.app')

@section('title', __('app.cash_flows.title'))
@section('page-title', __('app.cash_flows.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('finance.cash-flows.index') }}" method="GET" class="relative">
            <label class="sr-only" for="cashFlowSearch">{{ __('app.cash_flows.search_cash_flows') }}</label>
            <input
                id="cashFlowSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.cash_flows.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('finance.cash-flows.manage')
            <button
                id="addCashFlowBtn"
                type="button"
                data-action="{{ route('finance.cash-flows.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.cash_flows.add_cash_flow') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[820px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.cash_flows.transaction_date') }}</th>
                    <th class="font-semibold">{{ __('app.cash_flows.type') }}</th>
                    <th class="font-semibold">{{ __('app.cash_flows.account') }}</th>
                    <th class="font-semibold">{{ __('app.cash_flows.description') }}</th>
                    <th class="font-semibold">{{ __('app.common.value') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cashFlows as $cashFlow)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ \Illuminate\Support\Carbon::parse($cashFlow->transaction_date)->format('d M Y') }}</td>
                        <td>
                            @if($cashFlow->type === 'in')
                                <span class="badge-good inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    <i class="fa-solid fa-arrow-down"></i> {{ __('app.cash_flows.cash_in') }}
                                </span>
                            @else
                                <span class="badge-bad inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    <i class="fa-solid fa-arrow-up"></i> {{ __('app.cash_flows.cash_out') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-[var(--ink-400)]">{{ $cashFlow->account?->account_name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)] max-w-[220px] truncate">{{ $cashFlow->description ?: '—' }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format((float) $cashFlow->amount, 2) }}</td>
                        <td class="text-right pr-5">
                            @can('finance.cash-flows.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-cash-flow-btn icon-btn"
                                        aria-label="Edit cash flow entry"
                                        data-action="{{ route('finance.cash-flows.update', $cashFlow) }}"
                                        data-transaction-date="{{ \Illuminate\Support\Carbon::parse($cashFlow->transaction_date)->format('Y-m-d') }}"
                                        data-type="{{ $cashFlow->type }}"
                                        data-account-id="{{ $cashFlow->account_id }}"
                                        data-description="{{ $cashFlow->description }}"
                                        data-amount="{{ $cashFlow->amount }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-cash-flow-btn icon-btn"
                                        aria-label="Delete cash flow entry"
                                        data-action="{{ route('finance.cash-flows.destroy', $cashFlow) }}"
                                        data-name="{{ $cashFlow->description ?: ($cashFlow->type === 'in' ? __('app.cash_flows.cash_in') : __('app.cash_flows.cash_out')) }}"
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
                            {{ __('app.cash_flows.no_cash_flows_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $cashFlows->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="cashFlowModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="cashFlowModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="cashFlowModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.cash_flows.add_cash_flow') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="cashFlowForm" method="POST" action="{{ route('finance.cash-flows.store') }}">
                @csrf
                <div id="cashFlowFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="transaction_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.cash_flows.transaction_date') }}</label>
                        <input id="transaction_date" name="transaction_date" type="date" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="type" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.cash_flows.type') }}</label>
                        <select id="type" name="type" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            <option value="in">{{ __('app.cash_flows.cash_in') }}</option>
                            <option value="out">{{ __('app.cash_flows.cash_out') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="account_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.cash_flows.account') }}</label>
                        <select id="account_id" name="account_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.value') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="description" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.cash_flows.description') }}</label>
                        <textarea id="description" name="description" rows="3" maxlength="1000"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"></textarea>
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.cash_flows.delete_cash_flow') }}</h3>
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
    <script src="{{ asset('js/finance-cash-flows.js') }}"></script>
@endpush
