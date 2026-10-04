<?php

/*
|--------------------------------------------------------------------------
| Store identity (printed on receipts and delivery notes)
|--------------------------------------------------------------------------
|
| Set these in .env. They are only used by the print views and the public
| digital receipt. Website and social links are left out on purpose: the
| owner has postponed them.
*/

return [
    'name' => env('STORE_NAME', 'TunasTaniMakmur'),
    'address' => env('STORE_ADDRESS', ''),
    'phone' => env('STORE_PHONE', ''),
    'receipt_footer' => env('STORE_RECEIPT_FOOTER', 'Terima kasih atas kunjungan Anda.'),
];
