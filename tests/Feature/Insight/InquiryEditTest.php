<?php

use App\Models\Brand;
use App\Models\InternalMutation;
use App\Models\ItemType;
use App\Models\PriceHistory;
use App\Models\PriceSetup;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function inqLogin(array $permissions = []): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'username' => 'inq-'.uniqid(),
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

function inqWarehouse(string $code = 'W-IQ', string $name = 'Gudang IQ'): Warehouse
{
    return Warehouse::firstOrCreate(['code' => $code], ['name' => $name]);
}

function inqPrices(Product $product): array
{
    return PriceSetup::where('product_id', $product->id)->get()
        ->mapWithKeys(fn ($setup) => [$setup->price_category => (float) $setup->amount])
        ->all();
}

it('keeps add and edit away from users without inquiry.manage', function () {
    inqLogin(['inquiry.view']);
    $product = Product::create(['code' => 'P-IQ1', 'name' => 'Urea', 'unit_name' => 'kg']);

    $this->post(route('inquiry.store'), ['product_id' => $product->id, 'unit_name' => 'pcs'])->assertForbidden();
    $this->put(route('inquiry.update', $product), ['unit_name' => 'pcs'])->assertForbidden();
    $this->get(route('inquiry.product-data', $product))->assertForbidden();

    expect($product->fresh()->unit_name)->toBe('kg');

    $this->get(route('inquiry.index'))
        ->assertOk()
        ->assertDontSee('id="inquiryModal"', false)
        ->assertDontSee('id="addInquiryProductBtn"', false);
});

it('offers every product in a select instead of a typed name', function () {
    inqLogin(['inquiry.view', 'inquiry.manage']);
    Product::create(['code' => 'P-IQ2', 'name' => 'Urea', 'unit_name' => 'kg']);
    Product::create(['code' => 'P-IQ2B', 'name' => 'Pupuk Lain', 'unit_name' => 'kg']);

    $this->get(route('inquiry.index', ['q' => 'Urea']))
        ->assertOk()
        ->assertSee('id="addInquiryProductBtn"', false)
        ->assertSee('id="inquiryModal"', false)
        ->assertSee('edit-inquiry-btn', false)
        ->assertSee('<select id="inquiry_product_id" name="product_id"', false)
        ->assertSee('js/product-picker.js', false) // makes the select searchable
        ->assertDontSee('name="name"', false)
        // the select lists every product, not only the ones the filter keeps
        ->assertSee('P-IQ2B — Pupuk Lain', false);
});

it('gives the current data of a picked product to fill the form', function () {
    inqLogin(['inquiry.view', 'inquiry.manage']);
    $warehouse = inqWarehouse();
    $brand = Brand::create(['name' => 'Merk A']);
    $product = Product::create(['code' => 'P-IQ5', 'name' => 'Urea', 'unit_name' => 'kg', 'brand_id' => $brand->id]);
    PriceSetup::create(['product_id' => $product->id, 'price_category' => 'Retail', 'amount' => 5000, 'effective_date' => '2026-09-01']);
    PriceSetup::create(['product_id' => $product->id, 'price_category' => 'Beli', 'amount' => 4000, 'effective_date' => '2026-09-01']);
    app(StockService::class)->increase($product->id, $warehouse->id, 12, 'SEED-IQ5');

    // without pricing.view the purchase price is not given
    $json = $this->getJson(route('inquiry.product-data', $product))->assertOk()->json();

    expect($json['code'])->toBe('P-IQ5')
        ->and($json['unit_name'])->toBe('kg')
        ->and($json['brand_id'])->toBe($brand->id)
        ->and((float) $json['prices']['retail'])->toBe(5000.0)
        ->and($json['prices'])->not->toHaveKey('purchase')
        ->and($json['stock'][$warehouse->id])->toBe(12);

    inqLogin(['inquiry.view', 'inquiry.manage', 'pricing.view']);

    $json = $this->getJson(route('inquiry.product-data', $product))->assertOk()->json();
    expect((float) $json['prices']['purchase'])->toBe(4000.0);
});

it('sets the data, prices and stock of a picked product, and logs them', function () {
    $user = inqLogin(['inquiry.view', 'inquiry.manage', 'pricing.view']);
    $warehouse = inqWarehouse();
    $brand = Brand::create(['name' => 'Merk A']);
    $type = ItemType::create(['name' => 'Pupuk']);
    $product = Product::create(['code' => 'P-IQ6', 'name' => 'Pupuk Baru', 'unit_name' => 'pcs']);

    $this->post(route('inquiry.store'), [
        'product_id' => $product->id,
        'code' => '',
        'unit_name' => 'kg',
        'brand_id' => $brand->id,
        'item_type_id' => $type->id,
        'prices' => ['purchase' => '4000', 'retail' => '5000', 'wholesale' => '4500'],
        'price_effective_date' => '2026-11-01',
        'stock' => [$warehouse->id => '25'],
    ])->assertSessionHasNoErrors();

    $product->refresh();

    expect($product->name)->toBe('Pupuk Baru')
        ->and($product->code)->toBe('P-IQ6')
        ->and($product->unit_name)->toBe('kg')
        ->and($product->brand_id)->toBe($brand->id)
        ->and($product->item_type_id)->toBe($type->id);

    expect(Product::count())->toBe(1);
    expect(inqPrices($product))->toEqual(['Beli' => 4000.0, 'Retail' => 5000.0, 'Grosir' => 4500.0]);
    expect(PriceHistory::where('product_id', $product->id)->pluck('user_id')->unique()->all())->toBe([$user->id]);
    expect(PriceHistory::where('product_id', $product->id)->count())->toBe(3);

    expect(app(StockService::class)->available($product->id, $warehouse->id))->toBe(25);

    $deviation = InternalMutation::where('type', 'Deviation')->firstOrFail();
    expect($deviation->status)->toBe('completed')
        ->and((int) $deviation->from_warehouse_id)->toBe($warehouse->id);
});

