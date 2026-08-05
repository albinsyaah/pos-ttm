{{--
  Sidebar navigation.
  Each top-level module is gated behind the matching permission from
  App\Support\AccessControl (e.g. @can('customers.view')) so a signed-in
  user only ever sees the modules their role grants them. Super Admin
  bypasses every @can check automatically (see AppServiceProvider).
--}}
<aside id="sidebar" class="w-[280px] bg-white border-r border-gray-100 shrink-0 flex flex-col h-screen sticky top-0">

    <div class="flex items-center gap-3 px-6 py-6 shrink-0">
        {{-- <div class="w-10 h-10 rounded-xl bg-[var(--brand-600)] text-white flex items-center justify-center text-lg">
            <i class="fa-solid fa-cash-register"></i>
        </div> --}}
        <h1 class="text-xl font-extrabold tracking-tight text-[var(--ink-900)]">TunasTaniMakmur</h1>
    </div>

    <label class="relative block px-4 mb-2 shrink-0">
        <span class="sr-only">{{ __('app.sidebar.search_menu') }}</span>
        <input id="navSearch" type="search" placeholder="{{ __('app.sidebar.search_menu') }}"
            class="w-full rounded-xl bg-[var(--surface)] py-2 pl-9 pr-3 text-xs outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
        <i class="fa-solid fa-magnifying-glass absolute left-7 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-xs"></i>
    </label>

    <nav id="mainNav" class="flex-1 overflow-y-auto px-3 pb-4 text-[var(--ink-400)] text-sm" aria-label="Main navigation">

        @can('dashboard.view')
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '#' }}"
               class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <i class="fa-solid fa-grip w-4 text-center"></i> {{ __('app.sidebar.dashboard') }}
            </a>
        @endcan

        @can('customers.view')
            <a href="{{ \Illuminate\Support\Facades\Route::has('customers.index') ? route('customers.index') : '#' }}"
               class="sidebar-item {{ request()->routeIs('customers.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('customers.*')) aria-current="page" @endif>
                <i class="fa-solid fa-users w-4 text-center"></i> {{ __('app.sidebar.customer') }}
            </a>
        @endcan

        {{-- Inventory --}}
        @can('inventory.view')
        <div class="nav-group {{ request()->routeIs('inventory.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('inventory.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-layer-group w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.inventory') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="{{ route('inventory.brands.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.brands.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.brands.*')) aria-current="page" @endif>{{ __('app.sidebar.brand') }}</a>
                <a href="{{ route('inventory.item-types.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.item-types.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.item-types.*')) aria-current="page" @endif>{{ __('app.sidebar.item_type') }}</a>
                <a href="{{ route('inventory.product-groups.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.product-groups.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.product-groups.*')) aria-current="page" @endif>{{ __('app.sidebar.product_group') }}</a>
                <a href="{{ route('inventory.products.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.products.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.products.*')) aria-current="page" @endif>{{ __('app.sidebar.product') }}</a>
            </div>
        </div>
        @endcan

        @can('assets.view')
            <a href="{{ route('assets.index') }}"
               class="sidebar-item {{ request()->routeIs('assets.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('assets.*')) aria-current="page" @endif>
                <i class="fa-solid fa-box w-4 text-center"></i> {{ __('app.sidebar.asset') }}
            </a>
        @endcan

        @can('pricing.view')
            <a href="{{ route('pricing.price-setups.index') }}"
               class="sidebar-item {{ request()->routeIs('pricing.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('pricing.*')) aria-current="page" @endif>
                <i class="fa-solid fa-tags w-4 text-center"></i> {{ __('app.sidebar.price_setup') }}
            </a>
        @endcan

        {{-- Keuangan --}}
        @can('finance.view')
        <div class="nav-group {{ request()->routeIs('finance.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('finance.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-sack-dollar w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.finance') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="{{ route('finance.chart-of-accounts.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('finance.chart-of-accounts.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('finance.chart-of-accounts.*')) aria-current="page" @endif>{{ __('app.sidebar.chart_of_accounts') }}</a>
                <a href="{{ route('finance.cash-flows.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('finance.cash-flows.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('finance.cash-flows.*')) aria-current="page" @endif>{{ __('app.sidebar.cash_flow') }}</a>
                <a href="{{ route('finance.general-ledgers.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('finance.general-ledgers.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('finance.general-ledgers.*')) aria-current="page" @endif>{{ __('app.sidebar.general_ledger') }}</a>
            </div>
        </div>
        @endcan

        @can('suppliers.view')
            <a href="{{ route('suppliers.index') }}"
               class="sidebar-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('suppliers.*')) aria-current="page" @endif>
                <i class="fa-solid fa-truck-field w-4 text-center"></i> {{ __('app.sidebar.supplier') }}
            </a>
        @endcan

        @can('warehouses.view')
            <a href="{{ route('warehouses.index') }}"
               class="sidebar-item {{ request()->routeIs('warehouses.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('warehouses.*')) aria-current="page" @endif>
                <i class="fa-solid fa-warehouse w-4 text-center"></i> {{ __('app.sidebar.warehouse') }}
            </a>
        @endcan

        {{-- Kepegawaian --}}
        @can('hr.view')
        <div class="nav-group {{ request()->routeIs('hr.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('hr.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-id-badge w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.hr') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="{{ route('hr.employees.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('hr.employees.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('hr.employees.*')) aria-current="page" @endif>{{ __('app.sidebar.employee') }}</a>
                <a href="{{ route('hr.salesmen.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('hr.salesmen.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('hr.salesmen.*')) aria-current="page" @endif>{{ __('app.sidebar.salesman') }}</a>
            </div>
        </div>
        @endcan

        {{-- Transaksi --}}
        @can('transactions.view')
        <div class="nav-group {{ request()->routeIs('transactions.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('transactions.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-right-left w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.transactions') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">

                {{-- Account Payable --}}
                <div class="nav-group {{ request()->routeIs('transactions.purchase-orders.*', 'transactions.purchases.*', 'transactions.purchase-returns.*', 'transactions.payable-payments.*') ? 'open' : '' }}">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="{{ request()->routeIs('transactions.purchase-orders.*', 'transactions.purchases.*', 'transactions.purchase-returns.*', 'transactions.payable-payments.*') ? 'true' : 'false' }}">
                        <span class="flex-1 text-left">{{ __('app.sidebar.account_payable') }}</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        <a href="{{ route('transactions.purchase-orders.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.purchase-orders.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.purchase-orders.*')) aria-current="page" @endif>{{ __('app.sidebar.purchase_order') }}</a>
                        <a href="{{ route('transactions.purchases.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.purchases.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.purchases.*')) aria-current="page" @endif>{{ __('app.sidebar.purchase') }}</a>
                        <a href="{{ route('transactions.purchase-returns.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.purchase-returns.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.purchase-returns.*')) aria-current="page" @endif>{{ __('app.sidebar.purchase_return') }}</a>
                        <a href="{{ route('transactions.payable-payments.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.payable-payments.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.payable-payments.*')) aria-current="page" @endif>{{ __('app.sidebar.payable_payment') }}</a>
                    </div>
                </div>

                {{-- Account Receivable --}}
                <div class="nav-group">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="false">
                        <span class="flex-1 text-left">{{ __('app.sidebar.account_receivable') }}</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.sales_order') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.sales') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.point_of_sales_new') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.point_of_sales') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.sales_return') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.sales_spg') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Pembayaran Hutang</a>
                    </div>
                </div>

                {{-- Mutasi Internal --}}
                <div class="nav-group">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="false">
                        <span class="flex-1 text-left">{{ __('app.sidebar.internal_mutation') }}</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.item_request') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.internal_expenditure') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.internal_receipt') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.warehouse_transfer') }}</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">{{ __('app.sidebar.deviation') }}</a>
                    </div>
                </div>

                <a href="#" class="sidebar-item sub flex items-center gap-3 pl-11 pr-4 py-2">{{ __('app.sidebar.cash_management') }}</a>
                <a href="#" class="sidebar-item sub flex items-center gap-3 pl-11 pr-4 py-2">{{ __('app.sidebar.general_ledger') }}</a>
            </div>
        </div>
        @endcan

        {{-- Report --}}
        @can('reports.view')
        <div class="nav-group">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="false">
                <i class="fa-solid fa-chart-line w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.report') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.purchase_order_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.purchase_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.purchase_return_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.payable_payment_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.sales_order_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.sales_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.sales_report_2') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.sales_return_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.receivable_payment_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.receivable_card_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.receivable_aging_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.expenditure_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.receipt_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.transfer_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.deviation_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.stock_card') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.position_report') }}</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">{{ __('app.sidebar.inventory_report') }}</a>
            </div>
        </div>
        @endcan

        @can('inquiry.view')
            <a href="#" class="sidebar-item flex items-center gap-3 px-4 py-2.5">
                <i class="fa-solid fa-magnifying-glass-chart w-4 text-center"></i> {{ __('app.sidebar.inquiry') }}
            </a>
        @endcan

        @role('Super Admin')
            <div class="my-3 border-t border-gray-100"></div>

            <a href="{{ route('admin.users.index') }}"
               class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>
                <i class="fa-solid fa-user-shield w-4 text-center"></i> {{ __('app.sidebar.administrator') }}
            </a>

            <a href="{{ route('admin.roles.index') }}"
               class="sidebar-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('admin.roles.*')) aria-current="page" @endif>
                <i class="fa-solid fa-shield-halved w-4 text-center"></i> {{ __('app.sidebar.roles_permissions') }}
            </a>
        @endrole

    </nav>

    {{-- <div class="mx-4 my-4 p-4 rounded-2xl bg-[var(--surface)] shrink-0">
        <p class="text-xs font-semibold text-[var(--ink-900)]">Shift status</p>
        <p class="text-xs text-[var(--ink-400)] mt-1">Register #2 &middot; Open since 9:00 AM</p>
        <button class="mt-3 w-full text-xs font-semibold bg-white border border-gray-200 rounded-xl py-2 text-[var(--ink-700)] hover:border-[var(--brand-600)] hover:text-[var(--brand-600)] transition-colors">
            Close shift
        </button>
    </div> --}}
</aside>
