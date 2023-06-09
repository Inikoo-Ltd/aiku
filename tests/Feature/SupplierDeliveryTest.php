<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Tests\Feature;

use App\Actions\Procurement\PurchaseOrder\AddItemPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\Supplier\StoreSupplier;
use App\Actions\Procurement\SupplierDelivery\StoreSupplierDelivery;
use App\Actions\Procurement\SupplierDelivery\UpdateStateToCheckedSupplierDelivery;
use App\Actions\Procurement\SupplierDelivery\UpdateStateToDispatchSupplierDelivery;
use App\Actions\Procurement\SupplierDelivery\UpdateStateToReceivedSupplierDelivery;
use App\Actions\Procurement\SupplierDelivery\UpdateStateToSettledSupplierDelivery;
use App\Actions\Procurement\SupplierDeliveryItem\StoreSupplierDeliveryItem;
use App\Actions\Procurement\SupplierDeliveryItem\StoreSupplierDeliveryItemBySelectedPurchaseOrderItem;
use App\Actions\Procurement\SupplierDeliveryItem\UpdateStateToCheckedSupplierDeliveryItem;
use App\Actions\Procurement\SupplierProduct\StoreSupplierProduct;
use App\Actions\Tenancy\Group\StoreGroup;
use App\Actions\Tenancy\Tenant\StoreTenant;
use App\Enums\Procurement\SupplierDelivery\SupplierDeliveryStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Models\Procurement\SupplierDelivery;
use App\Models\Procurement\SupplierDeliveryItem;
use App\Models\Tenancy\Group;
use App\Models\Tenancy\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

beforeAll(function () {
    loadDB('test_base_database.dump');
});


beforeEach(function () {
    $tenant = Tenant::first();
    if (!$tenant) {
        $group  = StoreGroup::make()->asAction(Group::factory()->definition());
        $tenant = StoreTenant::make()->action($group, Tenant::factory()->definition());
    }
    $tenant->makeCurrent();
});

test('create independent supplier', function () {
    $arrayData = [
        'code'          => 'SUPABC',
        'contact_name'  => 'artha',
        'company_name'  => 'artha inc',
        'currency_id'   => 1,
    ];

    $supplier = StoreSupplier::make()->action(app('currentTenant'), Arr::prepend($arrayData, 'supplier', 'type'));

    expect($supplier->code)->toBe($arrayData['code'])->and($supplier->currency_id)->toBeNumeric(1);

    return $supplier;
});

test('create purchase order while no products', function ($supplier) {
    expect(function () use ($supplier) {
        StorePurchaseOrder::make()->action($supplier, PurchaseOrder::factory()->definition());
    })->toThrow(ValidationException::class);
})->depends('create independent supplier');

test('create supplier product', function ($supplier) {
    $arrayData =[
        'code' => 'ABC',
        'name' => 'ABC Product',
        'cost' => 200,
    ];

    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $arrayData);

    expect($supplierProduct->code)->toBe($arrayData['code'])
        ->and($supplierProduct->name)->toBe($arrayData['name'])
        ->and($supplierProduct->cost)->toBeNumeric(200);

    return $supplierProduct;
})->depends('create independent supplier');

test('create purchase order', function ($supplier) {

    $purchaseOrder = StorePurchaseOrder::make()->action($supplier->fresh(), PurchaseOrder::factory()->definition());
    $this->assertModelExists($purchaseOrder);

    return $purchaseOrder;
})->depends('create independent supplier');

test('create supplier delivery', function ($supplier) {
    $supplierDelivery = StoreSupplierDelivery::make()->action($supplier, SupplierDelivery::factory()->definition());
    $this->assertModelExists($supplierDelivery);

    return $supplierDelivery->fresh();
})->depends('create independent supplier');

test('create supplier delivery items', function ($supplierDelivery) {
    $supplier = StoreSupplierDeliveryItem::run($supplierDelivery, SupplierDeliveryItem::factory()->definition());
    $this->assertModelExists($supplier);

    return $supplier;
})->depends('create supplier delivery');

test('add items to purchase order', function ($purchaseOrder) {
    $i = 1;
    do {
        $items = AddItemPurchaseOrder::make()->action($purchaseOrder, PurchaseOrderItem::factory()->definition());
        $i++;
    } while ($i <= 5);

    $this->assertModelExists($items);

    return $items;
})->depends('create purchase order');

test('create supplier delivery items by selected purchase order', function ($supplierDelivery, $items) {
    $supplier = StoreSupplierDeliveryItemBySelectedPurchaseOrderItem::run($supplierDelivery, $items->pluck('id')->toArray());
    expect($supplier)->toBeArray();

    return $supplier;
})->depends('create supplier delivery', 'add items to purchase order');

test('change state to dispatch from creating supplier delivery', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToDispatchSupplierDelivery::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(SupplierDeliveryStateEnum::DISPATCHED);
})->depends('create supplier delivery');

test('change state to received from dispatch supplier delivery', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToReceivedSupplierDelivery::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(SupplierDeliveryStateEnum::RECEIVED);
})->depends('create supplier delivery');

test('change state to checked from dispatch supplier delivery', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToCheckedSupplierDelivery::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(SupplierDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery');

test('change state to settled from checked supplier delivery', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToSettledSupplierDelivery::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(SupplierDeliveryStateEnum::SETTLED);
})->depends('create supplier delivery');

test('change state to checked from settled supplier delivery', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToCheckedSupplierDelivery::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(SupplierDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery');

test('change state to received from checked supplier delivery', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToReceivedSupplierDelivery::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(SupplierDeliveryStateEnum::RECEIVED);
})->depends('create supplier delivery');

test('check supplier delivery items not correct', function ($supplierDeliveryItem) {
    $supplierDeliveryItem = UpdateStateToCheckedSupplierDeliveryItem::make()->action($supplierDeliveryItem, [
        'unit_quantity_checked' => 2
    ]);
    expect($supplierDeliveryItem->supplierDelivery->state)->toEqual(SupplierDeliveryStateEnum::RECEIVED);
})->depends('create supplier delivery items');

test('check supplier delivery items all correct', function ($supplierDeliveryItems) {
    foreach ($supplierDeliveryItems as $supplierDeliveryItem) {
        UpdateStateToCheckedSupplierDeliveryItem::make()->action($supplierDeliveryItem, [
            'unit_quantity_checked' => 6
        ]);
    }
    expect($supplierDeliveryItems[0]->supplierDelivery->fresh()->state)->toEqual(SupplierDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery items by selected purchase order');
