<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>@yield('title', 'PointDash') &middot; PointDash</title>

{{-- Swap for your compiled Tailwind build (Vite/Mix) once you set one up.
     The CDN build is fine for prototyping. --}}
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="{{ asset('css/app.css') }}">

@stack('styles')
</head>

<body>

<div class="flex min-h-screen">

    @include('partials.sidebar')

    <main class="flex-1 min-w-0">

        @include('partials.navbar')

        <div id="toastHost" class="fixed top-6 right-6 z-50 flex flex-col gap-2"></div>

        <section class="p-5 sm:p-8">
            @yield('content')
        </section>

    </main>

</div>

<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/sidebar.js') }}"></script>

@stack('scripts')

</body>
</html>
