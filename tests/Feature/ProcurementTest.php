<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Procurement\OrgSupplierProducts\UI\GetOrgSupplierProductShowcase;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\HousekeepAgentSupplierPurchaseOrders;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\StoreAgentSupplierPurchaseOrder;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\StoreAgentSupplierPurchaseOrdersFromPurchaseOrder;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UpdateAgentSupplierPurchaseOrder;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderDeliveryStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\DeleteStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\RepairStockDeliveryCostings;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateToReceived;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsChecked;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsPlaced;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItemBySelectedPurchaseOrderTransaction;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStateToCheckedStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStateToConfirmedStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpsertStockDeliveryItemPlaced;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\RevertPurchaseOrderToSubmitted;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToCancelled;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToInProcess;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderTransactionQuantity;
use App\Actions\Procurement\PurchaseOrderTransaction\CancelPurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Actions\Procurement\ShoppingListItem\CherryPickShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\DeleteShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\ProposeDismissShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\ResolveDismissShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\UpdateShoppingListItem;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\ShoppingListItem;
use App\Actions\SupplyChain\Agent\HydrateAgents;
use App\Actions\SupplyChain\Agent\Search\ReindexAgentSearch;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\HydrateSuppliers;
use App\Actions\SupplyChain\Supplier\Search\ReindexSupplierSearch;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\UI\Grp\Layout\GetOrganisationNavigation;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\Stock;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation      = createOrganisation();
    $this->otherOrganisation = createOrganisation();
    $this->group             = group();
    $this->adminGuest        = createAdminGuest($this->organisation->group);


    $this->stocks    = createStocks($this->group);
    $this->orgStocks = createOrgStocks($this->organisation, $this->stocks);

    $agent = Agent::first();
    if (!$agent) {
        $modelData = Agent::factory()->definition();
        $agent     = StoreAgent::make()->action(
            group: $this->group,
            modelData: $modelData
        );
    }
    $this->agent = $agent;

    $orgAgent = OrgAgent::first();
    if (!$orgAgent) {
        $orgAgent = StoreOrgAgent::make()->action(
            $this->organisation,
            $this->agent,
            []
        );
    }

    $this->orgAgent = $orgAgent;


    $supplier = Supplier::where('agent_id', $this->agent->id)->first();
    if (!$supplier) {
        $storeData = Supplier::factory()->definition();
        $supplier  = StoreSupplier::make()->action(
            $this->agent,
            $storeData
        );
    }

    $this->supplier = $supplier;

    $stock = Stock::first();
    if (!$stock) {
        $storeData = Stock::factory()->definition();
        $stock     = StoreStock::make()->action(
            $this->organisation->group,
            $storeData
        );
    }

    $this->stock = $stock;

    $supplierProduct = SupplierProduct::first();
    if (!$supplierProduct) {
        $storeData = SupplierProduct::factory()->definition();
        data_set($storeData, 'stock_id', $this->stock->id);
        $supplierProduct = StoreSupplierProduct::make()->action(
            $this->supplier,
            $storeData
        );
    }

    $this->supplierProduct = $supplierProduct;

    $orgSupplier = OrgSupplier::first();
    if (!$orgSupplier) {
        $orgSupplier = StoreOrgSupplier::make()->action(
            $this->organisation,
            $this->supplier,
        );
    }

    $this->orgSupplier = $orgSupplier;

    $orgSupplierProduct = OrgSupplierProduct::first();
    if (!$orgSupplierProduct) {
        $orgSupplierProduct = StoreOrgSupplierProduct::make()->action(
            $this->orgSupplier,
            $this->supplierProduct
        );
    }

    $this->orgSupplierProduct = $orgSupplierProduct;

    $orgPartner = OrgPartner::first();
    if (!$orgPartner) {
        $orgPartner = StoreOrgPartner::make()->action(
            $this->organisation,
            $this->otherOrganisation,
        );
    }

    $this->orgPartner = $orgPartner;

    $stockDelivery = StockDelivery::first();
    if (!$stockDelivery) {
        $stockDelivery = StoreStockDelivery::make()->action(
            $this->orgSupplier,
            [
                'reference' => 12345,
                'date'      => date('Y-m-d')
            ]
        );
    }

    $this->stockDelivery = $stockDelivery;

    $purchaseOrder = PurchaseOrder::first();
    if (!$purchaseOrder) {
        $purchaseOrder = StorePurchaseOrder::make()->action(
            $this->orgSupplier,
            PurchaseOrder::factory()->definition()
        );
    }

    $this->purchaseOrder = $purchaseOrder;

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});


test('create independent supplier', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );

    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($this->group->supplyChainStats->number_suppliers)->toBe(2)
        ->and($this->organisation->procurementStats->number_org_suppliers)->toBe(2);

    return $supplier;
});

test('a new supplier is propagated to the organisation', function ($supplier) {
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();


    expect($orgSupplier)->toBeInstanceOf(OrgSupplier::class)
        ->and($this->organisation->procurementStats->number_org_suppliers)->toBe(2);

    return $orgSupplier;
})->depends('create independent supplier');

test('create purchase order while no available products', function ($orgSupplier) {
    expect(function () use ($orgSupplier) {
        StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    })->toThrow(ValidationException::class);
})->depends('a new supplier is propagated to the organisation');


test('create supplier product', function ($supplier) {
    $arrayData = [
        'code'             => 'ABC',
        'name'             => 'ABC Asset',
        'cost'             => 200,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100

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
})->depends('create supplier product', 'a new supplier is propagated to the organisation');


test('create purchase order independent supplier', function (OrgSupplierProduct $orgSupplierProduct) {
    $purchaseOrderData = PurchaseOrder::factory()->definition();

    $orgSupplier = $orgSupplierProduct->orgSupplier;

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, $purchaseOrderData);
    $supplier      = $orgSupplier->supplier;

    expect($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class)
        ->and($supplier->stats->number_purchase_orders)->toBe(1)
        ->and($purchaseOrder->parent_id)->toBe($orgSupplier->id)
        ->and($purchaseOrder->supplier_id)->toBe($supplier->id);


    return $purchaseOrder;
})->depends('attach supplier product to organisation');

