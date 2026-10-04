<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function makeProduct(array $attrs = []): Product
{
    return Product::create(array_merge([
        'code' => 'T-'.uniqid(),
        'name' => 'Barang Uji',
        'unit_name' => 'pcs',
    ], $attrs));
}

function productManager(): User
{
    $permission = Permission::firstOrCreate(['name' => 'inventory.products.manage', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'inventory.products.view', 'guard_name' => 'web']);

    $user = User::create([
        'username' => 'tester-'.uniqid(),
        'password' => bcrypt('secret-password'),
        'role' => 'Admin',
        'is_active' => true,
    ]);
    $user->givePermissionTo($permission);

    return $user;
}

// ---- Model helpers -------------------------------------------------------

it('formats a quantity as box, pack and satuan', function () {
    $p = makeProduct(['pack_name' => 'pack', 'pack_qty' => 10, 'box_name' => 'box', 'box_qty' => 100]);

    expect($p->formatQuantity(235))->toBe('2 box 3 pack 5 pcs');
    expect($p->formatQuantity(200))->toBe('2 box');
    expect($p->formatQuantity(30))->toBe('3 pack');
    expect($p->formatQuantity(7))->toBe('7 pcs');
    expect($p->formatQuantity(0))->toBe('0 pcs');
});

it('skips packagings the product does not define', function () {
    $noPack = makeProduct(['box_name' => 'dus', 'box_qty' => 12]);
    expect($noPack->formatQuantity(30))->toBe('2 dus 6 pcs');

    $plain = makeProduct();
    expect($plain->formatQuantity(42))->toBe('42 pcs');
});

it('keeps the sign for negative quantities', function () {
    $p = makeProduct(['pack_name' => 'pack', 'pack_qty' => 10]);
    expect($p->formatQuantity(-25))->toBe('-2 pack 5 pcs');
});

it('converts box, pack and satuan back to a total in satuan', function () {
    $p = makeProduct(['pack_name' => 'pack', 'pack_qty' => 10, 'box_name' => 'box', 'box_qty' => 100]);
    expect($p->toBaseQuantity(2, 3, 5))->toBe(235);
    expect($p->toBaseQuantity(0, 0, 9))->toBe(9);
});

// ---- Controller validation ----------------------------------------------

it('saves a product with pack and box', function () {
    $this->actingAs(productManager())
        ->post(route('inventory.products.store'), [
            'code' => 'P-1', 'name' => 'Pupuk', 'unit_name' => 'kg',
            'pack_name' => 'karung', 'pack_qty' => 5,
            'box_name' => 'palet', 'box_qty' => 50,
        ])->assertSessionHasNoErrors();

    $p = Product::where('code', 'P-1')->first();
    expect($p->unit_name)->toBe('kg')->and($p->pack_qty)->toBe(5)->and($p->box_qty)->toBe(50);
});

it('saves a product without pack and box', function () {
    $this->actingAs(productManager())
        ->post(route('inventory.products.store'), ['code' => 'P-2', 'name' => 'Cangkul', 'unit_name' => 'pcs'])
        ->assertSessionHasNoErrors();

    $p = Product::where('code', 'P-2')->first();
    expect($p->pack_name)->toBeNull()->and($p->box_qty)->toBeNull();
});

it('rejects decimal, too small, and missing conversions', function () {
    $user = productManager();
    $base = ['code' => 'P-3', 'name' => 'X', 'unit_name' => 'pcs'];

    $this->actingAs($user)->post(route('inventory.products.store'), $base + ['pack_name' => 'pack', 'pack_qty' => '2.5'])
        ->assertSessionHasErrors('pack_qty');
    $this->actingAs($user)->post(route('inventory.products.store'), $base + ['pack_name' => 'pack', 'pack_qty' => 1])
        ->assertSessionHasErrors('pack_qty');
    $this->actingAs($user)->post(route('inventory.products.store'), $base + ['pack_name' => 'pack'])
        ->assertSessionHasErrors('pack_qty');
    $this->actingAs($user)->post(route('inventory.products.store'), $base + ['pack_qty' => 10])
        ->assertSessionHasErrors('pack_name');

    expect(Product::where('code', 'P-3')->exists())->toBeFalse();
});

it('lets pack and box stand alone, but a box must hold more than a pack', function () {
    $user = productManager();
    $base = ['code' => 'P-4', 'name' => 'X', 'unit_name' => 'pcs', 'pack_name' => 'pack', 'pack_qty' => 12, 'box_name' => 'box'];

    $this->actingAs($user)->post(route('inventory.products.store'), $base + ['box_qty' => 12])
        ->assertSessionHasErrors('box_qty');
    // Not a multiple of the pack: allowed (independent conversions).
    $this->actingAs($user)->post(route('inventory.products.store'), $base + ['box_qty' => 100])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('inventory.products.store'), ['code' => 'P-5', 'name' => 'Y', 'unit_name' => 'pcs', 'box_name' => 'dus', 'box_qty' => 24])
        ->assertSessionHasNoErrors();
    expect(Product::where('code', 'P-5')->first()->pack_qty)->toBeNull();
});

it('clears pack and box when the fields are emptied on edit', function () {
    $p = makeProduct(['pack_name' => 'pack', 'pack_qty' => 10, 'box_name' => 'box', 'box_qty' => 100]);

    $this->actingAs(productManager())
        ->put(route('inventory.products.update', $p), [
            'code' => $p->code, 'name' => $p->name, 'unit_name' => 'pcs',
            'pack_name' => '', 'pack_qty' => '', 'box_name' => '', 'box_qty' => '',
        ])->assertSessionHasNoErrors();

    $p->refresh();
    expect($p->pack_name)->toBeNull()->and($p->pack_qty)->toBeNull()->and($p->box_name)->toBeNull()->and($p->box_qty)->toBeNull();
});
