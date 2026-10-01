@extends('prints.layout')

@section('title', __('app.print.small_receipt'))

@section('page_css')
    @page { size: {{ $width }}mm {{ $height }}mm; margin: 4mm; }
    .paper { width: {{ $width }}mm; min-height: {{ $height }}mm; padding: 5mm; font: 12px/1.4 "Courier New", monospace; }
    .paper h1 { font-size: 17px; margin: 0 0 2px; text-align: center; }
    .paper p { margin: 0; }
    hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    .row { display: flex; justify-content: space-between; gap: 6px; }
    .item-name { font-weight: bold; word-break: break-word; }
    .qr { width: 34mm; margin: 8px auto 2px; }
    @media print { .paper { width: auto; min-height: 0; padding: 0; } }
@endsection

@section('content')
@php use App\Support\Money; @endphp
<div class="paper">
    <h1>{{ $store['name'] }}</h1>
    @if(filled($store['address']))<p class="center">{{ $store['address'] }}</p>@endif
    @if(filled($store['phone']))<p class="center">{{ $store['phone'] }}</p>@endif
    <hr>
    <p>{{ __('app.print.invoice') }}: {{ $sale->invoice_number }}</p>
    <p>{{ __('app.print.date') }}: {{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d/m/Y') }}</p>
    @if($sale->customer)<p>{{ __('app.print.customer') }}: {{ $sale->customer->name }}</p>@endif
    <hr>
    @foreach($sale->saleDetails as $detail)
        <div class="item-name">{{ $detail->product?->name ?? '—' }}</div>
        <div class="row">
            <span>
                {{ $detail->product ? $detail->product->formatQuantity((int) $detail->qty) : $detail->qty }}
                @if((float) $detail->price > 0) x {{ Money::rupiah($detail->price) }} @endif
            </span>
            <span>{{ (float) $detail->price > 0 ? Money::rupiah($detail->qty * $detail->price) : __('app.print.free') }}</span>
        </div>
    @endforeach
    <hr>
    <div class="row"><strong>{{ __('app.print.total') }}</strong><strong>{{ Money::rupiah($sale->total_amount) }}</strong></div>
    <div class="row">
        <span>{{ __('app.print.payment') }}</span>
        <span>
            @if($sale->payment_type === \App\Models\Sale::PAYMENT_CREDIT)
                {{ __('app.print.credit') }}
            @else
                {{ $sale->paymentMethod?->name ?? __('app.print.cash') }}
            @endif
        </span>
    </div>
    <hr>
    <div class="qr" data-url="{{ $sale->digitalReceiptUrl() }}"></div>
    <p class="center muted">{{ __('app.print.scan_for_digital') }}</p>
    @if(filled($store['receipt_footer']))<p class="center">{{ $store['receipt_footer'] }}</p>@endif
</div>
@endsection