test('create agent supplier purchase order', function (PurchaseOrder $purchaseOrder) {
    $supplier = $this->supplier;

    $agentSupplierPurchaseOrder = StoreAgentSupplierPurchaseOrder::make()->action(
        $purchaseOrder,
        $supplier,
        []
    );

    expect($agentSupplierPurchaseOrder)->toBeInstanceOf(AgentSupplierPurchaseOrder::class)
        ->and($agentSupplierPurchaseOrder->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($agentSupplierPurchaseOrder->supplier_id)->toBe($supplier->id)
        ->and($agentSupplierPurchaseOrder->group_id)->toBe($supplier->group_id)
        ->and($agentSupplierPurchaseOrder->reference)->toBe($supplier->code.'-'.$purchaseOrder->reference)
        ->and($agentSupplierPurchaseOrder->currency_id)->toBe($supplier->currency_id)
        ->and($this->agent->stats->refresh()->number_agent_supplier_purchase_orders)->toBeGreaterThanOrEqual(1);

    if ($purchaseOrder->parent instanceof OrgAgent) {
        expect($purchaseOrder->parent->stats->refresh()->number_agent_supplier_purchase_orders)->toBeGreaterThanOrEqual(1);
    }

    return $agentSupplierPurchaseOrder;
})->depends('create purchase order independent supplier');

test('update agent supplier purchase order', function (AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder) {
    $updated = UpdateAgentSupplierPurchaseOrder::make()->action(
        $agentSupplierPurchaseOrder,
        [
            'state'          => AgentSupplierPurchaseOrderStateEnum::CONFIRMED,
            'delivery_state' => AgentSupplierPurchaseOrderDeliveryStateEnum::CONFIRMED,
            'cost_total'     => 1234.56,
            'deposit_amount' => 100.50,
            'deposit_paid_at' => now()->subDay(),
            'estimated_delivery_days' => 14,
        ]
    );

    expect($updated->state)->toBe(AgentSupplierPurchaseOrderStateEnum::CONFIRMED)
        ->and($updated->delivery_state)->toBe(AgentSupplierPurchaseOrderDeliveryStateEnum::CONFIRMED)
        ->and((float)$updated->cost_total)->toBe(1234.56)
        ->and((float)$updated->deposit_amount)->toBe(100.50)
        ->and($updated->deposit_paid_at)->not->toBeNull()
        ->and($updated->estimated_delivery_days)->toBe(14);
})->depends('create agent supplier purchase order');

test('add item to purchase order', function (PurchaseOrder $purchaseOrder, OrgSupplierProduct $orgSupplierProduct) {
    $orgStock                     = $this->orgStocks[0];
    $purchaseOrderTransactionData = PurchaseOrderTransaction::factory()->definition();

    $purchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $orgStock,
        $purchaseOrderTransactionData
    );

    expect($purchaseOrderTransaction)->toBeInstanceOf(PurchaseOrderTransaction::class)
        ->and($purchaseOrderTransaction->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($purchaseOrderTransaction->supplier_product_id)->toBe($orgSupplierProduct->supplierProduct->id)
        ->and($purchaseOrder->purchaseOrderTransactions()->count())->toBe(1);

    return $purchaseOrder;
})->depends('create purchase order independent supplier', 'attach supplier product to organisation');

test('link purchase order transaction to agent supplier purchase order', function (AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, PurchaseOrder $purchaseOrder) {
    $purchaseOrderTransaction = $purchaseOrder->purchaseOrderTransactions()->first();
    expect($purchaseOrderTransaction)->not->toBeNull();

    UpdatePurchaseOrderTransaction::make()->action(
        $purchaseOrderTransaction,
        ['agent_supplier_purchase_order_id' => $agentSupplierPurchaseOrder->id],
        strict: false
    );

    expect($agentSupplierPurchaseOrder->purchaseOrderTransactions()->count())->toBe(1)
        ->and($purchaseOrderTransaction->refresh()->agentSupplierPurchaseOrder->id)->toBe($agentSupplierPurchaseOrder->id);
})->depends('create agent supplier purchase order', 'add item to purchase order');

test('housekeeping flags legacy stalled agent supplier purchase orders', function (AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder) {
    $agentSupplierPurchaseOrder->update(['date' => now()->subYears(2)]);

    $flagged = HousekeepAgentSupplierPurchaseOrders::run();
    expect($flagged)->toBeGreaterThanOrEqual(1)
        ->and(data_get($agentSupplierPurchaseOrder->fresh()->data, 'housekeeping.reason'))->toBe('legacy stalled order, pre-aiku');

    $unflagged = HousekeepAgentSupplierPurchaseOrders::run(365, true);
    expect($unflagged)->toBeGreaterThanOrEqual(1)
        ->and(data_get($agentSupplierPurchaseOrder->fresh()->data, 'housekeeping'))->toBeNull();
})->depends('create agent supplier purchase order');

test('UI index agent supplier purchase orders in organisation', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.agent_supplier_purchase_orders.index', [$this->organisation->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrders')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI index agent supplier purchase orders in org agent', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_agents.show.agent_supplier_purchase_orders.index', [$this->organisation->slug, $this->orgAgent->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrders')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data')
            ->has('pageHead.subNavigation');
    });
});

test('UI index agent supplier purchase orders', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agent_supplier_purchase_orders.index'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrders')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI show agent supplier purchase order', function (AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agent_supplier_purchase_orders.show', [$agentSupplierPurchaseOrder->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($agentSupplierPurchaseOrder) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrder')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $agentSupplierPurchaseOrder->reference)
                    ->etc()
            )
            ->has('showcase.supplier')
            ->where('showcase.number_transactions', 1);
    });
})->depends('create agent supplier purchase order');

test('UI edit agent supplier purchase order', function (AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agent_supplier_purchase_orders.edit', [$agentSupplierPurchaseOrder->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($agentSupplierPurchaseOrder) {
        $page
            ->component('EditModel')
            ->has('formData.args.updateRoute')
            ->where('formData.args.updateRoute.name', 'grp.models.agent_supplier_purchase_order.update')
            ->where('formData.args.updateRoute.parameters', $agentSupplierPurchaseOrder->id);
    });
})->depends('create agent supplier purchase order');


test('add more items to purchase order', function (PurchaseOrder $purchaseOrder) {
    /** @var OrgSupplier $orgSupplier */
    $orgSupplier = $purchaseOrder->parent;

    $supplierProduct    = StoreSupplierProduct::make()->action($orgSupplier->supplier, [
        'code'             => 'product-2',
        'name'             => 'Product 2',
        'cost'             => 100,
        'stock_id'         => $this->stocks[1]->id,
        'units_per_pack'   => 50,
        'units_per_carton' => 200
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);


    $purchaseOrderTransaction2 = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[1], PurchaseOrderTransaction::factory()->definition());

    $supplierProduct           = StoreSupplierProduct::make()->action($orgSupplier->supplier, [
        'code'             => 'product-3',
        'name'             => 'Product 3',
        'cost'             => 150,
        'stock_id'         => $this->stocks[2]->id,
        'units_per_pack'   => 5,
        'units_per_carton' => 50
    ]);
    $orgSupplierProduct2       = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
    $purchaseOrderTransaction3 = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct2->supplierProduct->historicSupplierProduct, $this->orgStocks[2], PurchaseOrderTransaction::factory()->definition());

    $purchaseOrderTransaction3 = UpdatePurchaseOrderTransaction::make()->action($purchaseOrderTransaction3, [
        'quantity_ordered' => 65
    ]);
    $purchaseOrderTransaction3->refresh();
    expect($purchaseOrderTransaction2)->toBeInstanceOf(PurchaseOrderTransaction::class)
        ->and($purchaseOrderTransaction3)->toBeInstanceOf(PurchaseOrderTransaction::class)
        ->and(intval($purchaseOrderTransaction3->quantity_ordered))->toBe(65)
        ->and($purchaseOrder->purchaseOrderTransactions()->count())->toBe(3);

    return $purchaseOrder;
})->depends('add item to purchase order');


test('delete purchase order', function () {
    $supplier    = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProductData = [
        'code'             => 'ABC',
        'name'             => 'ABC Asset',
        'cost'             => 200,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ];
    $supplierProduct     = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    $purchaseOrder->refresh();

    expect($supplier->stats->number_purchase_orders)->toBe(1)->and($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class);
    $purchaseOrderDeleted = false;
    try {
        $purchaseOrderDeleted = DeletePurchaseOrder::make()->action($purchaseOrder);
    } catch (ValidationException) {
        // do nothing
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
    $purchaseOrder = StorePurchaseOrder::make()->action($this->orgAgent, PurchaseOrder::factory()->definition());
    $this->assertModelExists($purchaseOrder);

    return $purchaseOrder;
});

test('submit agent purchase order consolidates into agent supplier purchase orders', function (PurchaseOrder $purchaseOrder, OrgSupplierProduct $orgSupplierProduct) {
    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS);

    UpdateSupplierProduct::make()->action(
        $orgSupplierProduct->supplierProduct,
        ['delivery_time' => 21],
        strict: false
    );

    $purchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        PurchaseOrderTransaction::factory()->definition()
    );

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    expect($purchaseOrder->estimated_delivery_days)->toBe(21)
        ->and($purchaseOrder->estimated_received_at)->not->toBeNull();

    $supplier = $orgSupplierProduct->supplierProduct->supplier;
    $agentSupplierPurchaseOrder = AgentSupplierPurchaseOrder::where('purchase_order_id', $purchaseOrder->id)
        ->where('supplier_id', $supplier->id)
        ->first();

    expect($agentSupplierPurchaseOrder)->not->toBeNull()
        ->and($agentSupplierPurchaseOrder->state)->toBe(AgentSupplierPurchaseOrderStateEnum::SUBMITTED)
        ->and($purchaseOrderTransaction->refresh()->agent_supplier_purchase_order_id)->toBe($agentSupplierPurchaseOrder->id)
        ->and((float)$agentSupplierPurchaseOrder->cost_total)->toBe((float)$purchaseOrderTransaction->net_amount)
        ->and($agentSupplierPurchaseOrder->estimated_delivery_days)->toBe(21)
        ->and($agentSupplierPurchaseOrder->estimated_received_at)->not->toBeNull();

    StoreAgentSupplierPurchaseOrdersFromPurchaseOrder::make()->action($purchaseOrder->refresh());
    expect(AgentSupplierPurchaseOrder::where('purchase_order_id', $purchaseOrder->id)->count())->toBe(1)
        ->and($agentSupplierPurchaseOrder->refresh()->currency_id)->toBe($purchaseOrder->currency_id);

    $submittedAt = $agentSupplierPurchaseOrder->submitted_at;
    UpdateAgentSupplierPurchaseOrder::make()->action($agentSupplierPurchaseOrder, ['state' => AgentSupplierPurchaseOrderStateEnum::CONFIRMED], strict: false);
    StoreAgentSupplierPurchaseOrdersFromPurchaseOrder::make()->action($purchaseOrder->refresh());
    expect($agentSupplierPurchaseOrder->refresh()->state)->toBe(AgentSupplierPurchaseOrderStateEnum::CONFIRMED)
        ->and($agentSupplierPurchaseOrder->submitted_at?->toIso8601String())->toBe($submittedAt?->toIso8601String());

    CancelPurchaseOrderTransaction::make()->action($purchaseOrderTransaction->refresh());
    expect((float)$agentSupplierPurchaseOrder->refresh()->cost_total)->toBe(0.0);
})->depends('create purchase order by agent', 'attach supplier product to organisation');

