@extends('layouts.app')

@section('title', __('app.users.title'))
@section('page-title', __('app.users.page_title'))

@section('content')

    @if (session('success'))
        <div class="mb-5 rounded-2xl bg-[var(--good-100)] text-[var(--good-600)] text-sm px-4 py-3 flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-5 rounded-2xl bg-[var(--bad-100)] text-[var(--bad-600)] text-sm px-4 py-3 flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-2xl bg-[var(--bad-100)] text-[var(--bad-600)] text-sm px-4 py-3">
            <div class="flex items-center gap-2 font-semibold mb-1">
                <i class="fa-solid fa-circle-exclamation"></i> {{ __('app.common.please_fix_the_following') }}
            </div>
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3">
            <form action="{{ route('admin.users.index') }}" method="GET" class="relative" data-live-search="auto">
                <label class="sr-only" for="userSearch">{{ __('app.users.search_users') }}</label>
                <input
                    id="userSearch"
                    name="q"
                    type="search"
                    value="{{ $search }}"
                    placeholder="{{ __('app.users.search_placeholder') }}"
                    class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
                />
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
            </form>
            <a href="{{ route('admin.roles.index') }}" class="text-sm font-semibold text-[var(--brand-600)] hover:text-[var(--brand-700)] flex items-center gap-1.5">
                <i class="fa-solid fa-shield-halved"></i> {{ __('app.users.manage_roles_permissions') }}
            </a>
        </div>

        <button
            id="addUserBtn"
            type="button"
            data-action="{{ route('admin.users.store') }}"
            class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
        >
            <i class="fa-solid fa-plus"></i> {{ __('app.users.add_user') }}
        </button>
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[760px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.users.username') }}</th>
                    <th class="font-semibold">{{ __('app.users.employee') }}</th>
                    <th class="font-semibold">{{ __('app.users.roles') }}</th>
                    <th class="font-semibold">{{ __('app.common.status') }}</th>
                    <th class="font-semibold text-right pr-5">{{ __('app.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">
                            {{ $user->username }}
                            @if($user->id === auth()->id())
                                <span class="badge badge-neutral ml-1">{{ __('app.users.you') }}</span>
                            @endif
                        </td>
                        <td class="text-[var(--ink-700)]">{{ $user->employee->name ?? '—' }}</td>
                        <td>
                            @forelse($user->roles as $role)
                                <span class="badge {{ $role->name === 'Super Admin' ? 'badge-brand' : 'badge-neutral' }} mr-1 mb-1">{{ $role->name }}</span>
                            @empty
                                <span class="text-[var(--ink-400)]">{{ __('app.users.no_role') }}</span>
                            @endforelse
                        </td>
                        <td>
                            <form action="{{ route('admin.users.toggle-active', $user) }}" method="POST" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <label class="switch" title="{{ $user->is_active ? __('app.users.active_click_to_disable') : __('app.users.disabled_click_to_enable') }}">
                                    <input
                                        type="checkbox"
                                        onchange="this.form.requestSubmit()"
                                        {{ $user->is_active ? 'checked' : '' }}
                                        {{ $user->id === auth()->id() ? 'disabled' : '' }}
                                    >
                                    <span class="switch-track"></span>
                                </label>
                                <span class="badge {{ $user->is_active ? 'badge-good' : 'badge-bad' }}">
                                    {{ $user->is_active ? __('app.common.active') : __('app.common.disabled') }}
                                </span>
                            </form>
                        </td>
                        <td class="text-right pr-5">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    class="edit-user-btn icon-btn"
                                    aria-label="Edit {{ $user->username }}"
                                    data-action="{{ route('admin.users.update', $user) }}"
                                    data-username="{{ $user->username }}"
                                    data-employee-id="{{ $user->employee_id }}"
                                    data-role-ids="{{ $user->roles->pluck('id')->implode(',') }}"
                                    data-is-active="{{ $user->is_active ? '1' : '0' }}"
                                    data-is-self="{{ $user->id === auth()->id() ? '1' : '0' }}"
                                >
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                @if($user->id !== auth()->id())
                                    <button
                                        type="button"
                                        class="delete-user-btn icon-btn"
                                        aria-label="Delete {{ $user->username }}"
                                        data-action="{{ route('admin.users.destroy', $user) }}"
                                        data-name="{{ $user->username }}"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.users.no_users_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="userModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="userModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.users.add_user') }}</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="userForm" method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
                @csrf
                <div id="userFormMethod"></div>
                {{-- Records which URL/method the form was submitted with, so that if
                     validation fails we know whether to reopen this as "Add" or "Edit"
                     after the redirect (old() below repopulates this like any other field). --}}
                <input type="hidden" id="intendedAction" name="_intended_action" value="{{ old('_intended_action') }}">
                <input type="hidden" id="intendedTitle" name="_intended_title" value="{{ old('_intended_title') }}">

                <div>
                    <label for="username" class="block text-xs font-semibold text-[var(--ink-700)] mb-1.5">{{ __('app.users.username') }}</label>
                    <input id="username" name="username" type="text" required
                        class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-[var(--ink-700)] mb-1.5">
                        {{ __('app.users.password') }} <span id="passwordHint" class="text-[var(--ink-400)] font-normal"></span>
                    </label>
                    <input id="password" name="password" type="password" minlength="8"
                        class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                </div>

                <div>
                    <label for="employee_id" class="block text-xs font-semibold text-[var(--ink-700)] mb-1.5">{{ __('app.users.linked_employee_optional') }}</label>
                    <select id="employee_id" name="employee_id"
                        class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors">
                        <option value="">{{ __('app.common.none') }}</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <span class="block text-xs font-semibold text-[var(--ink-700)] mb-1.5">{{ __('app.users.roles') }}</span>
                    <div class="rounded-xl bg-[var(--surface)] p-3 max-h-40 overflow-y-auto space-y-1">
                        @foreach($roles as $role)
                            <label class="flex items-center gap-2 text-sm text-[var(--ink-700)]">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="role-checkbox rounded border-gray-300 text-[var(--brand-600)] focus:ring-[var(--brand-600)]">
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('roles')
                        <p class="text-xs text-[var(--bad-600)] mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <label id="activeRow" class="flex items-center gap-2 text-sm text-[var(--ink-700)]">
                    {{-- Hidden field first: if the checkbox is unchecked, browsers omit it from
                         the request entirely, so nothing would tell the server to turn the
                         account off. This hidden "0" is always sent; the checked box's "1"
                         (submitted after it) overrides it when checked. --}}
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked class="rounded border-gray-300 text-[var(--brand-600)] focus:ring-[var(--brand-600)]">
                    {{ __('app.users.account_active') }}
                </label>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="flex-1 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        {{ __('app.common.save') }}
                    </button>
                    <button type="button" class="modal-close flex-1 bg-[var(--surface)] hover:bg-[var(--ink-200)] text-[var(--ink-700)] text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        {{ __('app.common.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    <div id="deleteModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-sm text-center">
            <div class="w-14 h-14 rounded-full bg-[var(--bad-100)] text-[var(--bad-600)] flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-triangle-exclamation text-xl"></i>
            </div>
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)] mb-2">{{ __('app.users.delete_user') }}</h3>
            <p id="deleteModalText" class="text-sm text-[var(--ink-400)] mb-6"></p>
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="flex items-center gap-3">
                    <button type="submit" class="flex-1 bg-[var(--bad-600)] hover:opacity-90 text-white text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        {{ __('app.common.delete') }}
                    </button>
                    <button type="button" class="modal-close flex-1 bg-[var(--surface)] hover:bg-[var(--ink-200)] text-[var(--ink-700)] text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        {{ __('app.common.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        // If the "Add/Edit User" form failed validation, the redirect back() lands
        // here with old input + $errors but no memory of which modal was open.
        // These globals let admin-users.js reopen the right one with the data
        // the user already typed, instead of failing silently.
        window.__userFormErrors = @json($errors->any());
        window.__userFormOld = {
            action: @json(old('_intended_action')),
            title: @json(old('_intended_title')),
            method: @json(old('_method')),
            username: @json(old('username')),
            employee_id: @json(old('employee_id')),
            roles: @json(old('roles', [])),
            is_active: @json(old('is_active')),
        };
    </script>
    <script src="{{ asset('js/admin-users.js') }}"></script>
@endpush
