{{--
  Sidebar navigation.
  Every individual link/submenu item is gated behind its own permission
  from App\Support\AccessControl (e.g. @can('inventory.brands.view')), so a
  signed-in user only ever sees the exact pages their role grants them —
  down to a single submenu item, not just the parent module. A module's
  group wrapper (e.g. "Inventory", "Transaksi") is shown via @canany() if
  the user can see at least one page inside it. Super Admin bypasses every
  @can/@canany check automatically (see AppServiceProvider).
--}}
<aside id="sidebar" class="w-[280px] bg-white border-r border-gray-100 shrink-0 flex flex-col h-screen sticky top-0">

    <div class="flex items-center gap-3 px-6 py-6 shrink-0">
        {{-- <div class="w-10 h-10 rounded-xl bg-[var(--brand-600)] text-white flex items-center justify-center text-lg">
            <i class="fa-solid fa-cash-register"></i>
        </div> --}}
        <h1 class="text-xl font-extrabold tracking-tight text-[var(--brand-600)]">TunasTaniMakmur</h1>
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
        @canany(\App\Support\AccessControl::viewPermissions('inventory'))
        <div class="nav-group {{ request()->routeIs('inventory.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('inventory.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-layer-group w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.inventory') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                @can('inventory.brands.view')
                <a href="{{ route('inventory.brands.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.brands.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.brands.*')) aria-current="page" @endif>{{ __('app.sidebar.brand') }}</a>
                @endcan
                @can('inventory.item-types.view')
                <a href="{{ route('inventory.item-types.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.item-types.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.item-types.*')) aria-current="page" @endif>{{ __('app.sidebar.item_type') }}</a>
                @endcan
                @can('inventory.product-groups.view')
                <a href="{{ route('inventory.product-groups.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.product-groups.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.product-groups.*')) aria-current="page" @endif>{{ __('app.sidebar.product_group') }}</a>
                @endcan
                @can('inventory.products.view')
                <a href="{{ route('inventory.products.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.products.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.products.*')) aria-current="page" @endif>{{ __('app.sidebar.product') }}</a>
                @endcan
            </div>
        </div>
        @endcanany

        @can('assets.view')
            <a href="{{ route('assets.index') }}"
               class="sidebar-item {{ request()->routeIs('assets.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('assets.*')) aria-current="page" @endif>
                <i class="fa-solid fa-box w-4 text-center"></i> {{ __('app.sidebar.asset') }}
            </a>
        @endcan

        {{-- Harga (Price): setup + change history --}}
        @can('pricing.view')
        <div class="nav-group {{ request()->routeIs('pricing.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('pricing.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-tags w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.price') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="{{ route('pricing.price-setups.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('pricing.price-setups.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('pricing.price-setups.*')) aria-current="page" @endif>{{ __('app.sidebar.price_setup') }}</a>
                <a href="{{ route('pricing.price-histories.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('pricing.price-histories.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('pricing.price-histories.*')) aria-current="page" @endif>{{ __('app.sidebar.price_history') }}</a>
            </div>
        </div>
        @endcan

        {{-- Keuangan --}}
        @canany(\App\Support\AccessControl::viewPermissions('finance'))
        <div class="nav-group {{ request()->routeIs('finance.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('finance.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-sack-dollar w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.finance') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                @can('finance.chart-of-accounts.view')
                <a href="{{ route('finance.chart-of-accounts.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('finance.chart-of-accounts.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('finance.chart-of-accounts.*')) aria-current="page" @endif>{{ __('app.sidebar.chart_of_accounts') }}</a>
                @endcan
                @can('finance.cash-flows.view')
                <a href="{{ route('finance.cash-flows.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('finance.cash-flows.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('finance.cash-flows.*')) aria-current="page" @endif>{{ __('app.sidebar.cash_flow') }}</a>
                @endcan
                @can('finance.payment-methods.view')
                <a href="{{ route('finance.payment-methods.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('finance.payment-methods.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('finance.payment-methods.*')) aria-current="page" @endif>{{ __('app.sidebar.payment_method') }}</a>
                @endcan
                @can('finance.general-ledgers.view')
                <a href="{{ route('finance.general-ledgers.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('finance.general-ledgers.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('finance.general-ledgers.*')) aria-current="page" @endif>{{ __('app.sidebar.general_ledger') }}</a>
                @endcan
            </div>
        </div>
        @endcanany

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
        @canany(\App\Support\AccessControl::viewPermissions('hr'))
        <div class="nav-group {{ request()->routeIs('hr.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('hr.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-id-badge w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.hr') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                @can('hr.employees.view')
                <a href="{{ route('hr.employees.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('hr.employees.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('hr.employees.*')) aria-current="page" @endif>{{ __('app.sidebar.employee') }}</a>
                @endcan
                @can('hr.salesmen.view')
                <a href="{{ route('hr.salesmen.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('hr.salesmen.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('hr.salesmen.*')) aria-current="page" @endif>{{ __('app.sidebar.salesman') }}</a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- Transaksi --}}
        @canany(\App\Support\AccessControl::viewPermissions('transactions'))
        <div class="nav-group {{ request()->routeIs('transactions.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('transactions.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-right-left w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.transactions') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">

                {{-- Account Payable --}}
                @canany(['transactions.purchase-orders.view', 'transactions.purchases.view', 'transactions.purchase-returns.view', 'transactions.payable-payments.view'])
                <div class="nav-group {{ request()->routeIs('transactions.purchase-orders.*', 'transactions.purchases.*', 'transactions.purchase-returns.*', 'transactions.payable-payments.*') ? 'open' : '' }}">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="{{ request()->routeIs('transactions.purchase-orders.*', 'transactions.purchases.*', 'transactions.purchase-returns.*', 'transactions.payable-payments.*') ? 'true' : 'false' }}">
                        <span class="flex-1 text-left">{{ __('app.sidebar.account_payable') }}</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        @can('transactions.purchase-orders.view')
                        <a href="{{ route('transactions.purchase-orders.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.purchase-orders.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.purchase-orders.*')) aria-current="page" @endif>{{ __('app.sidebar.purchase_order') }}</a>
                        @endcan
                        @can('transactions.purchases.view')
                        <a href="{{ route('transactions.purchases.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.purchases.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.purchases.*')) aria-current="page" @endif>{{ __('app.sidebar.purchase') }}</a>
                        @endcan
                        @can('transactions.purchase-returns.view')
                        <a href="{{ route('transactions.purchase-returns.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.purchase-returns.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.purchase-returns.*')) aria-current="page" @endif>{{ __('app.sidebar.purchase_return') }}</a>
                        @endcan
                        @can('transactions.payable-payments.view')
                        <a href="{{ route('transactions.payable-payments.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.payable-payments.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.payable-payments.*')) aria-current="page" @endif>{{ __('app.sidebar.payable_payment') }}</a>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- Account Receivable --}}
                @canany(['transactions.sales-orders.view', 'transactions.sales.view', 'transactions.point-of-sale-new.view', 'transactions.point-of-sale.view', 'transactions.sales-returns.view', 'transactions.sales-spg.view', 'transactions.receivable-payments.view'])
                <div class="nav-group {{ request()->routeIs('transactions.sales-orders.*', 'transactions.sales.*', 'transactions.point-of-sale-new.*', 'transactions.point-of-sale.*', 'transactions.sales-returns.*', 'transactions.sales-spg.*', 'transactions.receivable-payments.*') ? 'open' : '' }}">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="{{ request()->routeIs('transactions.sales-orders.*', 'transactions.sales.*', 'transactions.point-of-sale-new.*', 'transactions.point-of-sale.*', 'transactions.sales-returns.*', 'transactions.sales-spg.*', 'transactions.receivable-payments.*') ? 'true' : 'false' }}">
                        <span class="flex-1 text-left">{{ __('app.sidebar.account_receivable') }}</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        @can('transactions.sales-orders.view')
                        <a href="{{ route('transactions.sales-orders.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.sales-orders.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.sales-orders.*')) aria-current="page" @endif>{{ __('app.sidebar.sales_order') }}</a>
                        @endcan
                        @can('transactions.sales.view')
                        <a href="{{ route('transactions.sales.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.sales.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.sales.*')) aria-current="page" @endif>{{ __('app.sidebar.sales') }}</a>
                        @endcan
                        @can('transactions.point-of-sale-new.view')
                        <a href="{{ route('transactions.point-of-sale-new.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.point-of-sale-new.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.point-of-sale-new.*')) aria-current="page" @endif>{{ __('app.sidebar.point_of_sales_new') }}</a>
                        @endcan
                        @can('transactions.point-of-sale.view')
                        <a href="{{ route('transactions.point-of-sale.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.point-of-sale.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.point-of-sale.*')) aria-current="page" @endif>{{ __('app.sidebar.point_of_sales') }}</a>
                        @endcan
                        @can('transactions.sales-returns.view')
                        <a href="{{ route('transactions.sales-returns.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.sales-returns.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.sales-returns.*')) aria-current="page" @endif>{{ __('app.sidebar.sales_return') }}</a>
                        @endcan
                        @can('transactions.sales-spg.view')
                        <a href="{{ route('transactions.sales-spg.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.sales-spg.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.sales-spg.*')) aria-current="page" @endif>{{ __('app.sidebar.sales_spg') }}</a>
                        @endcan
                        @can('transactions.receivable-payments.view')
                        <a href="{{ route('transactions.receivable-payments.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.receivable-payments.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.receivable-payments.*')) aria-current="page" @endif>{{ __('app.sidebar.receivable_payment') }}</a>
                        @endcan
                    </div>
                </div>
                @endcanany

                {{-- Mutasi Internal --}}
                @canany(['transactions.item-requests.view', 'transactions.internal-expenditures.view', 'transactions.internal-receipts.view', 'transactions.warehouse-transfers.view', 'transactions.deviations.view'])
                <div class="nav-group {{ request()->routeIs('transactions.item-requests.*', 'transactions.internal-expenditures.*', 'transactions.internal-receipts.*', 'transactions.warehouse-transfers.*', 'transactions.deviations.*') ? 'open' : '' }}">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="{{ request()->routeIs('transactions.item-requests.*', 'transactions.internal-expenditures.*', 'transactions.internal-receipts.*', 'transactions.warehouse-transfers.*', 'transactions.deviations.*') ? 'true' : 'false' }}">
                        <span class="flex-1 text-left">{{ __('app.sidebar.internal_mutation') }}</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        @can('transactions.item-requests.view')
                        <a href="{{ route('transactions.item-requests.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.item-requests.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.item-requests.*')) aria-current="page" @endif>{{ __('app.sidebar.item_request') }}</a>
                        @endcan
                        @can('transactions.internal-expenditures.view')
                        <a href="{{ route('transactions.internal-expenditures.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.internal-expenditures.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.internal-expenditures.*')) aria-current="page" @endif>{{ __('app.sidebar.internal_expenditure') }}</a>
                        @endcan
                        @can('transactions.internal-receipts.view')
                        <a href="{{ route('transactions.internal-receipts.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.internal-receipts.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.internal-receipts.*')) aria-current="page" @endif>{{ __('app.sidebar.internal_receipt') }}</a>
                        @endcan
                        @can('transactions.warehouse-transfers.view')
                        <a href="{{ route('transactions.warehouse-transfers.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.warehouse-transfers.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.warehouse-transfers.*')) aria-current="page" @endif>{{ __('app.sidebar.warehouse_transfer') }}</a>
                        @endcan
                        @can('transactions.deviations.view')
                        <a href="{{ route('transactions.deviations.index') }}"
                           class="sidebar-item sub2 {{ request()->routeIs('transactions.deviations.*') ? 'active' : '' }} flex items-center pl-16 pr-4 py-2"
                           @if(request()->routeIs('transactions.deviations.*')) aria-current="page" @endif>{{ __('app.sidebar.deviation') }}</a>
                        @endcan
                    </div>
                </div>
                @endcanany

                @can('transactions.cash-management.view')
                <a href="{{ route('transactions.cash-management.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('transactions.cash-management.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('transactions.cash-management.*')) aria-current="page" @endif>{{ __('app.sidebar.cash_management') }}</a>
                @endcan
                @can('transactions.general-ledger.view')
                <a href="{{ route('transactions.general-ledger.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('transactions.general-ledger.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('transactions.general-ledger.*')) aria-current="page" @endif>{{ __('app.sidebar.general_ledger') }}</a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- Report --}}
        @canany(\App\Support\AccessControl::viewPermissions('reports'))
        <div class="nav-group {{ request()->routeIs('reports.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-chart-line w-4 text-center"></i>
                <span class="flex-1 text-left">{{ __('app.sidebar.report') }}</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                @can('reports.purchase-orders.view')
                <a href="{{ route('reports.purchase-orders') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.purchase-orders') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.purchase-orders')) aria-current="page" @endif>{{ __('app.sidebar.purchase_order_report') }}</a>
                @endcan
                @can('reports.purchases.view')
                <a href="{{ route('reports.purchases') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.purchases') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.purchases')) aria-current="page" @endif>{{ __('app.sidebar.purchase_report') }}</a>
                @endcan
                @can('reports.purchase-returns.view')
                <a href="{{ route('reports.purchase-returns') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.purchase-returns') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.purchase-returns')) aria-current="page" @endif>{{ __('app.sidebar.purchase_return_report') }}</a>
                @endcan
                @can('reports.payable-payments.view')
                <a href="{{ route('reports.payable-payments') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.payable-payments') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.payable-payments')) aria-current="page" @endif>{{ __('app.sidebar.payable_payment_report') }}</a>
                @endcan
                @can('reports.sales-orders.view')
                <a href="{{ route('reports.sales-orders') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.sales-orders') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.sales-orders')) aria-current="page" @endif>{{ __('app.sidebar.sales_order_report') }}</a>
                @endcan
                @can('reports.sales.view')
                <a href="{{ route('reports.sales') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.sales') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.sales')) aria-current="page" @endif>{{ __('app.sidebar.sales_report') }}</a>
                @endcan
                @can('reports.sales-summary.view')
                <a href="{{ route('reports.sales-summary') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.sales-summary') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.sales-summary')) aria-current="page" @endif>{{ __('app.sidebar.sales_report_2') }}</a>
                @endcan
                @can('reports.sales-by-product.view')
                <a href="{{ route('reports.sales-by-product') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.sales-by-product') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.sales-by-product')) aria-current="page" @endif>{{ __('insight.sidebar.sales_by_product') }}</a>
                @endcan
                @can('reports.salesman.view')
                <a href="{{ route('reports.salesman') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.salesman') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.salesman')) aria-current="page" @endif>{{ __('insight.sidebar.salesman') }}</a>
                @endcan
                @can('reports.payment-methods.view')
                <a href="{{ route('reports.payment-methods') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.payment-methods') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.payment-methods')) aria-current="page" @endif>{{ __('insight.sidebar.payment_methods') }}</a>
                @endcan
                @can('reports.sales-returns.view')
                <a href="{{ route('reports.sales-returns') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.sales-returns') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.sales-returns')) aria-current="page" @endif>{{ __('app.sidebar.sales_return_report') }}</a>
                @endcan
                @can('reports.receivable-payments.view')
                <a href="{{ route('reports.receivable-payments') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.receivable-payments') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.receivable-payments')) aria-current="page" @endif>{{ __('app.sidebar.receivable_payment_report') }}</a>
                @endcan
                @can('reports.receivable-card.view')
                <a href="{{ route('reports.receivable-card') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.receivable-card') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.receivable-card')) aria-current="page" @endif>{{ __('app.sidebar.receivable_card_report') }}</a>
                @endcan
                @can('reports.receivable-aging.view')
                <a href="{{ route('reports.receivable-aging') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.receivable-aging') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.receivable-aging')) aria-current="page" @endif>{{ __('app.sidebar.receivable_aging_report') }}</a>
                @endcan
                @can('reports.expenditure.view')
                <a href="{{ route('reports.expenditure') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.expenditure') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.expenditure')) aria-current="page" @endif>{{ __('app.sidebar.expenditure_report') }}</a>
                @endcan
                @can('reports.receipt.view')
                <a href="{{ route('reports.receipt') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.receipt') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.receipt')) aria-current="page" @endif>{{ __('app.sidebar.receipt_report') }}</a>
                @endcan
                @can('reports.transfers.view')
                <a href="{{ route('reports.transfers') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.transfers') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.transfers')) aria-current="page" @endif>{{ __('app.sidebar.transfer_report') }}</a>
                @endcan
                @can('reports.deviations.view')
                <a href="{{ route('reports.deviations') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.deviations') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.deviations')) aria-current="page" @endif>{{ __('app.sidebar.deviation_report') }}</a>
                @endcan
                @can('reports.stock-card.view')
                <a href="{{ route('reports.stock-card') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.stock-card') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.stock-card')) aria-current="page" @endif>{{ __('app.sidebar.stock_card') }}</a>
                @endcan
                @can('reports.position.view')
                <a href="{{ route('reports.position') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.position') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.position')) aria-current="page" @endif>{{ __('app.sidebar.position_report') }}</a>
                @endcan
                @can('reports.inventory.view')
                <a href="{{ route('reports.inventory') }}"
                   class="sidebar-item sub {{ request()->routeIs('reports.inventory') ? 'active' : '' }} flex items-center pl-11 pr-4 py-2"
                   @if(request()->routeIs('reports.inventory')) aria-current="page" @endif>{{ __('app.sidebar.inventory_report') }}</a>
                @endcan
            </div>
        </div>
        @endcanany

        @can('inquiry.view')
            <a href="{{ route('inquiry.index') }}"
               class="sidebar-item {{ request()->routeIs('inquiry.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('inquiry.*')) aria-current="page" @endif>
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