test('change state to submitted purchase order', function ($purchaseOrder) {
    $purchaseOrder->refresh();

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::SUBMITTED);

    return $purchaseOrder;
})->depends('add item to purchase order');

test('change state to creating purchase order', function ($purchaseOrder) {
    $purchaseOrder->refresh();

    $purchaseOrder = UpdatePurchaseOrderStateToInProcess::make()->action($purchaseOrder);

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::IN_PROCESS);

    return $purchaseOrder;
})->depends('add item to purchase order');


test('change purchase order state to confirmed', function ($purchaseOrder) {
    $purchaseOrder->refresh();

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    $purchaseOrder->refresh();

    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder);

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED);

    return $purchaseOrder;
})->depends('add item to purchase order');

test('revert purchase order state to submitted', function ($purchaseOrder) {
    $purchaseOrder->refresh();

    $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, [
        'estimated_production_date' => '2026-07-27',
        'estimated_receiving_date'  => '2026-08-31',
    ]);

    $purchaseOrder = RevertPurchaseOrderToSubmitted::make()->action($purchaseOrder->refresh());

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::SUBMITTED)
        ->and($purchaseOrder->confirmed_at)->toBeNull()
        ->and(Arr::get($purchaseOrder->data, 'estimated_production_date'))->toBeNull()
        ->and(Arr::get($purchaseOrder->data, 'estimated_receiving_date'))->toBeNull();

    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED);

    return $purchaseOrder;
})->depends('change purchase order state to confirmed');

test('change purchase order state to cancelled', function ($purchaseOrder) {
    $purchaseOrder->refresh();
    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED]);

    $purchaseOrder = UpdatePurchaseOrderStateToCancelled::make()->action($purchaseOrder);

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CANCELLED);

    return $purchaseOrder;
})->depends('revert purchase order state to submitted');


test('create supplier delivery', function (OrgSupplier $orgSupplier) {
    $arrayData = [
        'reference' => 123457,
        'date'      => date('Y-m-d')
    ];

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, $arrayData);
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
})->depends('a new supplier is propagated to the organisation');

test('update supplier delivery', function (StockDelivery $stockDelivery) {
    $stockDelivery = UpdateStockDelivery::make()->action($stockDelivery, [
        'reference' => 'SP-01'
    ]);
    $stockDelivery->refresh();
    expect($stockDelivery)->toBeInstanceOf(StockDelivery::class)
        ->and($stockDelivery->reference)->toBe('SP-01');

    return $stockDelivery;
})->depends('create supplier delivery');


test('create supplier delivery items', function (StockDelivery $stockDelivery) {
    $supplier            = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier         = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProductData = [
        'code'             => 'ABC',
        'name'             => 'ABC Asset',
        'cost'             => 200,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ];
    $supplierProduct     = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $orgSupplierProduct  = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
    $orgStock            = $this->orgStocks[0];
    // dd($orgStock);
    $stockDeliveryItem = StoreStockDeliveryItem::make()->action($stockDelivery, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $orgStock, StockDeliveryItem::factory()->definition());

    expect($stockDeliveryItem->stock_delivery_id)->toBe($stockDelivery->id);
    $stockDelivery->refresh();

    return $stockDelivery;
})->depends('create supplier delivery');

test('update supplier delivery items', function (StockDelivery $stockDelivery) {
    /** @var StockDeliveryItem $stockDeliveryItem */
    $stockDeliveryItem = $stockDelivery->items()->first();
    $stockDeliveryItem = UpdateStockDeliveryItem::make()->action($stockDeliveryItem, [
        'unit_quantity' => 100
    ]);

    expect(intval($stockDeliveryItem->unit_quantity))->toBe(100);
    $stockDeliveryItem->refresh();

    return $stockDeliveryItem;
})->depends('create supplier delivery');

test('update org supplier product', function () {
    $supplier            = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier         = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProductData = [
        'code'             => 'ABC',
        'name'             => 'ABC Asset',
        'cost'             => 200,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ];
    $supplierProduct     = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $orgSupplierProduct  = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $orgSupplierProduct = UpdateOrgSupplierProduct::make()->action($orgSupplierProduct, [
        'is_available' => false
    ]);

    expect($orgSupplierProduct->is_available)->toBeFalse();
});

test('create supplier delivery items by selected purchase order', function (StockDelivery $stockDelivery, $items) {
    $supplier = StoreStockDeliveryItemBySelectedPurchaseOrderTransaction::run($stockDelivery, $items->pluck('id')->toArray());
    expect($supplier)->toBeArray();

    return $supplier;
})->depends('create supplier delivery items', 'add item to purchase order');

test('change supplier delivery state to dispatch from creating', function (StockDelivery $stockDelivery) {
    expect($stockDelivery)->toBeInstanceOf(StockDelivery::class)
        ->and($stockDelivery->state)->toBe(StockDeliveryStateEnum::IN_PROCESS);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::DISPATCHED);
})->depends('create supplier delivery');

test('change state to received from dispatch supplier delivery', function (StockDelivery $stockDelivery) {
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    expect($stockDelivery->state)->toEqual(StockDeliveryStateEnum::RECEIVED);
})->depends('create supplier delivery');

test('change state to received from checked supplier delivery', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'CHECKED-TO-RECEIVED', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), [
        'unit_quantity_checked' => 10
    ]);

    expect($stockDelivery->fresh()->state)->toEqual(StockDeliveryStateEnum::CHECKED);

    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery->fresh());

    expect($stockDelivery->state)->toEqual(StockDeliveryStateEnum::RECEIVED)
        ->and($stockDelivery->checked_at)->toBeNull();
});

