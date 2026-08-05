<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign in &middot; PointDash</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap"
        rel="stylesheet">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body>

    <div class="min-h-screen flex items-center justify-center p-5" style="background:var(--surface);">

        <div class="w-full max-w-md">

            <div class="text-center mb-8">
                <h1 class="text-2xl font-extrabold tracking-tight text-[var(--ink-900)]">TunasTaniMakmur</h1>
                <p class="text-sm text-[var(--ink-400)] mt-1">Sign in to your PointDash account</p>
            </div>

            <div class="bg-white rounded-3xl p-8 shadow-sm">

                @if ($errors->any())
                    <div
                        class="mb-5 rounded-2xl bg-[var(--bad-100)] text-[var(--bad-600)] text-sm px-4 py-3 flex items-start gap-2">
                        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @if (session('success'))
                    <div
                        class="mb-5 rounded-2xl bg-[var(--good-100)] text-[var(--good-600)] text-sm px-4 py-3 flex items-start gap-2">
                        <i class="fa-solid fa-circle-check mt-0.5"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="username"
                            class="block text-xs font-semibold text-[var(--ink-700)] mb-1.5">Username</label>
                        <div class="relative">
                            <input id="username" name="username" type="text" value="{{ old('username') }}" autofocus
                                autocomplete="username" required
                                class="w-full rounded-xl bg-[var(--surface)] py-3 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
                                placeholder="Enter your username" />
                            <i
                                class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
                        </div>
                    </div>

                    <div>
                        <label for="password"
                            class="block text-xs font-semibold text-[var(--ink-700)] mb-1.5">Password</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" autocomplete="current-password"
                                required
                                class="w-full rounded-xl bg-[var(--surface)] py-3 pl-11 pr-11 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
                                placeholder="Enter your password" />
                            <i
                                class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
                            <button type="button" id="togglePassword"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"
                                aria-label="Show password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-[var(--ink-700)]">
                            <input type="checkbox" name="remember"
                                class="rounded border-gray-300 text-[var(--brand-600)] focus:ring-[var(--brand-600)]" />
                            Remember me
                        </label>
                    </div>

                    <button type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-xl py-3 transition-colors">
                        Sign in <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-[var(--ink-400)] mt-6">
                Access is managed by your Super Admin. Contact them if you need an account or your access has changed.
            </p>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const input = document.getElementById('password');
            const icon = this.querySelector('i');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    </script>

</body>

</html>
