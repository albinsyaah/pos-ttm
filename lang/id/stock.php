<?php

return [
    'insufficient' => 'Stok tidak cukup untuk :product (:code) di gudang :warehouse: tersedia :available, diminta :requested.',
    'insufficient_reverse' => 'Transaksi ini tidak dapat dibatalkan atau diubah: stok :product (:code) di gudang :warehouse hanya :available, tetapi :requested harus ditarik kembali.',
    'insufficient_all' => 'Stok tidak cukup untuk :product (:code) di semua gudang: total tersedia :available, diminta :requested.',
    'product_not_found' => 'Produk #:id tidak ditemukan.',
    'return_not_received' => 'Pembelian :source belum diterima, sehingga tidak ada yang bisa diretur darinya.',
    'return_product_not_on_source' => ':product (:code) tidak ada di :source, sehingga tidak bisa diretur.',
    'return_exceeds' => 'Tidak bisa meretur :requested :product (:code): di :source ada :original, sudah diretur :returned, sisa :remaining.',
    'return_no_source' => 'Barang :product tidak bisa dikembalikan ke gudang karena penjualannya tidak tercatat mengambil barang itu dari gudang mana pun.',
];
