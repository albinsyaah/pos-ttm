<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\ArPayment;
use App\Models\PaymentMethod;
use App\Models\Customer;
use App\Services\ReceivableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ArPaymentController extends Controller implements HasMiddleware
{
    public function __construct(private readonly ReceivableService $receivables)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.receivable-payments.view', only: ['index']),
            new Middleware('permission:transactions.receivable-payments.manage', only: ['outstanding', 'store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $receivablePayments = ArPayment::query()
            ->with(['customer', 'paymentMethod'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('payment_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.receivable-payments.index', [
            'receivablePayments' => $receivablePayments,
            'search' => $search,
            'customers' => Customer::orderBy('name')->get(),
            // customer_id => what the customer still owes, shown next to the name in the form.
            'customerTotals' => $this->receivables->totalsByCustomer(),
            'paymentMethods' => PaymentMethod::active()->orderBy('id')->get(),
        ]);
    }

    /**
     * What one customer still owes, with the open invoices behind it, for the
     * payment form. When a payment is being edited, its own amount is not counted
     * as paid, so the balance shown is what it was before this payment.
     */
    public function outstanding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'payment_id' => ['nullable', 'integer'],
        ]);

        $paymentId = isset($data['payment_id']) ? (int) $data['payment_id'] : null;
        $balance = $this->receivables->balances((int) $data['customer_id'], $paymentId)->get((int) $data['customer_id']);

        return response()->json([
            'total' => $balance['total'] ?? 0.0,
            'invoices' => $balance['invoices'] ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayment($request);

        ArPayment::create($data);

        return redirect()->route('transactions.receivable-payments.index')->with('success', __('Receivable payment added successfully.'));
    }

    public function update(Request $request, ArPayment $receivablePayment): RedirectResponse
    {
        $data = $this->validatePayment($request, $receivablePayment->id, $receivablePayment->payment_method_id);

        $receivablePayment->update($data);

        return redirect()->route('transactions.receivable-payments.index')->with('success', __('Receivable payment updated successfully.'));
    }

    public function destroy(ArPayment $receivablePayment): RedirectResponse
    {
        $receivablePayment->delete();

        return redirect()->route('transactions.receivable-payments.index')->with('success', __('Receivable payment deleted successfully.'));
    }

    /**
     * @param  int|null  $currentMethodId  the method the payment already has (when editing),
     *                                      which stays valid even if it was deactivated since
     */
    protected function validatePayment(Request $request, ?int $ignoreId = null, ?int $currentMethodId = null): array
    {
        return $request->validate([
            'payment_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('ar_payments', 'payment_number')->ignore($ignoreId),
            ],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'payment_date' => ['required', 'date'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $currentMethodId)
                ),
            ],
            'customer_id' => ['required', 'exists:customers,id'],
        ]);
    }
}
