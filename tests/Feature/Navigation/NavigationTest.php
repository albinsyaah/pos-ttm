<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.locale' => 'id']));

function navLogin(array $permissions): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'nav-'.uniqid(),
        'password' => Hash::make('password'),
        'role' => 'Staff',
        'is_active' => true,
    ]);

    foreach ($permissions as $name) {
        $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    test()->actingAs($user);

    return $user;
}

// ---- Menu order --------------------------------------------------------------------------

it('puts Supplier, Inquiry, Pelanggan, Kepegawaian and Aset at the top of the sidebar', function () {
    navLogin([
        'dashboard.view', 'suppliers.view', 'inquiry.view', 'customers.view',
        'hr.employees.view', 'assets.view', 'inventory.brands.view',
        'pricing.view', 'warehouses.view',
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            route('dashboard'),
            route('suppliers.index'),
            route('inquiry.index'),
            route('customers.index'),
            route('hr.employees.index'),
            route('assets.index'),
            // everything else comes after the five
            route('inventory.brands.index'),
            route('pricing.price-setups.index'),
            route('warehouses.index'),
        ], false);
});

it('keeps the menu items a user may not open out of the sidebar', function () {
    navLogin(['dashboard.view', 'inquiry.view']);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('inquiry.index'), false)
        ->assertDontSee(route('suppliers.index'), false)
        ->assertDontSee(route('customers.index'), false)
        ->assertDontSee(route('assets.index'), false);
});

// ---- Live search -------------------------------------------------------------------------

it('marks every list page search form for live search, so no page needs Enter', function () {
    $missing = [];
    $checked = 0;

    foreach (File::allFiles(resource_path('views')) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());

        if (str_contains($path, '/partials/') || ! str_ends_with($path, '.blade.php')) {
            continue;
        }

        $contents = file_get_contents($path);

        // Only GET forms that hold a search box: those are the list filters.
        preg_match_all('/<form\b[^>]*>.*?<\/form>/s', $contents, $forms);

        foreach ($forms[0] as $form) {
            if (! str_contains($form, 'type="search"')) {
                continue;
            }

            preg_match('/<form\b[^>]*>/s', $form, $tag);

            if (preg_match('/method="POST"/i', $tag[0])) {
                continue;
            }

            $checked++;

            if (! preg_match('/data-live-search="(auto|custom)"/', $tag[0])) {
                $missing[] = basename(dirname($path)).'/'.basename($path);
            }
        }
    }

    expect($checked)->toBeGreaterThan(40)
        ->and($missing)->toBe([]);
});

it('loads the live search script on every page layout', function () {
    navLogin(['dashboard.view']);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('js/live-search.js', false);
});

it('renders the customer list with the live search marker', function () {
    navLogin(['customers.view']);

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertSee('data-live-search="auto"', false);
});
