@php
    $purchase = $reminder['purchase'];
    $overdue = $reminder['state'] === 'overdue';
    $when = match ($reminder['state']) {
        'overdue' => __('app.notifications.overdue_days', ['days' => abs($reminder['days_left'])]),
        'today' => __('app.notifications.due_today'),
        default => __('app.notifications.due_in_days', ['days' => $reminder['days_left']]),
    };
@endphp
<div class="px-4 py-3 border-b border-gray-50 last:border-b-0 flex items-start gap-3">
    <span class="mt-0.5 w-8 h-8 shrink-0 rounded-xl flex items-center justify-center {{ $overdue ? 'bg-[var(--bad-100)] text-[var(--bad-600)]' : 'bg-[var(--warn-100)] text-[var(--warn-600)]' }}">
        <i class="fa-solid {{ $overdue ? 'fa-circle-exclamation' : 'fa-clock' }} text-sm"></i>
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-[var(--ink-900)] truncate">{{ $purchase->invoice_number }} · {{ $purchase->supplier?->name ?: '—' }}</p>
        <p class="text-xs mt-0.5 {{ $overdue ? 'text-[var(--bad-600)] font-semibold' : 'text-[var(--ink-700)]' }}">
            {{ $when }} · {{ $reminder['due_date']->format('d M Y') }}
        </p>
        <p class="text-xs text-[var(--ink-400)] mt-0.5">{{ __('app.notifications.outstanding') }} Rp{{ number_format($reminder['outstanding'], 0, ',', '.') }}</p>
    </div>
</div>
