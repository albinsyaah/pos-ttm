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
        <span class="sr-only">Search menu</span>
        <input id="navSearch" type="search" placeholder="Search menu"
            class="w-full rounded-xl bg-[var(--surface)] py-2 pl-9 pr-3 text-xs outline-none border border-transparent focus:border-[var(--brand-600)] focus:bg-white transition-colors" />
        <i class="fa-solid fa-magnifying-glass absolute left-7 top-1/2 -translate-y-1/2 text-[var(--ink-400)] text-xs"></i>
    </label>

    <nav id="mainNav" class="flex-1 overflow-y-auto px-3 pb-4 text-[var(--ink-400)] text-sm" aria-label="Main navigation">

        @can('dashboard.view')
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '#' }}"
               class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                <i class="fa-solid fa-grip w-4 text-center"></i> Dashboard
            </a>
        @endcan

        @can('customers.view')
            <a href="{{ \Illuminate\Support\Facades\Route::has('customers.index') ? route('customers.index') : '#' }}"
               class="sidebar-item {{ request()->routeIs('customers.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('customers.*')) aria-current="page" @endif>
                <i class="fa-solid fa-users w-4 text-center"></i> Customer
            </a>
        @endcan

        {{-- Inventory --}}
        @can('inventory.view')
        <div class="nav-group {{ request()->routeIs('inventory.*') ? 'open' : '' }}">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="{{ request()->routeIs('inventory.*') ? 'true' : 'false' }}">
                <i class="fa-solid fa-layer-group w-4 text-center"></i>
                <span class="flex-1 text-left">Inventory</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="{{ route('inventory.brands.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.brands.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.brands.*')) aria-current="page" @endif>Merk</a>
                <a href="{{ route('inventory.item-types.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.item-types.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.item-types.*')) aria-current="page" @endif>Jenis Barang</a>
                <a href="{{ route('inventory.product-groups.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.product-groups.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.product-groups.*')) aria-current="page" @endif>Grup Produk</a>
                <a href="{{ route('inventory.products.index') }}"
                   class="sidebar-item sub {{ request()->routeIs('inventory.products.*') ? 'active' : '' }} flex items-center gap-3 pl-11 pr-4 py-2"
                   @if(request()->routeIs('inventory.products.*')) aria-current="page" @endif>Barang</a>
            </div>
        </div>
        @endcan

        @can('assets.view')
            <a href="#" class="sidebar-item flex items-center gap-3 px-4 py-2.5">
                <i class="fa-solid fa-box w-4 text-center"></i> Asset
            </a>
        @endcan

        @can('pricing.view')
            <a href="#" class="sidebar-item flex items-center gap-3 px-4 py-2.5">
                <i class="fa-solid fa-tags w-4 text-center"></i> Setup Harga
            </a>
        @endcan

        {{-- Keuangan --}}
        @can('finance.view')
        <div class="nav-group">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="false">
                <i class="fa-solid fa-sack-dollar w-4 text-center"></i>
                <span class="flex-1 text-left">Keuangan</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="#" class="sidebar-item sub flex items-center gap-3 pl-11 pr-4 py-2">Cash Flow</a>
            </div>
        </div>
        @endcan

        @can('suppliers.view')
            <a href="#" class="sidebar-item flex items-center gap-3 px-4 py-2.5">
                <i class="fa-solid fa-truck-field w-4 text-center"></i> Supplier
            </a>
        @endcan

        @can('warehouses.view')
            <a href="#" class="sidebar-item flex items-center gap-3 px-4 py-2.5">
                <i class="fa-solid fa-warehouse w-4 text-center"></i> Gudang
            </a>
        @endcan

        {{-- Kepegawaian --}}
        @can('hr.view')
        <div class="nav-group">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="false">
                <i class="fa-solid fa-id-badge w-4 text-center"></i>
                <span class="flex-1 text-left">Kepegawaian</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="#" class="sidebar-item sub flex items-center gap-3 pl-11 pr-4 py-2">Karyawan</a>
                <a href="#" class="sidebar-item sub flex items-center gap-3 pl-11 pr-4 py-2">Salesman</a>
            </div>
        </div>
        @endcan

        {{-- Transaksi --}}
        @can('transactions.view')
        <div class="nav-group">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="false">
                <i class="fa-solid fa-right-left w-4 text-center"></i>
                <span class="flex-1 text-left">Transaksi</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">

                {{-- Account Payable --}}
                <div class="nav-group">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="false">
                        <span class="flex-1 text-left">Account Payable</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Purchase Order</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Pembelian</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Retur Pembelian</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Pembayaran Hutang</a>
                    </div>
                </div>

                {{-- Account Receivable --}}
                <div class="nav-group">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="false">
                        <span class="flex-1 text-left">Account Receivable</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Sales Order</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Penjualan</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Point of Sales New</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Point of Sales</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Retur Penjualan</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Sales SPG</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Pembayaran Hutang</a>
                    </div>
                </div>

                {{-- Mutasi Internal --}}
                <div class="nav-group">
                    <button class="nav-toggle sidebar-item sub w-full flex items-center gap-3 pl-11 pr-4 py-2" aria-expanded="false">
                        <span class="flex-1 text-left">Mutasi Internal</span>
                        <i class="fa-solid fa-chevron-right nav-chevron text-[10px]"></i>
                    </button>
                    <div class="nav-panel">
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Permintaan Barang</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Pengeluaran Internal</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Penerimaan Internal</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Transfer Gudang</a>
                        <a href="#" class="sidebar-item sub2 flex items-center pl-16 pr-4 py-2">Deviasi</a>
                    </div>
                </div>

                <a href="#" class="sidebar-item sub flex items-center gap-3 pl-11 pr-4 py-2">Cash Management</a>
                <a href="#" class="sidebar-item sub flex items-center gap-3 pl-11 pr-4 py-2">General Ledger</a>
            </div>
        </div>
        @endcan

        {{-- Report --}}
        @can('reports.view')
        <div class="nav-group">
            <button class="nav-toggle sidebar-item w-full flex items-center gap-3 px-4 py-2.5" aria-expanded="false">
                <i class="fa-solid fa-chart-line w-4 text-center"></i>
                <span class="flex-1 text-left">Report</span>
                <i class="fa-solid fa-chevron-right nav-chevron text-xs"></i>
            </button>
            <div class="nav-panel">
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Purchase Order Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Pembelian Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Retur Pembelian Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Payment Hutang Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Sales Order Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Sales Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Penjualan Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Retur Penjualan Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Payment Piutang Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Kartu Piutang Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Umur Piutang Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Pengeluaran Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Penerimaan Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Transfer Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Deviasi Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Kartu Stok</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Posisi Report</a>
                <a href="#" class="sidebar-item sub flex items-center pl-11 pr-4 py-2">Persediaan Report</a>
            </div>
        </div>
        @endcan

        @can('inquiry.view')
            <a href="#" class="sidebar-item flex items-center gap-3 px-4 py-2.5">
                <i class="fa-solid fa-magnifying-glass-chart w-4 text-center"></i> Inquery
            </a>
        @endcan

        @role('Super Admin')
            <div class="my-3 border-t border-gray-100"></div>

            <a href="{{ route('admin.users.index') }}"
               class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>
                <i class="fa-solid fa-user-shield w-4 text-center"></i> Administrator
            </a>

            <a href="{{ route('admin.roles.index') }}"
               class="sidebar-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }} flex items-center gap-3 px-4 py-2.5"
               @if(request()->routeIs('admin.roles.*')) aria-current="page" @endif>
                <i class="fa-solid fa-shield-halved w-4 text-center"></i> Roles &amp; Permissions
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
