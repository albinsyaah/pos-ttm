@extends('prints.layout')

@section('title', __('app.print.large_receipt'))

@section('page_css')
    @page { size: A4; margin: 12mm; }
    .paper { width: 210mm; min-height: 297mm; padding: 14mm; font: 13px/1.45 system-ui, "Segoe UI", Arial, sans-serif; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .head h1 { margin: 0; font-size: 22px; }
    .head .doc { text-align: right; }
    .head .doc h2 { margin: 0; font-size: 20px; letter-spacing: .08em; }
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 18px 0; }
    .meta dl { margin: 0; }
    .meta dt { color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
    .meta dd { margin: 0 0 8px; font-weight: 600; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f3f8; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
    th, td { padding: 7px 8px; border-bottom: 1px solid #d9dde8; vertical-align: top; }
    tfoot td { border: 0; padding-top: 10px; }
    .total { font-size: 16px; font-weight: 700; }
    .foot { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 28px; gap: 16px; }
    .qr { width: 28mm; }
    @media print { .paper { width: auto; min-height: 0; padding: 0; } }
@endsection

@section('content')
@php use App\Support\Money; @endphp
<div class="paper">
    <div class="head">
        <div>
            <h1>{{ $store['name'] }}</h1>
            @if(filled($store['address']))<div>{{ $store['address'] }}</div>@endif
            @if(filled($store['phone']))<div>{{ $store['phone'] }}</div>@endif
        </div>
        <div class="doc"><h2>{{ __('app.print.invoice_title') }}</h2></div>
    </div>

    <div class="meta">
        <dl>
            <dt>{{ __('app.print.invoice') }}</dt><dd>{{ $sale->invoice_number }}</dd>
            <dt>{{ __('app.print.date') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}</dd>
            <dt>{{ __('app.print.payment') }}</dt>
            <dd>
                @if($sale->payment_type === \App\Models\Sale::PAYMENT_CREDIT)
                    {{ __('app.print.credit') }}
                @else
                    {{ $sale->paymentMethod?->name ?? __('app.print.cash') }}
                @endif
            </dd>
        </dl>
        <dl>
            <dt>{{ __('app.print.customer') }}</dt><dd>{{ $sale->customer?->name ?? __('app.print.walk_in') }}</dd>
            @if($sale->customer && filled($sale->customer->address))
                <dt>{{ __('app.print.address') }}</dt><dd>{{ $sale->customer->address }}</dd>
            @endif
            @if($sale->customer && filled($sale->customer->phone))
                <dt>{{ __('app.print.phone') }}</dt><dd>{{ $sale->customer->phone }}</dd>
            @endif
        </dl>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th>{{ __('app.print.product') }}</th>
                <th style="width:18%">{{ __('app.print.qty') }}</th>
                <th class="right" style="width:17%">{{ __('app.print.price') }}</th>
                <th class="right" style="width:17%">{{ __('app.print.subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->saleDetails as $i => $detail)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $detail->product?->name ?? '—' }}</td>
                    <td>{{ $detail->product ? $detail->product->formatQuantity((int) $detail->qty) : $detail->qty }}</td>
                    <td class="right">{{ (float) $detail->price > 0 ? Money::rupiah($detail->price) : __('app.print.free') }}</td>
                    <td class="right">{{ (float) $detail->price > 0 ? Money::rupiah($detail->qty * $detail->price) : __('app.print.free') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="right total">{{ __('app.print.total') }}</td>
                <td class="right total">{{ Money::rupiah($sale->total_amount) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="foot">
        <div class="muted">
            @if(filled($store['receipt_footer']))<div>{{ $store['receipt_footer'] }}</div>@endif
            <div>{{ __('app.print.scan_for_digital') }}</div>
        </div>
        <div class="qr" data-url="{{ $sale->digitalReceiptUrl() }}"></div>
    </div>
</div>
@endsection
