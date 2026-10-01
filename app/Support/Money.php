<?php

namespace App\Support;

class Money
{
    /** Rupiah as printed on receipts: "Rp 1.250.000" (no decimals, dot as thousands separator). */
    public static function rupiah(float|int|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
