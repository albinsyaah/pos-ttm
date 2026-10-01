<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Master data for payment methods (Tunai, Transfer, QRIS, ...). Admin can add,
 * rename, deactivate and delete methods. The same list feeds the checkout
 * terminal and the payable/receivable payment forms.
 */
class PaymentMethodController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:finance.payment-methods.view', only: ['index']),
            new Middleware('permission:finance.payment-methods.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $paymentMethods = PaymentMethod::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->withCount(['sales', 'apPayments', 'arPayments'])
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('finance.payment-methods.index', [
            'paymentMethods' => $paymentMethods,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMethod($request);

        $this->assertKeepsACashMethod(null, $data['is_cash'], $data['is_active']);

        PaymentMethod::create($data + ['code' => $this->makeCode($data['name'])]);

        return redirect()->route('finance.payment-methods.index')
            ->with('success', __('app.payment_methods.added'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $this->validateMethod($request, $paymentMethod->id);

        $this->assertKeepsACashMethod($paymentMethod, $data['is_cash'], $data['is_active']);

        // The code is fixed at creation; only the display fields change.
        $paymentMethod->update($data);

        return redirect()->route('finance.payment-methods.index')
            ->with('success', __('app.payment_methods.updated'));
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->isInUse()) {
            return back()->with('error', __('app.payment_methods.in_use'));
        }

        if ($this->wouldLeaveNoCashMethod($paymentMethod, false, false)) {
            return back()->with('error', __('app.payment_methods.need_cash'));
        }

        $paymentMethod->delete();

        return redirect()->route('finance.payment-methods.index')
            ->with('success', __('app.payment_methods.deleted'));
    }

    /**
     * @return array{name: string, is_cash: bool, is_active: bool}
     */
    protected function validateMethod(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('payment_methods', 'name')->ignore($ignoreId)],
            'is_cash' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim($validated['name']),
            'is_cash' => (bool) ($validated['is_cash'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }

    /**
     * A cash sale that arrives without a chosen method falls back to the
     * active cash method (Tunai), so at least one must always exist.
     */
    private function assertKeepsACashMethod(?PaymentMethod $method, bool $isCash, bool $isActive): void
    {
        if ($this->wouldLeaveNoCashMethod($method, $isCash, $isActive)) {
            throw ValidationException::withMessages(['name' => __('app.payment_methods.need_cash')]);
        }
    }

    private function wouldLeaveNoCashMethod(?PaymentMethod $method, bool $isCash, bool $isActive): bool
    {
        $anotherCash = PaymentMethod::active()
            ->where('is_cash', true)
            ->when($method, fn ($query) => $query->where('id', '!=', $method->id))
            ->exists();

        return ! $anotherCash && ! ($isCash && $isActive);
    }

    /** A stable, unique slug for the new method (e.g. "Kartu Debit" => "kartu_debit"). */
    private function makeCode(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'method';
        $code = $base;
        $i = 2;

        while (PaymentMethod::where('code', $code)->exists()) {
            $code = $base.'_'.$i++;
        }

        return Str::limit($code, 50, '');
    }
}
