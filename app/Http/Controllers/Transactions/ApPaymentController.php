<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\ApPayment;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ApPaymentController extends Controller implements HasMiddleware
{
    /**
     * Fixed payment methods.
     */
    public const PAYMENT_METHODS = ['cash', 'bank_transfer', 'check', 'giro'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.view', only: ['index']),
            new Middleware('permission:transactions.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $payablePayments = ApPayment::query()
            ->with('supplier')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('payment_date')
            ->paginate(10)
            ->withQueryString();

        return view('transactions.payable-payments.index', [
            'payablePayments' => $payablePayments,
            'search' => $search,
            'suppliers' => Supplier::orderBy('name')->get(),
            'paymentMethods' => self::PAYMENT_METHODS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayment($request);

        ApPayment::create($data);

        return redirect()->route('transactions.payable-payments.index')->with('success', 'Payable payment added successfully.');
    }

    public function update(Request $request, ApPayment $payablePayment): RedirectResponse
    {
        $data = $this->validatePayment($request, $payablePayment->id);

        $payablePayment->update($data);

        return redirect()->route('transactions.payable-payments.index')->with('success', 'Payable payment updated successfully.');
    }

    public function destroy(ApPayment $payablePayment): RedirectResponse
    {
        $payablePayment->delete();

        return redirect()->route('transactions.payable-payments.index')->with('success', 'Payable payment deleted successfully.');
    }

    protected function validatePayment(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'payment_number' => [
                'required', 'string', 'max:100',
                Rule::unique('ap_payments', 'payment_number')->ignore($ignoreId),
            ],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', Rule::in(self::PAYMENT_METHODS)],
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ]);
    }
}
