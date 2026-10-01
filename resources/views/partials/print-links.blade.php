{{-- Print shortcuts for one sale. Each link only shows for users holding that print permission. --}}
@canany(['print.receipt-small', 'print.receipt-large', 'print.delivery-note'])
    <div class="inline-flex items-center gap-2 mr-2">
        @can('print.receipt-small')
            <a href="{{ route('transactions.sales.print.receipt-small', $sale) }}" target="_blank" rel="noopener"
               class="icon-btn" title="{{ __('app.print.small_receipt') }}" aria-label="{{ __('app.print.small_receipt') }} {{ $sale->invoice_number }}">
                <i class="fa-solid fa-receipt text-xs"></i>
            </a>
        @endcan
        @can('print.receipt-large')
            <a href="{{ route('transactions.sales.print.receipt-large', $sale) }}" target="_blank" rel="noopener"
               class="icon-btn" title="{{ __('app.print.large_receipt') }}" aria-label="{{ __('app.print.large_receipt') }} {{ $sale->invoice_number }}">
                <i class="fa-solid fa-file-invoice text-xs"></i>
            </a>
        @endcan
        @can('print.delivery-note')
            <a href="{{ route('transactions.sales.print.delivery-note', $sale) }}" target="_blank" rel="noopener"
               class="icon-btn" title="{{ __('app.print.delivery_note') }}" aria-label="{{ __('app.print.delivery_note') }} {{ $sale->invoice_number }}">
                <i class="fa-solid fa-truck text-xs"></i>
            </a>
        @endcan
    </div>
@endcanany
