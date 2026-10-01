@extends('layouts.app')

@section('title', __('app.price_histories.title'))
@section('page-title', __('app.price_histories.title'))

@section('content')

    <div class="flex items-center justify-between flex-wrap gap-4">
        <form id="priceHistorySearchForm" action="{{ route('pricing.price-histories.index') }}" method="GET" class="relative">
            <label class="sr-only" for="priceHistorySearch">{{ __('app.price_histories.search') }}</label>
            <input
                id="priceHistorySearch"
                name="q"
                type="search"
                value="{{ $search }}"
                placeholder="{{ __('app.price_histories.search_placeholder') }}"
                class="w-64 sm:w-80 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
            />
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
        </form>
    </div>

    <div class="bg-white rounded-3xl mt-6 overflow-x-auto">
        <table class="w-full text-sm min-w-[900px]">
            <thead class="text-[var(--ink-400)] text-xs uppercase tracking-wide">
                <tr class="text-left border-b border-gray-100">
                    <th class="p-5 font-semibold">{{ __('app.price_histories.when') }}</th>
                    <th class="font-semibold">{{ __('app.price_histories.who') }}</th>
                    <th class="font-semibold">{{ __('app.price_setups.product') }}</th>
                    <th class="font-semibold">{{ __('app.price_setups.price_category') }}</th>
                    <th class="font-semibold">{{ __('app.price_histories.action') }}</th>
                    <th class="font-semibold">{{ __('app.price_histories.from') }}</th>
                    <th class="font-semibold pr-5">{{ __('app.price_histories.to') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($histories as $h)
                    <tr class="table-row border-b border-gray-50">
                        <td class="p-5 text-[var(--ink-400)]">{{ $h->created_at?->format('d M Y H:i') }}</td>
                        <td class="text-[var(--ink-700)]">{{ $h->user?->username ?: '—' }}</td>
                        <td class="font-medium text-[var(--ink-900)]">
                            {{ $h->product?->name ?: '—' }}
                            @if($h->product?->code)
                                <span class="block text-xs text-[var(--ink-400)] font-normal">{{ $h->product->code }}</span>
                            @endif
                        </td>
                        <td class="text-[var(--ink-700)]">{{ $h->price_category }}</td>
                        <td class="text-[var(--ink-700)]">{{ __('app.price_histories.action_'.$h->action) }}</td>
                        <td class="text-[var(--ink-400)]">
                            @if($h->old_amount !== null)
                                {{ number_format((float) $h->old_amount, 2) }}
                                <span class="block text-xs">{{ $h->old_effective_date?->format('d M Y') }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="pr-5 font-medium text-[var(--ink-900)]">
                            @if($h->new_amount !== null)
                                {{ number_format((float) $h->new_amount, 2) }}
                                <span class="block text-xs font-normal text-[var(--ink-400)]">{{ $h->new_effective_date?->format('d M Y') }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-14 text-[var(--ink-400)] text-sm">
                            <i class="fa-regular fa-face-frown text-2xl block mb-2"></i>
                            {{ __('app.price_histories.none') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $histories->links() }}
    </div>

@endsection

@push('scripts')
    <script>
        // Live search: filter while typing (300 ms debounce), no Enter needed.
        (function () {
            const form = document.getElementById('priceHistorySearchForm');
            const input = document.getElementById('priceHistorySearch');
            let timer;
            input?.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.submit(), 300);
            });
            if (input && input.value) {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        })();
    </script>
@endpush
