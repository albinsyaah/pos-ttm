@extends('layouts.app')

@section('title', __('app.receivable_payments.title'))
@section('page-title', __('app.receivable_payments.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('transactions.receivable-payments.index') }}" method="GET" class="relative" data-live-search="auto">
            <label class="sr-only" for="receivablePaymentSearch">{{ __('app.receivable_payments.search_receivable_payments') }}</label>
            <input
                id="receivablePaymentSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.receivable_payments.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('transactions.receivable-payments.manage')
            <button
                id="addReceivablePaymentBtn"
                type="button"
                data-action="{{ route('transactions.receivable-payments.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.receivable_payments.add_receivable_payment') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.receivable_payments.payment_number') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.payment_date') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.customer') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.payment_method') }}</th>
                    <th class="font-semibold">{{ __('app.receivable_payments.amount') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receivablePayments as $payment)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $payment->payment_number }}</td>
                        <td class="text-[var(--ink-400)]">{{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $payment->customer?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $payment->paymentMethod?->name ?? '-' }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="text-right pr-5">
                            @can('transactions.receivable-payments.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-receivable-payment-btn icon-btn"
                                        aria-label="Edit {{ $payment->payment_number }}"
                                        data-action="{{ route('transactions.receivable-payments.update', $payment) }}"
                                        data-payment-number="{{ $payment->payment_number }}"
                                        data-payment-date="{{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('Y-m-d') }}"
                                        data-customer-id="{{ $payment->customer_id }}"
                                        data-payment-method-id="{{ $payment->payment_method_id }}"
                                        data-payment-method-name="{{ $payment->paymentMethod?->name }}"
                                        data-amount="{{ $payment->amount }}"
                                        data-payment-id="{{ $payment->id }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-receivable-payment-btn icon-btn"
                                        aria-label="Delete {{ $payment->payment_number }}"
                                        data-action="{{ route('transactions.receivable-payments.destroy', $payment) }}"
                                        data-name="{{ $payment->payment_number }}"
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
                            {{ __('app.receivable_payments.no_receivable_payments_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $receivablePayments->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="receivablePaymentModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="receivablePaymentModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-lg max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-5">
                <h3 id="receivablePaymentModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.receivable_payments.add_receivable_payment') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="receivablePaymentForm" method="POST" action="{{ route('transactions.receivable-payments.store') }}"
                  data-outstanding-url="{{ route('transactions.receivable-payments.outstanding') }}"
                  data-text-over="{{ __('app.receivable_payments.over_amount') }}"
                  data-text-failed="{{ __('app.receivable_payments.balance_failed') }}">
                @csrf
                <div id="receivablePaymentFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="payment_number" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.receivable_payments.payment_number') }}</label>
                        <input id="payment_number" name="payment_number" type="text" readonly maxlength="100" placeholder="{{ __('app.auto_number') }}"
                               class="cursor-not-allowed text-[var(--ink-400)] w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="payment_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.receivable_payments.payment_date') }}</label>
                        <input id="payment_date" name="payment_date" type="date" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="customer_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.receivable_payments.customer') }}</label>
                        <select id="customer_id" name="customer_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($customers as $customer)
                                @php($owed = $customerTotals[$customer->id] ?? 0)
                                <option value="{{ $customer->id }}">{{ $customer->code }} — {{ $customer->name }} — {{ $owed > 0 ? __('app.receivable_payments.owes').' Rp'.number_format($owed, 2) : __('app.receivable_payments.owes_none') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="payment_method" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.receivable_payments.payment_method') }}</label>
                        <select id="payment_method" name="payment_method_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.receivable_payments.amount') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div id="balancePanel" class="hidden rounded-xl bg-[var(--surface)] p-3 text-xs text-[var(--ink-700)] space-y-1.5">
                        <div class="flex items-center justify-between"><span>{{ __('app.receivable_payments.total_owed') }}</span><strong data-bal="total"></strong></div>
                        <div class="flex items-center justify-between"><span>{{ __('app.receivable_payments.paying_now') }}</span><span data-bal="pay"></span></div>
                        <div class="flex items-center justify-between border-t border-gray-200 pt-1.5"><span>{{ __('app.receivable_payments.left_after') }}</span><strong data-bal="after"></strong></div>
                        <p data-bal="over" class="hidden text-[var(--bad-600)]"></p>
                        <div class="pt-1.5">
                            <p class="text-[10px] uppercase tracking-wide text-[var(--ink-400)] mb-1">{{ __('app.receivable_payments.invoice_breakdown') }}</p>
                            <div class="max-h-40 overflow-y-auto rounded-lg bg-white">
                                <table class="w-full text-[11px]">
                                    <thead>
                                        <tr class="text-left text-[var(--ink-400)]">
                                            <th class="py-1 px-2 font-medium">{{ __('app.receivable_payments.invoice') }}</th>
                                            <th class="py-1 px-2 font-medium">{{ __('app.receivable_payments.invoice_date') }}</th>
                                            <th class="py-1 px-2 font-medium text-right">{{ __('app.receivable_payments.invoice_left') }}</th>
                                            <th class="py-1 px-2 font-medium text-right">{{ __('app.receivable_payments.invoice_after') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody data-bal="rows"></tbody>
                                </table>
                            </div>
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.receivable_payments.delete_receivable_payment') }}</h3>
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
            document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'fa-triangle-exclamation', 'var(--bad-600)'));
        @endif
    </script>
    <script src="{{ asset('js/payment-balance.js') }}"></script>
    <script src="{{ asset('js/transactions-receivable-payments.js') }}"></script>
@endpush
