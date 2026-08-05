<header class="bg-white px-5 sm:px-8 py-5 border-b border-gray-100 sticky top-0 z-10">
    <div class="flex items-center justify-between gap-4 flex-wrap">

        <div class="flex items-center gap-3">
            <button id="menuBtn" class="lg:hidden icon-btn" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-[var(--ink-900)]">@yield('page-title', __('app.layout.dashboard'))</h2>
                <p class="text-xs text-[var(--ink-400)] mt-0.5" id="todayDate">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-5 ml-auto">

            <label class="relative hidden sm:block">
                <span class="sr-only">{{ __('app.layout.search_orders_products') }}</span>
                <input
                    id="searchInput"
                    class="w-48 md:w-64 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
                    placeholder="{{ __('app.layout.search_placeholder') }}"
                    type="search"
                />
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
            </label>

            <button id="newSaleBtn" class="hidden sm:flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-plus"></i> {{ __('app.layout.new_sale') }}
            </button>

            {{-- Language switcher --}}
            <div class="relative">
                <button id="langMenuBtn" type="button" class="icon-btn flex items-center gap-1.5 !w-auto px-3" aria-haspopup="true" aria-expanded="false" aria-label="{{ __('app.language') }}">
                    <i class="fa-solid fa-globe"></i>
                    <span class="text-xs font-semibold uppercase hidden sm:inline">{{ app()->getLocale() }}</span>
                    <i class="fa-solid fa-chevron-down text-[10px] text-[var(--ink-400)]"></i>
                </button>

                <div id="langMenu" class="hidden absolute right-0 mt-3 w-44 bg-white rounded-2xl shadow-lg border border-gray-100 py-2 z-20">
                    <a href="{{ route('language.switch', 'en') }}"
                       class="flex items-center justify-between px-4 py-2 text-sm hover:bg-[var(--surface)] {{ app()->getLocale() === 'en' ? 'text-[var(--brand-600)] font-semibold' : 'text-[var(--ink-700)]' }}">
                        {{ __('app.lang_en') }}
                        @if(app()->getLocale() === 'en')<i class="fa-solid fa-check text-xs"></i>@endif
                    </a>
                    <a href="{{ route('language.switch', 'id') }}"
                       class="flex items-center justify-between px-4 py-2 text-sm hover:bg-[var(--surface)] {{ app()->getLocale() === 'id' ? 'text-[var(--brand-600)] font-semibold' : 'text-[var(--ink-700)]' }}">
                        {{ __('app.lang_id') }}
                        @if(app()->getLocale() === 'id')<i class="fa-solid fa-check text-xs"></i>@endif
                    </a>
                </div>
            </div>

            <button id="notifBtn" class="icon-btn relative" aria-label="{{ __('app.layout.notifications') }}, {{ $unreadNotifications ?? 4 }} unread">
                <i class="fa-regular fa-bell"></i>
                <span class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-[var(--bad-600)] text-white text-[10px] font-bold flex items-center justify-center">
                    {{ $unreadNotifications ?? 4 }}
                </span>
            </button>

            <img
                src="https://images.unsplash.com/photo-1633332755192-727a05c4013d?w=200"
                alt="{{ auth()->user()?->displayName() ?? 'User' }} profile photo"
                class="w-11 h-11 rounded-full object-cover ring-2 ring-[var(--ink-200)]"
            />

            <div class="relative">
                <button id="userMenuBtn" type="button" class="flex items-center gap-2" aria-haspopup="true" aria-expanded="false">
                    <span class="hidden md:block text-left">
                        <span class="block text-sm font-semibold text-[var(--ink-900)] leading-tight">{{ auth()->user()?->displayName() ?? 'User' }}</span>
                        <span class="block text-xs text-[var(--ink-400)] leading-tight">{{ auth()->user()?->roles->pluck('name')->join(', ') ?: __('app.layout.no_role') }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-down text-xs text-[var(--ink-400)]"></i>
                </button>

                <div id="userMenu" class="hidden absolute right-0 mt-3 w-48 bg-white rounded-2xl shadow-lg border border-gray-100 py-2 z-20">
                    <div class="px-4 py-2 md:hidden border-b border-gray-50 mb-1">
                        <p class="text-sm font-semibold text-[var(--ink-900)]">{{ auth()->user()?->displayName() ?? 'User' }}</p>
                        <p class="text-xs text-[var(--ink-400)]">{{ auth()->user()?->roles->pluck('name')->join(', ') ?: __('app.layout.no_role') }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-[var(--bad-600)] hover:bg-[var(--bad-100)] flex items-center gap-2">
                            <i class="fa-solid fa-right-from-bracket"></i> {{ __('app.layout.log_out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
