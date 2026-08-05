<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class ChartOfAccountController extends Controller implements HasMiddleware
{
    /**
     * Fixed account types (standard accounting classifications).
     */
    public const TYPES = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:finance.view', only: ['index']),
            new Middleware('permission:finance.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $accounts = ChartOfAccount::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('account_code', 'like', "%{$search}%")
                        ->orWhere('account_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('account_code')
            ->paginate(10)
            ->withQueryString();

        return view('finance.chart-of-accounts.index', [
            'accounts' => $accounts,
            'search' => $search,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAccount($request);

        ChartOfAccount::create($data);

        return redirect()->route('finance.chart-of-accounts.index')->with('success', 'Account added successfully.');
    }

    public function update(Request $request, ChartOfAccount $account): RedirectResponse
    {
        $data = $this->validateAccount($request, $account->id);

        $account->update($data);

        return redirect()->route('finance.chart-of-accounts.index')->with('success', 'Account updated successfully.');
    }

    public function destroy(ChartOfAccount $account): RedirectResponse
    {
        if ($account->cashFlows()->exists() || $account->generalLedgers()->exists()) {
            return back()->with('error', 'This account already has cash flow or ledger entries and cannot be deleted.');
        }

        $account->delete();

        return redirect()->route('finance.chart-of-accounts.index')->with('success', 'Account deleted successfully.');
    }

    protected function validateAccount(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'account_code' => [
                'required', 'string', 'max:50',
                Rule::unique('chart_of_accounts', 'account_code')->ignore($ignoreId),
            ],
            'account_name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in(self::TYPES)],
        ]);
    }
}
