<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function lcLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'lc-'.uniqid(),
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

// ---- Every English sentence written in code has an Indonesian translation ----------------------

it('has an Indonesian translation for every plain sentence passed to __()', function () {
    $translations = json_decode(file_get_contents(lang_path('id.json')), true);

    $missing = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all('/__\(\s*([\'"])(.+?)(?<!\\\\)\1/s', file_get_contents($file->getPathname()), $found);

        foreach ($found[2] as $key) {
            $key = str_replace(["\\'", '\\"'], ["'", '"'], $key);

            // Keys such as app.common.select live in lang/*/app.php; only sentences belong in id.json.
            if (preg_match('/^[a-z_]+(\.[a-z_*]+)+$/i', $key) || str_contains($key, '$')) {
                continue;
            }

            if (! array_key_exists($key, $translations)) {
                $missing[] = $file->getFilename().': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});

it('has an Indonesian text for every title the page scripts write', function () {
    $translations = json_decode(file_get_contents(lang_path('id.json')), true);

    $missing = [];

    foreach (glob(public_path('js/*.js')) as $file) {
        preg_match_all("/__t\('([^']+)'\)/", file_get_contents($file), $found);

        foreach ($found[1] as $key) {
            if (! array_key_exists($key, $translations)) {
                $missing[] = basename($file).': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});

// ---- What the person sees -----------------------------------------------------------------------

it('shows success messages in Indonesian and in English', function () {
    lcLogin(['customers.view', 'customers.manage']);

    config(['app.locale' => 'id']);
    $this->post(route('customers.store'), ['name' => 'Toko Satu'])
        ->assertSessionHas('success', 'Pelanggan berhasil ditambahkan.');

    config(['app.locale' => 'en']);
    $this->post(route('customers.store'), ['name' => 'Toko Dua'])
        ->assertSessionHas('success', 'Customer added successfully.');
});

it('shows validation messages with Indonesian field names', function () {
    lcLogin(['customers.view', 'customers.manage']);
    config(['app.locale' => 'id']);

    $this->post(route('customers.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);
});

it('shows the login failure in Indonesian', function () {
    User::create([
        'username' => 'kasir-lc',
        'password' => Hash::make('rahasia123'),
        'role' => 'Staff',
        'is_active' => true,
    ]);

    config(['app.locale' => 'id']);

    $this->post(route('login.store'), ['username' => 'kasir-lc', 'password' => 'salah'])
        ->assertSessionHasErrors(['username' => 'Username atau kata sandi salah.']);
});

it('hands the page scripts the modal titles in the current language', function () {
    lcLogin(['customers.view']);

    config(['app.locale' => 'id']);
    $this->get(route('customers.index'))
        ->assertOk()
        ->assertSee('"Add Customer":"Tambah Pelanggan"', false);

    config(['app.locale' => 'en']);
    $this->get(route('customers.index'))
        ->assertOk()
        ->assertDontSee('Tambah Pelanggan', false);
});

it('hides the free badge on cashier lines that are not free', function () {
    lcLogin(['transactions.point-of-sale-new.view']);

    // The badge's own display rule used to beat .hidden, so it showed on every line.
    $this->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertSee('.pos-badge-free.hidden { display: none; }', false);
});

it('uses Indonesian as the application default', function () {
    // The default is read from config/app.php; the test environment may override
    // it, so read the file itself rather than the running configuration.
    expect(file_get_contents(config_path('app.php')))->toContain("env('APP_LOCALE', 'id')");
});
