<?php

// Teks Tahap 7: Inquiry, dashboard, dan laporan baru.
return [
    'common' => [
        'print' => 'Cetak',
        'export_excel' => 'Export ke Excel',
        'reset_filters' => 'Atur Ulang Filter',
        'date_to' => 's/d',
        'total' => 'Total',
        'total_revenue' => 'Total Penjualan',
        'revenue' => 'Penjualan',
        'product' => 'Barang',
        'free' => 'Gratis',
        'inactive' => 'nonaktif',
    ],

    'returns' => [
        'sales_returns' => 'Retur Penjualan',
        'gross_sales' => 'Penjualan kotor',
        'net_hint' => 'Sudah dikurangi retur',
        'return_column' => 'Retur',
        'returned_qty' => 'Retur',
    ],

    'terminal' => [
        'head_title' => 'Point of Sales Induk',
    ],

    'sidebar' => [
        'sales_by_product' => 'Penjualan per Barang',
        'salesman' => 'Laporan Salesman',
        'payment_methods' => 'Laporan Metode Pembayaran',
    ],

    'dashboard' => [
        'range_day' => 'Hari',
        'range_week' => 'Minggu',
        'range_month' => 'Bulan',
        'top_products' => '10 Produk Terlaris',
        'by_units_sold' => 'Berdasarkan jumlah terjual (tidak termasuk barang gratis)',
        'no_sales_in_range' => 'Belum ada penjualan pada periode ini.',
        'income_by_method' => 'Penghasilan per Metode Pembayaran',
        'income_by_method_hint' => 'Penjualan langsung ditambah pembayaran piutang yang diterima pada periode ini',
        'cash_sales' => 'Penjualan langsung',
        'receivable_payments' => 'Piutang',
        'total_income' => 'Total penghasilan',
        'no_income_in_range' => 'Belum ada penghasilan pada periode ini.',
        'inactive' => 'nonaktif',
    ],

    'inquiry' => [
        'available' => 'Tersedia',
        'out_of_stock' => 'Habis',
        'effective' => 'berlaku',
        'price_history' => 'Riwayat harga',
        'no_price_history' => 'Belum ada perubahan harga yang tercatat untuk barang ini.',
        'when' => 'Waktu',
        'category' => 'Kategori',
        'change' => 'Perubahan',
        'effective_date' => 'Berlaku sejak',
        'by' => 'Oleh',
        'set_to' => 'Ditetapkan',
        'removed' => 'Dihapus',
    ],

    'payment_methods' => [
        'title' => 'Laporan Metode Pembayaran',
        'all_methods' => 'Semua Metode',
        'method' => 'Metode',
        'cash_sales_count' => 'Transaksi',
        'cash_sales' => 'Penjualan Langsung',
        'receivable_count' => 'Pembayaran',
        'receivable_payments' => 'Pembayaran Piutang',
        'income' => 'Penghasilan',
        'total_income' => 'Total Penghasilan',
        'refunds' => 'Retur Tunai',
        'supplier_paid' => 'Bayar Hutang Supplier',
        'no_data' => 'Belum ada data metode pembayaran.',
        'note' => 'Penghasilan = penjualan langsung + pembayaran piutang yang diterima. Penjualan kredit baru dihitung saat pelanggan membayar. Pembayaran hutang supplier adalah uang keluar dan tidak ikut dijumlahkan ke penghasilan. Retur penjualan tunai dikurangkan dari metode yang dipakai saat penjualan asal, sedangkan retur penjualan kredit mengurangi piutang.',
    ],

    'salesman' => [
        'title' => 'Laporan Salesman',
        'search_label' => 'Cari barang',
        'search_placeholder' => 'Cari barang yang dijual',
        'all_salesmen' => 'Semua Salesman',
        'salesmen_count' => 'Jumlah Salesman',
        'salesman' => 'Salesman',
        'sales' => 'penjualan',
        'paid_qty' => 'Terjual',
        'free_qty' => 'Gratis',
        'no_data' => 'Belum ada penjualan oleh salesman.',
    ],

    'by_product' => [
        'title' => 'Laporan Penjualan per Barang',
        'search_label' => 'Cari barang',
        'search_placeholder' => 'Cari berdasarkan kode atau nama barang',
        'all_warehouses' => 'Semua Gudang',
        'total_qty' => 'Total Jumlah Terjual',
        'price' => 'Harga Jual',
        'qty' => 'Jumlah',
        'sales_count' => 'Transaksi',
        'period' => 'Periode',
        'price_changed' => 'harga berubah',
        'no_data' => 'Tidak ada penjualan ditemukan.',
    ],

    'sales_summary' => [
        'free_goods_loss' => 'Kerugian Barang Gratis',
        'free_goods_loss_hint' => 'Modal barang gratis (harga beli terakhir)',
        'free_loss_column' => 'Kerugian Gratis',
        'discount_column' => 'Diskon',
        'discount_total' => 'Total Diskon',
        'free_goods_title' => 'Rincian Barang Gratis',
        'free_goods_basis' => 'Modal dihitung dari harga beli terakhir sebelum atau pada tanggal penjualan.',
        'free_qty' => 'Jumlah Gratis',
        'no_purchase_price' => 'belum ada harga beli',
    ],
];
