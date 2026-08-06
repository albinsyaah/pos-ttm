<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CashManagementController extends Controller implements HasMiddleware
{
    /**
     * Fixed cash transaction types.
     */
    public const TYPES = ['in', 'out'];

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

        $cashTransactions = CashFlow::query()
            ->with('account')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhereHas('account', function ($accountQuery) use ($search) {
                            $accountQuery->where('account_code', 'like', "%{$search}%")
                                ->orWhere('account_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('transaction_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.cash-management.index', [
            'cashTransactions' => $cashTransactions,
            'search' => $search,
            'accounts' => ChartOfAccount::orderBy('account_code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCashTransaction($request);

        CashFlow::create($data);

        return redirect()->route('transactions.cash-management.index')->with('success', 'Cash transaction added successfully.');
    }

    public function update(Request $request, CashFlow $cashManagement): RedirectResponse
    {
        $data = $this->validateCashTransaction($request);

        $cashManagement->update($data);

        return redirect()->route('transactions.cash-management.index')->with('success', 'Cash transaction updated successfully.');
    }

    public function destroy(CashFlow $cashManagement): RedirectResponse
    {
        $cashManagement->delete();

        return redirect()->route('transactions.cash-management.index')->with('success', 'Cash transaction deleted successfully.');
    }

    protected function validateCashTransaction(Request $request): array
    {
        return $request->validate([
            'transaction_date' => ['required', 'date'],
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'description' => ['nullable', 'string', 'max:1000'],
            'account_id' => ['required', 'exists:chart_of_accounts,id'],
        ]);
    }
}
