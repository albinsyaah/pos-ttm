@extends('prints.layout')

@section('title', __('app.print.delivery_note'))

@section('page_css')
    @page { size: A4; margin: 12mm; }
    .paper { width: 210mm; min-height: 297mm; padding: 14mm; font: 13px/1.45 system-ui, "Segoe UI", Arial, sans-serif; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .head h1 { margin: 0; font-size: 22px; }
    .head h2 { margin: 0; font-size: 20px; letter-spacing: .08em; text-align: right; }
    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 18px 0; }
    .meta dl { margin: 0; }
    .meta dt { color: #555; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
    .meta dd { margin: 0 0 8px; font-weight: 600; min-height: 1.45em; }
    .blank-line { border-bottom: 1px solid #000; min-width: 60mm; display: inline-block; height: 1.3em; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f3f8; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
    th, td { padding: 7px 8px; border-bottom: 1px solid #d9dde8; vertical-align: top; }
    .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12mm; margin-top: 22mm; text-align: center; page-break-inside: avoid; }
    .sign .space { height: 24mm; border-bottom: 1px solid #000; }
    .sign .who { margin-top: 4px; font-weight: 600; }
    .sign .name { color: #555; font-size: 11px; min-height: 1.4em; }
    @media print { .paper { width: auto; min-height: 0; padding: 0; } }
@endsection

@section('content')
<div class="paper">
    <div class="head">
        <div>
            <h1>{{ $store['name'] }}</h1>
            @if(filled($store['address']))<div>{{ $store['address'] }}</div>@endif
            @if(filled($store['phone']))<div>{{ $store['phone'] }}</div>@endif
        </div>
        <h2>{{ __('app.print.delivery_note_title') }}</h2>
    </div>

    <div class="meta">
        <dl>
            <dt>{{ __('app.print.invoice') }}</dt><dd>{{ $sale->invoice_number }}</dd>
            <dt>{{ __('app.print.date') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}</dd>
            <dt>{{ __('app.print.from_warehouse') }}</dt><dd>{{ $sale->warehouse?->name ?? '—' }}</dd>
            <dt>{{ __('app.print.driver') }}</dt>
            <dd>
                @if(filled($sale->driver_name)){{ $sale->driver_name }}@else<span class="blank-line"></span>@endif
            </dd>
        </dl>
        <dl>
            <dt>{{ __('app.print.deliver_to') }}</dt><dd>{{ $sale->customer?->name ?? __('app.print.walk_in') }}</dd>
            @if($sale->customer && filled($sale->customer->address))
                <dt>{{ __('app.print.address') }}</dt><dd>{{ $sale->customer->address }}</dd>
            @endif
            @if($sale->customer && filled($sale->customer->phone))
                <dt>{{ __('app.print.phone') }}</dt><dd>{{ $sale->customer->phone }}</dd>
            @endif
        </dl>
    </div>

    {{-- No prices on a delivery note: it lists what physically travels. --}}
    <table>
        <thead>
            <tr>
                <th style="width:6%">#</th>
                <th>{{ __('app.print.product') }}</th>
                <th style="width:28%">{{ __('app.print.qty') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->saleDetails as $i => $detail)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $detail->product?->name ?? '—' }}</td>
                    <td>{{ $detail->product ? $detail->product->formatQuantity((int) $detail->qty) : $detail->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="sign">
        <div>
            <div class="space"></div>
            <div class="who">{{ __('app.print.sign_admin') }}</div>
            <div class="name">&nbsp;</div>
        </div>
        <div>
            <div class="space"></div>
            <div class="who">{{ __('app.print.sign_warehouse_head') }}</div>
            <div class="name">&nbsp;</div>
        </div>
        <div>
            <div class="space"></div>
            <div class="who">{{ __('app.print.sign_receiver') }}</div>
            <div class="name">{{ $sale->customer?->name }}</div>
        </div>
    </div>
</div>
@endsection
