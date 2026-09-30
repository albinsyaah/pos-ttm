<?php

return [
    'insufficient' => 'Stok tidak cukup untuk :product (:code) di gudang :warehouse: tersedia :available, diminta :requested.',
    'insufficient_reverse' => 'Transaksi ini tidak dapat dibatalkan atau diubah: stok :product (:code) di gudang :warehouse hanya :available, tetapi :requested harus ditarik kembali.',
    'product_not_found' => 'Produk #:id tidak ditemukan.',
    'return_not_received' => 'Pembelian :source belum diterima, sehingga tidak ada yang bisa diretur darinya.',
    'return_product_not_on_source' => ':product (:code) tidak ada di :source, sehingga tidak bisa diretur.',
    'return_exceeds' => 'Tidak bisa meretur :requested :product (:code): di :source ada :original, sudah diretur :returned, sisa :remaining.',
];
