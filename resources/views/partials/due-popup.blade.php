{{-- Shown once right after login, for users with notifications.view, when invoices are overdue or due soon. --}}
<style>
    .due-popup-overlay { position: fixed; inset: 0; background: rgba(26,33,56,.45); display: flex; align-items: center; justify-content: center; padding: 1.5rem; z-index: 60; }
    .due-popup-overlay.hidden { display: none; }
    .due-popup-card { box-shadow: 0 24px 48px -16px rgba(26,33,56,.35); }
</style>
<div id="duePopup" class="due-popup-overlay" role="dialog" aria-modal="true" aria-labelledby="duePopupTitle">
    <div class="due-popup-card bg-white rounded-3xl w-full max-w-md overflow-hidden">
        <div class="p-6 pb-3">
            <div class="w-12 h-12 rounded-2xl bg-[var(--warn-100)] text-[var(--warn-600)] flex items-center justify-center mb-4">
                <i class="fa-solid fa-bell"></i>
            </div>
            <h3 id="duePopupTitle" class="font-semibold text-lg text-[var(--ink-900)]">{{ __('app.notifications.popup_title') }}</h3>
            <p class="text-sm text-[var(--ink-400)] mt-1">{{ __('app.notifications.popup_intro', ['count' => $dueReminders->count()]) }}</p>
        </div>
        <div class="max-h-72 overflow-y-auto border-y border-gray-50">
            @foreach($dueReminders->take(5) as $reminder)
                @include('partials.due-reminder-item', ['reminder' => $reminder])
            @endforeach
            @if($dueReminders->count() > 5)
                <p class="px-4 py-3 text-xs text-[var(--ink-400)]">{{ __('app.notifications.more', ['count' => $dueReminders->count() - 5]) }}</p>
            @endif
        </div>
        <div class="flex items-center justify-end gap-3 p-4">
            <button type="button" id="duePopupClose" class="text-sm font-medium text-[var(--ink-700)] px-4 py-2.5">{{ __('app.notifications.close') }}</button>
            @can('transactions.payable-payments.view')
                <a href="{{ route('transactions.payable-payments.index') }}" class="bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                    {{ __('app.notifications.view_payments') }}
                </a>
            @endcan
        </div>
    </div>
</div>
