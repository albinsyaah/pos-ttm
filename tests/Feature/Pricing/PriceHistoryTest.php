<?php

use App\Models\PriceHistory;
use App\Models\PriceSetup;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function priceTestUser(array $permissions = ['pricing.view', 'pricing.manage']): User
{
    $user = User::create([
        'username' => 'price-tester-'.uniqid(),
        'password' => bcrypt('secret-password'),
        'role' => 'Admin',
        'is_active' => true,
    ]);

    foreach ($permissions as $name) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }

    return $user;
}

function priceTestProduct(string $code = 'PR-1'): Product
{
    return Product::create(['code' => $code, 'name' => "Produk {$code}"]);
}

function priceTestSetup(Product $product, float $amount, string $date, string $category = 'Retail'): PriceSetup
{
    return PriceSetup::create([
        'product_id' => $product->id,
        'price_category' => $category,
        'amount' => $amount,
        'effective_date' => $date,
    ]);
}

// ---- PriceService: price applies from its date, to all stock ---------------

it('picks the latest price on or before the date', function () {
    $p = priceTestProduct();
    priceTestSetup($p, 1000, '2026-01-01');
    priceTestSetup($p, 1200, '2026-06-01');
    priceTestSetup($p, 1500, '2026-12-01');

    $service = new PriceService();

    expect($service->currentPrices([$p->id], 'Retail', '2026-03-15')[$p->id]['amount'])->toBe(1000.0);
    expect($service->currentPrices([$p->id], 'Retail', '2026-06-01')[$p->id]['amount'])->toBe(1200.0);
    expect($service->currentPrices([$p->id], 'Retail', '2026-10-01')[$p->id]['amount'])->toBe(1200.0);
    expect($service->currentPrices([$p->id], 'Retail', '2026-12-01')[$p->id]['amount'])->toBe(1500.0);
});

it('ignores a price that is not effective yet and returns nothing before the first price', function () {
    $p = priceTestProduct();
    priceTestSetup($p, 1500, '2099-01-01');

    $service = new PriceService();

    expect($service->currentPrices([$p->id]))->toBe([]);
    expect($service->currentPrices([$p->id], 'Retail', '2099-01-01'))->toHaveKey($p->id);
});

it('keeps categories apart and breaks same-day ties with the newest row', function () {
    $p = priceTestProduct();
    priceTestSetup($p, 1000, '2026-01-01', 'Retail');
    priceTestSetup($p, 900, '2026-01-01', 'Grosir');
    priceTestSetup($p, 1100, '2026-01-01', 'Retail'); // same day, newer row

    $service = new PriceService();

    expect($service->currentPrices([$p->id], 'Retail', '2026-02-01')[$p->id]['amount'])->toBe(1100.0);
    expect($service->currentPrices([$p->id], 'Grosir', '2026-02-01')[$p->id]['amount'])->toBe(900.0);
});

it('builds a price book newest first for the cashier page', function () {
    $p = priceTestProduct();
    priceTestSetup($p, 1000, '2026-01-01');
    priceTestSetup($p, 1200, '2026-06-01');

    $book = (new PriceService())->priceBook([$p->id]);

    expect($book[$p->id])->toBe([
        ['date' => '2026-06-01', 'amount' => 1200.0],
        ['date' => '2026-01-01', 'amount' => 1000.0],
    ]);
});

// ---- Change log: who, when, from what to what ---------------------------------

it('logs a new price with the user who added it', function () {
    $user = priceTestUser();
    $p = priceTestProduct();

    $this->actingAs($user)->post(route('pricing.price-setups.store'), [
        'product_id' => $p->id, 'price_category' => 'Retail', 'amount' => 2500, 'effective_date' => '2026-10-01',
    ])->assertSessionHasNoErrors();

    $log = PriceHistory::first();
    expect($log->action)->toBe('created')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->product_id)->toBe($p->id)
        ->and($log->old_amount)->toBeNull()
        ->and((float) $log->new_amount)->toBe(2500.0);
});

