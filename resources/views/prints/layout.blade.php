<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ $sale->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e9ecf2; color: #111; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: .5rem; align-items: center; justify-content: center; padding: .6rem; background: #1a2138; color: #fff; font: 14px system-ui, sans-serif; }
        .toolbar button, .toolbar a { background: #fff; color: #1a2138; border: 0; border-radius: 999px; padding: .45rem 1rem; font: 600 13px system-ui, sans-serif; cursor: pointer; text-decoration: none; }
        .toolbar span { opacity: .8; }
        .paper { background: #fff; margin: 1rem auto; box-shadow: 0 8px 24px rgba(26, 33, 56, .18); }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #555; }
        .qr svg { display: block; width: 100%; height: auto; }
        @yield('paper_css')
        @media print {
            html, body { background: #fff; }
            .toolbar { display: none !important; }
            .paper { margin: 0; box-shadow: none; }
        }
    </style>
    <style>@yield('page_css')</style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('app.print.print') }}</button>
        @yield('toolbar_extra')
        <span>{{ $sale->invoice_number }}</span>
    </div>

    @yield('content')

    <script src="{{ asset('vendor/qrcode.js') }}"></script>
    <script>
        // Draw every .qr box from its data-url (the digital-receipt link).
        document.querySelectorAll('.qr[data-url]').forEach(function (box) {
            try {
                var qr = qrcode(0, 'M');
                qr.addData(box.dataset.url);
                qr.make();
                box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
            } catch (e) {
                box.textContent = box.dataset.url;
            }
        });
        // ?auto=1 (used by the cashier's "print receipt" link) opens the print dialog straight away.
        if (location.search.indexOf('auto=1') !== -1) {
            window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
        }
    </script>
</body>
</html>
