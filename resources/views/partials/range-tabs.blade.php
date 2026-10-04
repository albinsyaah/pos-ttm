{{-- Day / week / month switch for the dashboard panels. Plain links: no script needed. --}}
<div class="flex items-center gap-1 rounded-full bg-[var(--surface)] p-1 text-xs font-semibold" role="tablist" data-range-tabs>
    @foreach(['day', 'week', 'month'] as $option)
        <a href="{{ route('dashboard', ['range' => $option]) }}#insights"
           role="tab"
           data-range="{{ $option }}"
           aria-selected="{{ $range === $option ? 'true' : 'false' }}"
           class="px-3 py-1.5 rounded-full transition-colors {{ $range === $option ? 'bg-[var(--brand-600)] text-white' : 'text-[var(--ink-400)] hover:text-[var(--ink-900)]' }}">
            {{ __('insight.dashboard.range_'.$option) }}
        </a>
    @endforeach
</div>
