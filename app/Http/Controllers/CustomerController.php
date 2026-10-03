<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:customers.view', only: ['index']),
            new Middleware('permission:customers.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
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

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCustomer($request);

        Customer::create($data);

        return redirect()->route('customers.index')->with('success', __('Customer added successfully.'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validateCustomer($request, $customer->id);

        $customer->update($data);

        return redirect()->route('customers.index')->with('success', __('Customer updated successfully.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('Customer deleted successfully.'));
    }

    protected function validateCustomer(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('customers', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