test('check supplier delivery items not correct', function (StockDelivery $stockDelivery) {
    /** @var StockDeliveryItem $stockDeliveryItem */
    $stockDeliveryItem = $stockDelivery->items()->first();
    $stockDeliveryItem = UpdateStateToCheckedStockDeliveryItem::make()->action($stockDeliveryItem, [
        'unit_quantity_checked' => 2
    ]);

    expect($stockDeliveryItem->state)->toEqual(StockDeliveryItemStateEnum::CHECKED)
        ->and($stockDeliveryItem->stockDelivery->fresh()->state)->toEqual(StockDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery items');

test('check supplier delivery items all correct', function ($stockDeliveryItems) {
    foreach ($stockDeliveryItems as $stockDeliveryItem) {
        UpdateStateToCheckedStockDeliveryItem::make()->action($stockDeliveryItem, [
            'unit_quantity_checked' => 6
        ]);
    }
    expect($stockDeliveryItems[0]->stockDelivery->fresh()->state)->toEqual(StockDeliveryStateEnum::CHECKED);
})->depends('create supplier delivery items by selected purchase order');

test('create stock delivery from purchase order', function () {
    $supplier    = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'PO-SD',
        'name'             => 'Purchase order to stock delivery',
        'cost'             => 150,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        PurchaseOrderTransaction::factory()->definition()
    );

    $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder->refresh(), [
        'delivery_type'             => 'container',
        'incoterm'                  => 'FOB',
        'estimated_production_date' => '2026-07-27',
        'estimated_receiving_date'  => '2026-08-31',
    ]);

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

    $stockDelivery = StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh());

    expect($stockDelivery)->toBeInstanceOf(StockDelivery::class)
        ->and($stockDelivery->state)->toEqual(StockDeliveryStateEnum::IN_PROCESS)
        ->and($stockDelivery->parent_id)->toBe($orgSupplier->id)
        ->and($stockDelivery->number_purchase_orders)->toBe(1)
        ->and($stockDelivery->items()->count())->toBe(1)
        ->and($stockDelivery->items()->first()->state)->toEqual(StockDeliveryItemStateEnum::IN_PROCESS)
        ->and(Arr::get($stockDelivery->data, 'delivery_type'))->toBe('container')
        ->and(Arr::get($stockDelivery->data, 'incoterm'))->toBe('FOB')
        ->and(Arr::get($stockDelivery->data, 'estimated_dispatched_date'))->toBe('2026-07-27')
        ->and(Arr::get($stockDelivery->data, 'estimated_receiving_date'))->toBe('2026-08-31');

    return $stockDelivery;
});


test('hydrate agents', function () {
    $agent = Agent::first();
    HydrateAgents::run($agent);
    $this->artisan('hydrate:agents')->assertExitCode(0);
});

test('hydrate suppliers', function () {
    $supplier = Supplier::first();
    HydrateSuppliers::run($supplier);
    $this->artisan('hydrate:suppliers')->assertExitCode(0);
});

test('agents record search', function () {
    ReindexAgentSearch::run();
    $this->artisan('reindex_search:agents')->assertExitCode(0);
});

test('suppliers record search', function () {

    ReindexSupplierSearch::run();
    $this->artisan('reindex_search:suppliers')->assertExitCode(0);
});

test('UI show procurement dashboard', function () {
    // dd($this->orgSupplier);
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.procurement.dashboard', [$this->organisation->slug,]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/ProcurementDashboard')
            ->has('title')
            ->has('breadcrumbs', 2)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Procurement')
                    ->etc()
            );
    });
});

test('UI Index org suppliers', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_suppliers.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgSuppliers')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
});

test('procurement navigation positions the shipping list and separates agent suppliers', function () {
    $navigation = GetOrganisationNavigation::run($this->adminGuest->getUser(), $this->organisation);

    expect(data_get($navigation, 'procurement.topMenu.subSections.2'))
        ->toMatchArray([
            'label' => "Agent's Shipping List",
            'route' => [
                'name'       => 'grp.org.procurement.shopping_list.index',
                'parameters' => [$this->organisation->slug],
            ],
        ])
        ->and(data_get($navigation, 'procurement.topMenu.subSections.3.route'))->toBe([
            'name'       => 'grp.org.procurement.org_agent_suppliers.index',
            'parameters' => [$this->organisation->slug],
        ])
        ->and(data_get($navigation, 'procurement.topMenu.subSections.4.route'))->toBe([
            'name'       => 'grp.org.procurement.org_suppliers.index',
            'parameters' => [
                'organisation' => $this->organisation->slug,
                '_query'       => [
                    'sort' => 'code',
                ],
            ],
        ]);
});

test('UI show org supplier', function () {
    // dd($this->orgSupplier);
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.procurement.org_suppliers.show', [$this->organisation->slug, $this->orgSupplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgSupplier')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->orgSupplier->supplier->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI Index org agents', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgAgents')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
});

test('UI show org agents', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $this->orgAgent->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgAgent')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->orgAgent->agent->organisation->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI index org supplier products', function () {
    $response = $this->get(route('grp.org.procurement.org_supplier_products.index', [$this->organisation->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgSupplierProducts')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Supplier Products')
                    ->etc()
            );
    });
});

test('UI show org supplier product', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_supplier_products.show', [$this->organisation->slug, $this->orgSupplierProduct->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgSupplierProduct')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->orgSupplierProduct->supplierProduct->name)
                    ->etc()
            )
            ->has('tabs');
    });

    $showcase = GetOrgSupplierProductShowcase::run($this->orgSupplierProduct);
    expect($showcase['composition'])->toBeArray();
});

test('UI edit org supplier product', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_supplier_products.edit', [$this->organisation->slug, $this->orgSupplierProduct->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->orgSupplierProduct->supplierProduct->code)
                    ->etc()
            )
            ->has('formData.args.updateRoute');
    });
});

test('UI Index purchase orders', function () {
    $response = $this->get(route('grp.org.procurement.purchase_orders.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/PurchaseOrders')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
});

test('UI index purchase orders for supplier shows supplier-specific columns and row details', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action(
        $this->orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'Supplier table test PO']),
        strict: false,
    );
    $this->orgSupplier->supplier->stats()->update([
        'number_purchase_orders_state_in_process'   => 11,
        'number_purchase_orders_state_submitted'    => 12,
        'number_purchase_orders_state_confirmed'    => 13,
        'number_purchase_orders_state_settled'      => 14,
        'number_purchase_orders_state_cancelled'    => 15,
        'number_purchase_orders_state_not_received' => 16,
    ]);

    $this->get(route('grp.org.procurement.org_suppliers.show.purchase_orders.index', [
        $this->organisation->slug,
        $this->orgSupplier->slug,
    ]))->assertInertia(function (AssertableInertia $page) use ($purchaseOrder) {
        $page
            ->component('Procurement/PurchaseOrders')
            ->where('queryBuilderProps.default.columns', function ($columns) {
                $columnsByKey = $columns->keyBy('key');

                return !$columnsByKey->has('parent_name')
                    && $columnsByKey->has('number_current_purchase_order_transactions')
                    && $columnsByKey->get('date')['align'] === 'right';
            })
            ->where('queryBuilderProps.default.elementGroups.state.elements', [
                'in_process'   => [__('In process'), 11],
                'submitted'    => [__('Submitted'), 12],
                'confirmed'    => [__('Confirmed'), 13],
                'settled'      => [__('Settled'), 14],
                'cancelled'    => [__('Cancelled'), 15],
                'not_received' => [__('Not Received'), 16],
            ])
            ->where('data.data', function ($rows) use ($purchaseOrder) {
                $row = $rows->firstWhere('slug', $purchaseOrder->slug);

                return $row
                    && $row['state_label'] === PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value]
                    && $row['number_current_purchase_order_transactions'] === $purchaseOrder->number_current_purchase_order_transactions;
            })
            ->etc();
    });
});

test('UI show purchase order', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $this->purchaseOrder->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/PurchaseOrder')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Purchase Order')
                    ->where('afterTitle.label', $this->purchaseOrder->reference)
                    ->etc()
            )
            ->has('tabs')
            ->has('box_stats')
            ->has('timelines')
            ->has('data');
    });
});

test('UI Index org partners', function () {
    $response = $this->get(route('grp.org.procurement.org_partners.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Procurement/Partners')
            ->has('title')
            ->has('breadcrumbs', 3);
    });
});

test('UI show org partners', function () {
    $response = $this->get(route('grp.org.procurement.org_partners.show', [$this->organisation->slug, $this->orgPartner->id]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Procurement/Partner')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->orgPartner->partner->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI get section route index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.procurement.dashboard', [
        'organisation' => $this->organisation->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->organisation_id)->toBe($this->organisation->id)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::ORG_PROCUREMENT->value)
        ->and($sectionScope->model_slug)->toBe($this->organisation->slug);
});

test('UI Index stock deliveries', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Stock Deliveries')
                    ->etc()
            )
            ->has('data');
    });
});

