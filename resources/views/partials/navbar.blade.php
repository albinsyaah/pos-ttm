<header class="bg-white px-5 sm:px-8 py-5 border-b border-gray-100 sticky top-0 z-10">
    <div class="flex items-center justify-between gap-4 flex-wrap">

        <div class="flex items-center gap-3">
            <button id="menuBtn" class="lg:hidden icon-btn" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-[var(--ink-900)]">@yield('page-title', 'Dashboard')</h2>
                <p class="text-xs text-[var(--ink-400)] mt-0.5" id="todayDate">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3 sm:gap-5 ml-auto">

            <label class="relative hidden sm:block">
                <span class="sr-only">Search orders or products</span>
                <input
                    id="searchInput"
                    class="w-48 md:w-64 rounded-full bg-[var(--surface)] py-2.5 pl-11 pr-4 text-sm outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors"
                    placeholder="Search orders, products"
                    type="search"
                />
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-sm"></i>
            </label>

            <button id="newSaleBtn" class="hidden sm:flex items-center gap-2 bg-[var(--brand-600)] hover:bg-[var(--brand-700)] text-white text-sm font-semibold rounded-full px-5 py-2.5 transition-colors">
                <i class="fa-solid fa-plus"></i> New Sale
            </button>

            <button id="notifBtn" class="icon-btn relative" aria-label="Notifications, {{ $unreadNotifications ?? 4 }} unread">
                <i class="fa-regular fa-bell"></i>
                <span class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-[var(--bad-600)] text-white text-[10px] font-bold flex items-center justify-center">
                    {{ $unreadNotifications ?? 4 }}
                </span>
            </button>

            <img
                src="{{ auth()->user()->avatar_url ?? 'https://images.unsplash.com/photo-1633332755192-727a05c4013d?w=200' }}"
                alt="{{ auth()->user()->name ?? 'Cashier' }} profile photo"
                class="w-11 h-11 rounded-full object-cover ring-2 ring-[var(--ink-200)]"
            />
        </div>
    </div>
</header>
