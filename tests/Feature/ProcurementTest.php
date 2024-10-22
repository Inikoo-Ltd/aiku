<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderTransactionQuantity;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToCheckedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToConfirmPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToCreatingPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToDispatchedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToManufacturedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToReceivedPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdateStateToSettledPurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\StockDelivery\StoreStockDelivery;
use App\Actions\Procurement\StockDelivery\UpdateStateToCheckedStockDelivery;
use App\Actions\Procurement\StockDelivery\UpdateStateToDispatchStockDelivery;
use App\Actions\Procurement\StockDelivery\UpdateStateToSettledStockDelivery;
use App\Actions\Procurement\StockDelivery\UpdateStockDeliveryStateToReceived;
use App\Actions\Procurement\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Procurement\StockDeliveryItem\StoreStockDeliveryItemBySelectedPurchaseOrderTransaction;
use App\Actions\Procurement\StockDeliveryItem\UpdateStateToCheckedStockDeliveryItem;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\Procurement\StockDelivery;
use App\Models\Procurement\StockDeliveryItem;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Validation\ValidationException;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();

    $this->stocks             = createStocks($this->group);
    $this->orgStocks          = createOrgStocks($this->organisation, $this->stocks);

    $agent = Agent::first();
    if (!$agent) {
        $modelData = Agent::factory()->definition();
        $agent     = StoreAgent::make()->action(
            group: $this->group,
            modelData: $modelData
        );
    }
    $this->agent = $agent;

});


test('create independent supplier', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );

    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($this->group->supplyChainStats->number_suppliers)->toBe(1)
        ->and($this->organisation->procurementStats->number_org_suppliers)->toBe(0);

    return $supplier;
});

test('attach supplier to organisation', function ($supplier) {
    $orgSupplier = StoreOrgSupplier::make()->action($this->organisation, $supplier);


    expect($orgSupplier)->toBeInstanceOf(OrgSupplier::class)
        ->and($this->organisation->procurementStats->number_org_suppliers)->toBe(1);

    return $orgSupplier;
})->depends('create independent supplier');

test('create purchase order while no available products', function ($orgSupplier) {
    expect(function () use ($orgSupplier) {
        StorePurchaseOrder::make()->action($this->organisation, $orgSupplier, PurchaseOrder::factory()->definition());
    })->toThrow(ValidationException::class);
})->depends('attach supplier to organisation');


test('create supplier product', function ($supplier) {

    $arrayData = [
        'code'    => 'ABC',
        'name'    => 'ABC Asset',
        'cost'    => 200,
        'stock_id' => $this->stocks[0]->id
    ];

    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $arrayData);

    expect($supplierProduct)->toBeInstanceOf(SupplierProduct::class)
        ->and($supplierProduct->supplier_id)->toBe($supplier->id)
        ->and($supplierProduct->code)->toBe($arrayData['code'])
        ->and($supplierProduct->name)->toBe($arrayData['name'])
        ->and($supplierProduct->cost)->toBeNumeric(200);
    $supplier->refresh();

    return $supplierProduct;
})->depends('create independent supplier');

test('attach supplier product to organisation', function (SupplierProduct $supplierProduct, OrgSupplier $orgSupplier) {

    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $orgSupplierProduct->refresh();
    expect($orgSupplierProduct)->toBeInstanceOf(OrgSupplierProduct::class)
        ->and($orgSupplierProduct->supplier_product_id)->toBe($supplierProduct->id)
        ->and($orgSupplierProduct->organisation_id)->toBe($this->organisation->id);

    return $orgSupplierProduct;
})->depends('create supplier product', 'attach supplier to organisation');


test('create purchase order independent supplier', function (OrgSupplierProduct $orgSupplierProduct) {
    $purchaseOrderData = PurchaseOrder::factory()->definition();

    $orgSupplier = $orgSupplierProduct->orgSupplier;

    $purchaseOrder = StorePurchaseOrder::make()->action($this->organisation, $orgSupplier, $purchaseOrderData);
    $supplier      = $orgSupplier->supplier;

    expect($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class)
        ->and($supplier->stats->number_purchase_orders)->toBe(1)
        ->and($purchaseOrder->parent_id)->toBe($orgSupplier->id)
        ->and($purchaseOrder->supplier_id)->toBe($supplier->id);


    return $purchaseOrder;
})->depends('attach supplier product to organisation');

