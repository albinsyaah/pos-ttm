@extends('layouts.app')

@section('title', __('app.payment_methods.title'))
@section('page-title', __('app.payment_methods.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('finance.payment-methods.index') }}" method="GET" class="relative" data-live-search="auto">
            <label class="sr-only" for="paymentMethodSearch">{{ __('app.payment_methods.search') }}</label>
            <input
                id="paymentMethodSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.payment_methods.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('finance.payment-methods.manage')
            <button
                id="addPaymentMethodBtn"
                type="button"
                data-action="{{ route('finance.payment-methods.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> {{ __('app.payment_methods.add') }}
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[640px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.common.name') }}</th>
                    <th class="font-semibold">{{ __('app.payment_methods.kind') }}</th>
                    <th class="font-semibold">{{ __('app.payment_methods.status') }}</th>
                    <th class="font-semibold">{{ __('app.payment_methods.used') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($paymentMethods as $method)
                    @php($used = $method->sales_count + $method->ap_payments_count + $method->ar_payments_count)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $method->name }}</td>
                        <td class="text-[var(--ink-400)]">{{ $method->is_cash ? __('app.payment_methods.kind_cash') : __('app.payment_methods.kind_non_cash') }}</td>
                        <td class="{{ $method->is_active ? 'text-[var(--ink-700)]' : 'text-[var(--ink-400)]' }}">{{ $method->is_active ? __('app.payment_methods.active') : __('app.payment_methods.inactive') }}</td>
                        <td class="text-[var(--ink-400)]">{{ $used }}</td>
                        <td class="text-right pr-5">
                            @can('finance.payment-methods.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-payment-method-btn icon-btn"
                                        aria-label="Edit {{ $method->name }}"
                                        data-action="{{ route('finance.payment-methods.update', $method) }}"
                                        data-name="{{ $method->name }}"
                                        data-is-cash="{{ $method->is_cash ? 1 : 0 }}"
                                        data-is-active="{{ $method->is_active ? 1 : 0 }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-payment-method-btn icon-btn"
                                        aria-label="Delete {{ $method->name }}"
                                        data-action="{{ route('finance.payment-methods.destroy', $method) }}"
                                        data-name="{{ $method->name }}"
                                        data-used="{{ $used > 0 ? 1 : 0 }}"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.payment_methods.none_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $paymentMethods->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="paymentMethodModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="paymentMethodModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="paymentMethodModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.payment_methods.add') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="paymentMethodForm" method="POST" action="{{ route('finance.payment-methods.store') }}">
                @csrf
                <div id="paymentMethodFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.name') }}</label>
                        <input id="name" name="name" type="text" required maxlength="100"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>

                    <div>
                        <input type="hidden" name="is_cash" value="0" />
                        <label class="flex items-start gap-3 text-sm text-[var(--ink-700)]">
                            <input id="is_cash" name="is_cash" type="checkbox" value="1" class="mt-1" />
                            <span>
                                {{ __('app.payment_methods.is_cash') }}
                                <span class="block text-xs text-[var(--ink-400)]">{{ __('app.payment_methods.is_cash_hint') }}</span>
                            </span>
                        </label>
                    </div>

                    <div>
                        <input type="hidden" name="is_active" value="0" />
                        <label class="flex items-start gap-3 text-sm text-[var(--ink-700)]">
                            <input id="is_active" name="is_active" type="checkbox" value="1" checked class="mt-1" />
                            <span>
                                {{ __('app.payment_methods.is_active') }}
                                <span class="block text-xs text-[var(--ink-400)]">{{ __('app.payment_methods.is_active_hint') }}</span>
                            </span>
                        </label>
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.payment_methods.delete') }}</h3>
            <p id="deleteModalText" class="text-sm text-[var(--ink-400)] mt-1.5" data-used-text="{{ __('app.payment_methods.in_use_hint') }}" data-default-text="{{ __('app.common.this_action_cannot_be_undone') }}">{{ __('app.common.this_action_cannot_be_undone') }}</p>

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
        @if($errors->any())
            document.addEventListener('DOMContentLoaded', () => showToast(@json($errors->first())));
        @endif
    </script>
    <script src="{{ asset('js/finance-payment-methods.js') }}"></script>
@endpush
