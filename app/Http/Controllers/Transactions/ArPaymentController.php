<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\ArPayment;
use App\Models\PaymentMethod;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ArPaymentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.receivable-payments.view', only: ['index']),
            new Middleware('permission:transactions.receivable-payments.manage', only: ['store', 'update', 'destroy']),
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
            'paymentMethods' => PaymentMethod::active()->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayment($request);

        ArPayment::create($data);

        return redirect()->route('transactions.receivable-payments.index')->with('success', 'Receivable payment added successfully.');
    }

    public function update(Request $request, ArPayment $receivablePayment): RedirectResponse
    {
        $data = $this->validatePayment($request, $receivablePayment->id, $receivablePayment->payment_method_id);

        $receivablePayment->update($data);

        return redirect()->route('transactions.receivable-payments.index')->with('success', 'Receivable payment updated successfully.');
    }

    public function destroy(ArPayment $receivablePayment): RedirectResponse
    {
        $receivablePayment->delete();

        return redirect()->route('transactions.receivable-payments.index')->with('success', 'Receivable payment deleted successfully.');
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
