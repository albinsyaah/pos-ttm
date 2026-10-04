@extends('layouts.app')

@section('title', __('app.suppliers.title'))
@section('page-title', __('app.suppliers.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('suppliers.index') }}" method="GET" class="relative" data-live-search="auto">
            <label class="sr-only" for="supplierSearch">{{ __('app.suppliers.search_suppliers') }}</label>
            <input
                id="supplierSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.suppliers.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        <button
            id="addSupplierBtn"
            type="button"
            data-action="{{ route('suppliers.store') }}"
            class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
        >
            <i class="fa-solid fa-plus"></i> {{ __('app.suppliers.add_supplier') }}
        </button>
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.common.code') }}</th>
                    <th class="font-semibold">{{ __('app.common.name') }}</th>
                    <th class="font-semibold">{{ __('app.common.contact_person') }}</th>
                    <th class="font-semibold">{{ __('app.common.phone') }}</th>
                    <th class="font-semibold">{{ __('app.common.address') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($suppliers as $supplier)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $supplier->code }}</td>
                        <td class="text-[var(--ink-700)]">{{ $supplier->name }}</td>
                        <td class="text-[var(--ink-400)]">{{ $supplier->contact_person ?: '—' }}</td>
                        <td class="text-[var(--ink-400)]">{{ $supplier->phone ?: '—' }}</td>
                        <td class="text-[var(--ink-400)] max-w-xs truncate">{{ $supplier->address ?: '—' }}</td>
                        <td class="text-right pr-5">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    class="edit-supplier-btn icon-btn"
                                    aria-label="Edit {{ $supplier->name }}"
                                    data-action="{{ route('suppliers.update', $supplier) }}"
                                    data-code="{{ $supplier->code }}"
                                    data-name="{{ $supplier->name }}"
                                    data-contact-person="{{ $supplier->contact_person }}"
                                    data-phone="{{ $supplier->phone }}"
                                    data-address="{{ $supplier->address }}"
                                >
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button
                                    type="button"
                                    class="delete-supplier-btn icon-btn"
                                    aria-label="Delete {{ $supplier->name }}"
                                    data-action="{{ route('suppliers.destroy', $supplier) }}"
                                    data-name="{{ $supplier->name }}"
                                >
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.suppliers.no_suppliers_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $suppliers->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="supplierModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="supplierModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="supplierModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.suppliers.add_supplier') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="supplierForm" method="POST" action="{{ route('suppliers.store') }}">
                @csrf
                <div id="supplierFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="code" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.code') }}</label>
                        <input id="code" name="code" type="text" readonly maxlength="50" placeholder="{{ __('app.auto_number') }}"
                               class="cursor-not-allowed text-[var(--ink-400)] w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.name') }}</label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="contact_person" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.contact_person') }}</label>
                        <input id="contact_person" name="contact_person" type="text" maxlength="150"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.phone') }}</label>
                        <input id="phone" name="phone" type="text" maxlength="30"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="address" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.address') }}</label>
                        <textarea id="address" name="address" rows="3" maxlength="500"
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.suppliers.delete_supplier') }}</h3>
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
    <script src="{{ asset('js/suppliers.js') }}"></script>
@endpush
