@extends('layouts.app')

@section('title', __('app.employees.title'))
@section('page-title', __('app.employees.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('hr.employees.index') }}" method="GET" class="relative" data-live-search="auto">
            <label class="sr-only" for="employeeSearch">{{ __('app.employees.search_employees') }}</label>
            <input
                id="employeeSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.employees.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        <button
            id="addEmployeeBtn"
            type="button"
            data-action="{{ route('hr.employees.store') }}"
            class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
        >
            <i class="fa-solid fa-plus"></i> {{ __('app.employees.add_employee') }}
        </button>
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[680px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.common.code') }}</th>
                    <th class="font-semibold">{{ __('app.common.name') }}</th>
                    <th class="font-semibold">{{ __('app.common.position') }}</th>
                    <th class="font-semibold">{{ __('app.common.phone') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $employee->code }}</td>
                        <td class="text-[var(--ink-700)]">{{ $employee->name }}</td>
                        <td class="text-[var(--ink-400)]">{{ $employee->position }}</td>
                        <td class="text-[var(--ink-400)]">{{ $employee->phone ?: '—' }}</td>
                        <td class="text-right pr-5">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    class="edit-employee-btn icon-btn"
                                    aria-label="Edit {{ $employee->name }}"
                                    data-action="{{ route('hr.employees.update', $employee) }}"
                                    data-code="{{ $employee->code }}"
                                    data-name="{{ $employee->name }}"
                                    data-position="{{ $employee->position }}"
                                    data-phone="{{ $employee->phone }}"
                                >
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button
                                    type="button"
                                    class="delete-employee-btn icon-btn"
                                    aria-label="Delete {{ $employee->name }}"
                                    data-action="{{ route('hr.employees.destroy', $employee) }}"
                                    data-name="{{ $employee->name }}"
                                >
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.employees.no_employees_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $employees->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="employeeModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="employeeModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="employeeModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.employees.add_employee') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="employeeForm" method="POST" action="{{ route('hr.employees.store') }}">
                @csrf
                <div id="employeeFormMethod"></div>

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
                        <label for="position" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.position') }}</label>
                        <input id="position" name="position" type="text" required maxlength="100"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">{{ __('app.common.phone') }}</label>
                        <input id="phone" name="phone" type="text" maxlength="30"
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.employees.delete_employee') }}</h3>
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
    <script src="{{ asset('js/hr-employees.js') }}"></script>
@endpush
