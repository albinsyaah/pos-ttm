@extends('layouts.app')

@section('title', __('app.payable_payments.title'))
@section('page-title', __('app.payable_payments.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('transactions.payable-payments.index') }}" method="GET" class="relative">
            <label class="sr-only" for="payablePaymentSearch">{{ __('app.payable_payments.search_payable_payments') }}</label>
            <input
                id="payablePaymentSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.payable_payments.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('transactions.manage')
            <button
                id="addPayablePaymentBtn"
                type="button"
                data-action="{{ route('transactions.payable-payments.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.payable_payments.add_payable_payment') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.payable_payments.payment_number') }}</th>
                    <th class="font-semibold">{{ __('app.payable_payments.payment_date') }}</th>
                    <th class="font-semibold">{{ __('app.payable_payments.supplier') }}</th>
                    <th class="font-semibold">{{ __('app.payable_payments.payment_method') }}</th>
                    <th class="font-semibold">{{ __('app.payable_payments.amount') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payablePayments as $payment)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $payment->payment_number }}</td>
                        <td class="text-[var(--ink-400)]">{{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $payment->supplier?->name ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $payment->payment_method }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="text-right pr-5">
                            @can('transactions.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-payable-payment-btn icon-btn"
                                        aria-label="Edit {{ $payment->payment_number }}"
                                        data-action="{{ route('transactions.payable-payments.update', $payment) }}"
                                        data-payment-number="{{ $payment->payment_number }}"
                                        data-payment-date="{{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('Y-m-d') }}"
                                        data-supplier-id="{{ $payment->supplier_id }}"
                                        data-payment-method="{{ $payment->payment_method }}"
                                        data-amount="{{ $payment->amount }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-payable-payment-btn icon-btn"
                                        aria-label="Delete {{ $payment->payment_number }}"
                                        data-action="{{ route('transactions.payable-payments.destroy', $payment) }}"
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
                            {{ __('app.payable_payments.no_payable_payments_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $payablePayments->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="payablePaymentModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="payablePaymentModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="payablePaymentModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.payable_payments.add_payable_payment') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="payablePaymentForm" method="POST" action="{{ route('transactions.payable-payments.store') }}">
                @csrf
                <div id="payablePaymentFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="payment_number" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.payable_payments.payment_number') }}</label>
                        <input id="payment_number" name="payment_number" type="text" required maxlength="100"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="payment_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.payable_payments.payment_date') }}</label>
                        <input id="payment_date" name="payment_date" type="date" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="supplier_id" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.payable_payments.supplier') }}</label>
                        <select id="supplier_id" name="supplier_id" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            <option value="">{{ __('app.common.select') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="payment_method" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.payable_payments.payment_method') }}</label>
                        <select id="payment_method" name="payment_method" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method }}">{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="amount" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.payable_payments.amount') }}</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.payable_payments.delete_payable_payment') }}</h3>
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
    <script src="{{ asset('js/transactions-payable-payments.js') }}"></script>
@endpush