test('UI Index org agent stock deliveries shows deliveries with empty between filter', function () {
    $agentStockDelivery = StoreStockDelivery::make()->action(
        $this->orgAgent,
        [
            'reference'   => 'AGENT-DELIVERY-1',
            'date'        => date('Y-m-d'),
            'currency_id' => $this->organisation->currency_id,
        ]
    );

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_agents.show.stock-deliveries.index', [$this->organisation->slug, $this->orgAgent->slug]).'?between[date]=');

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->has('data');
    });

    $data        = $response->viewData('page')['props']['data'];
    $deliveryIds = collect($data['data'] ?? $data)->pluck('id');
    expect($deliveryIds->contains($agentStockDelivery->id))->toBeTrue();

    expect($this->orgAgent->stats->refresh()->number_stock_deliveries)->toBeGreaterThanOrEqual(1);
});

test('UI Index org agent stock deliveries shows container columns and state counts', function () {
    StoreStockDelivery::make()->action(
        $this->orgAgent,
        [
            'reference'   => 'AGENT-DELIVERY-2',
            'date'        => date('Y-m-d'),
            'currency_id' => $this->organisation->currency_id,
        ],
        strict: false,
    );

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_agents.show.stock-deliveries.index', [$this->organisation->slug, $this->orgAgent->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->has('data');
    });

    $data  = $response->viewData('page')['props']['data'];
    $items = collect($data['data'] ?? $data);
    expect($items->first())->toHaveKeys(['items', 'cbm', 'gross_weight', 'amount', 'state_label']);

    $reflection      = new ReflectionMethod(IndexStockDeliveries::class, 'getElementGroups');
    $reflection->setAccessible(true);
    $indexAction      = IndexStockDeliveries::make();
    $parentReflection = new ReflectionProperty(IndexStockDeliveries::class, 'parent');
    $parentReflection->setAccessible(true);
    $parentReflection->setValue($indexAction, $this->orgAgent);

    $stateElements = $reflection->invoke($indexAction)['state']['elements'];
    expect(collect($stateElements)->pluck(1)->sum())->toBeGreaterThanOrEqual(1);
});

test('UI create stock delivery', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.procurement.stock_deliveries.create', [$this->organisation->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('formData')
            ->has('pageHead')
            ->has('breadcrumbs', 4);
    });
});

test('UI show stock delivery', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $this->stockDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->stockDelivery->reference)
                    ->etc()
            )
            ->has(
                'tabs',
                fn (AssertableInertia $page) => $page
                    ->where('current', StockDeliveryTabsEnum::ITEMS->value)
                    ->etc()
            )
            ->missing('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value)
            ->has('queryBuilderProps.items.columns', 8)
            ->has(
                'queryBuilderProps.items.columns.0',
                fn (AssertableInertia $page) => $page
                    ->where('key', 'state_icon')
                    ->where('type', 'icon')
                    ->etc()
            )
            ->has(
                'queryBuilderProps.items.columns.7',
                fn (AssertableInertia $page) => $page
                    ->where('key', 'actions')
                    ->where('align', 'right')
                    ->etc()
            );
    });
});

test('UI show stock delivery pending and done item tabs', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PENDING-DONE-TABS', [10, 10]);
    $items         = $stockDelivery->items()->orderBy('id')->get();

    UpdateStateToConfirmedStockDeliveryItem::make()->action($items[0]);

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::PENDING_ITEMS->value)
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::DONE_ITEMS->value);
    });

    $pendingResponse = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::PENDING_ITEMS->value);
    $pendingResponse->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has(StockDeliveryTabsEnum::PENDING_ITEMS->value.'.data', 2);
    });

    $doneResponse = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::DONE_ITEMS->value);
    $doneResponse->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has(StockDeliveryTabsEnum::DONE_ITEMS->value.'.data', 0);
    });
});

test('UI edit stock delivery', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.procurement.stock_deliveries.edit', [$this->organisation->slug, $this->stockDelivery->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData')
            ->has('pageHead')
            ->has('breadcrumbs', 3);
    });
});

function createStockDeliveryWithItems($test, string $code, array $unitQuantities): StockDelivery
{
    $supplier    = StoreSupplier::make()->action(parent: $test->group, modelData: Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $test->organisation->id)->first();

    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => $code,
        'name'             => $code,
        'cost'             => 100,
        'stock_id'         => $test->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);

    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, [
        'reference' => $code,
        'date'      => date('Y-m-d'),
    ]);

    foreach ($unitQuantities as $index => $unitQuantity) {
        StoreStockDeliveryItem::make()->action(
            $stockDelivery,
            $supplierProduct->historicSupplierProduct,
            $test->orgStocks[$index],
            ['unit_quantity' => $unitQuantity]
        );
    }

    return $stockDelivery->refresh();
}

test('stock delivery counts under and over delivered items', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'UNDER-OVER', [10, 10, 10]);
    $items         = $stockDelivery->items()->orderBy('id')->get();

    $under   = SetStockDeliveryItemCheckedQuantity::make()->action($items[0], ['unit_quantity_checked' => 8]);
    $over    = SetStockDeliveryItemCheckedQuantity::make()->action($items[1], ['unit_quantity_checked' => 12]);
    $missing = SetStockDeliveryItemCheckedQuantity::make()->action($items[2], ['unit_quantity_checked' => 0]);

    expect((float) $under->unit_quantity_checked)->toBe(8.0)
        ->and((float) $over->unit_quantity_checked)->toBe(12.0)
        ->and($under->state)->toBe(StockDeliveryItemStateEnum::CHECKED)
        ->and($over->state)->toBe(StockDeliveryItemStateEnum::CHECKED)
        ->and($missing->state)->toBe(StockDeliveryItemStateEnum::NOT_RECEIVED)
        ->and($missing->not_received_at)->not->toBeNull();

    $stockDelivery->refresh();

    expect($stockDelivery->number_stock_delivery_items_under_delivered)->toBe(2)
        ->and($stockDelivery->number_stock_delivery_items_over_delivered)->toBe(1);
});

test('under delivered stock delivery item is placed and books in the delivery', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'UNDER-PLACED', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = $stockDelivery->items()->first();
    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => 8]);

    expect($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::CHECKED)
        ->and($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::CHECKED);

    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 8]);

    expect((float) $stockDeliveryItem->unit_quantity_placed)->toBe(8.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED)
        ->and($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN)
        ->and($stockDelivery->fresh()->booked_in_at)->not->toBeNull();
});

test('stock delivery item confirmation sets confirmed_at real column', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'CONFIRM-TS', [10]);

    expect($stockDelivery->confirmed_at)->toBeNull();

    UpdateStateToConfirmedStockDeliveryItem::make()->action($stockDelivery->items()->first());

    $stockDelivery = $stockDelivery->fresh();

    expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::CONFIRMED)
        ->and($stockDelivery->confirmed_at)->not->toBeNull()
        ->and($stockDelivery->data)->not->toHaveKey('confirmed_at');
});

test('UI show stock delivery under over delivered items tab', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'UNDER-OVER-TAB', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 8]);
    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 8]);

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.show', [
        $this->organisation->slug,
        $stockDelivery->slug,
    ]).'?tab='.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value);

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value)
            ->has(
                StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value.'.data',
                1,
                fn (AssertableInertia $page) => $page
                    ->where('difference_units', -2)
                    ->where('difference_percentage', -20)
                    ->where('difference_skos', -0.2)
                    ->etc()
            );
    });
});

test('UI stock delivery partial reload refreshes item state filters and tabs', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PARTIAL-RELOAD', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $url = route('grp.org.procurement.stock_deliveries.show', [
        $this->organisation->slug,
        $stockDelivery->slug,
    ]);

    $this->withoutExceptionHandling();

    $this->get($url)->assertInertia(function (AssertableInertia $page) {
        $page
            ->where('queryBuilderProps.items.elementGroups.state.elements.placed.1', 0)
            ->has('tabs.navigation', 6)
            ->missing('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value);
    });

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 10]);

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);

    $response = $this->get($url, [
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Procurement/StockDelivery',
        'X-Inertia-Partial-Data'      => implode(',', [StockDeliveryTabsEnum::ITEMS->value, 'tabs', 'queryBuilderProps']),
    ]);

    $response->assertOk()
        ->assertJsonPath('props.queryBuilderProps.items.elementGroups.state.elements.placed.1', 1)
        ->assertJsonCount(7, 'props.tabs.navigation')
        ->assertJsonPath(
            'props.tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value.'.title',
            StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->blueprint()['title']
        );
});

