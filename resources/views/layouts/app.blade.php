<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'POS') &middot; POS</title>

    {{-- Swap for your compiled Tailwind build (Vite/Mix) once you set one up.
     The CDN build is fine for prototyping. --}}
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Elms+Sans:ital,wght@0,100..900;1,100..900&family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Reddit+Sans:ital,wght@0,200..900;1,200..900&family=Stack+Sans+Text:wght@200..700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @stack('styles')
</head>

<body>

    <div class="flex min-h-screen">

        @include('partials.sidebar')

        <main class="flex-1 min-w-0">

            @include('partials.navbar')

            <div id="toastHost" class="fixed top-6 right-6 z-50 flex flex-col gap-2"></div>

            @if ($showDuePopup ?? false)
                @include('partials.due-popup')
            @endif

            <section class="p-5 sm:p-8">
                @yield('content')
            </section>

        </main>

    </div>

    <script>
        {{-- Texts the page scripts write themselves (modal titles, ...) in the current language. --}}
        window.APP_I18N = @json(app('translator')->getLoader()->load(app()->getLocale(), '*', '*'));
        window.__t = function(key) {
            return (window.APP_I18N && window.APP_I18N[key]) || key;
        };
    </script>
    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/sidebar.js') }}"></script>
    <script src="{{ asset('js/live-search.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="{{ asset('js/report-common.js') }}"></script>

    @stack('scripts')

</body>

</html>
