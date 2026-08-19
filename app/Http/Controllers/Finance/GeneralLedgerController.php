<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class GeneralLedgerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:finance.general-ledgers.view', only: ['index']),
            new Middleware('permission:finance.general-ledgers.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $ledgers = GeneralLedger::query()
            ->with('account')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                        ->orWhereHas('account', function ($accountQuery) use ($search) {
                            $accountQuery->where('account_code', 'like', "%{$search}%")
                                ->orWhere('account_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('transaction_date')
            ->paginate(20)
            ->withQueryString();

        return view('finance.general-ledgers.index', [
            'ledgers' => $ledgers,
            'search' => $search,
            'accounts' => ChartOfAccount::orderBy('account_code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateLedger($request);

        GeneralLedger::create($data);

        return redirect()->route('finance.general-ledgers.index')->with('success', 'General ledger entry added successfully.');
    }

    public function update(Request $request, GeneralLedger $generalLedger): RedirectResponse
    {
        $data = $this->validateLedger($request);

        $generalLedger->update($data);

        return redirect()->route('finance.general-ledgers.index')->with('success', 'General ledger entry updated successfully.');
    }

    public function destroy(GeneralLedger $generalLedger): RedirectResponse
    {
        $generalLedger->delete();

        return redirect()->route('finance.general-ledgers.index')->with('success', 'General ledger entry deleted successfully.');
    }

    protected function validateLedger(Request $request): array
    {
        return $request->validate([
            'transaction_date' => ['required', 'date'],
            'debit' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'credit' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'account_id' => ['required', 'exists:chart_of_accounts,id'],
        ]);
    }
}