test('stock delivery item can not be placed beyond the checked quantity', function () {
    $stockDelivery     = createStockDeliveryWithItems($this, 'OVER-PLACED', [10]);
    $stockDeliveryItem = $stockDelivery->items()->first();
    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => 8]);

    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 9]);
})->throws(ValidationException::class);

function createLocationOrgStockFor($test, StockDeliveryItem $stockDeliveryItem): LocationOrgStock
{
    $warehouse = Warehouse::where('organisation_id', $test->organisation->id)->first();

    if (!$warehouse) {
        $warehouse = StoreWarehouse::make()->action($test->organisation, Warehouse::factory()->definition());
    }

    $location = StoreLocation::make()->action($warehouse, Location::factory()->definition());

    return StoreLocationOrgStock::make()->action(OrgStock::find($stockDeliveryItem->org_stock_id), $location, [
        'type' => LocationStockTypeEnum::PICKING,
    ]);
}

test('stock delivery item checks all the delivered quantity', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'CHECK-ALL', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemAsChecked::make()->action($stockDelivery->items()->first());

    expect((float) $stockDeliveryItem->unit_quantity_checked)->toBe(10.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::CHECKED)
        ->and($stockDeliveryItem->checked_at)->not->toBeNull()
        ->and($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::CHECKED);
});

test('stock delivery item places all the remaining checked quantity in one location', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-ALL', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);

    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, [
        'location_org_stock_id' => $locationOrgStock->id,
    ]);

    $sowing = $stockDeliveryItem->sowings()->first();

    expect((float) $stockDeliveryItem->unit_quantity_placed)->toBe(10.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED)
        ->and($stockDeliveryItem->sowings()->count())->toBe(1)
        ->and($sowing->location_id)->toBe($locationOrgStock->location_id)
        ->and((float) $sowing->quantity)->toBe(10.0)
        ->and($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);
});

test('stock delivery item places all without a location when the org stock has none', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-ALL-NO-LOCATION', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, []);

    expect((float) $stockDeliveryItem->unit_quantity_placed)->toBe(10.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED)
        ->and($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);
});

test('UI show stock delivery items exposes the placement and sowings data', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACEMENT-UI', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);

    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, [
        'quantity'              => 4,
        'location_org_stock_id' => $locationOrgStock->id,
    ]);

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.show', [
        $this->organisation->slug,
        $stockDelivery->slug,
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($locationOrgStock) {
        $page
            ->component('Procurement/StockDelivery')
            ->where('queryBuilderProps.items.columns.4.key', 'sowings')
            ->where('queryBuilderProps.items.columns.5.key', 'checked_unit')
            ->where('queryBuilderProps.items.columns.6.key', 'placement')
            ->has(
                StockDeliveryTabsEnum::ITEMS->value.'.data',
                1,
                fn (AssertableInertia $page) => $page
                    ->where('placement_remaining', 6)
                    ->where('has_available_qty', true)
                    ->where('is_editable', true)
                    ->where('placeAllRoute.name', 'grp.models.stock-delivery-item.place-all')
                    ->where('checkAllRoute.name', 'grp.models.stock-delivery-item.set-all-checked')
                    ->where('warehouse_slug', $locationOrgStock->warehouse->slug)
                    ->has('locations.0.warehouse_slug')
                    ->has('sowings.0.undo_sowing_route')
                    ->where('sowings.0.quantity', 4)
                    ->where('sowings.0.location_code', $locationOrgStock->location->code)
                    ->etc()
            );
    });
});

test('UI edit org supplier relationship (not owned)', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_suppliers.edit', [$this->organisation->slug, $this->orgSupplier->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has(
                'formData',
                fn (AssertableInertia $page) => $page
                    ->where('args.updateRoute.name', 'grp.models.org_supplier.update')
                    ->has('blueprint.0.fields.status')
                    ->etc()
            );
    });
});

test('UI create org supplier picker page', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_suppliers.create', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Procurement/CreateOrgSupplier')
            ->has('title')
            ->has('data')
            ->where('pageHead.title', 'Add suppliers');
    });
});

test('adopt a free supplier into the organisation', function () {
    $foreignAgent   = StoreAgent::make()->action($this->group, Agent::factory()->definition());
    $foreignSupplier = StoreSupplier::make()->action($foreignAgent, Supplier::factory()->definition());

    expect(OrgSupplier::where('organisation_id', $this->organisation->id)->where('supplier_id', $foreignSupplier->id)->exists())->toBeFalse();

    $this->withoutExceptionHandling();
    $this->post(route('grp.models.org.org_supplier.store', [$this->organisation->id, $foreignSupplier->id]))
        ->assertSessionHasNoErrors();

    $orgSupplier = OrgSupplier::where('organisation_id', $this->organisation->id)
        ->where('supplier_id', $foreignSupplier->id)
        ->first();

    expect($orgSupplier)->not->toBeNull();
});

test('agent organisation manages its own supplier identity', function () {
    $ownAgent = StoreAgent::make()->action($this->group, Agent::factory()->definition());

    StoreOrgAgent::make()->action($ownAgent->organisation, $ownAgent, []);

    $ownSupplier = StoreSupplier::make()->action($ownAgent, Supplier::factory()->definition());

    $orgSupplier = OrgSupplier::where('organisation_id', $ownAgent->organisation_id)
        ->where('supplier_id', $ownSupplier->id)
        ->firstOrFail();

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_suppliers.edit', [$ownAgent->organisation->slug, $orgSupplier->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has(
                'formData',
                fn (AssertableInertia $page) => $page
                    ->where('args.updateRoute.name', 'grp.models.supplier.update')
                    ->has('blueprint.0.fields.code')
                    ->etc()
            );
    });

    $this->patch(route('grp.models.supplier.update', $ownSupplier->id), [
        'company_name' => 'Renamed Own Supplier',
    ])->assertSessionHasNoErrors();

    expect($ownSupplier->fresh()->company_name)->toBe('Renamed Own Supplier');
});

test('agent organisation creates a supplier under its own agent', function () {
    $ownAgent = StoreAgent::make()->action($this->group, Agent::factory()->definition());

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.procurement.org_suppliers.create_for_agent', [$ownAgent->organisation->slug]))
        ->assertOk();

    $storeData = Supplier::factory()->definition();
    $this->post(route('grp.models.agent.supplier.store', $ownAgent->id), $storeData)
        ->assertSessionHasNoErrors();

    expect(Supplier::where('agent_id', $ownAgent->id)->where('code', $storeData['code'])->exists())->toBeTrue();
});