it('logs the old and new amount when a price is changed', function () {
    $user = priceTestUser();
    $p = priceTestProduct();
    $setup = priceTestSetup($p, 1000, '2026-01-01');

    $this->actingAs($user)->put(route('pricing.price-setups.update', $setup), [
        'product_id' => $p->id, 'price_category' => 'Retail', 'amount' => 1300, 'effective_date' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    $log = PriceHistory::latest('id')->first();
    expect($log->action)->toBe('updated')
        ->and((float) $log->old_amount)->toBe(1000.0)
        ->and((float) $log->new_amount)->toBe(1300.0)
        ->and($log->old_effective_date->toDateString())->toBe('2026-01-01')
        ->and($log->new_effective_date->toDateString())->toBe('2026-02-01')
        ->and($log->user_id)->toBe($user->id);
});

it('writes no log when a price is saved without changes', function () {
    $user = priceTestUser();
    $p = priceTestProduct();
    $setup = priceTestSetup($p, 1000, '2026-01-01');

    $this->actingAs($user)->put(route('pricing.price-setups.update', $setup), [
        'product_id' => $p->id, 'price_category' => 'Retail', 'amount' => 1000, 'effective_date' => '2026-01-01',
    ])->assertSessionHasNoErrors();

    expect(PriceHistory::count())->toBe(0);
});

it('keeps the log after the price row is deleted', function () {
    $user = priceTestUser();
    $p = priceTestProduct();
    $setup = priceTestSetup($p, 1000, '2026-01-01');

    $this->actingAs($user)->delete(route('pricing.price-setups.destroy', $setup))->assertSessionHasNoErrors();

    expect(PriceSetup::count())->toBe(0);
    $log = PriceHistory::first();
    expect($log->action)->toBe('deleted')
        ->and((float) $log->old_amount)->toBe(1000.0)
        ->and($log->new_amount)->toBeNull();
});

it('saves nothing and logs nothing when the price is rejected', function () {
    $user = priceTestUser();
    $p = priceTestProduct();

    $this->actingAs($user)->post(route('pricing.price-setups.store'), [
        'product_id' => $p->id, 'price_category' => 'Retail', 'amount' => -5, 'effective_date' => '2026-10-01',
    ])->assertSessionHasErrors('amount');

    expect(PriceSetup::count())->toBe(0)->and(PriceHistory::count())->toBe(0);
});

// ---- History page ------------------------------------------------------------------

it('lists the history and filters it by product', function () {
    $user = priceTestUser();
    $a = priceTestProduct('AAA-1');
    $b = priceTestProduct('BBB-1');
    $this->actingAs($user);
    PriceHistory::record('created', null, priceTestSetup($a, 1000, '2026-01-01'));
    PriceHistory::record('created', null, priceTestSetup($b, 2000, '2026-01-01'));

    $this->get(route('pricing.price-histories.index'))
        ->assertOk()->assertSee('AAA-1')->assertSee('BBB-1')->assertSee($user->username);

    $this->get(route('pricing.price-histories.index', ['q' => 'BBB']))
        ->assertOk()->assertSee('BBB-1')->assertDontSee('AAA-1');
});

it('keeps the history page behind the pricing.view permission', function () {
    $this->actingAs(priceTestUser([]))
        ->get(route('pricing.price-histories.index'))
        ->assertForbidden();
});

// ---- Cashier terminal -----------------------------------------------------------------

it('hands the cashier terminal the dated reference prices', function () {
    $user = priceTestUser(['transactions.point-of-sale-new.view']);
    $p = priceTestProduct('KSR-1');
    priceTestSetup($p, 1000, '2026-01-01');
    priceTestSetup($p, 1200, '2026-06-01');

    $this->actingAs($user)->get(route('transactions.point-of-sale-new.index'))
        ->assertOk()
        ->assertViewHas('priceBook', fn ($book) => count($book[$p->id]) === 2 && $book[$p->id][0]['amount'] === 1200.0);
});
