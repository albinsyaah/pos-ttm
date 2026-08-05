@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')

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

    <div class="flex items-center justify-between flex-wrap gap-4">
        <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-[var(--brand-600)] hover:text-[var(--brand-700)] flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i> Back to Users
        </a>

        <button
            id="addRoleBtn"
            type="button"
            data-action="{{ route('admin.roles.store') }}"
            class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
        >
            <i class="fa-solid fa-plus"></i> Add Role
        </button>
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[560px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">Role</th>
                    <th class="font-semibold">Permissions</th>
                    <th class="font-semibold">Users</th>
                    <th class="font-semibold text-right pr-5">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">
                            {{ $role->name }}
                            @if($role->name === 'Super Admin')
                                <span class="badge badge-brand ml-1">Protected</span>
                            @endif
                        </td>
                        <td class="text-[var(--ink-700)]">{{ $role->permissions->count() }} of {{ $totalPermissions }}</td>
                        <td class="text-[var(--ink-700)]">{{ $role->users_count }}</td>
                        <td class="text-right pr-5">
                            <div class="inline-flex items-center gap-2">
                                <button
                                    type="button"
                                    class="edit-role-btn icon-btn"
                                    aria-label="Edit {{ $role->name }}"
                                    data-action="{{ route('admin.roles.update', $role) }}"
                                    data-name="{{ $role->name }}"
                                    data-permissions="{{ $role->permissions->pluck('name')->implode(',') }}"
                                    data-protected="{{ $role->name === 'Super Admin' ? '1' : '0' }}"
                                >
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                @if($role->name !== 'Super Admin')
                                    <button
                                        type="button"
                                        class="delete-role-btn icon-btn"
                                        aria-label="Delete {{ $role->name }}"
                                        data-action="{{ route('admin.roles.destroy', $role) }}"
                                        data-name="{{ $role->name }}"
                                    >
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-14 text-[var(--ink-400)] text-sm">No roles yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add / Edit modal --}}
    <div id="roleModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="roleModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-lg">
            <div class="flex items-center justify-between mb-5">
                <h3 id="roleModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">Add Role</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="roleForm" method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4">
                @csrf
                <div id="roleFormMethod"></div>

                <div>
                    <label for="name" class="block text-xs font-semibold text-[var(--ink-700)] mb-1.5">Role name</label>
                    <input id="name" name="name" type="text" required
                        class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                </div>

                <div>
                    <span class="block text-xs font-semibold text-[var(--ink-700)] mb-2">Module access</span>
                    <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                        @foreach($modules as $key => $module)
                            <details class="perm-group" open>
                                <summary>{{ $module['label'] }}</summary>
                                <div class="mt-2 pl-1">
                                    @foreach($module['permissions'] as $slug => $label)
                                        <label class="perm-checkbox">
                                            <input type="checkbox" name="permissions[]" value="{{ $slug }}" class="permission-checkbox rounded border-gray-300 text-[var(--brand-600)] focus:ring-[var(--brand-600)]">
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="flex-1 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        Save
                    </button>
                    <button type="button" class="modal-close flex-1 bg-[var(--surface)] hover:bg-[var(--ink-200)] text-[var(--ink-700)] text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        Cancel
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)] mb-2">Delete role?</h3>
            <p id="deleteModalText" class="text-sm text-[var(--ink-400)] mb-6"></p>
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="flex items-center gap-3">
                    <button type="submit" class="flex-1 bg-[var(--bad-600)] hover:opacity-90 text-white text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        Delete
                    </button>
                    <button type="button" class="modal-close flex-1 bg-[var(--surface)] hover:bg-[var(--ink-200)] text-[var(--ink-700)] text-sm font-semibold rounded-xl py-2.5 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin-roles.js') }}"></script>
@endpush
