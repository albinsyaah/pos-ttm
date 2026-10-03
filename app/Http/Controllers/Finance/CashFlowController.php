<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashFlow;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CashFlowController extends Controller implements HasMiddleware
{
    /**
     * Fixed cash flow types.
     */
    public const TYPES = ['in', 'out'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:finance.cash-flows.view', only: ['index']),
            new Middleware('permission:finance.cash-flows.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $cashFlows = CashFlow::query()
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

        return view('finance.cash-flows.index', [
            'cashFlows' => $cashFlows,
            'search' => $search,
            'accounts' => ChartOfAccount::orderBy('account_code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCashFlow($request);

        CashFlow::create($data);

        return redirect()->route('finance.cash-flows.index')->with('success', __('Cash flow entry added successfully.'));
    }

    public function update(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        $data = $this->validateCashFlow($request);

        $cashFlow->update($data);

        return redirect()->route('finance.cash-flows.index')->with('success', __('Cash flow entry updated successfully.'));
    }

    public function destroy(CashFlow $cashFlow): RedirectResponse
    {
        $cashFlow->delete();

        return redirect()->route('finance.cash-flows.index')->with('success', __('Cash flow entry deleted successfully.'));
    }

    protected function validateCashFlow(Request $request): array
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