test('add item to purchase order', function (PurchaseOrder $purchaseOrder, OrgSupplierProduct $orgSupplierProduct) {


    $purchaseOrderTransactionData = PurchaseOrderTransaction::factory()->definition();

    $purchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $purchaseOrderTransactionData
    );

    expect($purchaseOrderTransaction)->toBeInstanceOf(PurchaseOrderTransaction::class)
        ->and($purchaseOrderTransaction->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($purchaseOrderTransaction->supplier_product_id)->toBe($orgSupplierProduct->supplierProduct->id)
        ->and($purchaseOrder->purchaseOrderTransactions()->count())->toBe(1);

    return $purchaseOrder;
})->depends('create purchase order independent supplier', 'attach supplier product to organisation');


test('add more items to purchase order', function (PurchaseOrder $purchaseOrder) {


    /** @var OrgSupplier $orgSupplier */
    $orgSupplier = $purchaseOrder->parent;

    $supplierProduct = StoreSupplierProduct::make()->action($orgSupplier->supplier, [
        'code'    => 'product-2',
        'name'    => 'Product 2',
        'cost'    => 100,
        'stock_id' => $this->stocks[1]->id
    ]);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);


    $purchaseOrderTransaction2 = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, PurchaseOrderTransaction::factory()->definition());

    $supplierProduct = StoreSupplierProduct::make()->action($orgSupplier->supplier, [
        'code'    => 'product-3',
        'name'    => 'Product 3',
        'cost'    => 150,
        'stock_id' => $this->stocks[2]->id
    ]);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
    $purchaseOrderTransaction3 = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, PurchaseOrderTransaction::factory()->definition());


    expect($purchaseOrderTransaction2)->toBeInstanceOf(PurchaseOrderTransaction::class)
        ->and($purchaseOrderTransaction3)->toBeInstanceOf(PurchaseOrderTransaction::class)
        ->and($purchaseOrder->purchaseOrderTransactions()->count())->toBe(3);

    return $purchaseOrder;
})->depends('add item to purchase order');


test('delete purchase order', function () {
    $supplier        = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier     = StoreOrgSupplier::make()->action($this->organisation, $supplier);

    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[0]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $purchaseOrder = StorePurchaseOrder::make()->action($this->organisation, $orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->refresh();

    expect($supplier->stats->number_purchase_orders)->toBe(1)->and($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class);
    $purchaseOrderDeleted = false;
    try {
        $purchaseOrderDeleted = DeletePurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException $e) {
        dd($e);
    }
    $supplier->refresh();

    expect($purchaseOrderDeleted)->toBeTrue()->and($supplier->stats->number_purchase_orders)->toBe(0);
});

test('update quantity items to 0 in purchase order', function ($purchaseOrder) {
    $item = $purchaseOrder->purchaseOrderTransactions()->first();

    $item = UpdatePurchaseOrderTransactionQuantity::make()->action($item, [
        'quantity_ordered' => 0
    ]);

    $this->assertModelMissing($item);
    expect($purchaseOrder->purchaseOrderTransactions()->count())->toBe(2);
})->depends('add item to purchase order');

test('update quantity items in purchase order', function ($purchaseOrder) {
    $item = $purchaseOrder->purchaseOrderTransactions()->first();

    $item = UpdatePurchaseOrderTransactionQuantity::make()->action($item, [
        'quantity_ordered' => 12
    ]);
    expect($item)->toBeInstanceOf(PurchaseOrderTransaction::class)
        ->and($item->quantity_ordered)->toBe(12);
})->depends('add item to purchase order');


test('update purchase order', function ($purchaseOrder) {
    $dataToUpdate  = [
        'reference' => 'PO-12345bis',
    ];
    $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, $dataToUpdate);
    $this->assertModelExists($purchaseOrder);
})->depends('create purchase order independent supplier');

test('create purchase order by agent', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action($this->organisation, $this->agent, PurchaseOrder::factory()->definition());
    $this->assertModelExists($purchaseOrder);
})->todo();

test('change state to submit purchase order', function ($purchaseOrder) {
    $purchaseOrder->refresh();

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::SUBMITTED);

    return $purchaseOrder;
})->depends('add item to purchase order');

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
    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::SUBMITTED);
})->depends('create purchase order independent supplier');

test('change state to creating from submitted purchase order', function ($purchaseOrder) {
    $purchaseOrder = UpdateStateToCreatingPurchaseOrder::make()->action($purchaseOrder);
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CREATING);
})->depends('create purchase order independent supplier');