describe('shopping list', function () {
    beforeEach(function () {
        $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
            ['stock_id' => $this->stock->id, 'supplier_product_id' => $this->supplierProduct->id],
            ['available' => true]
        );

        OrgStockHasOrgSupplierProduct::firstOrCreate(
            ['org_stock_id' => $this->orgStocks[0]->id, 'org_supplier_product_id' => $this->orgSupplierProduct->id],
            ['stock_has_supplier_product_id' => $stockHasSupplierProduct->id, 'status' => true, 'local_priority' => 0]
        );
    });

    test('store shopping list item denormalises and snapshots', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, [
            'quantity_units' => 100,
        ]);

        expect($item)->toBeInstanceOf(ShoppingListItem::class)
            ->and($item->group_id)->toBe($this->orgSupplierProduct->group_id)
            ->and($item->organisation_id)->toBe($this->orgSupplierProduct->organisation_id)
            ->and($item->org_supplier_product_id)->toBe($this->orgSupplierProduct->id)
            ->and($item->supplier_product_id)->toBe($this->supplierProduct->id)
            ->and($item->supplier_id)->toBe($this->supplierProduct->supplier_id)
            ->and($item->agent_id)->toBe($this->agent->id)
            ->and($item->units_per_pack_snapshot)->toBe($this->supplierProduct->units_per_pack)
            ->and($item->units_per_carton_snapshot)->toBe($this->supplierProduct->units_per_carton)
            ->and((float) $item->quantity_units)->toBe(100.0)
            ->and($item->state)->toBe(ShoppingListItemStateEnum::OPEN);

        return $item;
    });

    test('update shopping list item while open', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 50]);

        $item = UpdateShoppingListItem::make()->action($item, ['quantity_units' => 75, 'notes' => 'more please']);

        expect((float) $item->quantity_units)->toBe(75.0)
            ->and($item->notes)->toBe('more please');

        return $item;
    });

    test('propose dismiss requires a reason', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 10]);

        expect(fn () => ProposeDismissShoppingListItem::make()->action($item, []))
            ->toThrow(ValidationException::class);
    });

    test('propose dismiss, reinstate, propose again, accept', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 10]);

        $item = ProposeDismissShoppingListItem::make()->action($item, ['dismiss_reason' => 'not needed right now']);
        expect($item->state)->toBe(ShoppingListItemStateEnum::DISMISS_PROPOSED)
            ->and($item->dismiss_reason)->toBe('not needed right now');

        $item = ResolveDismissShoppingListItem::make()->action($item, false);
        expect($item->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and($item->dismiss_reason)->toBeNull()
            ->and($item->dismiss_proposed_at)->toBeNull();

        $item = ProposeDismissShoppingListItem::make()->action($item, ['dismiss_reason' => 'still not needed']);
        $item = ResolveDismissShoppingListItem::make()->action($item, true);

        expect($item->state)->toBe(ShoppingListItemStateEnum::DISMISSED)
            ->and($item->resolved_at)->not->toBeNull();
    });

    test('delete shopping list item while open', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 10]);

        DeleteShoppingListItem::make()->action($item);

        expect(ShoppingListItem::find($item->id))->toBeNull();
    });

    test('cherry pick full quantity creates or reuses purchase order and marks item ordered', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 60]);

        $result = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item->id],
        ]);

        expect($result['picked'])->toBe(1)
            ->and($result['skipped'])->toBe([])
            ->and($result['purchase_orders'])->toHaveCount(1);

        $purchaseOrder = $result['purchase_orders'][0];
        expect($purchaseOrder->parent_type)->toBe('OrgAgent')
            ->and($purchaseOrder->parent_id)->toBe($this->orgAgent->id);

        $item = $item->fresh();
        expect($item->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and($item->purchase_order_transaction_id)->not->toBeNull();

        $transaction = $item->purchase_order_transaction_id
            ? \App\Models\Procurement\PurchaseOrderTransaction::find($item->purchase_order_transaction_id)
            : null;
        expect($transaction)->not->toBeNull()
            ->and((float) $transaction->quantity_ordered)->toBe(60.0);
    });

    test('cherry pick partial quantity splits the line and keeps parent created_at', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 100]);
        $originalCreatedAt = $item->created_at;

        $result = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item->id, 'quantity_units' => 40],
        ]);

        expect($result['picked'])->toBe(1);

        $item = $item->fresh();
        expect($item->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and((float) $item->quantity_units)->toBe(40.0);

        $child = ShoppingListItem::where('parent_id', $item->id)->first();
        expect($child)->not->toBeNull()
            ->and((float) $child->quantity_units)->toBe(60.0)
            ->and($child->state)->toBe(ShoppingListItemStateEnum::OPEN)
            ->and($child->created_at->eq($originalCreatedAt))->toBeTrue();
    });

    test('shopping list index page renders', function () {
        $this->withoutExceptionHandling();
        $response = $this->get(route('grp.org.procurement.shopping_list.index', [$this->organisation->slug]));

        $response->assertInertia(function (AssertableInertia $page) {
            $page->component('Procurement/ShoppingListItems');
        });
    });

    test('cherry pick creates an in_process ASPO and reuses it for the same agent-supplier', function () {
        $item1 = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 30]);
        $item2 = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 20]);

        $result = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item1->id],
        ]);

        expect($result['agent_supplier_purchase_orders'])->toHaveCount(1);
        $aspo = $result['agent_supplier_purchase_orders'][0];
        expect($aspo->state)->toBe(\App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum::IN_PROCESS)
            ->and($aspo->supplier_id)->toBe($this->supplierProduct->supplier_id);

        $result2 = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item2->id],
        ]);

        expect($result2['agent_supplier_purchase_orders'])->toHaveCount(1)
            ->and($result2['agent_supplier_purchase_orders'][0]->id)->toBe($aspo->id);

        $transaction1 = \App\Models\Procurement\PurchaseOrderTransaction::find($item1->fresh()->purchase_order_transaction_id);
        $transaction2 = \App\Models\Procurement\PurchaseOrderTransaction::find($item2->fresh()->purchase_order_transaction_id);
        expect($transaction1->agent_supplier_purchase_order_id)->toBe($aspo->id)
            ->and($transaction2->agent_supplier_purchase_order_id)->toBe($aspo->id);
    });

    test('shopping list board exposes the open ASPO for a supplier', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 30]);

        CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item->id],
        ]);

        $secondItem = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 15]);

        $response = $this->get(route('grp.org.procurement.shopping_list.board', [$this->agent->organisation->slug]));

        $response->assertInertia(function (AssertableInertia $page) use ($secondItem) {
            $agents = $page->toArray()['props']['agents'];
            $supplier = collect($agents[0]['suppliers'])->firstWhere('supplier_id', $secondItem->supplier_id);
            expect($supplier['open_agent_supplier_purchase_order'])->not->toBeNull();
        });
    });

    test('shopping list board renders for group and agent organisation', function () {
        $this->withoutExceptionHandling();

        $this->get(route('grp.supply-chain.shopping_list.board'))
            ->assertInertia(function (AssertableInertia $page) {
                $page->component('SupplyChain/ShoppingListBoard');
            });

        $this->get(route('grp.org.procurement.shopping_list.board', [$this->agent->organisation->slug]))
            ->assertInertia(function (AssertableInertia $page) {
                $page->component('Procurement/ShoppingListBoard');
            });
    });
});

describe('stock delivery costing checklist', function () {
    beforeEach(function () {
        $orgSupplier = OrgSupplier::first();
        $this->costingStockDelivery = StoreStockDelivery::make()->action($orgSupplier, [
            'reference' => 'COSTING-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
    });

    test('storing cost rows syncs delivery costs and derives is_costed', function () {
        $stockDelivery = $this->costingStockDelivery;

        $agentInvoice = StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'        => StockDeliveryCostTypeEnum::AGENT_INVOICE->value,
            'amount'      => 1000,
            'received_at' => now(),
        ]);
        $shipping = StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'        => StockDeliveryCostTypeEnum::SHIPPING->value,
            'amount'      => 200,
            'received_at' => now(),
        ]);

        $stockDelivery->refresh();
        expect($agentInvoice)->toBeInstanceOf(StockDeliveryCost::class)
            ->and($stockDelivery->costs()->count())->toBe(2)
            ->and($stockDelivery->is_costed)->toBeFalse();

        $duty = StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'  => StockDeliveryCostTypeEnum::DUTY->value,
            'is_na' => true,
        ]);

        $stockDelivery->refresh();
        expect($duty->is_na)->toBeTrue()
            ->and($stockDelivery->is_costed)->toBeTrue();

        UpdateStockDeliveryCost::make()->action($shipping, ['received_at' => null]);
        expect($stockDelivery->refresh()->is_costed)->toBeFalse();
    });

    test('non extra cost types are singletons', function () {
        $stockDelivery = $this->costingStockDelivery;

        StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'   => StockDeliveryCostTypeEnum::SHIPPING->value,
            'amount' => 100,
        ]);

        expect(fn () => StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'   => StockDeliveryCostTypeEnum::SHIPPING->value,
            'amount' => 50,
        ]))->toThrow(ValidationException::class);

        StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::EXTRA->value, 'label' => 'Fine', 'amount' => 10]);
        $secondExtra = StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::EXTRA->value, 'label' => 'Storage', 'amount' => 20]);

        expect($stockDelivery->costs()->where('type', 'extra')->count())->toBe(2);

        DeleteStockDeliveryCost::make()->action($secondExtra);
        expect($stockDelivery->costs()->where('type', 'extra')->count())->toBe(1);
    });

    test('repair creates checklist rows from legacy cost columns', function () {
        $stockDelivery = $this->costingStockDelivery;
        $stockDelivery->update([
            'placed_at'     => now(),
            'cost_items'    => 500,
            'cost_shipping' => 100,
            'cost_duties'   => 0,
            'is_costed'     => true,
        ]);

        RepairStockDeliveryCostings::make()->handle($stockDelivery->refresh());

        expect($stockDelivery->costs()->count())->toBe(3)
            ->and($stockDelivery->costs()->where('type', 'agent_invoice')->value('amount'))->toBe('500.00')
            ->and($stockDelivery->costs()->where('type', 'shipping')->first()->received_at)->not->toBeNull()
            ->and($stockDelivery->costs()->where('type', 'duty')->first()->is_na)->toBeTrue();
    });
});

