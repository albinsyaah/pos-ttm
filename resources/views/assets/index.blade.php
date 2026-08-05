@extends('layouts.app')

@section('title', 'Asset')
@section('page-title', 'Asset')

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form action="{{ route('assets.index') }}" method="GET" class="relative">
            <label class="sr-only" for="assetSearch">Search assets</label>
            <input
                id="assetSearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="Search by code or name"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>

        @can('assets.manage')
            <button
                id="addAssetBtn"
                type="button"
                data-action="{{ route('assets.store') }}"
                class="flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors"
            >
                <i class="fa-solid fa-plus"></i> Add Asset
            </button>
        @endcan
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[680px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">Code</th>
                    <th class="font-semibold">Name</th>
                    <th class="font-semibold">Purchase Date</th>
                    <th class="font-semibold">Value</th>
                    <th class="font-semibold text-right pr-5">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $asset)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 font-medium text-[var(--ink-900)]">{{ $asset->asset_code }}</td>
                        <td class="text-[var(--ink-700)]">{{ $asset->name }}</td>
                        <td class="text-[var(--ink-400)]">{{ \Illuminate\Support\Carbon::parse($asset->purchase_date)->format('d M Y') }}</td>
                        <td class="text-[var(--ink-700)]">{{ number_format((float) $asset->value, 2) }}</td>
                        <td class="text-right pr-5">
                            @can('assets.manage')
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="edit-asset-btn icon-btn"
                                        aria-label="Edit {{ $asset->name }}"
                                        data-action="{{ route('assets.update', $asset) }}"
                                        data-asset-code="{{ $asset->asset_code }}"
                                        data-name="{{ $asset->name }}"
                                        data-purchase-date="{{ \Illuminate\Support\Carbon::parse($asset->purchase_date)->format('Y-m-d') }}"
                                        data-value="{{ $asset->value }}"
                                    >
                                        <i class="fa-solid fa-pen text-xs"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="delete-asset-btn icon-btn"
                                        aria-label="Delete {{ $asset->name }}"
                                        data-action="{{ route('assets.destroy', $asset) }}"
                                        data-name="{{ $asset->name }}"
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
                            No assets found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $assets->links() }}
    </div>

    {{-- Add / Edit modal --}}
    <div id="assetModal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="assetModalTitle">
        <div class="modal-card bg-white rounded-3xl p-6 w-full max-w-md">
            <div class="flex items-center justify-between mb-5">
                <h3 id="assetModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">Add Asset</h3>
                <button type="button" class="icon-btn modal-close" aria-label="Close">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form id="assetForm" method="POST" action="{{ route('assets.store') }}">
                @csrf
                <div id="assetFormMethod"></div>

                <div class="space-y-4">
                    <div>
                        <label for="asset_code" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">Code</label>
                        <input id="asset_code" name="asset_code" type="text" required maxlength="50"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="name" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">Name</label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="purchase_date" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">Purchase Date</label>
                        <input id="purchase_date" name="purchase_date" type="date" required
                               class="w-full rounded-xl bg-[var(--surface)] py-2.5 px-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
                    </div>
                    <div>
                        <label for="value" class="block text-xs font-medium text-[var(--ink-700)] mb-1.5">Value</label>
                        <input id="value" name="value" type="number" step="0.01" min="0" required
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
                    <button type="button" class="modal-close text-sm font-medium text-[var(--ink-700)] px-4 py-2.5">Cancel</button>
                    <button type="submit" class="bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">Save</button>
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
            <h3 id="deleteModalTitle" class="font-semibold text-lg text-[var(--ink-900)]">Delete asset?</h3>
            <p id="deleteModalText" class="text-sm text-[var(--ink-400)] mt-1.5">This action cannot be undone.</p>

            <form id="deleteForm" method="POST" class="mt-6 flex items-center justify-end gap-3">
                @csrf
                @method('DELETE')
                <button type="button" class="modal-close text-sm font-medium text-[var(--ink-700)] px-4 py-2.5">Cancel</button>
                <button type="submit" class="bg-[var(--bad-600)] hover:opacity-90 text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">Delete</button>
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
    <script src="{{ asset('js/assets.js') }}"></script>
@endpush