it('refuses to add without a product from the list', function () {
    inqLogin(['inquiry.view', 'inquiry.manage']);

    $this->post(route('inquiry.store'), ['unit_name' => 'kg'])->assertSessionHasErrors('product_id');
    $this->post(route('inquiry.store'), ['product_id' => 999999, 'unit_name' => 'kg'])->assertSessionHasErrors('product_id');
});

it('edits the product data and writes only the prices and stock that changed', function () {
    inqLogin(['inquiry.view', 'inquiry.manage', 'pricing.view']);
    $warehouse = inqWarehouse();
    $other = inqWarehouse('W-IQ2', 'Gudang Dua');

    $product = Product::create(['code' => 'P-IQ3', 'name' => 'Urea', 'unit_name' => 'kg']);
    PriceSetup::create(['product_id' => $product->id, 'price_category' => 'Retail', 'amount' => 5000, 'effective_date' => '2026-09-01']);
    app(StockService::class)->increase($product->id, $warehouse->id, 10, 'SEED-IQ3');
    app(StockService::class)->increase($product->id, $other->id, 4, 'SEED-IQ3B');

    $this->put(route('inquiry.update', $product), [
        'name' => 'Nama Lain',
        'code' => '',
        'unit_name' => 'sak',
        'prices' => ['purchase' => '', 'retail' => '5000', 'wholesale' => '4400'],
        'price_effective_date' => Carbon::today()->toDateString(),
        'stock' => [$warehouse->id => '7', $other->id => '4'],
    ])->assertSessionHasNoErrors();

    $product->refresh();
    expect($product->name)->toBe('Urea') // the name cannot be changed from Inquiry
        ->and($product->unit_name)->toBe('sak')
        ->and($product->code)->toBe('P-IQ3'); // a blank code keeps the existing one

    // Same retail price: no new row. Wholesale was new. Purchase left blank.
    expect(inqPrices($product))->toEqual(['Retail' => 5000.0, 'Grosir' => 4400.0]);
    expect(PriceHistory::where('product_id', $product->id)->count())->toBe(1);

    // Only the warehouse whose number changed gets a Deviation, for the difference.
    expect(app(StockService::class)->available($product->id, $warehouse->id))->toBe(7)
        ->and(app(StockService::class)->available($product->id, $other->id))->toBe(4);

    $deviations = InternalMutation::where('type', 'Deviation')->with('internalMutationDetails')->get();
    expect($deviations)->toHaveCount(1)
        ->and((int) $deviations->first()->internalMutationDetails->first()->qty)->toBe(-3);
});

it('corrects a price in place when it is changed on the same effective date', function () {
    inqLogin(['inquiry.view', 'inquiry.manage']);
    $product = Product::create(['code' => 'P-IQ4', 'name' => 'Urea', 'unit_name' => 'kg']);
    PriceSetup::create(['product_id' => $product->id, 'price_category' => 'Retail', 'amount' => 5000, 'effective_date' => '2026-09-01']);

    $this->put(route('inquiry.update', $product), [
        'unit_name' => 'kg',
        'prices' => ['retail' => '5500'],
        'price_effective_date' => '2026-09-01',
    ])->assertSessionHasNoErrors();

    expect(PriceSetup::where('product_id', $product->id)->count())->toBe(1)
        ->and(inqPrices($product))->toEqual(['Retail' => 5500.0]);

    $log = PriceHistory::where('product_id', $product->id)->firstOrFail();
    expect($log->action)->toBe('updated')
        ->and((float) $log->old_amount)->toBe(5000.0)
        ->and((float) $log->new_amount)->toBe(5500.0);
});

it('ignores a purchase price from a user who may not open pricing', function () {
    inqLogin(['inquiry.view', 'inquiry.manage']);
    $product = Product::create(['code' => 'P-IQ7', 'name' => 'Pupuk Baru', 'unit_name' => 'kg']);

    $this->post(route('inquiry.store'), [
        'product_id' => $product->id, 'unit_name' => 'kg',
        'prices' => ['purchase' => '4000', 'retail' => '5000'],
    ])->assertSessionHasNoErrors();

    expect(inqPrices($product))->toEqual(['Retail' => 5000.0]);
});

it('refuses a duplicate code, a missing unit and a negative stock', function () {
    inqLogin(['inquiry.view', 'inquiry.manage']);
    $warehouse = inqWarehouse();
    $first = Product::create(['code' => 'DUP-1', 'name' => 'Urea', 'unit_name' => 'kg']);
    $second = Product::create(['code' => 'DUP-2', 'name' => 'Lain', 'unit_name' => 'kg']);

    $this->put(route('inquiry.update', $second), ['code' => 'DUP-1', 'unit_name' => 'kg'])
        ->assertSessionHasErrors('code');

    $this->put(route('inquiry.update', $second), ['unit_name' => ''])
        ->assertSessionHasErrors('unit_name');

    $this->put(route('inquiry.update', $second), ['unit_name' => 'kg', 'stock' => [$warehouse->id => '-1']])
        ->assertSessionHasErrors('stock.'.$warehouse->id);

    // A product may keep its own code when it is edited.
    $this->put(route('inquiry.update', $first), ['code' => 'DUP-1', 'unit_name' => 'sak'])
        ->assertSessionHasNoErrors();

    expect($first->fresh()->unit_name)->toBe('sak');
});