describe('supplier deposits', function () {
    beforeEach(function () {
        $purchaseOrder = StorePurchaseOrder::make()->action(
            $this->orgSupplier,
            array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'ASPO-DEP-'.PurchaseOrder::count()]),
            strict: false
        );

        $this->depositAspo = \App\Actions\SupplyChain\AgentSupplierPurchaseOrder\StoreAgentSupplierPurchaseOrder::make()->action(
            $purchaseOrder,
            $this->supplier,
            []
        );
    });

    test('deposit lifecycle: pending to paid to supplier', function () {
        $deposit = \App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit::make()->action($this->depositAspo, [
            'amount' => 300,
        ]);

        expect($deposit)->toBeInstanceOf(\App\Models\SupplyChain\AspoDeposit::class)
            ->and($deposit->agent_id)->toBe($this->agent->id)
            ->and($deposit->state->value)->toBe('pending')
            ->and($deposit->currency_id)->toBe($this->depositAspo->currency_id);

        $paid = \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        expect($paid->state->value)->toBe('paid_to_supplier')
            ->and($paid->paid_to_supplier_at)->not->toBeNull();

        $refunded = \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($paid, ['state' => 'refunded']);
        expect($refunded->state->value)->toBe('refunded')
            ->and($refunded->refunded_at)->not->toBeNull();
    });

    test('deposit request consolidation auto-settles when all items paid', function () {
        $depositOne = \App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit::make()->action($this->depositAspo, ['amount' => 100]);
        $depositTwo = \App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit::make()->action($this->depositAspo, ['amount' => 150]);

        $depositRequest = \App\Actions\SupplyChain\DepositRequest\StoreDepositRequest::make()->action($this->agent, [
            'currency_id' => $this->depositAspo->currency_id,
            'items'       => [
                ['aspo_deposit_id' => $depositOne->id, 'organisation_id' => $this->organisation->id, 'amount' => 100],
                ['aspo_deposit_id' => $depositTwo->id, 'organisation_id' => $this->organisation->id, 'amount' => 150],
            ],
        ]);

        expect($depositRequest)->toBeInstanceOf(\App\Models\SupplyChain\DepositRequest::class)
            ->and($depositRequest->items()->count())->toBe(2)
            ->and($depositRequest->state->value)->toBe('requested');

        $items = $depositRequest->items()->get();

        \App\Actions\SupplyChain\DepositRequest\MarkDepositRequestItemPaid::make()->action($items[0]);
        expect($depositRequest->refresh()->state->value)->toBe('requested');

        \App\Actions\SupplyChain\DepositRequest\MarkDepositRequestItemPaid::make()->action($items[1]);
        expect($depositRequest->refresh()->state->value)->toBe('settled')
            ->and($depositRequest->settled_at)->not->toBeNull();
    });

    test('deposit application splits across two deliveries and tracks unapplied balance', function () {
        $deposit = \App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit::make()->action($this->depositAspo, ['amount' => 100]);
        \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        $deliveryOne = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-APP-1-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        $deliveryTwo = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-APP-2-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        $deliveryOne->update(['agent_id' => $this->agent->id]);
        $deliveryTwo->update(['agent_id' => $this->agent->id]);

        \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($deliveryOne->refresh(), [
            'aspo_deposit_id' => $deposit->id,
            'amount'          => 40,
        ]);
        \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($deliveryTwo->refresh(), [
            'aspo_deposit_id' => $deposit->id,
            'amount'          => 30,
        ]);

        $deposit->refresh();
        expect($deposit->applied_amount)->toBe(70.0)
            ->and($deposit->unapplied_amount)->toBe(30.0)
            ->and($deliveryOne->depositApplications()->sum('amount'))->toBe('40.00')
            ->and($deliveryTwo->depositApplications()->sum('amount'))->toBe('30.00');

        expect(fn () => \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($deliveryOne->refresh(), [
            'aspo_deposit_id' => $deposit->id,
            'amount'          => 50,
        ]))->toThrow(ValidationException::class);
    });

    test('settlement math: agent invoice minus applied deposits equals balance due', function () {
        $deposit = \App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit::make()->action($this->depositAspo, ['amount' => 300]);
        \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        $delivery = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-SETTLE-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        $delivery->update(['agent_id' => $this->agent->id]);

        StoreStockDeliveryCost::make()->action($delivery, [
            'type'   => StockDeliveryCostTypeEnum::AGENT_INVOICE->value,
            'amount' => 1000,
        ]);

        \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($delivery->refresh(), [
            'aspo_deposit_id' => $deposit->id,
            'amount'          => 300,
        ]);

        $agentInvoiceAmount = (float) $delivery->costs()->where('type', 'agent_invoice')->value('amount');
        $appliedTotal        = (float) $delivery->depositApplications()->sum('amount');

        expect($agentInvoiceAmount)->toBe(1000.0)
            ->and($appliedTotal)->toBe(300.0)
            ->and($agentInvoiceAmount - $appliedTotal)->toBe(700.0);
    });

    test('un-applying a deposit application is audited, restores balance, and allows re-apply', function () {
        $deposit = \App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit::make()->action($this->depositAspo, ['amount' => 300]);
        \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        $delivery = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-UNAPPLY-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        $delivery->update(['agent_id' => $this->agent->id]);

        StoreStockDeliveryCost::make()->action($delivery, [
            'type'   => StockDeliveryCostTypeEnum::AGENT_INVOICE->value,
            'amount' => 1000,
        ]);

        $application = \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($delivery->refresh(), [
            'aspo_deposit_id' => $deposit->id,
            'amount'          => 200,
        ]);

        expect($deposit->refresh()->unapplied_amount)->toBe(100.0)
            ->and((float) $delivery->depositApplications()->sum('amount'))->toBe(200.0);

        \App\Actions\GoodsIn\StockDelivery\DeleteStockDeliveryDepositApplication::make()->action($application);

        expect($deposit->refresh()->unapplied_amount)->toBe(300.0)
            ->and((float) $delivery->depositApplications()->sum('amount'))->toBe(0.0)
            ->and($delivery->depositApplications()->count())->toBe(0);

        $trashed = \App\Models\GoodsIn\StockDeliveryDepositApplication::withTrashed()->find($application->id);
        expect($trashed)->not->toBeNull()
            ->and($trashed->trashed())->toBeTrue()
            ->and($trashed->deleted_by)->toBeNull()
            ->and($trashed->deleted_at)->not->toBeNull();

        $reapplied = \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($delivery->refresh(), [
            'aspo_deposit_id' => $deposit->id,
            'amount'          => 150,
        ]);

        expect($reapplied)->toBeInstanceOf(\App\Models\GoodsIn\StockDeliveryDepositApplication::class)
            ->and($deposit->refresh()->unapplied_amount)->toBe(150.0)
            ->and((float) $delivery->depositApplications()->sum('amount'))->toBe(150.0)
            ->and(\App\Models\GoodsIn\StockDeliveryDepositApplication::withTrashed()->where('aspo_deposit_id', $deposit->id)->count())->toBe(2);
    });
});