test('create supplier delivery', function (OrgSupplier $orgSupplier) {
    $arrayData = [
        'reference' => 12345,
        'date'      => date('Y-m-d')
    ];

    $stockDelivery = StoreStockDelivery::make()->action($this->organisation, $orgSupplier, $arrayData);
    $stockDelivery->refresh();
    expect($stockDelivery)->toBeInstanceOf(StockDelivery::class)
        ->and($stockDelivery->organisation_id)->toBe($this->organisation->id)
        ->and($stockDelivery->group_id)->toBe($this->organisation->group_id)
        ->and($stockDelivery->supplier_id)->toBe($orgSupplier->supplier_id)
        ->and($stockDelivery->agent_id)->toBeNull()
        ->and($stockDelivery->partner_id)->toBeNull()
        ->and($stockDelivery->parent_type)->toBe('OrgSupplier')
        ->and($stockDelivery->parent_id)->toBe($orgSupplier->id)
        ->and($stockDelivery->reference)->toBeNumeric($arrayData['reference']);

    return $stockDelivery;
})->depends('attach supplier to organisation');


test('create supplier delivery items', function (StockDelivery $stockDelivery) {
    $supplier = StoreStockDeliveryItem::run($stockDelivery, StockDeliveryItem::factory()->definition());

    expect($supplier->stock_delivery_id)->toBe($stockDelivery->id);

    return $supplier;
})->depends('create supplier delivery')->todo();

test('create supplier delivery items by selected purchase order', function (StockDelivery $stockDelivery, $items) {
    $supplier = StoreStockDeliveryItemBySelectedPurchaseOrderTransaction::run($stockDelivery, $items->pluck('id')->toArray());
    expect($supplier)->toBeArray();

    return $supplier;
})->depends('create supplier delivery', 'add item to purchase order')->todo();

test('change supplier delivery state to dispatch from creating', function (StockDelivery $stockDelivery) {
    expect($stockDelivery)->toBeInstanceOf(StockDelivery::class)
        ->and($stockDelivery->state)->toBe(StockDeliveryStateEnum::IN_PROCESS);
    $stockDelivery = UpdateStateToDispatchStockDelivery::make()->action($stockDelivery);
    expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::DISPATCHED);
})->depends('create supplier delivery');

test('change state to received from dispatch supplier delivery', function (StockDelivery $stockDelivery) {
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    expect($stockDelivery->state)->toEqual(StockDeliveryStateEnum::RECEIVED);
})->depends('create supplier delivery');

test('change state to checked from dispatch supplier delivery', function (StockDelivery $stockDelivery) {
    $stockDelivery = UpdateStateToCheckedStockDelivery::make()->action($stockDelivery);
    expect($stockDelivery->state)->toEqual(StockDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery');

test('change state to settled from checked supplier delivery', function (StockDelivery $stockDelivery) {
    $stockDelivery = UpdateStateToSettledStockDelivery::make()->action($stockDelivery);
    expect($stockDelivery->state)->toEqual(StockDeliveryStateEnum::SETTLED);
})->depends('create supplier delivery');

test('change state to checked from settled supplier delivery', function (StockDelivery $stockDelivery) {
    $stockDelivery = UpdateStateToCheckedStockDelivery::make()->action($stockDelivery);
    expect($stockDelivery->state)->toEqual(StockDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery');

test('change state to received from checked supplier delivery', function ($stockDelivery) {
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    expect($stockDelivery->state)->toEqual(StockDeliveryStateEnum::RECEIVED);
})->depends('create supplier delivery');

test('check supplier delivery items not correct', function ($stockDeliveryItem) {
    $stockDeliveryItem = UpdateStateToCheckedStockDeliveryItem::make()->action($stockDeliveryItem, [
        'quantity_ordered_checked' => 2
    ]);
    expect($stockDeliveryItem->stockDelivery->state)->toEqual(StockDeliveryStateEnum::RECEIVED);
})->depends('create supplier delivery items');

test('check supplier delivery items all correct', function ($stockDeliveryItems) {
    foreach ($stockDeliveryItems as $stockDeliveryItem) {
        UpdateStateToCheckedStockDeliveryItem::make()->action($stockDeliveryItem, [
            'quantity_ordered_checked' => 6
        ]);
    }
    expect($stockDeliveryItems[0]->stockDelivery->fresh()->state)->toEqual(StockDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery items by selected purchase order');
