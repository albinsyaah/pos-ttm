<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('app.print.digital_receipt') }} · {{ $sale->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f6; color: #1a2138; font: 15px/1.5 system-ui, "Segoe UI", Arial, sans-serif; }
        .card { max-width: 440px; margin: 0 auto; min-height: 100vh; background: #fff; padding: 24px 20px 40px; }
        @media (min-width: 480px) { .card { margin: 24px auto; min-height: 0; border-radius: 20px; box-shadow: 0 12px 32px rgba(26, 33, 56, .12); } }
        h1 { margin: 0; font-size: 20px; text-align: center; }
        .sub { text-align: center; color: #5b6478; font-size: 13px; margin-top: 2px; }
        .badge { display: block; width: max-content; margin: 16px auto 4px; padding: 4px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .04em; }
        .badge.paid { background: #e3f6ea; color: #17803d; }
        .badge.credit { background: #fff3d6; color: #9a6700; }
        dl { margin: 18px 0; display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: 14px; }
        dt { color: #5b6478; }
        dd { margin: 0; text-align: right; font-weight: 600; word-break: break-word; }
        .lines { border-top: 1px dashed #c5cbd9; border-bottom: 1px dashed #c5cbd9; padding: 8px 0; }
        .line { padding: 8px 0; }
        .line .name { font-weight: 600; }
        .line .calc { display: flex; justify-content: space-between; gap: 10px; color: #5b6478; font-size: 14px; }
        .line .calc strong { color: #1a2138; }
        .total { display: flex; justify-content: space-between; margin-top: 14px; font-size: 18px; font-weight: 700; }
        .foot { margin-top: 24px; text-align: center; color: #5b6478; font-size: 13px; }
    </style>
</head>
<body>
@php use App\Support\Money; @endphp
<main class="card">
    <h1>{{ $store['name'] }}</h1>
    @if(filled($store['address']))<div class="sub">{{ $store['address'] }}</div>@endif
    @if(filled($store['phone']))<div class="sub">{{ $store['phone'] }}</div>@endif

    @if($sale->payment_type === \App\Models\Sale::PAYMENT_CREDIT)
        <span class="badge credit">{{ __('app.print.credit') }}</span>
    @else
        <span class="badge paid">{{ __('app.print.paid') }}</span>
    @endif

    <dl>
        <dt>{{ __('app.print.invoice') }}</dt><dd>{{ $sale->invoice_number }}</dd>
        <dt>{{ __('app.print.date') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}</dd>
        @if($sale->customer)<dt>{{ __('app.print.customer') }}</dt><dd>{{ $sale->customer->name }}</dd>@endif
        <dt>{{ __('app.print.payment') }}</dt>
        <dd>
            @if($sale->payment_type === \App\Models\Sale::PAYMENT_CREDIT)
                {{ __('app.print.credit') }}
            @else
                {{ $sale->paymentMethod?->name ?? __('app.print.cash') }}
            @endif
        </dd>
    </dl>

    <div class="lines">
        @foreach($sale->saleDetails as $detail)
            <div class="line">
                <div class="name">{{ $detail->product?->name ?? '—' }}</div>
                <div class="calc">
                    <span>
                        {{ $detail->product ? $detail->product->formatQuantity((int) $detail->qty) : $detail->qty }}
                        @if((float) $detail->price > 0) × {{ Money::rupiah($detail->price) }} @endif
                    </span>
                    <strong>{{ (float) $detail->price > 0 ? Money::rupiah($detail->qty * $detail->price) : __('app.print.free') }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    @if($sale->hasDiscount())
        <div style="display:flex;justify-content:space-between;margin-top:12px;font-size:14px;"><span>{{ __('app.print.subtotal') }}</span><span>{{ Money::rupiah($sale->subtotalBeforeDiscount()) }}</span></div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:14px;"><span>{{ __('app.print.discount') }}@if((float) $sale->discount_percent > 0) ({{ $sale->discountDescription() }})@endif</span><span>-{{ Money::rupiah($sale->discount_total) }}</span></div>
    @endif
    <div class="total"><span>{{ __('app.print.total') }}</span><span>{{ Money::rupiah($sale->total_amount) }}</span></div>

    @if(filled($store['receipt_footer']))<div class="foot">{{ $store['receipt_footer'] }}</div>@endif
</main>
</body>
</html>
