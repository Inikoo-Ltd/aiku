<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\Procurement\PurchaseOrder\AddItemPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderItemQuantity;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToCheckedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToConfirmPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToCreatingPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToDispatchedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToManufacturedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToReceivedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToSettledPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToSubmittedPurchaseOrder;
use App\Actions\Procurement\Supplier\StoreSupplier;
use App\Actions\Procurement\SupplierProduct\StoreSupplierProduct;
use App\Actions\Procurement\SupplierProduct\SyncSupplierProductTradeUnits;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Validation\ValidationException;

beforeAll(function () {
    loadDB('test_base_database.dump');
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
});





test('create purchase order independent supplier', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action($this->organisation, $supplier, PurchaseOrder::factory()->definition());

    expect($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class)
        ->and($supplier->stats->number_purchase_orders)->toBe(1);

    return $purchaseOrder;
})->todo();

test('add items to purchase order', function ($purchaseOrder) {
    $i = 1;
    do {
        AddItemPurchaseOrder::make()->action($purchaseOrder, PurchaseOrderItem::factory()->definition());
        $i++;
    } while ($i <= 5);

    $purchaseOrder->load('items');
    expect($purchaseOrder->items()->count())->toBe(5);

    return $purchaseOrder;
})->depends('create purchase order independent supplier');


test('delete purchase order', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    StoreSupplierProduct::make()->action($supplier, SupplierProduct::factory()->definition());

    $purchaseOrder = StorePurchaseOrder::make()->action($this->organisation, $supplier, PurchaseOrder::factory()->definition());


    expect($supplier->stats->number_purchase_orders)->toBe(1);
    $purchaseOrderDeleted = false;
    try {
        $purchaseOrderDeleted = DeletePurchaseOrder::make()->action($purchaseOrder->fresh());
    } catch (ValidationException) {
    }
    $supplier->refresh();

    expect($purchaseOrderDeleted)->toBeTrue()->and($supplier->stats->number_purchase_orders)->toBe(0);
});

test('update quantity items to 0 in purchase order', function ($purchaseOrder) {
    $item = $purchaseOrder->items()->first();

    $item = UpdatePurchaseOrderItemQuantity::make()->action($item, [
        'unit_quantity' => 0
    ]);

    $this->assertModelMissing($item);
    expect($purchaseOrder->items()->count())->toBe(4);
})->depends('add items to purchase order');

test('update quantity items in purchase order', function ($purchaseOrder) {
    $item = $purchaseOrder->items()->first();

    $item = UpdatePurchaseOrderItemQuantity::make()->action($item, [
        'unit_quantity' => 12
    ]);
    expect($item)->toBeInstanceOf(PurchaseOrderItem::class)->and($item->unit_quantity)->toBe(12);
})->depends('add items to purchase order');

test('sync supplier product and trade units', function () {
    $syncSupplierProductTradeUnit = SyncSupplierProductTradeUnits::run($supplier, [1]);
    $this->assertModelExists($syncSupplierProductTradeUnit);
})->todo();

test('update purchase order', function ($agent) {
    $purchaseOrder = UpdatePurchaseOrder::make()->action($agent, PurchaseOrder::factory()->definition());
    $this->assertModelExists($purchaseOrder);
})->depends('create purchase order independent supplier');

test('create purchase order by agent', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action($this->organisation, $agent, PurchaseOrder::factory()->definition());
    $this->assertModelExists($purchaseOrder);
})->todo();

test('change state to submit purchase order', function ($purchaseOrder) {
    $purchaseOrder->refresh();
    try {
        $purchaseOrder = UpdateStateToSubmittedPurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException) {
    }
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::SUBMITTED);

    return $purchaseOrder;
})->depends('add items to purchase order');

test('change state to confirm purchase order', function ($purchaseOrder) {
    try {
        $purchaseOrder = UpdateStateToConfirmPurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException) {
    }
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED);

    return $purchaseOrder;
})->depends('change state to submit purchase order');

test('change state to manufactured purchase order', function ($purchaseOrder) {
    try {
        $purchaseOrder = UpdateStateToManufacturedPurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException) {
    }
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::MANUFACTURED);

    return $purchaseOrder;
})->depends('create purchase order independent supplier');

test('change state to dispatched from manufacture purchase order', function ($purchaseOrder) {
    try {
        $purchaseOrder = UpdateStateToDispatchedPurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException) {
    }
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::DISPATCHED);

    return $purchaseOrder;
})->depends('change state to confirm purchase order');

test('change state to received from dispatch purchase order', function ($purchaseOrder) {
    try {
        $purchaseOrder = UpdateStateToReceivedPurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException) {
    }
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::RECEIVED);

    return $purchaseOrder;
})->depends('change state to manufactured purchase order');

test('change state to checked from received purchase order', function ($purchaseOrder) {
    try {
        $purchaseOrder = UpdateStateToCheckedPurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException) {
    }
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CHECKED);

    return $purchaseOrder;
})->depends('change state to received from dispatch purchase order');

test('change state to settled from checked purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToSettledPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::SETTLED);

    return $purchaseOrder;
})->depends('change state to checked from received purchase order');

test('change state to checked from settled purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToCheckedPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CHECKED);
})->depends('create purchase order independent supplier');

test('change state to received from checked purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToReceivedPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::RECEIVED);
})->depends('create purchase order independent supplier');

test('change state to dispatched from received purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToDispatchedPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::DISPATCHED);
})->depends('create purchase order independent supplier');

test('change state to manufactured from dispatched purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToManufacturedPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::MANUFACTURED);
})->depends('create purchase order independent supplier');

test('change state to confirmed from manufactured purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToConfirmPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED);
})->depends('create purchase order independent supplier');

test('change state to submitted from confirmed purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToSubmittedPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::SUBMITTED);
})->depends('create purchase order independent supplier');

test('change state to creating from submitted purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToCreatingPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CREATING);
})->depends('create purchase order independent supplier');
