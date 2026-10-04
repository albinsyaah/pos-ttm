<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\ApPayment;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PayableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApPaymentController extends Controller implements HasMiddleware
{
    public function __construct(private readonly PayableService $payables)
    {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.payable-payments.view', only: ['index']),
            new Middleware('permission:transactions.payable-payments.manage', only: ['invoices', 'store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $payablePayments = ApPayment::query()
            ->with(['supplier', 'paymentMethod', 'purchase'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->where('invoice_number', 'like', "%{$search}%"))
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('payment_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.payable-payments.index', [
            'payablePayments' => $payablePayments,
            'search' => $search,
            'suppliers' => Supplier::orderBy('name')->get(),
            // supplier_id => what is still owed to the supplier, shown next to the name in the form.
            'supplierTotals' => $this->payables->totalsBySupplier(),
            'paymentMethods' => PaymentMethod::active()->orderBy('id')->get(),
        ]);
    }

    /**
     * Open invoices of one supplier for the payment form. When a payment is
     * being edited, its own amount is not counted as paid, so the invoice it
     * settles still shows (with room for the edited amount).
     */
    public function invoices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'payment_id' => ['nullable', 'integer'],
        ]);

        $paymentId = isset($data['payment_id']) ? (int) $data['payment_id'] : null;

        $rows = $this->payables->openInvoices((int) $data['supplier_id'], $paymentId)
            ->map(fn (array $row) => [
                'id' => $row['purchase']->id,
                'invoice_number' => $row['purchase']->invoice_number,
                'due_date' => $row['due_date']->format('d M Y'),
                'days_left' => $row['days_left'],
                'net' => $row['net'],
                'paid' => $row['paid'],
                'outstanding' => $row['outstanding'],
            ])
            ->values();

        return response()->json(['invoices' => $rows]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayment($request);

        DB::transaction(function () use ($data) {
            $this->assertWithinBalance($data);

            ApPayment::create($data);
        });

        return redirect()->route('transactions.payable-payments.index')->with('success', __('Payable payment added successfully.'));
    }

    public function update(Request $request, ApPayment $payablePayment): RedirectResponse
    {
        $data = $this->validatePayment($request, $payablePayment);

        DB::transaction(function () use ($data, $payablePayment) {
            $this->assertWithinBalance($data, $payablePayment->id);

            $payablePayment->update($data);
        });

        return redirect()->route('transactions.payable-payments.index')->with('success', __('Payable payment updated successfully.'));
    }

    public function destroy(ApPayment $payablePayment): RedirectResponse
    {
        $payablePayment->delete();

        return redirect()->route('transactions.payable-payments.index')->with('success', __('Payable payment deleted successfully.'));
    }

    /**
     * The payment may not exceed what is still owed on its invoice. The invoice
     * row is locked first, so two payments entered at the same moment cannot
     * both fit into the same remaining balance. Call inside a transaction.
     */
    protected function assertWithinBalance(array $data, ?int $ignorePaymentId = null): void
    {
        if (empty($data['purchase_id'])) {
            return; // an old, supplier-level payment being edited
        }

        Purchase::whereKey($data['purchase_id'])->lockForUpdate()->first();

        $outstanding = $this->payables->outstandingFor((int) $data['purchase_id'], $ignorePaymentId);

        if ((float) $data['amount'] > $outstanding + 0.005) {
            throw ValidationException::withMessages([
                'amount' => __('app.payable_payments.amount_exceeds', [
                    'outstanding' => number_format($outstanding, 2),
                ]),
            ]);
        }
    }

    protected function validatePayment(Request $request, ?ApPayment $payment = null): array
    {
        $currentMethodId = $payment?->payment_method_id;
        // A payment saved before invoices were required may stay without one.
        $legacy = $payment !== null && $payment->purchase_id === null;

        $data = $request->validate([
            'payment_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('ap_payments', 'payment_number')->ignore($payment?->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'payment_date' => ['required', 'date'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $currentMethodId)
                ),
            ],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_id' => [
                $legacy ? 'nullable' : 'required',
                Rule::exists('purchases', 'id')->where(
                    fn ($query) => $query
                        ->where('supplier_id', $request->input('supplier_id'))
                        ->whereIn('status', Purchase::PAYABLE_STATUSES)
                ),
            ],
        ]);

        $data['purchase_id'] = $data['purchase_id'] ?? null;

        return $data;
    }
}
