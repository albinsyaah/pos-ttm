<?php

namespace App\Http\Controllers\Transactions;

/**
 * Checkout terminal for the head cashier ("kasir induk"): the same screen as the cashier's
 * terminal plus the driver name, and the large receipt and delivery note after a sale.
 * Own routes and permission (transactions.point-of-sale-induk.*), so access is set per role.
 */
class PointOfSaleIndukController extends PointOfSaleNewController
{
    public const MODE = 'head';

    public const ROUTE = 'transactions.point-of-sale-induk';

    public const PERMISSION = 'transactions.point-of-sale-induk';
}
