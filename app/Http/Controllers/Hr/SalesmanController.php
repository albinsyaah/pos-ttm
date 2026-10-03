<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Salesmen are simply Employee records whose position is "Salesman".
 * This controller gives that subset its own page (matching the sidebar's
 * Kepegawaian > Salesman entry) without needing a separate table.
 */
class SalesmanController extends Controller implements HasMiddleware
{
    protected const POSITION = 'Salesman';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:hr.salesmen.view', only: ['index']),
            new Middleware('permission:hr.salesmen.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $salesmen = Employee::query()
            ->where('position', self::POSITION)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('hr.salesmen.index', [
            'salesmen' => $salesmen,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSalesman($request);
        $data['position'] = self::POSITION;

        Employee::create($data);

        return redirect()->route('hr.salesmen.index')->with('success', 'Salesman added successfully.');
    }

    public function update(Request $request, Employee $salesman): RedirectResponse
    {
        $data = $this->validateSalesman($request, $salesman->id);
        $data['position'] = self::POSITION;

        $salesman->update($data);

        return redirect()->route('hr.salesmen.index')->with('success', 'Salesman updated successfully.');
    }

    public function destroy(Employee $salesman): RedirectResponse
    {
        if ($salesman->sales()->exists()) {
            return back()->with('error', 'This salesman still has sales records and cannot be deleted.');
        }

        $salesman->delete();

        return redirect()->route('hr.salesmen.index')->with('success', 'Salesman deleted successfully.');
    }

    protected function validateSalesman(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
    }
}
