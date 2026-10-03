<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\SalesInsightService;
use Illuminate\Http\Request;

/**
 * Income per payment method: cash sales (method picked at the till) plus
 * receivable payments, with supplier payments shown beside it as money out.
 * Read-only. Permission is checked on the route.
 */
class PaymentMethodReportController extends Controller
{
    public function index(Request $request, SalesInsightService $insight)
    {
        $methodId = $request->query('payment_method_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $rows = $insight->incomeByMethod(
            $dateFrom ?: null,
            $dateTo ?: null,
            $methodId ? (int) $methodId : null,
        );

        $totals = [
            'sales_count' => collect($rows)->sum('sales_count'),
            'cash_sales' => collect($rows)->sum('cash_sales'),
            'ar_count' => collect($rows)->sum('ar_count'),
            'ar_received' => collect($rows)->sum('ar_received'),
            'returns' => collect($rows)->sum('returns'),
            'income' => collect($rows)->sum('income'),
            'ap_paid' => collect($rows)->sum('ap_paid'),
        ];

        return view('reports.payment-methods.index', [
            'rows' => $rows,
            'totals' => $totals,
            'methods' => PaymentMethod::orderBy('name')->get(),
            'methodId' => $methodId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
