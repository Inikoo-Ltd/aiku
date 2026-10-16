<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\SupplyChain\StockDeliveryInvoice\EstimateStockDeliveryInvoice;
use App\Models\SupplyChain\SupplierInvoice;
use App\Models\SupplyChain\AgentInvoice;
use App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum;
use App\Actions\Procurement\AgentOrder\SendAgentOrderToAgent;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Models\Goods\TradeUnit;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\SupplyChain\AgentInvoice\ApplyAgentInvoiceCosting;
use App\Actions\SupplyChain\AgentInvoice\ApproveAgentInvoiceCharges;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Actions\SupplyChain\AgentInvoice\UpdateAgentInvoiceCharges;
use App\Models\SysAdmin\User;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\StartStockDeliveryCosting;
use App\Actions\GoodsIn\Sowing\DeleteSowing;
use App\Actions\GoodsIn\StockDelivery\CancelStockDelivery;
use App\Actions\GoodsIn\StockDelivery\PrefillDomesticStockDeliveryCosts;
use App\Actions\GoodsIn\StockDelivery\ReceiveStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateToReceived;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsPlaced;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use Illuminate\Support\Facades\Queue;
use App\Actions\GoodsIn\StockDelivery\EvaluateStockDeliveryCosting;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItemCost;
use App\Actions\Inventory\OrgStock\Stock\RebuildOrgStockHistoriesSince;
use App\Models\Helpers\Audit;
use App\Models\Inventory\OrganisationStockHistory;
use Lorisleiva\Actions\Decorators\JobDecorator;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemBatches;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpsertStockDeliveryItemPlaced;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Models\CRM\Customer;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrderOrgSupplierProducts;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Catalogue\Shop\UI\GetCatalogueOnItsWay;
use App\Actions\Catalogue\UI\IndexCatalogueOnItsWay;
use App\Actions\Maintenance\GoodsIn\RepairStockDeliveryPurchaseOrderLinks;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Production\Production\StoreProduction;
use App\Actions\HumanResources\Employee\StoreEmployee;
use Illuminate\Support\Str;
use App\Models\HumanResources\JobPosition;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\SysAdmin\User\StoreUser;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Models\Production\Production;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\ShoppingListItem\CherryPickShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Catalogue\Shop;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Actions\UI\Grp\Layout\GetOrganisationNavigation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Models\Goods\Stock;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\StoreStockDeliveryServiceInvoice;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\DeleteStockDeliveryServiceInvoice;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use Illuminate\Support\Carbon;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementCostStatusEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Models\Procurement\OrgSupplier;
use App\Actions\Procurement\OrgSupplier\UI\CreateOrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Actions\GoodsIn\StockDelivery\PdfStockDelivery;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use Illuminate\Support\Arr;
use App\Actions\Procurement\Settings\UpdateProcurementSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyOutcomeEnum;
use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierUnitEnum;
use App\Actions\GoodsIn\StockDelivery\GetStockDeliveryInvoiceCosting;
use App\Models\Tasks\StaffTask;
use App\Actions\GoodsIn\StockDeliveryItem\ResolveStockDeliveryItemDiscrepancy;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCustomsLine;
use App\Actions\GoodsIn\StockDeliveryClaim\UpdateStockDeliveryClaim;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCustoms;
use Inertia\Testing\AssertableInertia;
use App\Actions\SysAdmin\User\UI\GetLoggedUser;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ProcurementHelpers.php';

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

    $agent = Agent::orderBy('id')->first();
    if (!$agent) {
        $modelData = Agent::factory()->definition();
        $agent     = StoreAgent::make()->action(
            group: $this->group,
            modelData: $modelData
        );
    }
    $this->agent = $agent;

    $orgAgent = OrgAgent::orderBy('id')->first();
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

    $stock = Stock::orderBy('id')->first();
    if (!$stock) {
        $storeData = Stock::factory()->definition();
        $stock     = StoreStock::make()->action(
            $this->organisation->group,
            $storeData
        );
    }

    $this->stock = $stock;

    $supplierProduct = SupplierProduct::where('supplier_id', $this->supplier->id)->orderBy('id')->first();
    if (!$supplierProduct) {
        $storeData = SupplierProduct::factory()->definition();
        data_set($storeData, 'stock_id', $this->stock->id);
        $supplierProduct = StoreSupplierProduct::make()->action(
            $this->supplier,
            $storeData
        );
    }

    $this->supplierProduct = $supplierProduct;

    $orgSupplier = OrgSupplier::where('supplier_id', $this->supplier->id)->orderBy('id')->first();
    if (!$orgSupplier) {
        $orgSupplier = StoreOrgSupplier::make()->action(
            $this->organisation,
            $this->supplier,
        );
    }

    $this->orgSupplier = $orgSupplier;

    $orgSupplierProduct = OrgSupplierProduct::where('org_supplier_id', $this->orgSupplier->id)
        ->where('supplier_product_id', $this->supplierProduct->id)
        ->orderBy('id')
        ->first();
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

    $orgPartner->partner->update(['is_manufacturing_hub' => true]);
    $this->orgPartner = $orgPartner;

    $stockDelivery = StockDelivery::orderBy('id')->first();
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

    $purchaseOrder = PurchaseOrder::orderBy('id')->first();
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

test('attach a supplier product to an org stock that has none, first one becomes preferred', function () {
    $orgStock = createUnlinkedOrgStock($this);
    expect(OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)->count())->toBe(0);

    $supplierProduct    = StoreSupplierProduct::make()->action($this->orgSupplier->supplier, [
        'code'             => 'attach-me',
        'name'             => 'Attach me',
        'cost'             => 12,
        'stock_id'         => $orgStock->stock_id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);

    $link = AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $orgSupplierProduct);
    expect($link->org_stock_id)->toBe($orgStock->id)
        ->and($link->org_supplier_product_id)->toBe($orgSupplierProduct->id)
        ->and((int) $link->local_priority)->toBe(10)
        ->and(StockHasSupplierProduct::where('stock_id', $orgStock->stock_id)->where('supplier_product_id', $supplierProduct->id)->exists())->toBeTrue();

    $again = AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $orgSupplierProduct);
    expect($again->id)->toBe($link->id)
        ->and(OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)->count())->toBe(1);

    $secondSupplierProduct    = StoreSupplierProduct::make()->action($this->orgSupplier->supplier, [
        'code'             => 'attach-me-2',
        'name'             => 'Attach me 2',
        'cost'             => 15,
        'stock_id'         => $orgStock->stock_id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $secondOrgSupplierProduct = StoreOrgSupplierProduct::make()->action($this->orgSupplier, $secondSupplierProduct);
    $second                   = AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $secondOrgSupplierProduct);
    expect((int) $second->local_priority)->toBe(0)
        ->and(OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)->count())->toBe(2);
});

test('an org stock can not be linked to another organisation\'s supplier product', function () {
    $stock             = $this->stocks[1];
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));
    $otherOrgStock     = createOrgStocks($otherOrganisation, [$stock])[0];

    $supplierProduct    = StoreSupplierProduct::make()->action($this->orgSupplier->supplier, [
        'code'             => 'cross-org-link',
        'name'             => 'Cross org link',
        'cost'             => 12,
        'stock_id'         => $stock->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplierProduct = OrgSupplierProduct::where('organisation_id', $this->organisation->id)->where('supplier_product_id', $supplierProduct->id)->first()
        ?? StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);

    $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
        ['stock_id' => $stock->id, 'supplier_product_id' => $supplierProduct->id],
        ['available' => true, 'priority' => 0]
    );

    expect(fn () => App\Actions\Inventory\OrgStockHasOrgSupplierProduct\StoreOrgStockHasOrgSupplierProduct::make()->action(
        stockHasSupplierProduct: $stockHasSupplierProduct,
        orgStock: $otherOrgStock,
        orgSupplierProduct: $orgSupplierProduct,
        modelData: ['status' => true, 'local_priority' => 0],
        strict: false
    ))->toThrow(Illuminate\Validation\ValidationException::class)
        ->and(OrgStockHasOrgSupplierProduct::where('org_stock_id', $otherOrgStock->id)->where('org_supplier_product_id', $orgSupplierProduct->id)->exists())->toBeFalse();
});

test('every organisation and group top menu subsection carries a label', function () {
    $unlabelled = function (array $navigation) {
        return collect($navigation)
            ->flatMap(fn ($section, $key) => collect(data_get($section, 'topMenu.subSections', []))
                ->filter(fn ($subSection) => is_array($subSection) && blank(data_get($subSection, 'label')))
                ->map(fn ($subSection) => $key.': '.data_get($subSection, 'route.name', '?')))
            ->values()
            ->all();
    };

    $user = $this->adminGuest->getUser();

    expect($unlabelled(GetOrganisationNavigation::run($user, $this->organisation)))->toBe([])
        ->and($unlabelled(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($user)))->toBe([]);
});

test('organisation navigation leaves out the menu of closed shops', function () {
    [$organisation, $user, $shop] = createShop();

    $originalState = $shop->state;
    $navigationPath = 'shops_fulfilments_navigation.shops_navigation.navigation.'.$shop->slug.'.subNavigation';

    $shop->update(['state' => ShopStateEnum::OPEN]);
    expect(data_get(GetOrganisationNavigation::run($user->fresh(), $organisation), $navigationPath))->not->toBeEmpty();

    $shop->update(['state' => ShopStateEnum::CLOSED]);
    expect(data_get(GetOrganisationNavigation::run($user->fresh(), $organisation), $navigationPath))->toBe([]);

    $shop->update(['state' => $originalState]);
});

test('incoming stock tells the customer when an out of stock product is expected back', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]));
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);

    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'ETA-01',
        'name'             => 'ETA asset',
        'cost'             => 100,
        'stock_id'         => $orgStock->stock_id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, [
        'reference' => 'ETA-DEL-1',
        'date'      => date('Y-m-d'),
    ]);
    StoreStockDeliveryItem::make()->action(
        $stockDelivery,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $orgStock,
        array_merge(StockDeliveryItem::factory()->definition(), ['unit_quantity' => 120])
    );

    [, $product] = createProduct(StoreShop::run($this->organisation, Shop::factory()->definition()));
    $product->orgStocks()->sync([$orgStock->id => ['quantity' => 1]]);
    $product->load('orgStocks');

    expect(GetProductIncomingStock::run($product))->toBe([]);

    DispatchStockDelivery::make()->action($stockDelivery);
    $product->load('orgStocks');

    $incoming = GetProductIncomingStock::run($product);

    expect($incoming)->toHaveCount(1)
        ->and($incoming[0]['type'])->toBe('stock_delivery')
        ->and($incoming[0]['reference'])->toBe('ETA-DEL-1')
        ->and($incoming[0]['quantity'])->toBe(120.0)
        ->and($incoming[0]['eta'])->toBeNull()
        ->and(GetProductIncomingStock::make()->earliestEta($product))->toBeNull();

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->update(['data' => array_merge($purchaseOrder->data ?? [], ['estimated_receiving_date' => now()->addDays(30)->toDateString()])]);
    $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);

    expect(GetProductIncomingStock::make()->earliestEta($product))->toBe(now()->addDays(30)->toDateString());

    $expectedEta = now()->addDays(9)->toDateString();
    $stockDelivery->update(['data' => array_merge($stockDelivery->data ?? [], ['estimated_receiving_date' => $expectedEta])]);

    expect(GetProductIncomingStock::run($product)[0]['eta'])->toBe($expectedEta)
        ->and(GetProductIncomingStock::make()->earliestEta($product))->toBe($expectedEta)
        ->and(GetProductIncomingStock::make()->earliestEtaByProduct([$product->id]))->toBe([$product->id => $expectedEta]);

    $stockKey       = "stock:$orgStock->stock_id";
    $onItsWay       = collect(GetCatalogueOnItsWay::run($product->shop))->firstWhere('key', $stockKey);
    $notInCatalogue = collect(GetCatalogueOnItsWay::run(StoreShop::run($this->organisation, Shop::factory()->definition())))->firstWhere('key', $stockKey);

    expect($onItsWay['products'][0]['slug'])->toBe($product->slug)
        ->and($onItsWay['quantity'])->toBe(120.0)
        ->and($onItsWay['eta'])->toBe($expectedEta)
        ->and($onItsWay['lines'][0]['reference'])->toBe('ETA-DEL-1')
        ->and($notInCatalogue['products'])->toBe([]);

    $keysInTable = function (array $facets) use ($product) {
        request()->merge(['on_its_way_facets' => $facets]);

        return collect(IndexCatalogueOnItsWay::run($product->shop, 'on_its_way')->response()->getData(true)['data'])->pluck('key');
    };

    expect($keysInTable(['catalogue' => 'in_catalogue']))->toContain($stockKey)
        ->and($keysInTable(['catalogue' => 'not_in_catalogue']))->not->toContain($stockKey)
        ->and($keysInTable(['source' => 'purchase_order']))->not->toContain($stockKey)
        ->and($keysInTable(['source' => 'stock_delivery', 'arrival' => 'month']))->toContain($stockKey);
    request()->offsetUnset('on_its_way_facets');

    $masterShop = StoreMasterShop::make()->action($this->group, ['type' => ShopTypeEnum::B2B, 'code' => 'ETA'.substr(uniqid(), -6), 'name' => 'ETA master']);
    $product->shop->updateQuietly(['master_shop_id' => $masterShop->id]);

    expect(collect(GetCatalogueOnItsWay::run($masterShop))->firstWhere('key', $stockKey)['products'])->toBe([]);

    $masterFamily = StoreMasterFamily::make()->action(
        StoreMasterDepartment::make()->action($masterShop, ['code' => 'ETAD'.substr(uniqid(), -6), 'name' => 'dep', 'type' => MasterProductCategoryTypeEnum::DEPARTMENT]),
        ['code' => 'ETAF'.substr(uniqid(), -6), 'name' => 'fam', 'type' => MasterProductCategoryTypeEnum::FAMILY]
    );
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, ['code' => 'ETA-MA', 'name' => 'eta master asset', 'is_main' => true, 'type' => MasterAssetTypeEnum::PRODUCT, 'price' => 10, 'stocks' => []]);
    $masterAsset->stocks()->sync([$orgStock->stock_id => ['quantity' => 1]]);

    $masterOnItsWay = collect(GetCatalogueOnItsWay::run($masterShop))->firstWhere('key', $stockKey);

    expect($masterOnItsWay['products'][0]['slug'])->toBe($masterAsset->slug)
        ->and($masterOnItsWay['lines'][0]['organisation_slug'])->toBe($this->organisation->slug);

    $product->update(['available_quantity' => 0]);
    $productCards = \App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource::collection(collect([$product->fresh()]))->resolve();

    expect($productCards[0]['expected_back_in_stock_at'])->toBe($expectedEta);
});

test('a partly delivered purchase order still shows the lines that are not in the delivery', function () {
    $orderedOrgStock   = createUnlinkedOrgStock($this);
    $deliveredOrgStock = createUnlinkedOrgStock($this);

    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $orgSupplierProducts = [];
    foreach ([$orderedOrgStock, $deliveredOrgStock] as $index => $orgStock) {
        $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
            'code'             => 'PARTIAL-'.$index,
            'name'             => 'Partial asset '.$index,
            'cost'             => 100,
            'stock_id'         => $orgStock->stock_id,
            'units_per_pack'   => 10,
            'units_per_carton' => 100,
        ]);
        $orgSupplierProducts[$index] = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
    }

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProducts[0]->supplierProduct->historicSupplierProduct,
        $orderedOrgStock,
        array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 90])
    );
    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProducts[1]->supplierProduct->historicSupplierProduct,
        $deliveredOrgStock,
        array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 50])
    );
    UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, [
        'reference' => 'PARTIAL-DEL-1',
        'date'      => date('Y-m-d'),
    ]);
    StoreStockDeliveryItem::make()->action(
        $stockDelivery,
        $orgSupplierProducts[1]->supplierProduct->historicSupplierProduct,
        $deliveredOrgStock,
        array_merge(StockDeliveryItem::factory()->definition(), ['unit_quantity' => 50])
    );
    $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);

    [, $product] = createProduct(StoreShop::run($this->organisation, Shop::factory()->definition()));
    $product->orgStocks()->syncWithoutDetaching([$orderedOrgStock->id => ['quantity' => 1]]);
    $product->load('orgStocks');

    $incoming = GetProductIncomingStock::run($product);

    $ordered = collect($incoming)->firstWhere('reference', $purchaseOrder->reference);

    expect($ordered)->not->toBeNull()
        ->and($ordered['type'])->toBe('purchase_order')
        ->and($ordered['quantity'])->toBe(90.0)
        ->and(collect($incoming)->where('org_stock_code', $deliveredOrgStock->code)->count())->toBe(0);
});

test('sko showcase shows days of cover and the purchase orders still to arrive', function () {
    $warehouse = $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    $warehouse->update(['address_id' => Address::factory()->create(['group_id' => $this->group->id])->id]);
    $orgStock = $this->orgStocks[0];

    $orgStock->stats->update([
        'days_of_cover'             => 12.4,
        'predicted_out_of_stock_at' => now()->addDays(12)->toDateString(),
        'predicted_daily_usage'     => 3.5,
    ]);

    $orgSupplier        = $this->orgSupplier;
    $supplierProduct    = StoreSupplierProduct::make()->action($orgSupplier->supplier, [
        'code'     => 'cover-product',
        'name'     => 'Cover product',
        'cost'             => 10,
        'stock_id'         => $orgStock->stock_id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [
        ...PurchaseOrder::factory()->definition(),
        'reference' => 'PO-COVER-'.PurchaseOrder::max('id'),
    ], strict: false);
    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $orgStock,
        [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 40]
    );
    UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    $showcase = $this->get(route('grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', [
        $this->organisation->slug, $warehouse->slug, $orgStock->slug,
    ]))->assertOk()->viewData('page')['props']['showcase'];

    expect($showcase['stocks_management']['cover']['days'])->toBe(12)
        ->and($showcase['stocks_management']['cover']['daily_usage'])->toBe(3.5)
        ->and(collect($showcase['future_orders'])->pluck('reference'))->toContain($purchaseOrder->reference)
        ->and((float) collect($showcase['future_orders'])->firstWhere('reference', $purchaseOrder->reference)['quantity'])->toBe(40.0);
});

test('purchase order products and items tabs show stock and quarterly usage of each product', function () {
    $this->travelTo('2030-05-15 12:00:00');

    $warehouse = $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    $warehouse->update(['address_id' => Address::factory()->create(['group_id' => $this->group->id])->id]);
    $shop      = $this->organisation->shops()->first() ?? StoreShop::run($this->organisation, Shop::factory()->definition());
    [, $product] = createProduct($shop);
    $orgStock    = $product->orgStocks()->first();
    $orgStock->update(['quantity_in_locations' => 17]);

    $order       = createOrder(StoreCustomer::make()->action($shop, Customer::factory()->definition()), $product);
    $transaction = $order->transactions()->first();
    DB::table('transactions')->where('id', $transaction->id)->update(['org_net_amount' => 120]);
    $deliveryNote = StoreDeliveryNote::make()->action($order, [
        'reference'        => 'DN-USAGE-'.uniqid(),
        'state'            => DeliveryNoteStateEnum::UNASSIGNED,
        'email'            => 'usage@example.com',
        'date'             => now()->toDateString(),
        'delivery_address' => new Address(Address::factory()->definition()),
        'warehouse_id'     => $warehouse->id,
    ]);
    DB::table('delivery_note_items')->insert([
        'group_id'            => $deliveryNote->group_id,
        'organisation_id'     => $deliveryNote->organisation_id,
        'shop_id'             => $deliveryNote->shop_id,
        'delivery_note_id'    => $deliveryNote->id,
        'org_stock_id'        => $orgStock->id,
        'transaction_id'      => $transaction->id,
        'quantity_required'   => 12,
        'quantity_dispatched' => 6,
        'data'                => '{}',
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    $supplierProduct    = StoreSupplierProduct::make()->action($this->orgSupplier->supplier, [
        'code'             => 'usage-product',
        'name'             => 'Usage product',
        'cost'             => 10,
        'stock_id'         => $orgStock->stock_id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);
    $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
        ['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $supplierProduct->id],
        ['available' => true]
    );
    DB::table('org_stock_has_org_supplier_products')->updateOrInsert(
        ['org_stock_id' => $orgStock->id, 'org_supplier_product_id' => $orgSupplierProduct->id],
        ['stock_has_supplier_product_id' => $stockHasSupplierProduct->id, 'status' => true, 'local_priority' => 1, 'created_at' => now(), 'updated_at' => now()]
    );

    $purchaseOrder = StorePurchaseOrder::make()->action($this->orgSupplier, [
        ...PurchaseOrder::factory()->definition(),
        'reference' => 'PO-USAGE-'.PurchaseOrder::max('id'),
    ], strict: false);

    foreach ([[now()->firstOfQuarter(), 0], [now()->firstOfQuarter()->addDay(), 0.667]] as [$outOfStockDay, $leftInLocations]) {
        $organisationStockHistoryId = DB::table('organisation_stock_histories')
            ->where('organisation_id', $orgStock->organisation_id)->where('date', $outOfStockDay->toDateString())->value('id')
            ?? DB::table('organisation_stock_histories')->insertGetId([
                'group_id'                       => $orgStock->group_id,
                'organisation_id'                => $orgStock->organisation_id,
                'date'                           => $outOfStockDay->toDateString(),
                'number_org_stocks'              => 0,
                'number_out_of_stock_org_stocks' => 0,
                'number_location_org_stocks'     => 0,
            ]);
        DB::table('org_stock_histories')->insert([
            'organisation_stock_history_id' => $organisationStockHistoryId,
            'organisation_id'               => $orgStock->organisation_id,
            'org_stock_id'                  => $orgStock->id,
            'date'                          => $outOfStockDay->toDateString(),
            'quantity_in_locations'         => $leftInLocations,
        ]);
    }

    $comingStockDelivery = StoreStockDelivery::make()->action($this->orgSupplier, ['reference' => 'SD-COMING-'.uniqid(), 'date' => now()->toDateString()], strict: false);
    StoreStockDeliveryItem::make()->action($comingStockDelivery, null, $orgStock, ['unit_quantity' => 30, 'state' => StockDeliveryItemStateEnum::IN_PROCESS], strict: false);

    $orgStock->stats()->updateOrCreate([], ['days_of_cover' => 45.5, 'days_of_cover_pessimistic' => 30, 'predicted_out_of_stock_at' => '2036-11-15']);
    $stockCover = ['days' => 45.5, 'days_worst_case' => 30.0, 'out_of_stock_at' => '2036-11-15'];

    $products = $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug, 'tab' => 'products']))
        ->assertOk()->viewData('page')['props']['products']['data'];
    $row      = collect($products)->firstWhere('id', $orgSupplierProduct->id);
    $currentQuarter = now()->year.'Q'.now()->quarter;

    expect($row['stock_in_locations'])->toBe('17')
        ->and($row['quarterly_usage'])->toHaveCount(8)
        ->and(collect($row['quarterly_usage'])->last()['period'])->toBe($currentQuarter)
        ->and((float) collect($row['quarterly_usage'])->last()['sales'])->toBe(6.0)
        ->and((float) collect($row['quarterly_usage'])->last()['sales_amount'])->toBe(60.0)
        ->and(collect($row['quarterly_usage'])->last()['currency'])->toBe($this->organisation->currency->code)
        ->and(collect($row['quarterly_usage'])->last()['days_out_of_stock'])->toBe(2)
        ->and(collect($row['quarterly_usage'])->last()['top_customer'])->toMatchArray(['id' => $deliveryNote->customer_id, 'sales' => 6.0])
        ->and(collect($row['stock_deliveries']['coming'])->pluck('reference'))->toContain($comingStockDelivery->reference)
        ->and((float) collect($row['stock_deliveries']['coming'])->firstWhere('reference', $comingStockDelivery->reference)['quantity'])->toBe(30.0)
        ->and(Arr::only($row['stock_cover'], array_keys($stockCover)))->toEqual($stockCover)
        ->and($row['stock_cover']['overstock_days'])->toBe(GetOrganisationStockCoverBuckets::EXCESS_DAYS)
        ->and($row['stock_cover']['lead_time_days'])->toBeInt();

    $agentId = $purchaseOrder->agent_id;
    $purchaseOrder->updateQuietly(['agent_id' => null]);
    $directProducts = fn (array $filter = []) => $this->get(route('grp.org.procurement.purchase_orders.show', [
        $this->organisation->slug, $purchaseOrder->slug, 'tab' => 'products', 'products_filter' => ['global' => $supplierProduct->code, ...$filter],
    ]))->assertOk()->viewData('page')['props']['products'];
    $stockGroup      = collect($directProducts()['data'])->firstWhere('id', $orgSupplierProduct->id)['bucket'];
    $inItsStockGroup = $directProducts(['cover' => $stockGroup]);
    $inAnotherGroup  = $directProducts(['cover' => $stockGroup === 'never' ? 'out' : 'never']);
    $openOrder = StorePurchaseOrder::make()->action($this->orgSupplier, [...PurchaseOrder::factory()->definition(), 'reference' => 'PO-OPEN-'.PurchaseOrder::max('id')], strict: false);
    $openLine  = StorePurchaseOrderTransaction::make()->action($openOrder, $supplierProduct->historicSupplierProduct, $orgStock, PurchaseOrderTransaction::factory()->definition());
    $openOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $notOnOrder = $directProducts(['hide_ordered' => 1]);
    $openLine->forceDelete();
    $openOrder->forceDelete();
    $purchaseOrder->updateQuietly(['agent_id' => $agentId]);

    expect(collect($notOnOrder['data'])->pluck('id'))->not->toContain($orgSupplierProduct->id)
        ->and($notOnOrder['ordering']['active']['hide_ordered'])->toBeTrue()
        ->and(collect($notOnOrder['ordering']['cover'])->sum('count'))->toBe(0);

    expect($stockGroup)->toBeIn(array_keys(App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets::BUCKETS))
        ->and(collect($inItsStockGroup['data'])->pluck('id'))->toContain($orgSupplierProduct->id)
        ->and(collect($inItsStockGroup['data'])->pluck('bucket')->unique()->all())->toBe([$stockGroup])
        ->and(collect($inItsStockGroup['ordering']['cover'])->firstWhere('value', $stockGroup)['count'])->toBe($inItsStockGroup['meta']['total'])
        ->and($inItsStockGroup['ordering']['active']['cover'])->toBe($stockGroup)
        ->and(collect($inAnotherGroup['data'])->pluck('id'))->not->toContain($orgSupplierProduct->id);

    $transaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $supplierProduct->historicSupplierProduct,
        $orgStock,
        PurchaseOrderTransaction::factory()->definition()
    );

    $items = $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug, 'tab' => 'items']))
        ->assertOk()->viewData('page')['props']['items']['data'];
    $item  = collect($items)->firstWhere('code', $supplierProduct->code);

    expect($item['stock_in_locations'])->toBe('17')
        ->and((float) collect($item['quarterly_usage'])->last()['sales'])->toBe(6.0)
        ->and(collect($item['quarterly_usage'])->last()['days_out_of_stock'])->toBe(2)
        ->and(collect($item['stock_deliveries']['coming'])->pluck('reference'))->toContain($comingStockDelivery->reference)
        ->and(Arr::only($item['stock_cover'], array_keys($stockCover)))->toEqual($stockCover)
        ->and($item['stock_cover']['overstock_days'])->toBe(GetOrganisationStockCoverBuckets::EXCESS_DAYS)
        ->and($item['stock_cover']['lead_time_days'])->toBeInt();

    DB::table('stock_delivery_items')->where('stock_delivery_id', $comingStockDelivery->id)->update(['unit_quantity_checked' => 20, 'unit_quantity_placed' => 20]);
    DB::table('stock_deliveries')->where('id', $comingStockDelivery->id)->update(['state' => StockDeliveryStateEnum::BOOKED_IN->value, 'received_at' => now()]);

    $items = $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug, 'tab' => 'items']))
        ->assertOk()->viewData('page')['props']['items']['data'];
    $item  = collect($items)->firstWhere('code', $supplierProduct->code);

    expect(collect($item['stock_deliveries']['coming'])->pluck('reference'))->not->toContain($comingStockDelivery->reference)
        ->and($item['stock_deliveries']['last_received']['reference'])->toBe($comingStockDelivery->reference);

    DB::table('delivery_note_items')->where('delivery_note_id', $deliveryNote->id)->update(['quantity_dispatched' => 0]);
    DB::table('org_stock_histories')->where('org_stock_id', $orgStock->id)->where('quantity_in_locations', 0)->delete();
});

test('stock delivery pdf downloads', function () {
    $response = $this->get(route('grp.org.procurement.stock_deliveries.pdf', [$this->organisation->slug, $this->stockDelivery->slug]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('procurement pdf links end in the file name the browser saves it under', function () {
    $this->stockDelivery->update(['reference' => 'SD 143_UK']);
    $filename = PdfStockDelivery::make()->filename($this->stockDelivery);

    $actions = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $this->stockDelivery->slug]))
        ->assertOk()->viewData('page')['props']['pageHead']['actions'];
    $pdfRoute = collect($actions)->firstWhere('route.name', 'grp.org.procurement.stock_deliveries.pdf')['route'];

    expect($filename)->toStartWith('SD-143_UK-')->toEndWith('.pdf')
        ->and($pdfRoute['parameters']['filename'])->toBe($filename);

    $this->get(route($pdfRoute['name'], $pdfRoute['parameters']))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="'.$filename.'"');

    $purchaseOrder = PurchaseOrder::where('organisation_id', $this->organisation->id)->firstOrFail();
    $this->get(route('grp.org.procurement.purchase_orders.pdf', [$this->organisation->slug, $purchaseOrder->slug, PdfPurchaseOrder::make()->filename($purchaseOrder)]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('stock delivery pdf is not found under another organisation', function () {
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));

    $this->get(route('grp.org.procurement.stock_deliveries.pdf', [$otherOrganisation->slug, $this->stockDelivery->slug]))
        ->assertNotFound();
});

test('stock delivery pdf is forbidden without procurement permission', function () {
    setPermissionsTeamId($this->group->id);
    $guest = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => []]));

    actingAs($guest->getUser());

    $this->get(route('grp.org.procurement.stock_deliveries.pdf', [$this->organisation->slug, $this->stockDelivery->slug]))
        ->assertForbidden();
});

test('stock put away from a delivery is valued at the line price, then at the landed cost once the delivery is costed', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-VALUED', [72]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = UpdateStockDeliveryItem::make()->action($stockDelivery->items()->first(), ['net_amount' => 360], strict: false);
    $packedIn          = $stockDeliveryItem->orgStock->packed_in;
    $stockDeliveryItem->orgStock->update(['packed_in' => 6]);
    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem->fresh(), ['sko_quantity_checked' => 12]);

    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);
    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 4, 'location_org_stock_id' => $locationOrgStock->id]);
    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, ['location_org_stock_id' => $locationOrgStock->id]);

    $movementIds  = $stockDeliveryItem->sowings()->orderBy('id')->pluck('org_stock_movement_id');
    $movements    = OrgStockMovement::whereIn('id', $movementIds)->orderBy('id')->get();
    $deliveryCost = $stockDeliveryItem->org_net_amount / 12;

    expect($deliveryCost)->toBeGreaterThan(0)
        ->and($movements)->toHaveCount(2)
        ->and((float) $movements[0]->cost_per_sku)->toEqualWithDelta($deliveryCost, 0.000001)
        ->and((float) $movements[0]->org_amount)->toEqualWithDelta(4 * $deliveryCost, 0.001)
        ->and((float) $movements[1]->org_amount)->toEqualWithDelta(8 * $deliveryCost, 0.001)
        ->and($movements[1]->cost_status)->toBe(OrgStockMovementCostStatusEnum::DELIVERY);

    $runningValueAtDeliveryCost = (float) $movements[1]->running_lpp_value;
    expect($runningValueAtDeliveryCost)->toBeGreaterThan(0);

    $stockDelivery = StartStockDeliveryCosting::make()->action($stockDelivery->fresh());
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, 'amount' => $stockDeliveryItem->net_amount, 'received_at' => now()]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::SHIPPING->value, 'amount' => 120, 'received_at' => now()]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);

    $stockDeliveryItem = $stockDeliveryItem->fresh();
    $landedCost        = $stockDeliveryItem->cost_total * ($stockDeliveryItem->org_exchange ?? 1) / 12;
    $movements         = OrgStockMovement::whereIn('id', $movementIds)->orderBy('id')->get();

    expect($stockDelivery->fresh()->is_costed)->toBeTrue()
        ->and((float) $stockDeliveryItem->cost_total)->toEqualWithDelta($stockDeliveryItem->net_amount + 120, 0.01)
        ->and((float) $movements[0]->cost_per_sku)->toEqualWithDelta($landedCost, 0.000001)
        ->and((float) $movements[0]->org_amount)->toEqualWithDelta(4 * $landedCost, 0.001)
        ->and((float) $movements[1]->org_amount)->toEqualWithDelta(8 * $landedCost, 0.001)
        ->and($movements[1]->cost_status)->toBe(OrgStockMovementCostStatusEnum::COSTED)
        ->and((float) $movements[1]->running_lpp_value)->toEqualWithDelta($runningValueAtDeliveryCost / $deliveryCost * $landedCost, 0.02);

    $stockDeliveryItem->orgStock->update(['packed_in' => $packedIn]);
});

test('a placed agent container is costed from its agent invoice and completes itself once our organisation adds the customs', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'AGENT-COST-'.Str::random(6), [10, 20]);
    $stockDelivery->updateQuietly(['agent_id' => $this->agent->id]);
    $items = $stockDelivery->items()->orderBy('id')->get();
    $items[0]->updateQuietly(['net_amount' => 100]);
    $items[1]->updateQuietly(['net_amount' => 300]);

    $invoice = StoreAgentInvoice::make()->handle($this->agent, $stockDelivery->refresh());
    UpdateAgentInvoiceCharges::make()->handle($invoice, ['charges' => [
        ['description' => 'Sea freight', 'type' => 'freight', 'amount' => 50],
        ['description' => 'Commission', 'type' => 'other', 'amount' => 40],
    ]]);

    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery->refresh());
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    foreach ($stockDelivery->items()->orderBy('id')->get() as $index => $item) {
        $item = SetStockDeliveryItemCheckedQuantity::make()->action($item->fresh(), ['unit_quantity_checked' => $index === 0 ? 12 : 15]);
        SetStockDeliveryItemAsPlaced::make()->action($item, ['location_org_stock_id' => createLocationOrgStockFor($this, $item)->id]);
    }

    $stockDelivery = StartStockDeliveryCosting::make()->action($stockDelivery->fresh());

    expect($stockDelivery->costs()->count())->toBe(0);

    ApproveAgentInvoiceCharges::make()->handle($stockDelivery);
    $stockDelivery = $stockDelivery->fresh();
    $items         = $stockDelivery->items()->orderBy('id')->get();
    $costs         = $stockDelivery->costs()->get();

    expect((float) $items[0]->cost_items)->toBe(100.0)
        ->and((float) $items[1]->cost_items)->toBe(225.0)
        ->and((float) $costs->firstWhere('type', StockDeliveryCostTypeEnum::SHIPPING)->amount)->toBe(50.0)
        ->and((float) $costs->where('type', StockDeliveryCostTypeEnum::EXTRA)->sum('amount'))->toBe(40.0)
        ->and((float) $costs->firstWhere('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->amount)->toBe(490.0)
        ->and($stockDelivery->is_costed)->toBeFalse();

    $orgExchange  = (float) ($stockDelivery->org_exchange ?: 1);
    $localFreight = StoreStockDeliveryServiceInvoice::make()->action($stockDelivery->organisation, [
        'type'         => StockDeliveryServiceInvoiceTypeEnum::FREIGHT->value,
        'issuer'       => 'Local Haulier',
        'date'         => '2026-10-04',
        'currency_id'  => $stockDelivery->organisation->currency_id,
        'total_amount' => 30,
        'allocations'  => [['stock_delivery_id' => $stockDelivery->id]],
    ]);
    $shipping = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->sole();

    expect($shipping->amountInDeliveryCurrency())->toEqualWithDelta(50 + 30 / $orgExchange, 0.02 / $orgExchange)
        ->and($shipping->label)->toBe('Sea freight, Local Haulier')
        ->and((float) $stockDelivery->items()->sum('cost_shipping'))->toEqualWithDelta(50 + 30 / $orgExchange, 0.02 / $orgExchange);

    DeleteStockDeliveryServiceInvoice::make()->action($localFreight);
    $shipping->refresh();

    expect((float) $shipping->amount)->toBe(50.0)
        ->and($shipping->currency_id)->toBeNull()
        ->and($shipping->from_service_invoices)->toBeFalse()
        ->and($shipping->received_at)->not->toBeNull();

    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'amount' => 65, 'received_at' => now()]);

    $stockDelivery = $stockDelivery->fresh();
    $items         = $stockDelivery->items()->orderBy('id')->get();

    expect($stockDelivery->is_costed)->toBeTrue()
        ->and((float) $items->sum('cost_extra'))->toEqualWithDelta(40, 0.001)
        ->and((float) $items->sum('cost_shipping'))->toEqualWithDelta(50, 0.001)
        ->and((float) $items->sum('cost_duties'))->toEqualWithDelta(65 / (float) ($stockDelivery->org_exchange ?: 1), 0.02)
        ->and((float) $items[1]->cost_extra)->toBeGreaterThan((float) $items[0]->cost_extra)
        ->and(ApplyAgentInvoiceCosting::run($stockDelivery))->toBeNull();
});

test('extra costs split by hand line by line survive finishing the costing (HELP-3566)', function () {
    $stockDelivery = placedStockDeliveryWithTwoLines($this, 'COST-BY-HAND');
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();

    expect((float) $first->cost_extra)->toBe(20.0)
        ->and((float) $second->cost_extra)->toBe(60.0);

    UpdateStockDeliveryItemCost::make()->action($first, ['cost_extra' => 0]);
    UpdateStockDeliveryItemCost::make()->action($second, ['cost_extra' => 80]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);

    expect($stockDelivery->fresh()->is_costed)->toBeTrue()
        ->and((float) $first->fresh()->cost_extra)->toBe(0.0)
        ->and((float) $second->fresh()->cost_extra)->toBe(80.0)
        ->and((float) $second->fresh()->cost_total)->toBe(380.0);
});

test('a hand split that does not add up keeps the costing open, and a new cost total splits by value again (HELP-3566)', function () {
    $stockDelivery = placedStockDeliveryWithTwoLines($this, 'COST-UNBALANCED');
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();

    UpdateStockDeliveryItemCost::make()->action($first, ['cost_extra' => 0]);
    UpdateStockDeliveryItemCost::make()->action($second, ['cost_extra' => 70]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);

    expect($stockDelivery->fresh()->is_costed)->toBeFalse()
        ->and(EvaluateStockDeliveryCosting::unbalancedHandSplits($stockDelivery->fresh()))->toBe(['cost_extra' => ['allocated' => 70.0, 'amount' => 80.0]]);

    $extraCost = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->first();
    UpdateStockDeliveryCost::make()->action($extraCost, ['amount' => 120]);

    expect($stockDelivery->fresh()->is_costed)->toBeTrue()
        ->and((float) $first->fresh()->cost_extra)->toBe(30.0)
        ->and((float) $second->fresh()->cost_extra)->toBe(90.0);
});

test('costs not split by hand follow the goods costs when they change (HELP-3566)', function () {
    $stockDelivery = placedStockDeliveryWithTwoLines($this, 'COST-REWEIGHT');
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();

    UpdateStockDeliveryItemCost::make()->action($first, ['cost_items' => 300]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);

    expect((float) $first->fresh()->cost_extra)->toBe(40.0)
        ->and((float) $second->fresh()->cost_extra)->toBe(40.0);
});

test('shipping is split by the weight received, duty and extra stay by value', function () {
    $stockDelivery = weighedPlacedStockDelivery($this, 'COST-WEIGHT', [100, 400]);
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();
    $second->update(['unit_quantity_checked' => 8]);

    setShippingAndDuty($stockDelivery, 130, 40);

    expect((float) $first->fresh()->cost_shipping)->toBe(50.0)
        ->and((float) $second->fresh()->cost_shipping)->toBe(80.0)
        ->and((float) $first->fresh()->cost_duties)->toBe(10.0)
        ->and((float) $second->fresh()->cost_duties)->toBe(30.0)
        ->and((float) $first->fresh()->cost_extra)->toBe(20.0)
        ->and((float) $second->fresh()->cost_extra)->toBe(60.0)
        ->and((float) $first->fresh()->cost_total)->toBe(180.0)
        ->and(\App\Actions\GoodsIn\StockDelivery\DistributeStockDeliveryExtraCost::shippingBasis($stockDelivery->fresh()))->toBe(['basis' => 'by_weight', 'missing_count' => 0, 'missing_codes' => []]);
});

test('shipping is split by value for the whole delivery when one item has no weight', function () {
    $stockDelivery = weighedPlacedStockDelivery($this, 'COST-NO-WEIGHT', [100, 400]);
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();
    \App\Models\Goods\Stock::whereKey($second->orgStock->stock_id)->update(['gross_weight' => null]);

    setShippingAndDuty($stockDelivery, 130);

    $basis = \App\Actions\GoodsIn\StockDelivery\DistributeStockDeliveryExtraCost::shippingBasis($stockDelivery->fresh());

    expect((float) $first->fresh()->cost_shipping)->toBe(32.5)
        ->and((float) $second->fresh()->cost_shipping)->toBe(97.5)
        ->and($basis['basis'])->toBe('by_value')
        ->and($basis['missing_count'])->toBe(1)
        ->and($basis['missing_codes'])->toBe([$second->orgStock->code]);
});

test('an item not received takes no share of shipping, duty or extra, and the others add up to the costs', function () {
    $stockDelivery = weighedPlacedStockDelivery($this, 'COST-NOT-RECEIVED', [100, 400]);
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();

    setShippingAndDuty($stockDelivery, 130, 40);

    expect((float) $first->fresh()->cost_shipping)->toBeGreaterThan(0.0)
        ->and((float) $first->fresh()->cost_extra)->toBeGreaterThan(0.0);

    $first->update(['state' => StockDeliveryItemStateEnum::NOT_RECEIVED, 'not_received_at' => now()]);
    UpdateStockDeliveryCost::make()->action($stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->first(), ['amount' => 140]);

    $first  = $first->fresh();
    $second = $second->fresh();

    expect((float) $first->cost_shipping)->toBe(0.0)
        ->and((float) $first->cost_duties)->toBe(0.0)
        ->and((float) $first->cost_extra)->toBe(0.0)
        ->and((float) $first->cost_total)->toBe((float) $first->cost_items + (float) $first->cost_tax)
        ->and((float) $second->cost_shipping)->toBe(140.0)
        ->and((float) $second->cost_duties)->toBe(40.0)
        ->and((float) $second->cost_extra)->toBe(80.0);
});

test('shipping split by hand is kept when the weight split would differ', function () {
    $stockDelivery = weighedPlacedStockDelivery($this, 'COST-SHIPPING-HAND', [100, 400]);
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();

    setShippingAndDuty($stockDelivery, 130);
    UpdateStockDeliveryItemCost::make()->action($first->fresh(), ['cost_shipping' => 0]);
    UpdateStockDeliveryItemCost::make()->action($second->fresh(), ['cost_shipping' => 130]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);

    expect((float) $first->fresh()->cost_shipping)->toBe(0.0)
        ->and((float) $second->fresh()->cost_shipping)->toBe(130.0)
        ->and(EvaluateStockDeliveryCosting::unbalancedHandSplits($stockDelivery->fresh()))->toBe([]);
});

test('weights of purchase orders and stock deliveries count SKOs, not units', function () {
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $tradeUnit->update(['gross_weight' => 50, 'net_weight' => 40]);
    $stock = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    SyncStockTradeUnits::run($stock, [$tradeUnit->id => ['quantity' => 10]]);
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStock->update(['packed_in' => 10]);

    expect((float) DB::table('model_has_trade_units')->where('model_type', 'OrgStock')->where('model_id', $orgStock->id)->value('quantity'))->toBe(10.0);

    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'SKO-WEIGHT-'.Str::upper(Str::random(6)),
        'name'             => 'SKO weight product',
        'cost'             => 10,
        'stock_id'         => $stock->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $supplierProduct->historicSupplierProduct,
        $orgStock,
        array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 100])
    );

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, ['reference' => 'SKO-WEIGHT-'.Str::upper(Str::random(6)), 'date' => date('Y-m-d')]);
    StoreStockDeliveryItem::make()->action($stockDelivery, $supplierProduct->historicSupplierProduct, $orgStock, ['unit_quantity' => 100]);
    \App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems::run($stockDelivery->refresh());

    $purchaseOrderWeight = \App\Actions\Procurement\PurchaseOrder\UI\ShowPurchaseOrder::make()->getPurchaseOrderWeightAndVolume($purchaseOrder->refresh());
    $stockDeliveryWeight = \App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery::make()->getStockDeliveryWeightAndVolume($stockDelivery->refresh());

    expect($purchaseOrderWeight['gross_weight'])->toBe(5.0)
        ->and($purchaseOrderWeight['net_weight'])->toBe(4.0)
        ->and($stockDeliveryWeight['gross_weight'])->toBe(5.0)
        ->and($stockDeliveryWeight['net_weight'])->toBe(4.0)
        ->and((float) $stockDelivery->gross_weight)->toBe(5.0)
        ->and((float) $stockDelivery->net_weight)->toBe(4.0);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('box_stats.second_block.weight', 5)
            ->etc());
});

test('only an accounting manager can update a costed delivery, every change is audited and the stock is revalued (HELP-3566)', function () {
    $stockDelivery = placedStockDeliveryWithTwoLines($this, 'COST-UPDATE');
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);
    $stockDelivery = $stockDelivery->fresh();
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();

    $suffix            = StockDelivery::count();
    $buyer             = userWithJobPosition($this, 'buy', 'cost-buyer-'.$suffix);
    $accountingManager = userWithJobPosition($this, 'acc-m', 'cost-acc-m-'.$suffix);
    $extraCost         = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->first();

    actingAs($buyer);
    $this->patch(route('grp.models.stock-delivery-cost.update', $extraCost->id), ['amount' => 90])
        ->assertSessionHasErrors('state');
    $this->patch(route('grp.models.stock-delivery.reopen-costing', $stockDelivery->id), ['reason' => 'Wrong split'])
        ->assertForbidden();

    actingAs($accountingManager);
    $this->patch(route('grp.models.stock-delivery.reopen-costing', $stockDelivery->id), [])
        ->assertSessionHasErrors('reason');
    $this->patch(route('grp.models.stock-delivery.reopen-costing', $stockDelivery->id), ['reason' => 'Surcharge is only on the second line'])
        ->assertSessionHasNoErrors();

    $stockDelivery = $stockDelivery->fresh();
    $reopenedAudit = Audit::where('auditable_type', 'StockDelivery')->where('auditable_id', $stockDelivery->id)->where('event', 'costing_reopened')->first();

    expect($stockDelivery->is_costed)->toBeFalse()
        ->and($first->fresh()->is_costed)->toBeFalse()
        ->and($reopenedAudit->user_id)->toBe($accountingManager->id)
        ->and($reopenedAudit->new_values['reason'])->toBe('Surcharge is only on the second line');

    actingAs($buyer);
    $this->patchJson(route('grp.models.stock-delivery-item.update-cost', $first->id), ['cost_extra' => 0])->assertForbidden();
    $this->patch(route('grp.models.stock-delivery-cost.update', $extraCost->id), ['amount' => 80])->assertForbidden();
    expect($stockDelivery->fresh()->is_costed)->toBeFalse();

    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::EXTRA->value, 'label' => 'Na row', 'is_na' => true]);
    expect($stockDelivery->fresh()->is_costed)->toBeFalse();

    actingAs($accountingManager);
    $this->patchJson(route('grp.models.stock-delivery-item.update-cost', $first->id), ['cost_extra' => 0])->assertOk();
    $this->patchJson(route('grp.models.stock-delivery-item.update-cost', $second->id), ['cost_extra' => 80])->assertOk();

    $lineAudit = Audit::where('auditable_type', 'StockDeliveryItem')->where('auditable_id', $second->id)->where('user_id', $accountingManager->id)->first();
    expect((float) Arr::get($lineAudit->old_values, 'cost_extra'))->toBe(60.0)
        ->and((float) Arr::get($lineAudit->new_values, 'cost_extra'))->toBe(80.0);

    Queue::fake();
    $this->patch(route('grp.models.stock-delivery.finish-costing', $stockDelivery->id))
        ->assertSessionHasNoErrors();

    $stockDelivery = $stockDelivery->fresh();
    $second        = $second->fresh();
    $movement      = OrgStockMovement::whereIn('id', $second->sowings()->select('org_stock_movement_id'))->first();

    expect($stockDelivery->is_costed)->toBeTrue()
        ->and(Arr::has($stockDelivery->data, 'costing_reopened'))->toBeFalse()
        ->and((float) $second->cost_extra)->toBe(80.0)
        ->and((float) $movement->org_amount)->toEqualWithDelta(380 * ($second->org_exchange ?? 1), 0.01)
        ->and(Audit::where('auditable_type', 'StockDelivery')->where('auditable_id', $stockDelivery->id)->where('event', 'costing_updated')->value('user_id'))->toBe($accountingManager->id);

    Queue::assertPushed(JobDecorator::class, fn ($job) => $job->getAction() instanceof RebuildOrgStockHistoriesSince
        && $job->getParameters() === [$movement->org_stock_id, Carbon::parse($movement->date)->toDateString()]);

    $this->withoutVite();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug, 'tab' => 'history']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('history.data.0.event', 'costing_updated')
            ->where('costing.can_edit', false)
            ->where('pageHead.actions.0.key', 'reopen_stock_delivery_costing'));

    actingAs($this->adminGuest->getUser());
});

test('rebuilding stock histories since a day writes each past day and today', function () {
    $orgStock = OrgStock::where('organisation_id', $this->organisation->id)->first();

    RebuildOrgStockHistoriesSince::run($orgStock->id, now()->subDay()->toDateString());

    expect(OrganisationStockHistory::where('organisation_id', $this->organisation->id)->where('date', now()->subDay()->toDateString())->exists())->toBeTrue();
});

test('undoing a put away from a delivery takes the stock out at the value it went in at', function () {
    $this->orgStocks[0]->update(['packed_in' => 1]);

    $placeFromDelivery = function (string $code, float $netAmount) {
        $stockDelivery = createStockDeliveryWithItems($this, $code, [10]);
        $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
        $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

        $stockDeliveryItem = UpdateStockDeliveryItem::make()->action($stockDelivery->items()->first(), ['net_amount' => $netAmount], strict: false);
        $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => 10]);

        return UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 4, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id])
            ->sowings()->first();
    };

    $sowing   = $placeFromDelivery('PLACE-UNDONE', 50);
    $purchase = $sowing->orgStockMovement;
    $placeFromDelivery('PLACE-DEARER', 150);

    DeleteSowing::make()->handle($sowing);

    $reversal = OrgStockMovement::where('org_stock_id', $purchase->org_stock_id)->where('id', '>', $purchase->id)
        ->where('type', OrgStockMovementTypeEnum::CANCEL_PURCHASE)->first();

    expect((float) $purchase->org_amount)->toBeGreaterThan(0)
        ->and((float) $reversal->quantity)->toBe(-4.0)
        ->and((float) $reversal->org_amount)->toBe(-(float) $purchase->org_amount);
});

test('stock delivery items can not be checked before the delivery is received', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'CHECK-NOT-RECEIVED', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);

    SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
})->throws(ValidationException::class);

test('a cancelled stock delivery item can not be put away', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-CANCELLED', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);

    CancelStockDelivery::make()->action($stockDelivery->fresh());

    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem->fresh(), ['quantity' => 10, 'location_org_stock_id' => $locationOrgStock->id]);
})->throws(ValidationException::class);

test('a stock delivery with stock already in locations can not be cancelled', function () {
    $this->orgStocks[0]->update(['packed_in' => 1]);
    $stockDelivery = createStockDeliveryWithItems($this, 'CANCEL-PLACED', [10, 10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);
    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 4, 'location_org_stock_id' => $locationOrgStock->id]);

    expect(fn () => CancelStockDelivery::make()->action($stockDelivery->fresh()))->toThrow(ValidationException::class)
        ->and($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::CHECKED)
        ->and((float) $locationOrgStock->fresh()->quantity)->toBe(4.0);
});

test('a dispatched stock delivery with no products can not be received and is cancelled instead', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'EMPTY-DISPATCHED', []);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);

    $this->withoutVite();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions.0.key', 'cancel_stock_delivery')
            ->where('pageHead.actions', fn ($actions) => collect($actions)->pluck('key')->doesntContain('receive_stock_delivery')));

    expect(fn () => ReceiveStockDelivery::make()->action($stockDelivery->fresh()))->toThrow(ValidationException::class);

    CancelStockDelivery::make()->action($stockDelivery->fresh());

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::CANCELLED);
});

test('a put away can not be undone once the delivery is booked in', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'UNDO-BOOKED-IN', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, ['location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id]);

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);

    DeleteSowing::make()->action($stockDeliveryItem->sowings()->first());
})->throws(ValidationException::class);

test('undoing a put away needs procurement permission', function () {
    $this->orgStocks[0]->update(['packed_in' => 1]);
    $stockDelivery = createStockDeliveryWithItems($this, 'UNDO-FORBIDDEN', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);
    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 4, 'location_org_stock_id' => $locationOrgStock->id]);

    setPermissionsTeamId($this->group->id);
    $guest = StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => []]));
    actingAs($guest->getUser());

    $this->delete(route('grp.models.sowing.delete', $stockDeliveryItem->sowings()->first()->id))->assertForbidden();

    expect((float) $locationOrgStock->fresh()->quantity)->toBe(4.0);
});

test('the items hydrator does not move a booked in delivery back to checked', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'HYDRATE-BOOKED-IN', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);

    $stockDelivery->fresh()->update(['state' => StockDeliveryStateEnum::BOOKED_IN]);
    StockDeliveriesHydrateItems::run($stockDelivery->fresh());

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);
});

test('place all puts away a checked quantity that is not a whole number of SKOs', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-ALL-FRACTION', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = $stockDelivery->items()->first();
    $packedIn          = $stockDeliveryItem->orgStock->packed_in;
    $stockDeliveryItem->orgStock->update(['packed_in' => 3]);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem->fresh(), ['unit_quantity_checked' => 10]);
    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, ['location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id]);

    expect((float) $stockDeliveryItem->unit_quantity_placed)->toBe(10.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED);

    $stockDeliveryItem->orgStock->update(['packed_in' => $packedIn]);
});

test('a fetched delivery linked to its purchase order by their shared aurora line is counted as on its way once', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);

    $supplier           = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier        = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'LINK-01',
        'name'             => 'Linked asset',
        'cost'             => 100,
        'stock_id'         => $orgStock->stock_id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    $auroraLineSourceId = $this->organisation->id.':'.random_int(900000000, 999999999);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $supplierProduct->historicSupplierProduct,
        $orgStock,
        array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 80])
    )->update(['source_id' => $auroraLineSourceId]);
    UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, [
        'reference' => 'LINK-DEL-1',
        'date'      => date('Y-m-d'),
    ]);
    StoreStockDeliveryItem::make()->action(
        $stockDelivery,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $orgStock,
        array_merge(StockDeliveryItem::factory()->definition(), ['unit_quantity' => 80])
    )->update(['source_id' => $auroraLineSourceId]);

    [, $product] = createProduct(StoreShop::run($this->organisation, Shop::factory()->definition()));
    $product->orgStocks()->sync([$orgStock->id => ['quantity' => 1]]);
    $product->load('orgStocks');

    RepairStockDeliveryPurchaseOrderLinks::run();

    expect($stockDelivery->purchaseOrders()->pluck('purchase_orders.id')->all())->toBe([$purchaseOrder->id])
        ->and($stockDelivery->refresh()->number_purchase_orders)->toBe(1)
        ->and(RepairStockDeliveryPurchaseOrderLinks::run())->toBe(0)
        ->and(collect(GetProductIncomingStock::run($product))->pluck('type')->all())->toBe(['purchase_order']);

    DispatchStockDelivery::make()->action($stockDelivery);

    $incoming = GetProductIncomingStock::run($product);

    expect($incoming)->toHaveCount(1)
        ->and($incoming[0]['type'])->toBe('stock_delivery')
        ->and($incoming[0]['quantity'])->toBe(80.0);
});

test('procurement exchange rates keep their precision, the ones four decimals rounded away are derived again with their amounts, aurora own rates stay', function () {
    $rupiah = Currency::where('code', 'IDR')->firstOrFail();
    $rupee  = Currency::where('code', 'INR')->firstOrFail();
    $euro   = Currency::where('code', 'EUR')->firstOrFail();
    $date   = '2019-03-14';

    foreach (['IDR' => 18500.1234, 'INR' => 93.1234, 'USD' => 1.3187, 'EUR' => 1.1654] as $code => $exchange) {
        DB::table('currency_exchanges')->upsert(
            ['currency_id' => Currency::where('code', $code)->value('id'), 'date' => $date, 'exchange' => $exchange, 'source' => 'M', 'created_at' => now(), 'updated_at' => now()],
            ['currency_id', 'date'],
            ['exchange']
        );
    }

    $orgRate = GetHistoricCurrencyExchange::run($rupiah, $this->organisation->currency, Carbon::parse($date));
    $grpRate = GetHistoricCurrencyExchange::run($rupiah, $this->organisation->group->currency, Carbon::parse($date));
    $rupeeRate = GetHistoricCurrencyExchange::run($rupee, $this->organisation->group->currency, Carbon::parse($date));

    $supplier        = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier     = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'RUPIAH-01',
        'name'             => 'Rupiah priced',
        'cost'             => 100,
        'stock_id'         => $this->orgStocks[0]->stock_id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, ['reference' => 'RUPIAH-PO', 'date' => $date, 'currency_id' => $rupiah->id]);
    $transaction   = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, $this->orgStocks[0], ['quantity_ordered' => 10]);

    expect($orgRate)->toBeLessThan(0.0001)
        ->and(round($rupeeRate, 4))->toBe(0.0142)
        ->and((float) $purchaseOrder->org_exchange)->toEqualWithDelta($orgRate, 1e-10);

    $stockDelivery = createStockDeliveryWithItems($this, 'RUPIAH-DEL', [10]);
    $deliveryItem  = $stockDelivery->items()->first();
    $euroDelivery  = createStockDeliveryWithItems($this, 'EURO-DEL', [10]);
    $rupeeDelivery = createStockDeliveryWithItems($this, 'RUPEE-DEL', [10]);
    $rupeeItem     = $rupeeDelivery->items()->first();

    $purchaseOrder->updateQuietly(['org_exchange' => 0.0001, 'grp_exchange' => 0]);
    $euroDelivery->updateQuietly(['currency_id' => $euro->id, 'date' => $date, 'org_exchange' => 1.2, 'grp_exchange' => 0]);
    $transaction->updateQuietly(['net_amount' => 1000000, 'org_exchange' => 0.0001, 'org_net_amount' => 100, 'grp_exchange' => 0, 'grp_net_amount' => 0]);
    $stockDelivery->updateQuietly(['currency_id' => $rupiah->id, 'date' => $date, 'org_exchange' => 0, 'grp_exchange' => 0.0001]);
    $deliveryItem->updateQuietly(['net_amount' => 2000000, 'org_exchange' => 0, 'org_net_amount' => 0, 'grp_exchange' => 0.0001, 'grp_net_amount' => 200]);
    $rupeeDelivery->updateQuietly(['currency_id' => $rupee->id, 'date' => $date, 'org_exchange' => 0.0150, 'grp_exchange' => 0.0142]);
    $rupeeItem->updateQuietly(['net_amount' => 1000, 'org_exchange' => 0.0150, 'org_net_amount' => 15, 'grp_exchange' => 0.0142, 'grp_net_amount' => 14.20]);

    (require database_path('migrations/2026_09_25_120100_backfill_procurement_exchanges_lost_to_rounding.php'))->up();

    $purchaseOrder->refresh();
    $transaction->refresh();
    $stockDelivery->refresh();
    $deliveryItem->refresh();

    expect((float) $purchaseOrder->org_exchange)->toEqualWithDelta($orgRate, 1e-10)
        ->and((float) $purchaseOrder->grp_exchange)->toEqualWithDelta($grpRate, 1e-10)
        ->and((float) $transaction->org_exchange)->toEqualWithDelta($orgRate, 1e-10)
        ->and((float) $transaction->org_net_amount)->toEqualWithDelta(1000000 * $orgRate, 0.01)
        ->and((float) $transaction->grp_net_amount)->toEqualWithDelta(1000000 * $grpRate, 0.01)
        ->and((float) $stockDelivery->org_exchange)->toEqualWithDelta($orgRate, 1e-10)
        ->and((float) $stockDelivery->grp_exchange)->toEqualWithDelta($grpRate, 1e-10)
        ->and((float) $deliveryItem->org_net_amount)->toEqualWithDelta(2000000 * $orgRate, 0.01)
        ->and((float) $deliveryItem->grp_net_amount)->toEqualWithDelta(2000000 * $grpRate, 0.01)
        ->and((float) $euroDelivery->fresh()->org_exchange)->toBe(1.2)
        ->and((float) $euroDelivery->fresh()->grp_exchange)->toBe(0.0)
        ->and((float) $rupeeDelivery->fresh()->org_exchange)->toBe(0.015)
        ->and((float) $rupeeDelivery->fresh()->grp_exchange)->toEqualWithDelta($rupeeRate, 1e-10)
        ->and((float) $rupeeItem->fresh()->org_net_amount)->toBe(15.0)
        ->and((float) $rupeeItem->fresh()->grp_net_amount)->toEqualWithDelta(1000 * $rupeeRate, 0.01);
});

test('procurement settings let the organisation connect a supplier mailbox of its own', function () {
    $originalSettings = $this->organisation->settings;

    $this->get(route('grp.org.procurement.settings.edit', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EditModel')
            ->where('formData.blueprint.0.fields.mailbox.type', 'mailbox_connect')
            ->where('formData.blueprint.0.fields.mailbox.value.connected', false));

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                    => \Illuminate\Support\Facades\Http::response(['access_token' => 'at', 'refresh_token' => 'rt']),
        'gmail.googleapis.com/gmail/v1/users/me/profile' => \Illuminate\Support\Facades\Http::response(['emailAddress' => 'purchasing@org.test', 'historyId' => '9']),
    ]);

    $state = ['procurement_organisation_id' => $this->organisation->id, 'user_id' => 1, 'return' => '/back'];

    \App\Actions\Comms\Mailbox\CallbackProcurementMailbox::make()->handle($this->organisation, 'code', $state);

    expect(session('notification')['status'])->toBe('success')
        ->and(Arr::get($this->organisation->fresh()->settings, 'procurement.gmail.email'))->toBe('purchasing@org.test');

    $this->post(route('grp.org.procurement.settings.mailbox.disconnect', [$this->organisation->slug]))->assertRedirect();

    expect(Arr::get($this->organisation->fresh()->settings, 'procurement.gmail'))->toBeNull();

    $this->organisation->update(['settings' => $originalSettings]);
});

test('procurement settings connect the WeCom chat archive of an agent in China, keeping its secrets encrypted and out of the page', function () {
    $agentOrganisation = $this->agent->organisation;
    $originalCountryId = $agentOrganisation->country_id;
    $originalSettings  = $agentOrganisation->settings;
    $shopSettings      = $this->organisation->settings;

    $agentOrganisation->country_id = \App\Models\Helpers\Country::where('code', 'CN')->value('id');
    $agentOrganisation->settings   = Arr::except($originalSettings ?? [], 'procurement.wechat');
    $agentOrganisation->saveQuietly();
    $agentOrganisation->refresh();

    expect(UpdateProcurementSettings::usesWechat($agentOrganisation))->toBeTrue()
        ->and(UpdateProcurementSettings::usesWechat($this->organisation))->toBeFalse();

    $this->get(route('grp.org.procurement.settings.edit', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('formData.blueprint', 2));

    $this->patch(route('grp.org.procurement.settings.update', [$this->organisation->slug]), ['wechat_corp_id' => 'ww0123456789abcdef'])->assertSessionHasNoErrors();

    expect(Arr::get($this->organisation->fresh()->settings, 'procurement.wechat'))->toBeNull();

    $privateKeyPem = '';
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privateKeyPem);
    $archiveSecret = 'archive-'.Str::random(32);
    $rules         = UpdateProcurementSettings::make()->rules();

    expect(\Illuminate\Support\Facades\Validator::make(['wechat_corp_id' => 'ww0123456789abcdef', 'wechat_key_version' => 3, 'wechat_archive_secret' => $archiveSecret, 'wechat_private_key' => $privateKeyPem], $rules)->passes())->toBeTrue()
        ->and(\Illuminate\Support\Facades\Validator::make([
            'wechat_corp_id'     => 'not a corp id',
            'wechat_private_key' => "-----BEGIN PRIVATE KEY-----\nnot-a-key\n-----END PRIVATE KEY-----",
        ], $rules)->errors()->keys())->toEqualCanonicalizing(['wechat_corp_id', 'wechat_private_key']);

    UpdateProcurementSettings::make()->handle($agentOrganisation, [
        'wechat_corp_id'        => 'ww0123456789abcdef',
        'wechat_key_version'    => 3,
        'wechat_archive_secret' => $archiveSecret,
        'wechat_private_key'    => $privateKeyPem,
    ]);

    $wechat = Arr::get($agentOrganisation->fresh()->settings, 'procurement.wechat');

    expect($wechat['corp_id'])->toBe('ww0123456789abcdef')
        ->and($wechat['key_version'])->toBe('3')
        ->and($wechat['archive_secret'])->not->toBe($archiveSecret)
        ->and(Crypt::decryptString($wechat['archive_secret']))->toBe($archiveSecret)
        ->and(Crypt::decryptString($wechat['private_key']))->toBe(trim($privateKeyPem))
        ->and(Arr::get($wechat, 'contact_secret'))->toBeNull();

    $section = \App\Actions\Procurement\Settings\UI\EditProcurementSettings::make()->wechatSection($agentOrganisation->fresh());

    expect($section['fields']['wechat_corp_id']['value'])->toBe('ww0123456789abcdef')
        ->and($section['fields']['wechat_archive_secret']['value'])->toBe('')
        ->and($section['fields']['wechat_archive_secret']['placeholder'])->toBe(__('Saved, type a new one to replace it'))
        ->and($section['fields']['wechat_contact_secret']['placeholder'])->toBe('')
        ->and($section['fields']['wechat_private_key']['value'])->toBe('')
        ->and(json_encode($section))->not->toContain($archiveSecret)
        ->and(json_encode($section))->not->toContain($wechat['archive_secret']);

    UpdateProcurementSettings::make()->handle($agentOrganisation->fresh(), ['wechat_archive_secret' => '']);

    expect(Crypt::decryptString(Arr::get($agentOrganisation->fresh()->settings, 'procurement.wechat.archive_secret')))->toBe($archiveSecret);

    $agentOrganisation->country_id = $originalCountryId;
    $agentOrganisation->settings   = $originalSettings;
    $agentOrganisation->saveQuietly();
    $this->organisation->update(['settings' => $shopSettings]);
});

test('WeCom tells an agent in China that new chats are archived through a signed, encrypted callback, which starts a pull', function () {
    $agentOrganisation = $this->agent->organisation;
    $originalCountryId = $agentOrganisation->country_id;
    $originalSettings  = $agentOrganisation->settings;
    $encodingAesKey    = Str::random(43);
    $crypto            = new \App\Services\Wecom\WecomCallbackCrypto('callbacktoken', $encodingAesKey, 'ww0123456789abcdef');
    $signed            = function (string $encrypted) {
        $parts = ['callbacktoken', '1700000000', 'nonce', $encrypted];
        sort($parts, SORT_STRING);

        return ['msg_signature' => sha1(implode('', $parts)), 'timestamp' => '1700000000', 'nonce' => 'nonce'];
    };

    $settings = Arr::except($originalSettings ?? [], 'procurement.wechat');
    data_set($settings, 'procurement.wechat', ['corp_id' => 'ww0123456789abcdef']);
    $agentOrganisation->country_id = \App\Models\Helpers\Country::where('code', 'CN')->value('id');
    $agentOrganisation->settings   = $settings;
    $agentOrganisation->saveQuietly();

    $this->get(route('webhooks.wecom_archive', $agentOrganisation->slug))->assertNotFound();

    UpdateProcurementSettings::make()->handle($agentOrganisation->fresh(), ['wechat_callback_token' => 'callbacktoken', 'wechat_encoding_aes_key' => $encodingAesKey]);

    expect(\Illuminate\Support\Facades\Validator::make(['wechat_encoding_aes_key' => 'too-short'], UpdateProcurementSettings::make()->rules())->fails())->toBeTrue();

    $echo = $crypto->encrypt('echo-123');
    $this->get(route('webhooks.wecom_archive', [$agentOrganisation->slug, ...$signed($echo), 'echostr' => $echo]))->assertOk()->assertSeeText('echo-123');
    $this->get(route('webhooks.wecom_archive', [$agentOrganisation->slug, ...$signed($echo), 'echostr' => $crypto->encrypt('tampered')]))->assertForbidden();

    \Illuminate\Support\Facades\Queue::fake();

    $notice = $crypto->encrypt('<xml><ToUserName><![CDATA[ww0123456789abcdef]]></ToUserName><MsgType><![CDATA[event]]></MsgType><Event><![CDATA[msgaudit_notify]]></Event></xml>');
    $body   = "<xml><ToUserName><![CDATA[ww0123456789abcdef]]></ToUserName><Encrypt><![CDATA[$notice]]></Encrypt></xml>";

    $this->call('POST', route('webhooks.wecom_archive', [$agentOrganisation->slug, ...$signed($notice), 'nonce' => 'other']), [], [], [], ['CONTENT_TYPE' => 'text/xml'], $body)->assertForbidden();
    \Illuminate\Support\Facades\Queue::assertNothingPushed();

    $this->call('POST', route('webhooks.wecom_archive', [$agentOrganisation->slug, ...$signed($notice)]), [], [], [], ['CONTENT_TYPE' => 'text/xml'], $body)->assertOk()->assertSeeText('success');
    \Illuminate\Support\Facades\Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Procurement\SupplierMessage\Wechat\PullProcurementWechatArchive);

    $this->get(route('webhooks.wecom_archive', $this->organisation->slug))->assertNotFound();

    $agentOrganisation->country_id = $originalCountryId;
    $agentOrganisation->settings   = $originalSettings;
    $agentOrganisation->saveQuietly();
});

test('WeCom archived supplier chats of an agent in China reach the messaging panel and the project they are filed under', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\SupplierWechatMessageStored::class]);

    $agentOrganisation = $this->agent->organisation;
    $originalCountryId = $agentOrganisation->country_id;
    $originalSettings  = $agentOrganisation->settings;
    $token             = Str::lower(Str::random(8));
    $supplierWecomId   = "wm$token";
    $coco              = User::factory()->create(['group_id' => $this->group->id]);

    $settings = Arr::except($originalSettings ?? [], 'procurement.wechat');
    data_set($settings, 'procurement.wechat', [
        'corp_id'        => 'ww0123456789abcdef',
        'archive_secret' => Crypt::encryptString('secret'),
        'private_key'    => Crypt::encryptString('key'),
        'key_version'    => '1',
        'staff'          => ["coco$token" => $coco->id],
    ]);
    $agentOrganisation->country_id = \App\Models\Helpers\Country::where('code', 'CN')->value('id');
    $agentOrganisation->settings   = $settings;
    $agentOrganisation->saveQuietly();
    $agentOrganisation->refresh();

    $sentAt   = fn (int $minutesAgo) => now()->subMinutes($minutesAgo)->getTimestampMs();
    $archive  = [
        1 => ['msgid' => "in-$token", 'action' => 'send', 'from' => $supplierWecomId, 'tolist' => ["coco$token"], 'roomid' => '', 'msgtime' => $sentAt(5), 'msgtype' => 'text', 'text' => ['content' => 'Samples ready Friday']],
        2 => ['msgid' => "out-$token", 'action' => 'send', 'from' => "coco$token", 'tolist' => [$supplierWecomId], 'roomid' => '', 'msgtime' => $sentAt(4), 'msgtype' => 'text', 'text' => ['content' => 'Please send photos']],
        3 => ['msgid' => "staff-$token", 'action' => 'send', 'from' => "coco$token", 'tolist' => ["tomas$token"], 'roomid' => '', 'msgtime' => $sentAt(3), 'msgtype' => 'text', 'text' => ['content' => 'Lunch?']],
        4 => ['msgid' => "group-$token", 'action' => 'send', 'from' => $supplierWecomId, 'tolist' => ["coco$token", "tomas$token"], 'roomid' => "room$token", 'msgtime' => $sentAt(2), 'msgtype' => 'file', 'file' => ['filename' => 'quote.pdf', 'sdkfileid' => "file-$token", 'filesize' => 1200]],
        5 => ['msgid' => "recall-$token", 'action' => 'recall', 'from' => $supplierWecomId, 'tolist' => ["coco$token"], 'roomid' => '', 'msgtime' => $sentAt(1), 'msgtype' => 'revoke'],
    ];
    $client = new class ($archive) extends \App\Services\Wecom\WecomArchiveClient {
        public function __construct(private readonly array $archive)
        {
            parent::__construct('ww0123456789abcdef', 'secret', 'key');
        }

        public function chatData(int $seq, int $limit): array
        {
            return collect($this->archive)->filter(fn (array $message, int $messageSeq) => $messageSeq > $seq)->take($limit)
                ->map(fn (array $message, int $messageSeq) => ['seq' => $messageSeq, 'msgid' => $message['msgid'], 'publickey_ver' => 1, 'encrypt_random_key' => '', 'encrypt_chat_msg' => ''])
                ->values()->all();
        }

        public function decrypt(array $chatData): array
        {
            return $this->archive[$chatData['seq']];
        }
    };
    app()->bind(\App\Services\Wecom\WecomArchiveClient::class, fn () => $client);

    expect(\App\Actions\Procurement\SupplierMessage\Wechat\PullProcurementWechatArchive::run($agentOrganisation))->toBe(3)
        ->and(\App\Actions\Procurement\SupplierMessage\Wechat\PullProcurementWechatArchive::run($agentOrganisation->fresh()))->toBe(0)
        ->and(Arr::get($agentOrganisation->fresh()->settings, 'procurement.wechat.last_seq'))->toBe(5)
        ->and(\App\Models\Procurement\SupplierMessage::where('wechat_message_id', "staff-$token")->exists())->toBeFalse();

    \Illuminate\Support\Facades\Event::assertDispatchedTimes(\App\Events\SupplierWechatMessageStored::class, 3);

    $single = \App\Models\Procurement\SupplierWechatConversation::where('organisation_id', $agentOrganisation->id)->where('external_userid', $supplierWecomId)->sole();
    $group  = \App\Models\Procurement\SupplierWechatConversation::where('organisation_id', $agentOrganisation->id)->where('room_id', "room$token")->sole();
    $reply  = \App\Models\Procurement\SupplierMessage::where('wechat_message_id', "out-$token")->sole();

    expect($single->staff_wecom_userid)->toBe("coco$token")
        ->and($single->messages()->count())->toBe(2)
        ->and($single->last_message_snippet)->toBe('Please send photos')
        ->and($reply->direction)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum::OUTBOUND)
        ->and($reply->user_id)->toBe($coco->id)
        ->and($reply->channel)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum::WECHAT)
        ->and($group->kind)->toBe(\App\Enums\Procurement\SupplierWechatConversation\SupplierWechatConversationKindEnum::GROUP)
        ->and($group->messages()->sole()->attachments[0]['sdk_file_id'])->toBe("file-$token")
        ->and($group->messages()->sole()->body_text)->toBe('[File] quote.pdf');

    $wechatSettings = $agentOrganisation->fresh()->settings;
    data_set($wechatSettings, 'procurement.wechat.contact_secret', Crypt::encryptString('contact-secret'));
    $agentOrganisation->update(['settings' => $wechatSettings]);
    \Illuminate\Support\Facades\Cache::forget("wecom-contact-token:$agentOrganisation->id");
    \Illuminate\Support\Facades\Http::fake([
        'qyapi.weixin.qq.com/cgi-bin/gettoken*'            => \Illuminate\Support\Facades\Http::response(['errcode' => 0, 'access_token' => "token-$token", 'expires_in' => 7200]),
        'qyapi.weixin.qq.com/cgi-bin/externalcontact/get*' => \Illuminate\Support\Facades\Http::response(['errcode' => 0, 'external_contact' => ['name' => 'Wang Wei', 'corp_name' => 'Yiwu Toys'], 'follow_user' => [['userid' => "coco$token", 'remark' => 'Yiwu Toys - Mr Wang']]]),
    ]);

    \App\Actions\Procurement\SupplierWechatConversation\FetchSupplierWechatConversationName::run($single->fresh());

    expect($single->fresh()->displayName())->toBe('Yiwu Toys - Mr Wang')
        ->and($single->fresh()->corp_name)->toBe('Yiwu Toys');

    \Spatie\Permission\Models\Permission::findOrCreate("procurement.$agentOrganisation->id.view");
    \Spatie\Permission\Models\Permission::findOrCreate("procurement.$agentOrganisation->id.edit");
    setPermissionsTeamId($this->group->id);
    $buyer    = User::factory()->create(['group_id' => $this->group->id]);
    $outsider = User::factory()->create(['group_id' => $this->group->id]);
    $buyer->givePermissionTo(["procurement.$agentOrganisation->id.view", "procurement.$agentOrganisation->id.edit"]);

    actingAs($outsider);
    $this->get(route('grp.chat.supplier_wechat.conversations.index'))->assertOk()->assertJsonCount(0, 'data');
    $this->get(route('grp.chat.supplier_wechat.messages.index', $single->ulid))->assertForbidden();

    actingAs($buyer);
    $listed = collect($this->get(route('grp.chat.supplier_wechat.conversations.index'))->assertOk()->json('data'))->keyBy('ulid');

    expect($listed[$single->ulid]['kind'])->toBe('supplier_wechat')
        ->and($listed[$single->ulid]['type'])->toBe('dm')
        ->and($listed[$single->ulid]['unread_count'])->toBe(1)
        ->and($listed[$group->ulid]['type'])->toBe('group');

    $thread = $this->get(route('grp.chat.supplier_wechat.messages.index', $single->ulid))->assertOk()->json('data');

    expect(collect($thread)->pluck('body')->all())->toBe(['Samples ready Friday', 'Please send photos'])
        ->and($thread[0]['is_from_supplier'])->toBeTrue()
        ->and(collect($this->get(route('grp.chat.supplier_wechat.conversations.index'))->json('data'))->firstWhere('ulid', $single->ulid)['unread_count'])->toBe(0);

    $project   = \App\Models\Helpers\TicketProject::create(['group_id' => $this->group->id, 'name' => "Packaging $token", 'owner_id' => $buyer->id, 'start_date' => now()->toDateString()]);
    $milestone = $project->milestones()->create(['name' => 'Samples', 'position' => 1]);
    $other     = \App\Models\Helpers\TicketProject::create(['group_id' => $this->group->id, 'name' => "Other $token", 'owner_id' => $buyer->id, 'start_date' => now()->toDateString()]);

    $this->patchJson(route('grp.chat.supplier_wechat.project.update', $single->ulid), ['ticket_project_id' => $project->id, 'ticket_project_milestone_id' => $other->milestones()->create(['name' => 'Elsewhere', 'position' => 1])->id])
        ->assertJsonValidationErrors('ticket_project_milestone_id');
    $this->patchJson(route('grp.chat.supplier_wechat.project.update', $single->ulid), ['ticket_project_id' => $project->id, 'ticket_project_milestone_id' => $milestone->id])
        ->assertOk()
        ->assertJsonPath('context_url', route('grp.projects.show', $project->slug));

    actingAs($outsider);
    $this->patchJson(route('grp.chat.supplier_wechat.project.update', $single->ulid), ['ticket_project_id' => null])->assertForbidden();

    $seenByBuyer    = \App\Actions\Helpers\TicketProject\UI\ShowTicketProject::supplierChats($project, $buyer);
    $seenByOutsider = \App\Actions\Helpers\TicketProject\UI\ShowTicketProject::supplierChats($project, $outsider);

    expect($single->fresh()->ticket_project_milestone_id)->toBe($milestone->id)
        ->and($seenByBuyer['conversations'])->toHaveCount(1)
        ->and($seenByBuyer['conversations'][0]['milestone'])->toBe('Samples')
        ->and(collect($seenByBuyer['conversations'][0]['messages'])->pluck('body')->all())->toBe(['Samples ready Friday', 'Please send photos'])
        ->and($seenByOutsider['conversations'])->toBe([])
        ->and($seenByOutsider['hidden'])->toBe(1);

    $agentOrganisation->country_id = $originalCountryId;
    $agentOrganisation->settings   = $originalSettings;
    $agentOrganisation->saveQuietly();
});

test('a mailbox already connected to a shop is refused as the procurement mailbox', function () {
    $shop             = $this->organisation->shops()->first() ?? \App\Models\Catalogue\Shop::factory()->create(['organisation_id' => $this->organisation->id, 'group_id' => $this->organisation->group_id]);
    $originalSettings = $shop->settings;
    $shop->update(['settings' => array_merge($shop->settings ?? [], ['gmail' => ['email' => 'care@shop.test']])]);

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                    => \Illuminate\Support\Facades\Http::response(['access_token' => 'at', 'refresh_token' => 'rt']),
        'gmail.googleapis.com/gmail/v1/users/me/profile' => \Illuminate\Support\Facades\Http::response(['emailAddress' => 'care@shop.test', 'historyId' => '9']),
    ]);

    $state = ['procurement_organisation_id' => $this->organisation->id, 'user_id' => 1, 'return' => '/back'];

    \App\Actions\Comms\Mailbox\CallbackProcurementMailbox::make()->handle($this->organisation, 'code', $state);

    expect(session('notification')['status'])->toBe('error')
        ->and(session('notification')['description'])->toContain($shop->name)
        ->and(Arr::get($this->organisation->fresh()->settings, 'procurement.gmail'))->toBeNull();

    $shop->update(['settings' => $originalSettings]);
});

test('procurement mailbox emails are routed to their supplier by address, thread, learned address and domain', function () {
    $originalSettings = $this->organisation->settings;
    $token            = Str::lower(Str::random(8));
    $mailbox          = "purchasing-$token@org.test";

    $settings = $this->organisation->settings ?? [];
    data_set($settings, 'procurement.gmail', ['email' => $mailbox, 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'connected_at' => now()->toIso8601String()]);
    $this->organisation->update(['settings' => $settings]);

    $supplier    = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['email' => "sales@acme-$token.com"]));
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $other       = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['email' => "lin-$token@163.com"]));
    $otherOrgSupplier = $other->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $messages = [
        "m1-$token" => procurementGmailMessage("m1-$token", "t1-$token", "Acme Sales <sales@acme-$token.com>", $mailbox, 'Proforma'),
        "m2-$token" => procurementGmailMessage("m2-$token", "t1-$token", $mailbox, "boss-$token@gmail.com", 'Re: Proforma', ['SENT']),
        "m3-$token" => procurementGmailMessage("m3-$token", "t3-$token", "accounts@acme-$token.com", $mailbox, 'Invoice'),
        "m4-$token" => procurementGmailMessage("m4-$token", "t4-$token", "helper-$token@qq.com", $mailbox, 'Samples'),
        "m5-$token" => procurementGmailMessage("m5-$token", "t5-$token", "helper-$token@qq.com", $mailbox, 'More samples'),
        "m6-$token" => procurementGmailMessage("m6-$token", "t6-$token", "noreply@newsletter-$token.com", $mailbox, 'Deals'),
    ];

    $fakes = ['oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at'])];
    foreach ($messages as $id => $message) {
        $fakes["gmail.googleapis.com/gmail/v1/users/me/messages/$id*"] = \Illuminate\Support\Facades\Http::response($message);
    }
    \Illuminate\Support\Facades\Http::fake($fakes);

    $process = fn (string $id) => \App\Actions\Procurement\SupplierMessage\ProcessProcurementEmail::run($this->organisation->fresh(), $id);

    $byAddress = $process("m1-$token");
    $byThread  = $process("m2-$token");
    $byDomain  = $process("m3-$token");
    $stranger  = $process("m4-$token");

    expect($byAddress->org_supplier_id)->toBe($orgSupplier->id)
        ->and($byAddress->routed_by)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum::ADDRESS)
        ->and($byAddress->attachments[0]['name'])->toBe('proforma.pdf')
        ->and($byThread->org_supplier_id)->toBe($orgSupplier->id)
        ->and($byThread->direction)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum::OUTBOUND)
        ->and($byThread->routed_by)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum::THREAD)
        ->and($byDomain->org_supplier_id)->toBe($orgSupplier->id)
        ->and($byDomain->routed_by)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum::DOMAIN)
        ->and($stranger->org_supplier_id)->toBeNull()
        ->and($process("m6-$token"))->toBeNull()
        ->and($process("m1-$token"))->toBeNull();

    \App\Actions\Procurement\SupplierMessage\AssignSupplierMessage::make()->handle($stranger, $otherOrgSupplier);

    $learned = $process("m5-$token");

    expect($learned->org_supplier_id)->toBe($otherOrgSupplier->id)
        ->and($learned->routed_by)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum::ADDRESS);

    $this->get(route('grp.org.procurement.supplier_messages.index', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Procurement/SupplierMessages')->has('data.data'));

    $this->get(route('grp.org.procurement.supplier_messages.show', [$this->organisation->slug, $byThread->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/SupplierMessage')
            ->has('messages', 2)
            ->where('supplier.name', $supplier->name));

    $this->get(route('grp.org.procurement.org_suppliers.show', [$this->organisation->slug, $orgSupplier->slug, 'tab' => 'inbox']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('inbox.data', 3));

    $this->organisation->update(['settings' => $originalSettings]);
});

test('a submitted purchase order is emailed to the supplier through SES with its PDF and is tracked', function () {
    \App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationOutboxes::run($this->organisation);

    $token       = Str::lower(Str::random(8));
    $supplier    = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['email' => "orders@factory-$token.com"]));
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    StoreSupplierProduct::make()->action($supplier, [
        'code'             => "PO-MAIL-$token",
        'name'             => 'PO mail product',
        'cost'             => 10,
        'stock_id'         => $this->orgStocks[0]->stock_id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    expect(\App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier::recipientEmail($purchaseOrder))->toBe("orders@factory-$token.com")
        ->and(\App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier::outbox($purchaseOrder))->not->toBeNull();

    $dispatchedEmail = \App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier::run($purchaseOrder);

    $sentRow = \App\Models\Procurement\SupplierMessage::where('purchase_order_id', $purchaseOrder->id)->first();

    expect($dispatchedEmail->emailAddress->email)->toBe("orders@factory-$token.com")
        ->and($dispatchedEmail->outbox->code)->toBe(\App\Enums\Comms\Outbox\OutboxCodeEnum::SEND_PURCHASE_ORDER_TO_SUPPLIER)
        ->and(\App\Models\Comms\ModelHasDispatchedEmail::where('model_type', 'PurchaseOrder')->where('model_id', $purchaseOrder->id)->value('dispatched_email_id'))->toBe($dispatchedEmail->id)
        ->and($sentRow->dispatched_email_id)->toBe($dispatchedEmail->id)
        ->and($sentRow->org_supplier_id)->toBe($orgSupplier->id)
        ->and($sentRow->direction)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum::OUTBOUND);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug, 'tab' => 'dispatched_emails']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('dispatched_emails.data', 1));

    \Illuminate\Support\Facades\Queue::fake();

    \App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted::make()->handle($purchaseOrder, sendVia: 'email');
    \App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted::make()->handle(StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition()));

    \Illuminate\Support\Facades\Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier);
    \Illuminate\Support\Facades\Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, 1);
});

test('staff reply to a supplier from Aiku through the procurement mailbox, threaded, and agents route and get their purchase orders by email', function () {
    $originalSettings = $this->organisation->settings;
    $token            = Str::lower(Str::random(8));
    $mailbox          = "buying-$token@org.test";

    $settings = $this->organisation->settings ?? [];
    data_set($settings, 'procurement.gmail', ['email' => $mailbox, 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'connected_at' => now()->toIso8601String()]);
    $this->organisation->update(['settings' => $settings]);

    $supplier    = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['email' => "sales@maker-$token.com"]));
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $agentOrganisation         = $this->orgAgent->agent->organisation;
    $originalAgentEmail        = $agentOrganisation->email;
    $agentOrganisation->email  = "desk@agent-$token.com";
    $agentOrganisation->saveQuietly();

    $inbound = procurementGmailMessage("r1-$token", "rt-$token", "Maker <sales@maker-$token.com>", $mailbox, 'Price list');
    $inbound['payload']['headers'][] = ['name' => 'Message-ID', 'value' => "<orig-$token@maker.com>"];

    $sent = procurementGmailMessage("r2-$token", "rt-$token", $mailbox, "sales@maker-$token.com", 'Re: Price list', ['SENT']);

    $fromAgent = procurementGmailMessage("r3-$token", "ra-$token", "Agent Desk <desk@agent-$token.com>", $mailbox, 'Container booked');

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token'                                   => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        'gmail.googleapis.com/gmail/v1/users/me/messages/send'          => \Illuminate\Support\Facades\Http::response(['id' => "r2-$token", 'threadId' => "rt-$token"]),
        "gmail.googleapis.com/gmail/v1/users/me/messages/r1-$token*"    => \Illuminate\Support\Facades\Http::response($inbound),
        "gmail.googleapis.com/gmail/v1/users/me/messages/r2-$token*"    => \Illuminate\Support\Facades\Http::response($sent),
        "gmail.googleapis.com/gmail/v1/users/me/messages/r3-$token*"    => \Illuminate\Support\Facades\Http::response($fromAgent),
    ]);

    $original = \App\Actions\Procurement\SupplierMessage\ProcessProcurementEmail::run($this->organisation->fresh(), "r1-$token");

    expect($original->header_message_id)->toBe("<orig-$token@maker.com>");

    $this->post(route('grp.org.procurement.supplier_messages.reply', [$this->organisation->slug, $original->id]), [
        'to'      => ["sales@maker-$token.com"],
        'subject' => 'Price list',
        'body'    => 'Thanks, please send the 2027 prices.',
    ])->assertRedirect();

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($token) {
        if (! str_ends_with($request->url(), 'messages/send')) {
            return false;
        }

        $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

        return $request['threadId'] === "rt-$token"
            && str_contains($raw, "In-Reply-To: <orig-$token@maker.com>")
            && str_contains($raw, 'Subject: Re: Price list');
    });

    $reply = \App\Models\Procurement\SupplierMessage::where('gmail_message_id', "r2-$token")->first();

    expect($reply->user_id)->toBe($this->adminGuest->getUser()->id)
        ->and($reply->org_supplier_id)->toBe($orgSupplier->id);

    $agentEmail = \App\Actions\Procurement\SupplierMessage\ProcessProcurementEmail::run($this->organisation->fresh(), "r3-$token");

    expect($agentEmail->org_agent_id)->toBe($this->orgAgent->id)
        ->and($agentEmail->org_supplier_id)->toBeNull();

    $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $this->orgAgent->slug, 'tab' => 'inbox']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('inbox.data', 1)->where('inbox.compose.email.counterpart', 'agent:'.$this->orgAgent->id));

    [$agentOrgSupplier] = createAgentOrgSupplierWithProduct($this);
    $agentPurchaseOrder = (new PurchaseOrder())->forceFill(['organisation_id' => $agentOrgSupplier->organisation_id, 'agent_id' => $agentOrgSupplier->orgAgent->agent_id, 'parent_type' => 'OrgSupplier']);
    $agentPurchaseOrder->setRelation('parent', $agentOrgSupplier);

    expect(\App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier::recipientEmail($agentPurchaseOrder))->toBe("desk@agent-$token.com");

    $agentOrganisation->email = $originalAgentEmail;
    $agentOrganisation->saveQuietly();
    $this->organisation->update(['settings' => $originalSettings]);
});

test('supplier documents in the procurement inbox go on the purchase order and its deliveries, by themselves when the email answers the order', function () {
    \App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationOutboxes::run($this->organisation);

    $originalSettings = $this->organisation->settings;
    $token            = Str::lower(Str::random(8));
    $mailbox          = "docs-$token@org.test";

    $settings = $this->organisation->settings ?? [];
    data_set($settings, 'procurement.gmail', ['email' => $mailbox, 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'connected_at' => now()->toIso8601String()]);
    $this->organisation->update(['settings' => $settings]);

    $supplier      = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['email' => "sales@docs-$token.com"]));
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    StoreSupplierProduct::make()->action($supplier, [
        'code'             => "DOCS-$token",
        'name'             => 'Docs product',
        'cost'             => 10,
        'stock_id'         => $this->orgStocks[0]->stock_id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => "PO-DOCS-$token"]));
    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, ['reference' => "SD-DOCS-$token", 'date' => date('Y-m-d')]);
    $stockDelivery->purchaseOrders()->attach($purchaseOrder->id);

    $dispatchedEmail = \App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier::run($purchaseOrder);
    $dispatchedEmail->update(['ses_id' => "ses-$token"]);

    $answer = procurementGmailMessage("d1-$token", "dt1-$token", "Docs <sales@docs-$token.com>", $mailbox, 'Re: your order');
    $answer['payload']['headers'][] = ['name' => 'In-Reply-To', 'value' => "<ses-$token@eu-west-1.amazonses.com>"];
    $answer['payload']['parts'][1]['filename'] = 'Invoice 2211.pdf';

    $unrelated = procurementGmailMessage("d2-$token", "dt2-$token", "Docs <sales@docs-$token.com>", $mailbox, "Packing for PO-DOCS-$token");
    $unrelated['payload']['parts'][1]['filename'] = 'packing.pdf';

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        "gmail.googleapis.com/gmail/v1/users/me/messages/d1-$token/attachments/*" => \Illuminate\Support\Facades\Http::response(['data' => rtrim(strtr(base64_encode("%PDF invoice $token"), '+/', '-_'), '=')]),
        "gmail.googleapis.com/gmail/v1/users/me/messages/d2-$token/attachments/*" => \Illuminate\Support\Facades\Http::response(['data' => rtrim(strtr(base64_encode("%PDF packing $token"), '+/', '-_'), '=')]),
        "gmail.googleapis.com/gmail/v1/users/me/messages/d1-$token*" => \Illuminate\Support\Facades\Http::response($answer),
        "gmail.googleapis.com/gmail/v1/users/me/messages/d2-$token*" => \Illuminate\Support\Facades\Http::response($unrelated),
    ]);

    $answered = \App\Actions\Procurement\SupplierMessage\ProcessProcurementEmail::run($this->organisation->fresh(), "d1-$token");
    $other    = \App\Actions\Procurement\SupplierMessage\ProcessProcurementEmail::run($this->organisation->fresh(), "d2-$token");

    expect($answered->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($answered->fresh()->attachments[0]['attached_media_id'])->not->toBeNull()
        ->and($purchaseOrder->attachments()->wherePivot('scope', 'Invoice')->count())->toBe(1)
        ->and($stockDelivery->attachments()->wherePivot('scope', 'Invoice')->count())->toBe(1)
        ->and($other->purchase_order_id)->toBeNull()
        ->and($other->attachments[0]['attached_media_id'] ?? null)->toBeNull();

    $this->get(route('grp.org.procurement.supplier_messages.show', [$this->organisation->slug, $other->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('messages.0.attachments.0.suggested_target', 'purchase_order:'.$purchaseOrder->id)
            ->where('messages.0.attachments.0.suggested_scope', 'Packing list')
            ->where('messages.0.attachments.0.attached_to', []));

    $this->post(route('grp.org.procurement.supplier_messages.attachment.attach', [$this->organisation->slug, $other->id, 0]), [
        'target' => 'stock_delivery:'.$stockDelivery->id,
        'scope'  => 'Packing list',
    ])->assertRedirect();

    expect($stockDelivery->attachments()->wherePivot('scope', 'Packing list')->count())->toBe(1)
        ->and($purchaseOrder->attachments()->wherePivot('scope', 'Packing list')->count())->toBe(1);

    $this->get(route('grp.org.procurement.supplier_messages.show', [$this->organisation->slug, $other->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('messages.0.attachments.0.attached_to', 2));

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug, 'tab' => 'attachments']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('attachments.data', 2)->has('attachmentScopes', count(\App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum::cases())));

    $this->organisation->update(['settings' => $originalSettings]);
});

test('a supplier invoice attached to a delivery is read into proposed costs that only reach the costing once reviewed and applied', function () {
    $token         = Str::upper(Str::random(6));
    $stockDelivery = createStockDeliveryWithItems($this, "INVREAD-$token", [10]);
    $stockDelivery->update(['state' => StockDeliveryStateEnum::BOOKED_IN]);
    $stockDelivery = StartStockDeliveryCosting::make()->action($stockDelivery->fresh());
    $item          = $stockDelivery->items()->first();
    $currency      = $stockDelivery->currency->code;

    $path = tempnam(sys_get_temp_dir(), 'invoice');
    file_put_contents($path, "%PDF-1.4\n% invoice $token\n%%EOF");
    $media = \App\Actions\Helpers\Media\SaveModelAttachment::make()->action($stockDelivery, [
        'path'         => $path,
        'originalName' => "invoice-$token.pdf",
        'extension'    => 'pdf',
        'scope'        => 'Invoice',
    ]);
    @unlink($path);

    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => \Illuminate\Support\Facades\Http::response(['choices' => [['message' => ['content' => json_encode([
            'is_invoice'     => true,
            'invoice_number' => "INV-$token",
            'invoice_date'   => '2026-09-20',
            'currency'       => $currency,
            'lines'          => [
                ['code' => "invread $token", 'description' => 'Goods', 'quantity' => 12, 'unit_price' => 9.5, 'amount' => 114],
                ['code' => 'SAMPLE-1', 'description' => 'Sample', 'quantity' => 1, 'unit_price' => 5, 'amount' => 5],
            ],
            'charges'        => [['label' => 'Freight', 'amount' => 30], ['label' => 'Early payment discount', 'amount' => -3]],
            'total'          => 146,
        ])]]]]),
    ]);

    $this->post(route('grp.models.stock-delivery.invoice.read', [$stockDelivery->id, $media->id]))->assertRedirect();

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request['model'] === 'gpt-5-mini'
        && $request['messages'][0]['content'][1]['type'] === 'file'
        && str_contains($request['messages'][0]['content'][0]['text'], "INVREAD-$token"));

    expect((float) $item->fresh()->cost_items)->toEqualWithDelta((float) $item->net_amount, 0.001);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('invoice_costing.0.reading.state', 'read')
            ->where('invoice_costing.0.reading.can_apply', true)
            ->where('invoice_costing.0.reading.total_mismatch', false)
            ->where('invoice_costing.0.reading.items.0.invoice_amount', 114)
            ->where('invoice_costing.0.reading.items.0.quantity_differs', true)
            ->where('invoice_costing.0.reading.items.0.proposed_cost', 95)
            ->has('invoice_costing.0.reading.unmatched', 1));

    $apply = fn () => $this->post(route('grp.models.stock-delivery.invoice.apply', [$stockDelivery->id, $media->id]), [
        'invoice_number' => "INV-$token",
        'invoice_date'   => '2026-09-20',
        'invoice_total'  => 146,
        'items'          => [['id' => $item->id, 'cost' => 100]],
        'charges'        => [['label' => 'Freight', 'amount' => 30]],
    ]);

    $apply()->assertSessionHasNoErrors();

    $item     = $item->fresh();
    $invoice  = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->first();
    $freight  = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->first();

    expect((float) $item->cost_items)->toEqualWithDelta(100, 0.001)
        ->and((float) $item->cost_extra)->toEqualWithDelta(30, 0.001)
        ->and((float) $invoice->amount)->toEqualWithDelta(146, 0.001)
        ->and($invoice->received_at->toDateString())->toBe('2026-09-20')
        ->and($freight->label)->toBe('Freight')
        ->and(\App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::reading($stockDelivery, $media)['applied_at'])->not->toBeNull();

    $apply()->assertSessionHasErrors('invoice');

    expect($stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->count())->toBe(1);
});

test('a supplier invoice in another currency is converted into the delivery when the delivery does not follow it', function () {
    \Illuminate\Support\Facades\Http::fake();
    [$stockDelivery, $media, $item, $deliveryCurrency, $organisationCurrency] = stockDeliveryWithInvoiceInAnotherCurrency($this, Str::upper(Str::random(6)));

    $review = \App\Actions\GoodsIn\StockDelivery\GetStockDeliveryInvoiceCosting::make()->review($stockDelivery, \App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::reading($stockDelivery, $media));

    expect($review['currency_mismatch'])->toBeTrue()
        ->and($review['currency_unknown'])->toBeFalse()
        ->and($review['follow_blockers'])->toBe([])
        ->and($review['unmatched'])->toHaveCount(1);

    $apply = fn (array $choice) => $this->post(route('grp.models.stock-delivery.invoice.apply', [$stockDelivery->id, $media->id]), [
        'invoice_number'  => 'INV-1',
        'invoice_date'    => '2026-09-20',
        'invoice_total'   => 100,
        'items'           => [['id' => $item->id, 'cost' => 95]],
        'not_in_delivery' => [['label' => 'SAMPLE-1 Sample', 'amount' => 5]],
        ...$choice,
    ]);

    $apply([])->assertSessionHasErrors('currency');
    $apply(['follow_invoice' => false])->assertSessionHasErrors('exchange');

    expect((float) $item->fresh()->cost_items)->toEqualWithDelta(9000, 0.001)
        ->and($stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->count())->toBe(0);

    $apply(['follow_invoice' => false, 'exchange' => 100])->assertSessionHasNoErrors();

    $invoice = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->first();
    $notIn   = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->first();

    expect($stockDelivery->fresh()->currency_id)->toBe($deliveryCurrency->id)
        ->and((float) $item->fresh()->cost_items)->toEqualWithDelta(9500, 0.001)
        ->and((float) $item->fresh()->cost_extra)->toEqualWithDelta(500, 0.001)
        ->and((float) $invoice->amount)->toEqualWithDelta(100, 0.001)
        ->and($invoice->currency_id)->toBe($organisationCurrency->id)
        ->and((float) $invoice->exchange)->toEqualWithDelta(100, 0.001)
        ->and($notIn->category)->toBe(\App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum::NOT_IN_DELIVERY)
        ->and($notIn->label)->toBe('SAMPLE-1 Sample')
        ->and($notIn->currency_id)->toBe($organisationCurrency->id)
        ->and(\App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryInvoiceCosting::convertedCosts(
            [['id' => 1, 'cost' => 10.01], ['id' => 2, 'cost' => 10.01], ['id' => 3, 'cost' => 10.02]],
            0.333
        ))->toBe([1 => 3.33, 2 => 3.33, 3 => 3.34])
        ->and(array_column(\App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum::billTypes(), 'value'))->not->toContain('not_in_delivery');
});

test('a stock delivery that follows a supplier invoice in another currency moves to that currency and keeps its worth', function () {
    \Illuminate\Support\Facades\Http::fake();
    [$stockDelivery, $media, $item, $deliveryCurrency, $organisationCurrency] = stockDeliveryWithInvoiceInAnotherCurrency($this, Str::upper(Str::random(6)));

    $typed = \App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost::make()->action($stockDelivery, [
        'type'        => StockDeliveryCostTypeEnum::EXTRA->value,
        'category'    => 'other',
        'label'       => 'Typed before the invoice',
        'amount'      => 300,
        'received_at' => '2026-09-19',
    ]);

    $this->post(route('grp.models.stock-delivery.invoice.apply', [$stockDelivery->id, $media->id]), [
        'invoice_number' => 'INV-2',
        'invoice_date'   => '2026-09-20',
        'invoice_total'  => 100,
        'items'          => [['id' => $item->id, 'cost' => 95]],
        'follow_invoice' => true,
    ])->assertSessionHasNoErrors();

    $stockDelivery = $stockDelivery->fresh();
    $item          = $item->fresh();
    $typed         = $typed->fresh();
    $invoice       = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->first();

    expect($stockDelivery->currency_id)->toBe($organisationCurrency->id)
        ->and((float) $stockDelivery->org_exchange)->toEqualWithDelta(1, 0.000001)
        ->and((float) $stockDelivery->grp_exchange)->toEqualWithDelta(2, 0.000001)
        ->and((float) $item->net_amount)->toEqualWithDelta(90, 0.001)
        ->and((float) $item->org_net_amount)->toEqualWithDelta(90, 0.001)
        ->and((float) $item->grp_net_amount)->toEqualWithDelta(180, 0.001)
        ->and((float) $item->org_exchange)->toEqualWithDelta(1, 0.000001)
        ->and((float) $item->cost_items)->toEqualWithDelta(95, 0.001)
        ->and((float) $item->cost_extra)->toEqualWithDelta(3, 0.001)
        ->and($typed->currency_id)->toBe($deliveryCurrency->id)
        ->and((float) $typed->amount)->toEqualWithDelta(300, 0.001)
        ->and((float) $typed->exchange)->toEqualWithDelta(0.01, 0.000001)
        ->and((float) $invoice->amount)->toEqualWithDelta(100, 0.001)
        ->and($invoice->currency_id)->toBeNull();

});

test('a forwarder invoice is read by two models and enters the service invoices by line type, exactly as read', function () {
    $shipment      = 'VAL'.random_int(100000, 999999);
    $stockDelivery = createStockDeliveryWithItems($this, "FWD-92-$shipment", [200000]);
    $currency      = $stockDelivery->organisation->currency->code;
    $number        = 'SSL-'.Str::upper(Str::random(6));
    $lines         = [
        ['description' => 'Road transport', 'type' => 'local_transport', 'amount' => 16.95],
        ['description' => 'Sea freight', 'type' => 'freight', 'amount' => 39.55],
        ['description' => 'Import clearance', 'type' => 'customs_clearance', 'amount' => 1.54],
    ];

    [$media, $review] = readServiceInvoiceDocument(
        $this,
        $stockDelivery,
        "Invoice,$number\nShipment,$shipment / FOB\nRoad transport,\"16,9500\"\nSea freight,\"39,55\"\nImport clearance,1.54\nTotal $currency,\"58,04\"\n",
        ['invoice_number' => $number, 'lines' => $lines, 'net_total' => 58.04, 'total_payable' => 58.04]
    );

    [$firstReader, $secondReader] = config('services.stock_delivery_documents.readers');
    \Illuminate\Support\Facades\Http::assertSentCount(2);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request['model'] === $firstReader);
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request['model'] === $secondReader);

    expect(Str::before($firstReader, '/'))->not->toBe(Str::before($secondReader, '/'))
        ->and($review['problems'])->toBe([])
        ->and($review['can_apply'])->toBeTrue()
        ->and($review['needs_manual_check'])->toBeFalse()
        ->and(array_column($review['lines'], 'type'))->toBe(['local_transport', 'freight', 'customs_clearance'])
        ->and($stockDelivery->serviceInvoices()->count())->toBe(0);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('service_invoices.readings.0.reading.can_apply', true)
            ->where('service_invoices.readings.0.reading.invoice_number', $number)
            ->has('service_invoices.readings.0.reading.lines', 3));

    $apply = fn (array $types, ?string $readAt = null) => $this->post(
        route('grp.models.stock-delivery.service-invoice.apply', [$stockDelivery->id, $media->id]),
        ['types' => $types, 'read_at' => $readAt ?? $review['read_at']]
    );

    $apply(['freight', 'freight'])->assertSessionHasErrors('types');
    $apply(['local_transport', 'freight', 'customs_clearance'], '2026-01-01T00:00:00+00:00')->assertSessionHasErrors('invoice');
    expect($stockDelivery->serviceInvoices()->count())->toBe(0);

    $apply(['local_transport', 'freight', 'customs_clearance'])->assertSessionHasNoErrors();

    $bills = $stockDelivery->serviceInvoices()->with('attachments')->get()->keyBy(fn ($bill) => $bill->type->value);

    expect($bills->keys()->sort()->values()->all())->toBe(['customs_clearance', 'freight', 'local_transport'])
        ->and((float) $bills['freight']->total_amount)->toBe(39.55)
        ->and((float) $bills['local_transport']->total_amount)->toBe(16.95)
        ->and((float) $bills['customs_clearance']->total_amount)->toBe(1.54)
        ->and($bills['freight']->reference)->toBe($number)
        ->and($bills['freight']->date->toDateString())->toBe('2026-09-18')
        ->and($bills['freight']->attachments->pluck('id')->all())->toBe([$media->id])
        ->and((float) $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->sole()->amount)->toBe(56.5)
        ->and((float) $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->sole()->amount)->toBe(1.54);

    $apply(['local_transport', 'freight', 'customs_clearance'])->assertSessionHasErrors('invoice');
    $this->post(route('grp.models.stock-delivery.service-invoice.read', [$stockDelivery->id, $media->id]))->assertStatus(422);

    [, $again] = readServiceInvoiceDocument(
        $this,
        $stockDelivery,
        "Copy of invoice,$number\nShipment,$shipment\nSea freight,58.04 $currency\nTotal,58.04\n",
        ['invoice_number' => $number, 'lines' => [['description' => 'Sea freight', 'type' => 'freight', 'amount' => 58.04]], 'net_total' => 58.04, 'total_payable' => 58.04]
    );

    [, $sameAmount] = readServiceInvoiceDocument(
        $this,
        $stockDelivery,
        "Invoice,$number-B\nShipment,$shipment\nSea freight,39.55 $currency\nTotal,39.55\n",
        ['invoice_number' => "$number-B", 'lines' => [['description' => 'Sea freight', 'type' => 'freight', 'amount' => 39.55]], 'net_total' => 39.55, 'total_payable' => 39.55]
    );

    expect($again['can_apply'])->toBeFalse()
        ->and(implode(' ', $again['problems']))->toContain('already in the service invoices')
        ->and($sameAmount['can_apply'])->toBeFalse()
        ->and(implode(' ', $sameAmount['problems']))->toContain('same amount is already on this delivery');

    \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response([], 500));
    \App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryServiceInvoice::run($stockDelivery, $media);

    expect($stockDelivery->serviceInvoices()->count())->toBe(3)
        ->and(\App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::reading($stockDelivery, $media)['applied_at'])->not->toBeNull();
});

test('costs on a stock delivery that no checklist row stands behind can be removed, real ones can not', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'UNBACKED-'.Str::upper(Str::random(6)), [10]);
    $stockDelivery->update(['cost_extra' => 500, 'cost_total' => (float) $stockDelivery->cost_items + 500]);
    $remove        = fn () => $this->delete(route('grp.models.stock-delivery.unbacked-costs.remove', [$stockDelivery->id]));

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('box_stats.third_block.remove_unbacked_costs_route.name', 'grp.models.stock-delivery.unbacked-costs.remove'));

    $remove()->assertSessionHasNoErrors();

    expect((float) $stockDelivery->fresh()->cost_extra)->toEqualWithDelta(0, 0.001);

    \App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost::make()->action($stockDelivery->fresh(), [
        'type'        => StockDeliveryCostTypeEnum::EXTRA->value,
        'category'    => 'other',
        'amount'      => 40,
        'received_at' => '2026-09-19',
    ]);

    $remove()->assertSessionHasErrors('costs');

    expect((float) $stockDelivery->fresh()->cost_extra)->toEqualWithDelta(40, 0.001);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('box_stats.third_block.remove_unbacked_costs_route', null));
});

test('a service invoice that does not name the delivery is added only once the reviewer types the confirmation', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'FWD-95-VAL'.random_int(100000, 999999), [200000]);
    $stockDelivery->update(['dispatched_at' => '2026-08-14']);
    $number        = 'UNN-'.Str::upper(Str::random(6));
    $currency      = $stockDelivery->organisation->currency->code;
    $read          = fn (string $number, string $date) => readServiceInvoiceDocument(
        $this,
        $stockDelivery,
        "Invoice,$number\nShipment,FWD-VAL95-OTHER\nSea freight,39.55\nTotal $currency,39.55\n",
        ['invoice_number' => $number, 'invoice_date' => $date, 'references' => ['FWD-VAL95-OTHER'], 'lines' => [['description' => 'Sea freight', 'type' => 'freight', 'amount' => 39.55]], 'net_total' => 39.55, 'total_payable' => 39.55]
    );

    [, $tooEarly] = $read("$number-A", '2026-07-01');

    expect($tooEarly['can_apply'])->toBeTrue()
        ->and($tooEarly['delivery_confirmation']['dates_fit'])->toBeFalse();

    [$media, $review] = $read($number, '2026-09-18');

    expect($review['problems'])->toBe([])
        ->and($review['delivery_confirmation']['dates_fit'])->toBeTrue()
        ->and($review['delivery_confirmation']['reference'])->toBe($stockDelivery->reference)
        ->and($review['delivery_confirmation']['printed'])->toBe(['FWD-VAL95-OTHER']);

    $apply = fn (array $data) => $this->post(route('grp.models.stock-delivery.service-invoice.apply', [$stockDelivery->id, $media->id]), ['types' => ['freight'], 'read_at' => $review['read_at'], ...$data]);

    $apply([])->assertSessionHasErrors('confirmation');
    $apply(['confirmation' => 'yes'])->assertSessionHasErrors('confirmation');

    expect($stockDelivery->serviceInvoices()->where('reference', $number)->count())->toBe(0);

    $apply(['confirmation' => '  i confirm this is the correct INVOICE. '])->assertSessionHasNoErrors();

    expect($stockDelivery->serviceInvoices()->where('reference', $number)->count())->toBe(1)
        ->and(\App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::reading($stockDelivery, $media)['delivery_confirmed']['dates_fit'])->toBeTrue();
});

test('a service invoice reading that fails a check is refused and never reaches the costing', function () {
    $shipment      = 'VAL'.random_int(100000, 999999);
    $stockDelivery = createStockDeliveryWithItems($this, "FWD-93-$shipment", [200000]);
    $stockDelivery->items()->update(['org_net_amount' => 1000]);
    $other         = createStockDeliveryWithItems($this, 'FWD-93-VAL'.random_int(100000, 999999), [10]);
    $other->update(['is_costed' => true]);
    $otherShipment = Str::afterLast($other->reference, '-');
    $currency      = $stockDelivery->organisation->currency->code;
    $freight       = fn (float $amount) => [['description' => 'Sea freight', 'type' => 'freight', 'amount' => $amount]];
    $reading       = fn (string $number, array $overrides = []) => ['invoice_number' => $number, 'lines' => $freight(39.55), 'net_total' => 39.55, 'total_payable' => 39.55, ...$overrides];
    $document      = fn (string $number, string $shipments, string $amount = '39.55', ?string $printedCurrency = null) => "Invoice,$number\nShipment,$shipments\nSea freight,$amount\nTotal ".($printedCurrency ?? $currency).",$amount\n";

    $refused = [
        'also mentions'                    => fn (string $number) => [$document($number, "$shipment and $otherShipment"), $reading($number)],
        'These amounts are not printed'    => fn (string $number) => [$document($number, $shipment, '35.95'), $reading($number)],
        'do not agree on'                  => fn (string $number) => [$document($number, $shipment), $reading($number), $reading($number, ['lines' => $freight(35.95), 'net_total' => 35.95])],
        'lines add up to'                  => fn (string $number) => [$document($number, $shipment."\nHandling,49.55"), $reading($number, ['net_total' => 49.55, 'total_payable' => 49.55])],
        'no total before VAT'              => fn (string $number) => [$document($number, $shipment), $reading($number, ['net_total' => null, 'vat_total' => null])],
        'no invoice number'                => fn (string $number) => [$document($number, $shipment), $reading($number, ['invoice_number' => null])],
        'invoice number'                   => fn (string $number) => [$document($number, $shipment), $reading("X$number")],
        'does not show a currency'         => fn (string $number) => [$document($number, $shipment), $reading($number, ['currency' => null])],
        "The currency $currency is not"    => fn (string $number) => [$document($number, $shipment, '39.55', 'money'), $reading($number)],
        'larger than the value'            => fn (string $number) => [$document($number, $shipment, '99999999.00'), $reading($number, ['lines' => $freight(99999999), 'net_total' => 99999999, 'total_payable' => 99999999])],
        'not look like an invoice'         => fn (string $number) => [$document($number, $shipment), $reading($number, ['is_invoice' => false])],
    ];

    $media = $review = null;
    foreach ($refused as $reason => $case) {
        $case             = $case('REF-'.Str::upper(Str::random(8)));
        [$media, $review] = readServiceInvoiceDocument($this, $stockDelivery, array_shift($case), ...$case);

        expect($review['can_apply'])->toBeFalse("$reason should refuse the reading")
            ->and(implode(' ', $review['problems']))->toContain($reason);

        $this->post(route('grp.models.stock-delivery.service-invoice.apply', [$stockDelivery->id, $media->id]), ['types' => ['freight'], 'read_at' => $review['read_at']])
            ->assertSessionHasErrors('invoice');
    }

    $number           = 'REF-'.Str::upper(Str::random(8));
    [$media, $review] = readServiceInvoiceDocument($this, $stockDelivery, $document($number, $shipment), $reading($number));
    expect($review['can_apply'])->toBeTrue();

    $stockDelivery->update(['parent_type' => 'OrgPartner']);
    $this->post(route('grp.models.stock-delivery.service-invoice.apply', [$stockDelivery->id, $media->id]), ['types' => ['freight'], 'read_at' => $review['read_at']])
        ->assertSessionHasErrors('invoice');

    expect($stockDelivery->serviceInvoices()->count())->toBe(0)
        ->and($stockDelivery->costs()->count())->toBe(0);
});

test('a scanned service invoice has no text to check, so it needs a person to confirm every amount', function () {
    $shipment      = 'VAL'.random_int(100000, 999999);
    $stockDelivery = createStockDeliveryWithItems($this, "FWD-94-$shipment", [200000]);
    $number        = 'SCAN-'.Str::upper(Str::random(6));

    [$media, $review] = readServiceInvoiceDocument($this, $stockDelivery, Str::random(10), [
        'invoice_number' => $number,
        'references'     => [$shipment],
        'lines'          => [['description' => 'Storage', 'type' => 'storage', 'amount' => 12.5]],
        'net_total'      => 12.5,
        'total_payable'  => 12.5,
    ]);

    expect($review['problems'])->toBe([])->and($review['needs_manual_check'])->toBeTrue();

    $apply = fn (array $data) => $this->post(route('grp.models.stock-delivery.service-invoice.apply', [$stockDelivery->id, $media->id]), ['types' => ['storage'], 'read_at' => $review['read_at'], ...$data]);

    $apply([])->assertSessionHasErrors('checked');
    expect($stockDelivery->serviceInvoices()->count())->toBe(0);

    $apply(['checked' => true])->assertSessionHasNoErrors();
    expect((float) $stockDelivery->serviceInvoices()->sole()->total_amount)->toBe(12.5);
});

test('costs typed by hand are handed to whoever adds a service invoice, with the cost each invoice type lands on', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'HAND-95-VAL'.random_int(100000, 999999), [200000]);
    $orgExchange   = (float) ($stockDelivery->org_exchange ?: 1);

    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::SHIPPING->value, 'amount' => 60, 'label' => 'Quoted by phone', 'received_at' => now()]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::EXTRA->value, 'category' => 'customs_clearance', 'amount' => 15, 'received_at' => now()]);
    StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('service_invoices.hand_typed', 2)
            ->where('service_invoices.hand_typed.0.kind', 'shipping')
            ->where('service_invoices.hand_typed.0.note', 'Quoted by phone')
            ->where('service_invoices.hand_typed.0.amount', fn ($amount) => abs($amount - round(60 * $orgExchange, 2)) < 0.005)
            ->where('service_invoices.hand_typed.0.is_estimate', false)
            ->where('service_invoices.hand_typed.1.kind', 'customs_clearance')
            ->where('service_invoices.cost_kinds.freight', 'shipping')
            ->where('service_invoices.cost_kinds.local_transport', 'shipping')
            ->where('service_invoices.cost_kinds.anti_dumping', 'duty')
            ->where('service_invoices.cost_kinds.storage', 'storage')
            ->where('service_invoices.cost_kinds.tax', null));

    StoreStockDeliveryServiceInvoice::make()->action($stockDelivery->organisation, [
        'type'         => StockDeliveryServiceInvoiceTypeEnum::FREIGHT->value,
        'issuer'       => 'Sea Forwarder',
        'reference'    => 'HAND-'.Str::upper(Str::random(6)),
        'date'         => '2026-10-01',
        'currency_id'  => $stockDelivery->organisation->currency_id,
        'total_amount' => 45,
        'allocations'  => [['stock_delivery_id' => $stockDelivery->id]],
    ]);

    expect(array_column(\App\Actions\GoodsIn\StockDelivery\GetStockDeliveryHandTypedCosts::run($stockDelivery->refresh()), 'kind'))->toBe(['customs_clearance']);
});

test('document checks find amounts and delivery numbers the way documents print them', function () {
    $checks  = \App\Actions\GoodsIn\StockDelivery\StockDeliveryDocumentChecks::class;
    $invoice = "1,000 1 695,0000\t01 695,00\t1 695,001 /Cestná preprava\n1,000ks 3 955,0000\t03 955,00\t3 955,002 /Námorná preprava\n1,000 154,0000\t0154,00\t154,003 /Importné preclenie\n5 804,00Celkom k úhrade\tEUR\nObjednávka č. :AWChina-92\nČíslo zásielky :VAL260365\nVAL260365 / MBL: KA0021691";

    foreach ([1695, 3955, 154, 5804] as $amount) {
        expect($checks::amountInText($amount, $invoice))->toBeTrue("$amount is on the invoice");
    }

    expect($checks::amountInText(3595, $invoice))->toBeFalse()
        ->and($checks::amountInText(5840, $invoice))->toBeFalse()
        ->and($checks::amountInText(15.4, $invoice))->toBeFalse()
        ->and($checks::amountInText(16698, 'RMB 16,698.00'))->toBeTrue()
        ->and($checks::amountInText(16698, '16.698,00 EUR'))->toBeTrue()
        ->and($checks::amountInText(1052.9, 'D0610 1052.9 '))->toBeTrue()
        ->and($checks::amountInText(1052.9, 'D0610 11052.9 '))->toBeFalse()
        ->and($checks::amountInText(105.29, 'D0610 1052.9 '))->toBeFalse()
        ->and($checks::mentions('AWChina-92-VAL260365', $invoice))->toBeTrue()
        ->and($checks::mentions('AWChina-92-VAL260332', $invoice))->toBeFalse()
        ->and($checks::mentions('AWChina-92-VAL26036', $invoice))->toBeFalse()
        ->and($checks::mentions('PO-1234', 'Your order PO 1234 of May'))->toBeTrue()
        ->and($checks::mentions('PO-1234', 'Your order PO-12345 of May'))->toBeFalse()
        ->and($checks::mentions('Spring', $invoice))->toBeNull()
        ->and($checks::mentions('2026-10', 'Invoice date 2026-10-13'))->toBeNull()
        ->and($checks::amountInText(12, 'Weight 12,000 kg'))->toBeFalse()
        ->and($checks::amountInText(1234.5, '1,234,500'))->toBeFalse()
        ->and($checks::currencyInText('CNY', 'TOTAL RMB 16,698.00'))->toBeTrue()
        ->and($checks::currencyInText('EUR', 'TOTAL RMB 16,698.00'))->toBeFalse();
});

test('suppliers talk to procurement on WhatsApp: routed by phone, answered within 24 hours, templated after, and purchase orders sent as a document', function () {
    config(['meta.base_endpoint' => 'https://graph.facebook.com', 'meta.whatsapp.api_version' => 'v21.0']);

    $originalSettings = $this->organisation->settings;
    $token            = Str::lower(Str::random(8));
    $phoneNumberId    = (string) random_int(100000000, 999999999);
    $supplierPhone    = '8613'.random_int(100000000, 999999999);

    $this->patch(route('grp.org.procurement.settings.update', [$this->organisation->slug]), ['whatsapp_phone_number_id' => $phoneNumberId])->assertRedirect();

    $settings = $this->organisation->fresh()->settings;
    data_set($settings, 'meta.access_key', 'meta-token');
    $this->organisation->update(['settings' => $settings]);

    expect(\App\Actions\Procurement\SupplierMessage\Whatsapp\StoreIncomingProcurementWhatsappMessage::organisationFor($phoneNumberId)?->id)->toBe($this->organisation->id);

    $supplier    = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['phone' => '+86 '.substr($supplierPhone, 2), 'email' => null]));
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    \Illuminate\Support\Facades\Queue::fake();
    \App\Actions\Chat\Whatsapp\HandleWhatsappWebhook::make()->handle(['entry' => [['changes' => [['field' => 'messages', 'value' => [
        'metadata' => ['phone_number_id' => $phoneNumberId],
        'messages' => [['id' => "wamid.in-$token", 'from' => $supplierPhone, 'type' => 'text', 'text' => ['body' => 'Goods ready Friday'], 'timestamp' => (string) now()->timestamp]],
    ]]]]]]);
    \Illuminate\Support\Facades\Queue::assertPushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof \App\Actions\Procurement\SupplierMessage\Whatsapp\StoreIncomingProcurementWhatsappMessage);

    $value = [
        'metadata' => ['phone_number_id' => $phoneNumberId],
        'contacts' => [['profile' => ['name' => 'Mr Lin']]],
        'messages' => [['id' => "wamid.in-$token", 'from' => $supplierPhone, 'type' => 'text', 'text' => ['body' => 'Goods ready Friday'], 'timestamp' => (string) now()->timestamp]],
    ];

    expect(\App\Actions\Procurement\SupplierMessage\Whatsapp\StoreIncomingProcurementWhatsappMessage::run($value))->toBe(1)
        ->and(\App\Actions\Procurement\SupplierMessage\Whatsapp\StoreIncomingProcurementWhatsappMessage::run($value))->toBe(0);

    $inbound = \App\Models\Procurement\SupplierMessage::where('whatsapp_message_id', "wamid.in-$token")->first();

    expect($inbound->org_supplier_id)->toBe($orgSupplier->id)
        ->and($inbound->channel)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum::WHATSAPP)
        ->and($inbound->body_text)->toBe('Goods ready Friday');

    \Illuminate\Support\Facades\Http::fake([
        "graph.facebook.com/v21.0/$phoneNumberId/media"    => \Illuminate\Support\Facades\Http::response(['id' => "media-$token"]),
        "graph.facebook.com/v21.0/$phoneNumberId/messages" => \Illuminate\Support\Facades\Http::sequence()
            ->push(['messages' => [['id' => "wamid.out-$token"]]])
            ->push(['messages' => [['id' => "wamid.po-$token"]]]),
    ]);

    $this->post(route('grp.org.procurement.supplier_messages.whatsapp', [$this->organisation->slug]), [
        'phone' => '+'.$supplierPhone,
        'body'  => 'Thanks, collect Friday',
    ])->assertRedirect();

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages') && $request['type'] === 'text' && $request['text']['body'] === 'Thanks, collect Friday');

    $outbound = \App\Models\Procurement\SupplierMessage::where('whatsapp_message_id', "wamid.out-$token")->first();

    expect($outbound->org_supplier_id)->toBe($orgSupplier->id)
        ->and($outbound->user_id)->toBe($this->adminGuest->getUser()->id);

    \App\Actions\Procurement\SupplierMessage\Whatsapp\UpdateProcurementWhatsappStatus::run(['statuses' => [
        ['id' => "wamid.out-$token", 'status' => 'read'],
        ['id' => "wamid.out-$token", 'status' => 'delivered'],
    ]]);

    expect($outbound->fresh()->delivery_state)->toBe('read');

    $inbound->update(['sent_at' => now()->subDays(2)]);

    expect(fn () => \App\Actions\Procurement\SupplierMessage\Whatsapp\SendSupplierWhatsappMessage::make()->handle($this->organisation->fresh(), null, $supplierPhone, 'Are you there?'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $this->patch(route('grp.org.procurement.settings.update', [$this->organisation->slug]), ['whatsapp_purchase_order_template' => 'purchase_order_v1'])->assertRedirect();

    StoreSupplierProduct::make()->action($supplier, [
        'code'             => "WA-$token",
        'name'             => 'WhatsApp product',
        'cost'             => 10,
        'stock_id'         => $this->orgStocks[0]->stock_id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    expect(\App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier::channels($purchaseOrder))->toBe([['channel' => 'whatsapp', 'to' => '+'.$supplierPhone]]);

    $poMessage = \App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier::run($purchaseOrder, 'whatsapp');

    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages')
        && $request['type'] === 'template'
        && $request['template']['name'] === 'purchase_order_v1'
        && $request['template']['components'][0]['parameters'][0]['document']['id'] === "media-$token");

    expect($poMessage->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($poMessage->whatsapp_message_id)->toBe("wamid.po-$token");

    $this->get(route('grp.org.procurement.supplier_messages.show', [$this->organisation->slug, $inbound->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/SupplierMessage')
            ->has('messages', 3)
            ->where('reply.channel', 'whatsapp')
            ->where('reply.window_open', false));

    $this->organisation->update(['settings' => $originalSettings]);
});

test('an agent order goes to the agent as one email, its PDF is built without a query per order, and the agent reply reaches every supplier order in it', function () {
    \App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationOutboxes::run($this->organisation);

    $originalSettings = $this->organisation->settings;
    $token            = Str::lower(Str::random(8));
    $mailbox          = "agent-order-$token@org.test";
    $agentEmail       = "desk@agent-order-$token.com";

    $settings = $this->organisation->settings ?? [];
    data_set($settings, 'procurement.gmail', ['email' => $mailbox, 'refresh_token' => \Illuminate\Support\Facades\Crypt::encryptString('rt'), 'connected_at' => now()->toIso8601String()]);
    $this->organisation->update(['settings' => $settings]);

    $orgAgent                 = createFreshOrgAgent($this);
    $agentOrganisation        = $orgAgent->agent->organisation;
    $originalAgentEmail       = $agentOrganisation->email;
    $agentOrganisation->email = $agentEmail;
    $agentOrganisation->saveQuietly();

    $orders    = createAgentOrderToSend($this, $orgAgent, 3);
    $reference = $orders[0]->agent_order_reference;
    $orderIds  = collect($orders)->pluck('id')->all();

    $queriesToBuildPdf = function (int $numberOfOrders) use ($orderIds): int {
        $purchaseOrders = PurchaseOrder::whereIn('id', array_slice($orderIds, 0, $numberOfOrders))->orderBy('reference')->get();

        DB::flushQueryLog();
        DB::enableQueryLog();
        \App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder::make()->handleMany($purchaseOrders);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $queriesToBuildPdf(1);

    expect($queriesToBuildPdf(3))->toBe($queriesToBuildPdf(1));

    SendAgentOrderToAgent::run($orderIds, $reference, 'email');

    $links             = \App\Models\Comms\ModelHasDispatchedEmail::where('model_type', 'PurchaseOrder')->whereIn('model_id', $orderIds)->get();
    $dispatchedEmailId = $links->pluck('dispatched_email_id')->unique()->sole();
    $sent              = \App\Models\Procurement\SupplierMessage::where('dispatched_email_id', $dispatchedEmailId)->sole();

    expect($links)->toHaveCount(3)
        ->and($orderIds)->toContain($sent->purchase_order_id)
        ->and($sent->org_agent_id)->toBe($orgAgent->id)
        ->and($sent->linkedPurchaseOrders()->pluck('id')->all())->toEqualCanonicalizing($orderIds);

    $this->get(route('grp.org.procurement.supplier_messages.show', [$this->organisation->slug, $sent->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('messages.0.purchase_orders', 3)
            ->where('messages.0.purchase_orders', fn ($purchaseOrders) => collect($purchaseOrders)->pluck('reference')->sort()->values()->all() === collect($orders)->pluck('reference')->sort()->values()->all()));

    \App\Models\Comms\DispatchedEmail::whereKey($dispatchedEmailId)->update(['ses_id' => "ses-$token"]);

    $answer                         = procurementGmailMessage("ao1-$token", "aot1-$token", "Desk <$agentEmail>", $mailbox, 'Re: purchase order');
    $answer['payload']['headers'][] = ['name' => 'In-Reply-To', 'value' => "<ses-$token@eu-west-1.amazonses.com>"];
    $answer['payload']['parts'][1]['filename'] = 'Invoice 4411.pdf';

    \Illuminate\Support\Facades\Http::fake([
        'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'at']),
        "gmail.googleapis.com/gmail/v1/users/me/messages/ao1-$token/attachments/*" => \Illuminate\Support\Facades\Http::response(['data' => rtrim(strtr(base64_encode("%PDF invoice $token"), '+/', '-_'), '=')]),
        "gmail.googleapis.com/gmail/v1/users/me/messages/ao1-$token*" => \Illuminate\Support\Facades\Http::response($answer),
    ]);

    $reply = \App\Actions\Procurement\SupplierMessage\ProcessProcurementEmail::run($this->organisation->fresh(), "ao1-$token");

    expect($reply->org_agent_id)->toBe($orgAgent->id)
        ->and($orderIds)->toContain($reply->purchase_order_id)
        ->and($reply->linkedPurchaseOrders()->pluck('id')->all())->toEqualCanonicalizing($orderIds);

    foreach ($orders as $order) {
        expect($order->attachments()->wherePivot('scope', 'Invoice')->count())->toBe(1);
    }

    $agentOrganisation->email = $originalAgentEmail;
    $agentOrganisation->saveQuietly();
    $this->organisation->update(['settings' => $originalSettings]);
});

test('a placed stock delivery costed in Aurora is marked as costed, one costed in aiku or not costed in Aurora is left alone', function () {
    $repair = \App\Actions\Transfers\Aurora\RepairAuroraStockDeliveryCosting::make();
    $token  = random_int(100000, 999999);

    $deliveries = collect(['costed' => 1, 'open' => 2, 'aiku' => 3])->map(function (int $index, string $code) use ($token) {
        $stockDelivery = createStockDeliveryWithItems($this, "AuCost-$code-$token", [5]);
        $stockDelivery->updateQuietly([
            'state'     => StockDeliveryStateEnum::PLACED,
            'is_costed' => false,
            'source_id' => $this->organisation->id.':'.($token * 10 + $index),
        ]);

        return $stockDelivery;
    });
    StoreStockDeliveryCost::make()->action($deliveries['aiku'], ['type' => StockDeliveryCostTypeEnum::EXTRA->value, 'label' => 'Port fee', 'amount' => 10, 'received_at' => now()]);
    $deliveries['aiku']->updateQuietly(['is_costed' => false]);

    $auroraStates = collect([
        $token * 10 + 1 => 'InvoiceChecked',
        $token * 10 + 2 => 'Placed',
        $token * 10 + 3 => 'InvoiceChecked',
    ]);

    expect($repair->markCostedInAurora($this->organisation, $auroraStates, true))->toBe(1)
        ->and($deliveries['costed']->fresh()->is_costed)->toBeFalse()
        ->and($repair->markCostedInAurora($this->organisation, $auroraStates))->toBe(1)
        ->and($deliveries['costed']->fresh()->is_costed)->toBeTrue()
        ->and($deliveries['costed']->items()->where('is_costed', false)->count())->toBe(0)
        ->and($deliveries['open']->fresh()->is_costed)->toBeFalse()
        ->and($deliveries['aiku']->fresh()->is_costed)->toBeFalse();
});

test('the agent order PDF leaves out cancelled supplier orders and cancelled lines', function () {
    $orgAgent          = createFreshOrgAgent($this);
    [$live, $cancelled] = createAgentOrderToSend($this, $orgAgent, 2);
    $pdf               = \App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder::make();

    $cancelled->update(['state' => PurchaseOrderStateEnum::CANCELLED]);
    $cancelled->purchaseOrderTransactions()->update(['state' => \App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum::CANCELLED]);

    expect($pdf->withoutCancelled(collect([$live, $cancelled]))->pluck('id')->all())->toBe([$live->id])
        ->and($pdf->withoutCancelled(collect([$cancelled]))->pluck('id')->all())->toBe([$cancelled->id])
        ->and($pdf->lines($cancelled->fresh()))->toHaveCount(1)
        ->and($pdf->lines($live->fresh()))->toHaveCount(1);

    $live->purchaseOrderTransactions()->update(['state' => \App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum::CANCELLED]);

    expect($pdf->lines($live->fresh()))->toHaveCount(0);
});

test('the agent order PDF is only served for a reference that has orders under that organisation and org agent', function () {
    $orgAgent      = createFreshOrgAgent($this);
    $otherOrgAgent = createFreshOrgAgent($this);
    $orders        = createAgentOrderToSend($this, $orgAgent, 2);
    $reference     = $orders[0]->agent_order_reference;

    $pdfRoute = fn (OrgAgent $agent, string $agentOrderReference) => route('grp.org.procurement.org_agents.show.agent_orders.pdf', [$this->organisation->slug, $agent->slug, $agentOrderReference]);

    $this->get($pdfRoute($orgAgent, $reference))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get($pdfRoute($orgAgent, "NOPE-$reference"))->assertNotFound();
    $this->get($pdfRoute($otherOrgAgent, $reference))->assertNotFound();
});

test('an agent order sent by WhatsApp is one message that answers every supplier order in it', function () {
    config(['meta.base_endpoint' => 'https://graph.facebook.com', 'meta.whatsapp.api_version' => 'v21.0']);

    $originalSettings = $this->organisation->settings;
    $token            = Str::lower(Str::random(8));
    $phoneNumberId    = (string) random_int(100000000, 999999999);
    $agentPhone       = '8613'.random_int(100000000, 999999999);

    $settings = $this->organisation->settings ?? [];
    data_set($settings, 'procurement.whatsapp.phone_number_id', $phoneNumberId);
    data_set($settings, 'procurement.whatsapp.purchase_order_template', 'purchase_order_v1');
    data_set($settings, 'meta.access_key', 'meta-token');
    $this->organisation->update(['settings' => $settings]);

    $orgAgent                 = createFreshOrgAgent($this);
    $agentOrganisation        = $orgAgent->agent->organisation;
    $originalAgentPhone       = $agentOrganisation->phone;
    $agentOrganisation->phone = '+'.$agentPhone;
    $agentOrganisation->saveQuietly();

    $orders    = createAgentOrderToSend($this, $orgAgent, 2);
    $reference = $orders[0]->agent_order_reference;
    $orderIds  = collect($orders)->pluck('id')->all();

    \Illuminate\Support\Facades\Http::fake([
        "graph.facebook.com/v21.0/$phoneNumberId/media"    => \Illuminate\Support\Facades\Http::response(['id' => "media-$token"]),
        "graph.facebook.com/v21.0/$phoneNumberId/messages" => \Illuminate\Support\Facades\Http::response(['messages' => [['id' => "wamid.ao-$token"]]]),
    ]);

    SendAgentOrderToAgent::run($orderIds, $reference, 'whatsapp');

    $sent = \App\Models\Procurement\SupplierMessage::where('whatsapp_message_id', "wamid.ao-$token")->sole();

    \Illuminate\Support\Facades\Http::assertSentCount(2);

    expect($sent->channel)->toBe(\App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum::WHATSAPP)
        ->and($sent->org_agent_id)->toBe($orgAgent->id)
        ->and($orderIds)->toContain($sent->purchase_order_id)
        ->and($sent->linkedPurchaseOrders()->pluck('id')->all())->toEqualCanonicalizing($orderIds);

    $agentOrganisation->phone = $originalAgentPhone;
    $agentOrganisation->saveQuietly();
    $this->organisation->update(['settings' => $originalSettings]);
});

test('agent organisations get the purchase order outbox, and only that one, so agents can email purchase orders to their suppliers', function () {
    $agentOrganisation = $this->agent->organisation;

    expect($agentOrganisation->type)->toBe(\App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::AGENT);

    \App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationOutboxes::run($agentOrganisation);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationOutboxes::run($agentOrganisation);

    $outboxes = $agentOrganisation->outboxes()->whereNull('shop_id')->get();

    expect($outboxes->pluck('code')->all())->toBe([\App\Enums\Comms\Outbox\OutboxCodeEnum::SEND_PURCHASE_ORDER_TO_SUPPLIER])
        ->and($outboxes->first()->state)->toBe(\App\Enums\Comms\Outbox\OutboxStateEnum::ACTIVE)
        ->and($outboxes->first()->emailOngoingRun)->not->toBeNull()
        ->and($outboxes->first()->org_post_room_id)->not->toBeNull();
});

test('stock deliveries from an agent use the agent organisation currency and its purchase orders the supplier currency', function () {
    $rupee = Currency::where('code', 'INR')->firstOrFail();
    $date  = now()->format('Y-m-d');
    foreach (['INR' => 93.1234, 'GBP' => 0.8, 'EUR' => 1.1654, 'USD' => 1.3187] as $code => $exchange) {
        DB::table('currency_exchanges')->upsert(
            ['currency_id' => Currency::where('code', $code)->value('id'), 'date' => $date, 'exchange' => $exchange, 'source' => 'M', 'created_at' => now(), 'updated_at' => now()],
            ['currency_id', 'date'],
            ['exchange']
        );
    }

    $agentOrganisation         = $this->orgAgent->agent->organisation;
    $agentOrganisationCurrency = $agentOrganisation->currency_id;
    $agentOrganisation->update(['currency_id' => $rupee->id]);

    [$agentOrgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($agentOrgSupplier, ['reference' => 'AGENT-INR-'.uniqid(), 'date' => now()], strict: false);
    $stockDelivery = StoreStockDelivery::make()->action($this->orgAgent, ['reference' => 'AGENT-INR-'.uniqid(), 'date' => now()], strict: false);

    $agentOrganisation->update(['currency_id' => $agentOrganisationCurrency]);

    expect($purchaseOrder->currency_id)->toBe($agentOrgSupplier->supplier->currency_id)
        ->and($stockDelivery->currency_id)->toBe($rupee->id);
});

test('procurement and accounting dashboards warn about stock deliveries waiting for costing', function () {
    $token  = uniqid();
    $before = \App\Actions\Procurement\GetUncostedStockDeliveriesCard::run($this->organisation);
    $toCostBefore = StockDelivery::where('organisation_id', $this->organisation->id)->toCost()->count();

    $staleDelivery       = createStockDeliveryWithItems($this, "UNCOSTED-OLD-$token", [10]);
    $recentDelivery      = createStockDeliveryWithItems($this, "UNCOSTED-NEW-$token", [10]);
    $placedDelivery      = createStockDeliveryWithItems($this, "UNCOSTED-PLACED-$token", [10]);
    $agentDelivery       = createStockDeliveryWithItems($this, "UNCOSTED-AGENT-$token", [10]);
    $costedAgentDelivery = createStockDeliveryWithItems($this, "COSTED-AGENT-$token", [10]);

    $staleDelivery->update(['state' => StockDeliveryStateEnum::BOOKED_IN, 'booked_in_at' => now()->subDays(8)]);
    $recentDelivery->update(['state' => StockDeliveryStateEnum::BOOKED_IN, 'booked_in_at' => now()->subDay()]);
    $placedDelivery->update(['state' => StockDeliveryStateEnum::PLACED, 'booked_in_at' => now()->subDays(8), 'placed_at' => now()->subDays(7)]);
    $agentDelivery->update(['state' => StockDeliveryStateEnum::PLACED, 'parent_type' => 'OrgAgent', 'placed_at' => now()->subDays(5), 'is_costed' => false]);
    $costedAgentDelivery->update(['state' => StockDeliveryStateEnum::PLACED, 'parent_type' => 'OrgAgent', 'placed_at' => now()->subDays(5), 'is_costed' => true]);

    $card = \App\Actions\Procurement\GetUncostedStockDeliveriesCard::run($this->organisation);

    expect($card['metrics'][0]['value'])->toBe(($before['metrics'][0]['value'] ?? 0) + 1)
        ->and($card['metrics'][1]['value'])->toBe(($before['metrics'][1]['value'] ?? 0) + 1)
        ->and($card['value'])->toBe(array_sum(array_column($card['metrics'], 'value')))
        ->and($card['metrics'][0]['route']['parameters']['_query']['between[booked_in_at]'])->toEndWith('-'.now()->subDays(4)->format('Ymd'));

    $toCost = StockDelivery::where('organisation_id', $this->organisation->id)->toCost()->count();
    expect($toCost)->toBe($toCostBefore + 4);

    $this->get(route('grp.org.procurement.dashboard', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboardCards.0.label', 'Deliveries to cost')
            ->where('dashboardCards.0.value', $toCost)
            ->where('dashboardCards.0.route.parameters._query', ['elements[costing]' => 'to_cost'])
            ->where('dashboardCards.1.label', 'Not costed')
            ->etc());

    expect(collect($this->get(route('grp.org.procurement.stock_deliveries.index', [$this->organisation->slug, 'elements[costing]' => 'to_cost']))->viewData('page')['props']['data']['data'])->pluck('reference'))
        ->toContain("UNCOSTED-OLD-$token", "UNCOSTED-AGENT-$token")
        ->not->toContain("COSTED-AGENT-$token");

    $this->get(route('grp.org.accounting.dashboard', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('uncostedStockDeliveries.value', $card['value'])->etc());

    foreach ($card['metrics'] as $metric) {
        $list       = $this->get(route($metric['route']['name'], [$this->organisation->slug, ...$metric['route']['parameters']['_query']]))->viewData('page')['props']['data'];
        $references = collect($list['data'])->pluck('reference');

        expect($list['meta']['total'])->toBe($metric['value'])
            ->and($references)
            ->not->toContain("UNCOSTED-NEW-$token", "UNCOSTED-PLACED-$token", "COSTED-AGENT-$token");
    }
});

describe('HELP-3519 buyer adds a new supplier and assigns an existing SKO to it', function () {
    beforeEach(function () {
        setPermissionsTeamId($this->group->id);

        $this->otherOrganisation = Organisation::where('code', 'prc2')->first()
            ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));

        $this->buyer = User::where('username', 'help-3519-buyer')->first();
        if ($this->buyer) {
            return;
        }

        $buyerPosition = JobPosition::where('organisation_id', $this->organisation->id)->where('code', 'buy')->firstOrFail();
        $employee      = StoreEmployee::make()->action($this->organisation, [
            'worker_number'   => 'help-3519-buyer',
            'alias'           => 'help-3519-buyer',
            'contact_name'    => 'Buyer',
            'state'           => EmployeeStateEnum::WORKING,
            'type'            => EmployeeTypeEnum::EMPLOYEE,
            'employment_type' => EmploymentTypeEnum::FULL_TIME,
            'positions'       => [['slug' => $buyerPosition->slug, 'scopes' => []]],
        ]);
        $this->buyer = StoreUser::make()->action($employee, [
            'username'       => 'help-3519-buyer',
            'password'       => Str::random(32),
            'status'         => true,
            'reset_password' => false,
        ]);
    });

    test('the new supplier belongs only to the buyer organisation', function () {
        expect($this->buyer->authTo("procurement.{$this->organisation->id}.edit"))->toBeTrue()
            ->and($this->buyer->authTo('supply-chain.edit'))->toBeFalse();

        actingAs($this->buyer);

        $this->get(route('grp.org.procurement.org_suppliers.create_new', [$this->organisation->slug]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('formData.route.name', 'grp.models.org.supplier.store'));

        $storeData = Supplier::factory()->definition();
        $this->post(route('grp.models.org.supplier.store', $this->organisation->id), $storeData)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $supplier = Supplier::where('code', $storeData['code'])->firstOrFail();
        expect($supplier->scope_type)->toBe('Organisation')
            ->and($supplier->scope_id)->toBe($this->organisation->id)
            ->and($supplier->orgSuppliers()->pluck('organisation_id')->all())->toBe([$this->organisation->id]);

        expect(CreateOrgSupplier::make()->handle($this->otherOrganisation)->getCollection()->pluck('id'))->not->toContain($supplier->id);

        $this->post(route('grp.models.org.supplier.store', $this->otherOrganisation->id), Supplier::factory()->definition())
            ->assertForbidden();
    });

    test('an existing SKO is assigned to the supplier with the supplier code, cost and packing', function () {
        $orgStock = $this->orgStocks[1];

        actingAs($this->buyer);

        $this->post(route('grp.models.org_stock.supplier_product.store', $orgStock->id), [
            'org_supplier_id'  => $this->orgSupplier->id,
            'code'             => 'HELP-3519-A',
            'name'             => 'Amber bottle 10ml',
            'cost'             => 0.17,
            'units_per_pack'   => 1,
            'units_per_carton' => 192,
        ])->assertSessionHasNoErrors();

        $link = OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)
            ->whereHas('orgSupplierProduct.supplierProduct', fn ($query) => $query->where('code', 'HELP-3519-A'))
            ->sole();
        expect($link->orgSupplierProduct->org_supplier_id)->toBe($this->orgSupplier->id)
            ->and($link->orgSupplierProduct->supplierProduct->code)->toBe('HELP-3519-A')
            ->and((float) $link->orgSupplierProduct->supplierProduct->cost)->toBe(0.17)
            ->and($link->orgSupplierProduct->supplierProduct->tradeUnits()->pluck('trade_units.id')->all())
            ->toEqualCanonicalizing($orgStock->tradeUnits()->pluck('trade_units.id')->all());

        $otherOrgSupplier = OrgSupplier::where('organisation_id', $this->otherOrganisation->id)->where('supplier_id', $this->orgSupplier->supplier_id)->first()
            ?? StoreOrgSupplier::make()->action($this->otherOrganisation, $this->orgSupplier->supplier);

        $this->post(route('grp.models.org_stock.supplier_product.store', $orgStock->id), [
            'org_supplier_id'  => $otherOrgSupplier->id,
            'code'             => 'HELP-3519-B',
            'name'             => 'Other org',
            'cost'             => 1,
            'units_per_pack'   => 1,
            'units_per_carton' => 1,
        ])->assertSessionHasErrors('org_supplier_id');
    });
});

test('stock deliveries export downloads only the deliveries of the organisation', function () {
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));
    $otherOrgSupplier = OrgSupplier::where('organisation_id', $otherOrganisation->id)->where('supplier_id', $this->supplier->id)->first()
        ?? StoreOrgSupplier::make()->action($otherOrganisation, $this->supplier);
    $otherDelivery = StoreStockDelivery::make()->action($otherOrgSupplier, ['reference' => 'other-org-export', 'date' => date('Y-m-d')], strict: false);

    $response = $this->get(route('grp.org.procurement.stock_deliveries.export', [$this->organisation->slug, 'type' => 'csv']))->assertOk();

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toContain($this->stockDelivery->slug)
        ->not->toContain($otherDelivery->slug);
});

test('purchase orders export downloads only the purchase orders of the organisation', function () {
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));
    $otherOrgSupplier = OrgSupplier::where('organisation_id', $otherOrganisation->id)->where('supplier_id', $this->supplier->id)->first()
        ?? StoreOrgSupplier::make()->action($otherOrganisation, $this->supplier);
    $ownPurchaseOrder   = StorePurchaseOrder::make()->action($this->orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'own-org-po-export']), strict: false);
    $otherPurchaseOrder = StorePurchaseOrder::make()->action($otherOrgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'other-org-po-export']), strict: false);

    $response = $this->get(route('grp.org.procurement.purchase_orders.export', [$this->organisation->slug, 'type' => 'csv']))->assertOk();

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toContain($ownPurchaseOrder->slug)
        ->not->toContain($otherPurchaseOrder->slug);
});

test('raw material unit cost follows the preferred supplier cost per unit when the supplier cost changes', function () {
    $stock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $stock);
    $orgStock->updateQuietly(['packed_in' => 25, 'sku_value' => 15.5]);
    DB::table('org_stock_has_org_supplier_products')->insert([
        'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $stock->id, 'supplier_product_id' => $this->orgSupplierProduct->supplier_product_id]),
        'org_stock_id'            => $orgStock->id,
        'org_supplier_product_id' => $this->orgSupplierProduct->id,
        'status'                  => true,
        'local_priority'          => 10,
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);
    $production  = Production::where('organisation_id', $this->organisation->id)->first() ?? StoreProduction::make()->action($this->organisation, ['code' => 'RMCOST', 'name' => 'Raw material cost factory']);
    $rawMaterial = \App\Actions\Production\RawMaterial\StoreRawMaterial::make()->action($production, [
        'type'         => \App\Enums\Production\RawMaterial\RawMaterialTypeEnum::STOCK->value,
        'code'         => 'RMCOST-'.$orgStock->id,
        'description'  => 'Bicarb',
        'unit'         => \App\Enums\Production\RawMaterial\RawMaterialUnitEnum::KILOGRAM->value,
        'org_stock_id' => $orgStock->id,
    ]);
    $supplierProduct = $this->orgSupplierProduct->supplierProduct;
    $originalCost    = $supplierProduct->cost;

    UpdateSupplierProduct::make()->action($supplierProduct, ['cost' => 0.43, 'extra_costs' => 0]);

    expect((float)$rawMaterial->refresh()->unit_cost)->toBe(0.43);

    UpdateSupplierProduct::make()->action($supplierProduct, ['cost' => $originalCost]);
});

test('a supplier product linked to several org stocks answers with the one where it is the preferred supplier', function () {
    $tradeUnit       = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $supplierProduct = StoreSupplierProduct::make()->action($this->supplier, [
        'code'             => 'preferred-link-'.$tradeUnit->id,
        'name'             => 'Preferred link',
        'cost'             => 1,
        'trade_units'      => [$tradeUnit->id],
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]);
    $orgSupplierProduct = OrgSupplierProduct::where('org_supplier_id', $this->orgSupplier->id)->where('supplier_product_id', $supplierProduct->id)->first()
        ?? StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);
    $orgSupplierProduct->updateQuietly(['is_available' => true, 'state' => OrgSupplierProductStateEnum::ACTIVE]);

    $newOrgStock = fn () => \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action(
        $this->organisation,
        StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]))
    );
    $otherOrgStock     = $newOrgStock();
    $preferredOrgStock = $newOrgStock();

    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
    foreach ([[$otherOrgStock, 0], [$preferredOrgStock, 10]] as [$orgStock, $priority]) {
        DB::table('org_stock_has_org_supplier_products')->insert([
            'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $supplierProduct->id]),
            'org_stock_id'                  => $orgStock->id,
            'org_supplier_product_id'       => $orgSupplierProduct->id,
            'status'                        => true,
            'local_priority'                => $priority,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ]);
    }

    $item   = StoreShoppingListItem::make()->action($orgSupplierProduct, ['quantity_units' => 10]);
    $picked = CherryPickShoppingListItems::make()->action($this->agent, [['id' => $item->id]]);

    expect(App\Actions\Procurement\OrgSupplier\GetSupplierOrderCapacity::linkedOrgStock($orgSupplierProduct)->id)->toBe($preferredOrgStock->id)
        ->and(App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity::linkedOrgStock($orgSupplierProduct)->id)->toBe($preferredOrgStock->id)
        ->and(App\Actions\Procurement\OrgSupplierProducts\ResolveOrgStockForSupplierProduct::run($this->organisation, $supplierProduct)->id)->toBe($preferredOrgStock->id)
        ->and((int) DB::table('org_supplier_products as osp')
            ->joinLateral(App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::bestOrgStock(), 'os')
            ->where('osp.id', $orgSupplierProduct->id)
            ->value('os.id'))->toBe($preferredOrgStock->id)
        ->and((int) App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets::make()->scopedQuery($this->orgSupplier)
            ->where('p.id', $orgSupplierProduct->id)
            ->value('os.id'))->toBe($preferredOrgStock->id)
        ->and($picked['picked'])->toBe(1)
        ->and(PurchaseOrderTransaction::find($item->fresh()->purchase_order_transaction_id)->org_stock_id)->toBe($preferredOrgStock->id);
});

test('an org stock linked to several supplier products answers with its preferred supplier product', function () {
    $stock         = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    $sellerStock   = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->orgPartner->partner, $stock);
    $buyerOrgStock = OrgStock::where('organisation_id', $this->orgPartner->organisation_id)->where('stock_id', $stock->id)->first()
        ?? \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->orgPartner->organisation, $stock);
    $sellerStock->updateQuietly(['packed_in' => 1]);

    foreach ([[6, 30, 0], [24, 9, 10]] as [$unitsPerCarton, $leadTimeDays, $priority]) {
        $tradeUnit       = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
        $supplierProduct = StoreSupplierProduct::make()->action($this->supplier, [
            'code'             => 'preferred-sp-'.$tradeUnit->id,
            'name'             => 'Preferred supplier product',
            'cost'             => 1,
            'trade_units'      => [$tradeUnit->id],
            'units_per_pack'   => 1,
            'units_per_carton' => $unitsPerCarton,
        ]);
        $supplierProduct->updateQuietly(['estimated_lead_time_days' => $leadTimeDays]);
        $orgSupplierProduct = OrgSupplierProduct::where('org_supplier_id', $this->orgSupplier->id)->where('supplier_product_id', $supplierProduct->id)->first()
            ?? StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);
        $orgSupplierProduct->updateQuietly(['state' => OrgSupplierProductStateEnum::ACTIVE]);
        DB::table('org_stock_has_org_supplier_products')->insert([
            'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $stock->id, 'supplier_product_id' => $supplierProduct->id]),
            'org_stock_id'                  => $sellerStock->id,
            'org_supplier_product_id'       => $orgSupplierProduct->id,
            'status'                        => true,
            'local_priority'                => $priority,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ]);
    }

    $buckets       = GetPartnerStockCoverBuckets::make();
    $partnerCarton = (int) DB::selectOne(
        'select '.(new ReflectionMethod($buckets, 'partnerCartonSkosSql'))->invoke($buckets).' as carton from org_stocks p, (select 1 as packed_in) os where p.id = ?',
        [$sellerStock->id]
    )->carton;
    $action        = IndexPurchaseOrderTransactions::make();
    $partnerStocks = (new ReflectionMethod($action, 'partnerStocks'))->invoke($action, $this->orgPartner, collect([$buyerOrgStock->id]));

    expect($partnerCarton)->toBe(24)
        ->and((int) $partnerStocks->get($buyerOrgStock->id)->units_per_carton)->toBe(24)
        ->and((int) DB::table('org_stocks')
            ->joinLateral(GetOrganisationStockCoverBuckets::make()->primarySupplierProduct(), 'primary_sp')
            ->where('org_stocks.id', $sellerStock->id)
            ->value('primary_sp.estimated_lead_time_days'))->toBe(9);
});

test('an SKO the agent supplies under several supplier products is listed once, under the preferred one', function () {
    $buckets = App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets::make();
    $listed  = fn (OrgSupplierProduct $orgSupplierProduct) => $buckets->scopedQuery($this->orgAgent)->where('osp.id', $orgSupplierProduct->id)->exists();

    $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action(
        $this->organisation,
        StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]))
    );

    $link = function (int $priority) use ($orgStock): OrgSupplierProduct {
        [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
        $orgSupplierProduct->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => true]);
        DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $orgSupplierProduct->id)->delete();
        DB::table('org_stock_has_org_supplier_products')->insert([
            'stock_has_supplier_product_id' => DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id]),
            'org_stock_id'                  => $orgStock->id,
            'org_supplier_product_id'       => $orgSupplierProduct->id,
            'status'                        => true,
            'local_priority'                => $priority,
            'created_at'                    => now(),
            'updated_at'                    => now(),
        ]);

        return $orgSupplierProduct;
    };

    $other = $link(0);
    expect($listed($other))->toBeTrue();

    $preferred = $link(10);
    expect($listed($preferred))->toBeTrue()
        ->and($listed($other))->toBeFalse();

    $preferred->update(['is_available' => false]);
    expect($listed($other))->toBeTrue();

    $preferred->update(['is_available' => true]);
    $tied = $link(10);
    expect($listed($preferred))->toBeTrue()
        ->and($listed($tied))->toBeFalse();
});

test('goods in records the batches a delivery line arrived in and puts them away in order', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'BATCHES-IN', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = $stockDelivery->items()->first();
    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => 10]);
    $checkedSkos       = 10 / $stockDeliveryItem->unitsPerSko();

    expect(fn () => SetStockDeliveryItemBatches::make()->action($stockDeliveryItem, ['batches' => [['code' => 'TOO-MANY', 'quantity' => $checkedSkos + 1]]]))
        ->toThrow(ValidationException::class);

    $this->patch(route('grp.models.stock-delivery-item.batches', $stockDeliveryItem->id), ['batches' => [
        ['code' => 'LOT-A', 'expiry_date' => '2027-03-01', 'quantity' => $checkedSkos * 0.6],
        ['code' => 'LOT-B', 'expiry_date' => null, 'quantity' => $checkedSkos * 0.4],
    ]])->assertSessionHasNoErrors()->assertRedirect();

    $batches = $stockDeliveryItem->batches()->with('batchCode')->get();
    expect($batches->pluck('batchCode.code')->all())->toBe(['LOT-A', 'LOT-B'])
        ->and($batches->first()->batchCode->expiry_date->toDateString())->toBe('2027-03-01');

    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => $checkedSkos * 0.7, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id]);

    $placed = $stockDeliveryItem->placedBatchQuantities();
    expect($placed[$batches[0]->batch_code_id])->toEqualWithDelta($checkedSkos * 0.6, 0.0001)
        ->and($placed[$batches[1]->batch_code_id])->toEqualWithDelta($checkedSkos * 0.1, 0.0001)
        ->and(fn () => SetStockDeliveryItemBatches::make()->action($stockDeliveryItem, ['batches' => [['code' => 'LOT-B', 'quantity' => $checkedSkos]]]))
        ->toThrow(ValidationException::class);

    DeleteSowing::make()->action($stockDeliveryItem->sowings()->first());

    expect($stockDeliveryItem->placedBatchQuantities())->toBe([])
        ->and((float) DB::table('org_stock_movement_batches')->where('org_stock_id', $stockDeliveryItem->org_stock_id)->whereIn('batch_code_id', $batches->pluck('batch_code_id'))->sum('quantity'))->toEqualWithDelta(0.0, 0.0001);
});

test('purchase orders list shows the agent order of a supplier order and searches by it', function () {
    $orgAgent      = createFreshOrgAgent($this);
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, []);
    $purchaseOrder->update(['reference' => 'ZZ'.Str::upper(Str::random(10))]);
    $agentOrderReference = $purchaseOrder->agent_order_reference;

    expect($agentOrderReference)->not->toBeNull()
        ->and($purchaseOrder->reference)->not->toContain($agentOrderReference);

    $this->get(route('grp.org.procurement.purchase_orders.index', [
        $this->organisation->slug,
        'filter[global]' => $agentOrderReference,
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/PurchaseOrders')
        ->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->firstWhere('key', 'agent_order_reference')['sortable'] === true)
        ->where('data.data', fn ($rows) => collect($rows)->firstWhere('slug', $purchaseOrder->slug)['agent_order_reference'] === $agentOrderReference)
        ->etc());

    $this->get(route('grp.org.procurement.purchase_orders.index', [
        $this->organisation->slug,
        'filter[global]' => 'NOSUCH'.Str::upper(Str::random(8)),
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('data.data', fn ($rows) => collect($rows)->firstWhere('slug', $purchaseOrder->slug) === null)
        ->etc());
});

test('purchase order edit form leaves the deposit to the deposit payments, and the update still rejects a deposit amount that is text or negative', function () {
    $orgAgent      = createFreshOrgAgent($this);
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, []);

    $this->get(route('grp.org.procurement.purchase_orders.edit', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EditModel')
            ->where('formData.blueprint', fn ($blueprint) => !collect($blueprint)->pluck('fields')->collapse()->has('deposit_amount')));

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['deposit_amount' => 'a lot'])
        ->assertSessionHasErrors('deposit_amount');
    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['deposit_amount' => -5])
        ->assertSessionHasErrors('deposit_amount');
});

test('UI show stock delivery lists its purchase orders in a tab', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PO-TAB-'.Str::random(6), [10]);

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('tabs.navigation.'.StockDeliveryTabsEnum::PURCHASE_ORDERS->value));

    $stockDelivery->purchaseOrders()->syncWithoutDetaching([$this->purchaseOrder->id]);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::PURCHASE_ORDERS->value)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/StockDelivery')
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::PURCHASE_ORDERS->value)
            ->has(StockDeliveryTabsEnum::PURCHASE_ORDERS->value.'.data', 1)
            ->where(StockDeliveryTabsEnum::PURCHASE_ORDERS->value.'.data.0.reference', $this->purchaseOrder->reference));
});

test('an Aurora stock delivery gets an estimated supplier or agent invoice from what it recorded, once, and estimates never cost the container', function () {
    $supplierDelivery = createStockDeliveryWithItems($this, 'EST-SUP-'.Str::random(6), [10, 4]);
    $supplierDelivery->items()->orderBy('id')->get()->each(fn ($item, $index) => $item->updateQuietly(['net_amount' => [100, 40][$index]]));
    $supplierDelivery->updateQuietly(['source_id' => '1:'.Str::random(8), 'agent_id' => null, 'cost_items' => 0, 'cost_shipping' => 20, 'cost_extra' => 5]);

    $invoice = EstimateStockDeliveryInvoice::run($supplierDelivery->refresh());

    expect($invoice)->toBeInstanceOf(SupplierInvoice::class)
        ->and($invoice->source)->toBe(StockDeliveryInvoiceSourceEnum::ESTIMATED)
        ->and((float) $invoice->goods_amount)->toBe(140.0)
        ->and((float) $invoice->charges_amount)->toBe(0.0)
        ->and((float) $invoice->total_amount)->toBe(140.0)
        ->and(EstimateStockDeliveryInvoice::run($supplierDelivery))->toBeNull()
        ->and(ShowStockDelivery::make()->getInvoiceSummary($supplierDelivery))->toMatchArray(['kind' => 'supplier', 'source' => 'estimated', 'total' => 140.0, 'paid' => null]);

    $costedDelivery = createStockDeliveryWithItems($this, 'EST-COST-'.Str::random(6), [10, 4]);
    $costedDelivery->items()->orderBy('id')->get()->each(fn ($item, $index) => $item->updateQuietly(['net_amount' => [100, 40][$index]]));
    $costedDelivery->updateQuietly(['source_id' => '1:'.Str::random(8), 'agent_id' => null, 'cost_items' => 70, 'cost_extra' => 9]);

    $costedInvoice = EstimateStockDeliveryInvoice::run($costedDelivery->refresh());

    expect((float) $costedInvoice->goods_amount)->toBe(70.0)
        ->and($costedInvoice->charges)->toBe([])
        ->and(array_column($costedInvoice->lines, 'amount'))->toEqual([50, 20]);

    $agentDelivery = createStockDeliveryWithItems($this, 'EST-AGT-'.Str::random(6), [10]);
    $agentDelivery->updateQuietly(['source_id' => '1:'.Str::random(8), 'agent_id' => $this->agent->id, 'state' => StockDeliveryStateEnum::PLACED, 'is_costed' => false]);

    $agentInvoice = EstimateStockDeliveryInvoice::run($agentDelivery->refresh());

    expect($agentInvoice)->toBeInstanceOf(AgentInvoice::class)
        ->and($agentInvoice->number)->toBeNull()
        ->and($agentInvoice->source)->toBe(StockDeliveryInvoiceSourceEnum::ESTIMATED)
        ->and(ApplyAgentInvoiceCosting::run($agentDelivery->refresh()))->toBeNull();
});

test('supplier payments are recorded on the stock delivery against its invoice, after the deposit of its purchase order (HELP-3872)', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'SUP-PAY-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['source_id' => '1:'.Str::random(8), 'agent_id' => null, 'cost_shipping' => 0, 'cost_extra' => 0]);

    $this->post(route('grp.models.stock-delivery.invoice.store', $stockDelivery->id), [
        'reference'    => 'SUP-PAY-INV',
        'date'         => '2026-03-02',
        'goods_amount' => 1000,
        'charges'      => [],
    ])->assertSessionHasNoErrors();
    $invoice = $stockDelivery->supplierInvoice()->firstOrFail();

    $purchaseOrder = StorePurchaseOrder::make()->action(
        $stockDelivery->parent,
        array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'SUP-PAY-PO-'.uniqid()]),
        strict: false
    );
    $purchaseOrder->update(['currency_id' => $invoice->currency_id, 'deposit_amount' => 300, 'deposit_paid_at' => now()]);
    $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);

    $statement  = \Illuminate\Http\UploadedFile::fake()->createWithContent('balance.pdf', '%PDF-1.4 balance '.Str::random(12));
    $storeRoute = route('grp.models.stock-delivery.supplier_payment.store', $stockDelivery->id);

    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 500, 'method' => 'bank_transfer'])->assertSessionHasErrors('bank_statement');
    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 0, 'method' => 'cash'])->assertSessionHasErrors('amount');

    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 500, 'method' => 'bank_transfer', 'reference' => 'TT-9', 'bank_statement' => $statement])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 50, 'method' => 'cash'])->assertRedirect()->assertSessionHasNoErrors();

    $payments = $stockDelivery->supplierPayments()->orderBy('id')->get();
    expect($payments)->toHaveCount(2)
        ->and($payments[0]->media_id)->not->toBeNull()
        ->and($payments[0]->currency_id)->toBe($invoice->currency_id)
        ->and($payments[1]->media_id)->toBeNull()
        ->and($stockDelivery->attachments()->wherePivot('scope', 'Bank statement')->count())->toBe(1);

    $summary = ShowStockDelivery::make()->getInvoiceSummary($stockDelivery->refresh());
    expect($summary['supplier']['deposits'])->toHaveCount(1)
        ->and($summary['supplier']['paid'])->toBe(850.0)
        ->and($summary['supplier']['balance_due'])->toBe(150.0)
        ->and($summary['supplier']['payments'][0]['method'])->toBe('Bank transfer');

    $this->delete(route('grp.models.supplier_payment.delete', $payments[1]->id))->assertRedirect();
    expect(ShowStockDelivery::make()->getInvoiceSummary($stockDelivery->refresh())['supplier']['balance_due'])->toBe(200.0);

    $stockDelivery->updateQuietly(['agent_id' => \App\Models\SupplyChain\Agent::query()->value('id')]);
    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 10, 'method' => 'cash'])->assertNotFound();
});

test('staff enter the real invoice of a delivery in place of its estimate, keeping the estimate, but never over the agent own invoice', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'REAL-INV-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['source_id' => '1:'.Str::random(8), 'agent_id' => null, 'cost_shipping' => 20, 'cost_extra' => 0]);
    $estimate = EstimateStockDeliveryInvoice::run($stockDelivery->refresh());

    $this->post(route('grp.models.stock-delivery.invoice.store', $stockDelivery->id), [
        'reference'    => 'SUP-778',
        'date'         => '2026-03-02',
        'goods_amount' => 130,
        'charges'      => [['description' => 'Sea freight', 'type' => 'freight', 'amount' => 35.5]],
    ])->assertSessionHasNoErrors();

    $invoice = $stockDelivery->supplierInvoice()->first();

    expect($invoice->id)->toBe($estimate->id)
        ->and($invoice->source)->toBe(StockDeliveryInvoiceSourceEnum::ACTUAL)
        ->and($invoice->reference)->toBe('SUP-778')
        ->and((float) $invoice->total_amount)->toBe(165.5)
        ->and((float) $invoice->data['estimate']['total_amount'])->toBe((float) $estimate->total_amount)
        ->and($stockDelivery->refresh()->data['invoice_number'])->toBe('SUP-778');

    $this->post(route('grp.models.stock-delivery.invoice.store', $stockDelivery->id), ['reference' => '', 'date' => 'x', 'goods_amount' => -1, 'charges' => [['description' => '']]])
        ->assertSessionHasErrors(['reference', 'date', 'goods_amount', 'charges.0.description', 'charges.0.amount']);

    $agentContainer = createStockDeliveryWithItems($this, 'REAL-AGT-'.Str::random(6), [4]);
    $agentContainer->updateQuietly(['agent_id' => $this->agent->id]);
    StoreAgentInvoice::make()->handle($this->agent, $agentContainer->refresh());

    $this->post(route('grp.models.stock-delivery.invoice.store', $agentContainer->id), ['reference' => 'X', 'date' => '2026-03-02', 'goods_amount' => 1, 'charges' => []])
        ->assertSessionHasErrors('invoice');
});

test('a count that is a clean multiple of what was expected is a possible unit mismatch, and the tolerance hides small differences (HELP-3863)', function () {
    expect(StockDeliveryItemDiscrepancyEnum::classify(80, 160, 100))->toBe(StockDeliveryItemDiscrepancyEnum::POSSIBLE_UNIT_MISMATCH)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(120, 24, 100))->toBe(StockDeliveryItemDiscrepancyEnum::POSSIBLE_UNIT_MISMATCH)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(80, 120, 100))->toBe(StockDeliveryItemDiscrepancyEnum::OVER)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(160, 120, 100))->toBe(StockDeliveryItemDiscrepancyEnum::UNDER)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(10, 0, 100))->toBe(StockDeliveryItemDiscrepancyEnum::UNDER)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(200, 202, 27000))->toBe(StockDeliveryItemDiscrepancyEnum::OVER)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(200, 202, 27000, tolerancePercentage: 2))->toBe(StockDeliveryItemDiscrepancyEnum::WITHIN_TOLERANCE)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(200, 202, 27000, tolerancePercentage: 2, toleranceAmount: 100))->toBe(StockDeliveryItemDiscrepancyEnum::OVER)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(200, 202, 27000, toleranceAmount: 300))->toBe(StockDeliveryItemDiscrepancyEnum::WITHIN_TOLERANCE)
        ->and(StockDeliveryItemDiscrepancyEnum::classify(10, 10, 100))->toBeNull();

    $stockDelivery = createStockDeliveryWithItems($this, 'UNIT-MISMATCH-'.Str::random(6), [80, 80, 150]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    $items         = $stockDelivery->items()->orderBy('id')->get();

    foreach ([160, 120, 102] as $index => $checked) {
        SetStockDeliveryItemCheckedQuantity::make()->action($items[$index], ['unit_quantity_checked' => $checked]);
    }

    $stockDelivery->refresh();

    expect($stockDelivery->number_stock_delivery_items_possible_unit_mismatch)->toBe(1)
        ->and($stockDelivery->number_stock_delivery_items_over_delivered)->toBe(1)
        ->and($stockDelivery->number_stock_delivery_items_under_delivered)->toBe(1);
});

test('a supplier that sells by the kg converts on the supplier product, the purchase order line and the invoice review (HELP-3863)', function () {
    $tradeUnit = new TradeUnit(['net_weight' => 500, 'gross_weight' => 510]);
    $tradeUnit->setRelation('pivot', new \Illuminate\Database\Eloquent\Relations\Pivot(['quantity' => 1]));

    $byWeight = new SupplierProduct(['supplier_unit' => SupplierUnitEnum::KG->value, 'units_per_supplier_unit' => 1]);
    $byWeight->setRelation('tradeUnits', collect([$tradeUnit]));

    expect($byWeight->unitsPerSupplierUnitByWeight())->toBe(2.0)
        ->and($byWeight->supplierUnitWarning())->not->toBeNull()
        ->and((new SupplierProduct())->unitsPerSupplierUnit())->toBe(1.0);

    $stockDelivery = createStockDeliveryWithItems($this, 'BincS-'.Str::random(6), [160]);
    $item          = $stockDelivery->items()->first();
    $item->update(['net_amount' => 20240]);
    $supplierProduct = $item->supplierProduct;

    UpdateSupplierProduct::make()->action($supplierProduct, ['supplier_unit' => 'kg', 'units_per_supplier_unit' => 2]);

    expect($supplierProduct->refresh()->supplier_unit)->toBe(SupplierUnitEnum::KG)
        ->and($supplierProduct->unitsPerSupplierUnit())->toBe(2.0);

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.supplier_unit', 'kg')
            ->where('items.data.0.units_per_supplier_unit', 2)
            ->etc());

    $review = GetStockDeliveryInvoiceCosting::make()->review($stockDelivery, [
        'state'    => 'read',
        'currency' => $stockDelivery->currency->code,
        'total'    => 20240,
        'charges'  => [],
        'lines'    => [['code' => $supplierProduct->code, 'quantity' => 80, 'unit_price' => 253, 'amount' => 20240]],
    ]);

    expect($review['items'][0]['invoice_quantity'])->toEqual(160)
        ->and($review['items'][0]['quantity_differs'])->toBeFalse()
        ->and($review['items'][0]['proposed_cost'])->toEqual(20240);

    UpdateSupplierProduct::make()->action($supplierProduct, ['supplier_unit' => null]);

    expect($supplierProduct->refresh()->units_per_supplier_unit)->toBeNull();
});

test('a flagged line is closed by a recount, a unit correction that uncovers a shortage, a tracked supplier claim or an accepted surplus (HELP-3863)', function () {
    $user          = $this->adminGuest->getUser();
    $stockDelivery = createStockDeliveryWithItems($this, 'RESOLVE-'.Str::random(6), [80, 80, 200]);
    $items         = $stockDelivery->items()->orderBy('id')->get();
    foreach ($items as $index => $item) {
        $item->update(['net_amount' => [10120, 10120, 27000][$index]]);
    }
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery->refresh());
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    foreach ([160, 120, 202] as $index => $checked) {
        SetStockDeliveryItemCheckedQuantity::make()->action($items[$index]->refresh(), ['unit_quantity_checked' => $checked]);
    }
    [$mismatch, $hiddenShortage, $surplus] = $stockDelivery->items()->orderBy('id')->get()->all();

    $tasksBefore = StaffTask::count();
    $hiddenShortage = ResolveStockDeliveryItemDiscrepancy::make()->action($hiddenShortage, $user, ['outcome' => 'recount_requested']);
    $task           = StaffTask::find($hiddenShortage->data['recount_staff_task_id']);

    expect(StaffTask::count())->toBe($tasksBefore + 1)
        ->and($task->department)->toBe('warehouse')
        ->and($task->model_id)->toBe($hiddenShortage->org_stock_id)
        ->and($task->data['organisation_id'])->toBe($hiddenShortage->stockDelivery->organisation_id)
        ->and($hiddenShortage->discrepancy_outcome)->toBe(StockDeliveryItemDiscrepancyOutcomeEnum::RECOUNT_REQUESTED)
        ->and($hiddenShortage->discrepancy_resolved_at)->toBeNull();

    $mismatch = ResolveStockDeliveryItemDiscrepancy::make()->action($mismatch, $user, ['outcome' => 'unit_error_corrected', 'unit_quantity' => 160, 'net_amount' => 20240]);

    expect((float) $mismatch->unit_quantity)->toBe(160.0)
        ->and((float) $mismatch->unit_quantity_checked)->toBe(160.0)
        ->and((float) $mismatch->net_amount)->toBe(20240.0)
        ->and($mismatch->discrepancy_outcome)->toBe(StockDeliveryItemDiscrepancyOutcomeEnum::UNIT_ERROR_CORRECTED)
        ->and($mismatch->discrepancy_resolved_by_id)->toBe($user->id)
        ->and($mismatch->data['unit_corrections'][0]['unit_quantity'])->toEqual(80);

    $hiddenShortage = ResolveStockDeliveryItemDiscrepancy::make()->action($hiddenShortage, $user, ['outcome' => 'unit_error_corrected', 'unit_quantity' => 160, 'net_amount' => 20240]);

    expect($hiddenShortage->discrepancy())->toBe(StockDeliveryItemDiscrepancyEnum::UNDER)
        ->and($hiddenShortage->discrepancy_outcome)->toBeNull();

    expect(fn () => ResolveStockDeliveryItemDiscrepancy::make()->action($hiddenShortage, $user, ['outcome' => 'surplus_accepted']))->toThrow(ValidationException::class);

    $hiddenShortage = ResolveStockDeliveryItemDiscrepancy::make()->action($hiddenShortage, $user, ['outcome' => 'supplier_claim', 'notes' => 'Short 20 kg']);
    $claim          = $hiddenShortage->claim;

    expect($hiddenShortage->discrepancy_outcome)->toBe(StockDeliveryItemDiscrepancyOutcomeEnum::SUPPLIER_CLAIM)
        ->and($claim->state)->toBe(StockDeliveryClaimStateEnum::OPEN)
        ->and((float) $claim->quantity)->toBe(40.0)
        ->and((float) $claim->amount)->toBe(5060.0)
        ->and($claim->currency_id)->toBe($stockDelivery->currency_id);

    expect(fn () => UpdateStockDeliveryClaim::make()->action($claim, ['state' => 'credit_received']))->toThrow(ValidationException::class);

    $claim = UpdateStockDeliveryClaim::make()->action($claim, ['state' => 'sent']);
    expect($claim->sent_at)->not->toBeNull()
        ->and($claim->closed_at)->toBeNull();

    expect(fn () => ResolveStockDeliveryItemDiscrepancy::make()->action($hiddenShortage->refresh(), $user, ['outcome' => 'recount_requested']))->toThrow(ValidationException::class);

    $claim = UpdateStockDeliveryClaim::make()->action($claim, ['state' => 'credit_received', 'credit_note_reference' => 'CN-26-002', 'credit_note_amount' => 5060, 'credit_note_date' => '2026-10-20']);
    expect($claim->closed_at)->not->toBeNull()
        ->and($claim->credit_note_reference)->toBe('CN-26-002')
        ->and((float) $hiddenShortage->refresh()->cost_items)->toBe(20240.0);

    $surplus = ResolveStockDeliveryItemDiscrepancy::make()->action($surplus, $user, ['outcome' => 'surplus_accepted']);
    expect($surplus->discrepancy_outcome)->toBe(StockDeliveryItemDiscrepancyOutcomeEnum::SURPLUS_ACCEPTED)
        ->and($surplus->discrepancy_resolved_at)->not->toBeNull();

    $stockDelivery->refresh();
    expect($stockDelivery->number_stock_delivery_items_under_delivered)->toBe(1)
        ->and($stockDelivery->number_stock_delivery_items_over_delivered)->toBe(1)
        ->and($stockDelivery->number_stock_delivery_items_possible_unit_mismatch)->toBe(0);

    $stockDelivery->updateQuietly(['state' => StockDeliveryStateEnum::BOOKED_IN]);
    $this->withoutExceptionHandling();
    $rows = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value)
        ->viewData('page')['props'][StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value]['data'];
    $row = collect($rows)->firstWhere('id', $hiddenShortage->id);

    expect($row['discrepancy'])->toBe('under')
        ->and($row['outcome'])->toBe('supplier_claim')
        ->and($row['claim']['state'])->toBe('credit_received')
        ->and($row['claim']['credit_note_amount'])->toEqual(5060);
});

test('duty is shared by customs line: a duty-free line takes none, items on no line share what the lines leave (HELP-3863)', function () {
    $declare = function (string $code, array $assignTo, float $duty): StockDelivery {
        $stockDelivery = placedStockDeliveryWithTwoLines($this, $code);
        $stockDelivery->update(['org_exchange' => 1]);

        UpdateStockDeliveryCustoms::make()->action($stockDelivery, [
            'customs_mrn'         => '26SK586480626590R2',
            'customs_released_at' => '2026-09-17',
            'lines'               => [
                ['tariff_code' => '3307 41 00 00', 'duty_rate' => 0, 'customs_value' => 100],
                ['tariff_code' => '6302 21 00 89', 'duty_rate' => 9.6, 'customs_value' => 312.5],
            ],
        ]);
        $lines = $stockDelivery->customsLines()->orderBy('id')->get()->values();
        foreach ($stockDelivery->items()->orderBy('id')->get()->values() as $index => $item) {
            SetStockDeliveryItemCustomsLine::make()->action($item, ['stock_delivery_customs_line_id' => $assignTo[$index] === null ? null : $lines[$assignTo[$index]]->id]);
        }
        setShippingAndDuty($stockDelivery->refresh(), 0, $duty);

        return $stockDelivery->refresh();
    };

    $stockDelivery = $declare('CUSTOMS-'.Str::random(6), [0, 1], 30);
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();
    [$dutyFree, $textiles] = $stockDelivery->customsLines()->orderBy('id')->get()->all();

    expect($stockDelivery->customs_mrn)->toBe('26SK586480626590R2')
        ->and($dutyFree->tariff_code)->toBe('3307410000')
        ->and((float) $textiles->duty_amount)->toBe(30.0)
        ->and((float) $first->cost_duties)->toBe(0.0)
        ->and((float) $second->cost_duties)->toBe(30.0);

    expect(fn () => SetStockDeliveryItemCustomsLine::make()->action($first, ['stock_delivery_customs_line_id' => null]))->toThrow(ValidationException::class);

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::CUSTOMS->value)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/StockDelivery')
            ->where('customs.customs_mrn', '26SK586480626590R2')
            ->has('customs.lines', 2)
            ->where('customs.lines.1.allocated', 30)
            ->etc());

    $partlyDeclared = $declare('CUSTOMS-PART-'.Str::random(6), [null, 1], 40);
    [$first, $second] = $partlyDeclared->items()->orderBy('id')->get()->all();

    expect((float) $first->cost_duties)->toBe(10.0)
        ->and((float) $second->cost_duties)->toBe(30.0);
});

test('correcting a unit error reprices only the put-away of that line (HELP-3863)', function () {
    $stockDelivery = placedStockDeliveryWithTwoLines($this, 'REPRICE-ONE-'.Str::random(6));
    [$corrected, $untouched] = $stockDelivery->items()->orderBy('id')->get()->all();

    $corrected->updateQuietly(['unit_quantity' => 50]);
    $untouchedMovement = OrgStockMovement::whereIn('id', $untouched->sowings()->select('org_stock_movement_id'))->first();
    $untouchedMovement->updateQuietly(['org_amount' => 123.456]);

    ResolveStockDeliveryItemDiscrepancy::make()->action($corrected->refresh(), $this->adminGuest->getUser(), ['outcome' => 'unit_error_corrected', 'unit_quantity' => 10]);

    $correctedMovement = OrgStockMovement::whereIn('id', $corrected->sowings()->select('org_stock_movement_id'))->first();
    $expectedCost      = $corrected->refresh()->orgStockMovementCost()['cost_per_sku'];

    expect($corrected->discrepancy_outcome)->toBe(StockDeliveryItemDiscrepancyOutcomeEnum::UNIT_ERROR_CORRECTED)
        ->and((float) $correctedMovement->org_amount)->toEqual(round($expectedCost * (float) $correctedMovement->quantity, 3))
        ->and((float) $untouchedMovement->refresh()->org_amount)->toBe(123.456);
});

test('agent order stages follow purchase orders and stock deliveries to arrival', function () {
    $stages = App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage::class;

    $supplier      = StoreSupplier::make()->action($this->orgAgent->agent, Supplier::factory()->definition());
    $orgSupplier   = OrgSupplier::where('supplier_id', $supplier->id)->where('org_agent_id', $this->orgAgent->id)->firstOrFail();
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);
    DB::table('purchase_order_transactions')->insert([
        'group_id'          => $purchaseOrder->group_id,
        'organisation_id'   => $purchaseOrder->organisation_id,
        'purchase_order_id' => $purchaseOrder->id,
        'org_stock_id'      => $this->orgStocks[0]->id,
        'state'             => PurchaseOrderTransactionStateEnum::SUBMITTED->value,
        'delivery_state'    => App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
        'quantity_ordered'  => 100,
        'net_amount'        => 250,
        'data'              => '{}',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    $isOrdered = fn () => $stages::documents($this->orgAgent, $stages::ORDERED)->where('po.id', $purchaseOrder->id)->exists();
    $ordered   = $stages::countOurs($this->orgAgent, $stages::ORDERED);

    expect($isOrdered())->toBeFalse();

    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);

    expect($isOrdered())->toBeTrue()
        ->and($stages::countOurs($this->orgAgent, $stages::ORDERED))->toBe($ordered + 1);

    $row = collect($stages::make()->handle($this->orgAgent, $stages::ORDERED)->items())->firstWhere('id', $purchaseOrder->id);

    expect($row['lines'])->toBe(1)
        ->and($row['amount'])->toBe(250.0)
        ->and($row['url'])->toContain($purchaseOrder->slug);

    $stockDelivery = StoreStockDelivery::make()->action($this->orgAgent, ['reference' => 'stage-'.$purchaseOrder->id, 'date' => date('Y-m-d')], strict: false);
    DB::table('purchase_order_stock_delivery')->insert(['purchase_order_id' => $purchaseOrder->id, 'stock_delivery_id' => $stockDelivery->id, 'created_at' => now(), 'updated_at' => now()]);

    expect($isOrdered())->toBeFalse()
        ->and($stages::countOurs($this->orgAgent, $stages::ORDERED))->toBe($ordered);

    $stageOf = fn () => collect($stages::STAGES)->reject(fn (string $stage) => $stage === $stages::ORDERED)
        ->filter(fn (string $stage) => $stages::documents($this->orgAgent, $stage)->where('sd.id', $stockDelivery->id)->exists())
        ->values()->all();

    $stageUrl     = route('grp.org.procurement.org_agents.show.shopping.stage', [$this->organisation->slug, $this->orgAgent->slug, $stages::PREPARING]);
    $containerUrl = route('grp.org.procurement.org_agents.show.shopping.container', [$this->organisation->slug, $this->orgAgent->slug, $stockDelivery->slug]);

    DB::table('stock_deliveries')->where('organisation_id', $this->organisation->id)->where('agent_id', $this->orgAgent->agent_id)
        ->whereIn('state', ['in_process', 'confirmed', 'ready_to_ship'])->where('id', '!=', $stockDelivery->id)
        ->update(['state' => StockDeliveryStateEnum::CANCELLED->value]);

    $this->get($stageUrl)->assertRedirect($containerUrl);
    $this->get($stageUrl.'?scope=all')->assertOk();
    $this->get($containerUrl)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/AgentContainerInPreparation')
        ->where('stockDelivery.reference', $stockDelivery->reference)
        ->where('summary.products', 0)
        ->where('container.cbm', $this->orgAgent->order_container->cbm())
        ->where('container.fill', 0)
        ->where('container.is_worth_booking', false)
        ->where('pageHead.subNavigation', fn ($navigation) => collect($navigation)->where('active', true)->pluck('route.parameters.2')->all() === [$stages::PREPARING])
        ->has('rows.data', 0));

    $fill = App\Actions\Procurement\OrgAgent\UI\ShowAgentContainerInPreparation::fillPercent(...);
    expect($fill(60.92, 67.7))->toBe(90.0)
        ->and($fill(60.93, 67.7))->toBe(90.0)
        ->and($fill(60.0, 67.7))->toBe(88.6)
        ->and($fill(1.0, null))->toBe(0.0);

    foreach ([
        'in_process'    => [$stages::PREPARING],
        'ready_to_ship' => [$stages::PREPARING],
        'dispatched'    => [$stages::ON_THE_WAY],
        'received'      => [$stages::DELIVERED],
        'placed'        => [$stages::DELIVERED],
        'cancelled'     => [],
        'not_received'  => [],
    ] as $state => $expected) {
        DB::table('stock_deliveries')->where('id', $stockDelivery->id)->update(['state' => $state]);
        expect($stageOf())->toBe($expected);
    }

    $this->get($containerUrl)->assertNotFound();

    DB::table('stock_deliveries')->where('id', $stockDelivery->id)->update([
        'state'         => StockDeliveryStateEnum::DISPATCHED->value,
        'dispatched_at' => now(),
        'data'          => json_encode(['estimated_receiving_date' => now()->subDays(3)->toDateString()]),
    ]);

    $row = collect($stages::make()->handle($this->orgAgent, $stages::ON_THE_WAY)->items())->firstWhere('id', $stockDelivery->id);

    expect($row['days_late'])->toBe(3)
        ->and($row['url'])->toContain($stockDelivery->slug);

    $invoiceState = fn () => collect($stages::make()->handle($this->orgAgent, $stages::ON_THE_WAY)->items())->firstWhere('id', $stockDelivery->id)['agent_invoice'];
    $isListedAs   = fn (string $filter) => collect($stages::make()->handle($this->orgAgent, $stages::ON_THE_WAY, $filter)->items())->contains('id', $stockDelivery->id);
    $missing      = $stages::agentInvoiceCounts($this->orgAgent, $stages::ON_THE_WAY)['missing'];

    expect($invoiceState())->toBe('missing')
        ->and($isListedAs('missing'))->toBeTrue()
        ->and($isListedAs('unpaid'))->toBeFalse();

    DB::table('agent_invoices')->insert([
        'group_id'          => $stockDelivery->group_id,
        'agent_id'          => $stockDelivery->agent_id,
        'organisation_id'   => $stockDelivery->organisation_id,
        'stock_delivery_id' => $stockDelivery->id,
        'reference'         => 'stage-invoice-'.$stockDelivery->id,
        'lines'             => '[]',
        'date'              => now(),
        'currency_id'       => $stockDelivery->currency_id,
        'total_amount'      => 1000,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    expect($invoiceState())->toBe('unpaid')
        ->and($isListedAs('unpaid'))->toBeTrue()
        ->and($isListedAs('missing'))->toBeFalse()
        ->and($stages::agentInvoiceCounts($this->orgAgent, $stages::ON_THE_WAY)['missing'])->toBe($missing - 1);

    DB::table('agent_payments')->insert([
        'group_id'          => $stockDelivery->group_id,
        'agent_id'          => $stockDelivery->agent_id,
        'organisation_id'   => $stockDelivery->organisation_id,
        'stock_delivery_id' => $stockDelivery->id,
        'date'              => now(),
        'amount'            => 1000,
        'currency_id'       => $stockDelivery->currency_id,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    expect($invoiceState())->toBe('paid')
        ->and($isListedAs('paid'))->toBeTrue()
        ->and($isListedAs('unpaid'))->toBeFalse();
});

test('UI agent order stages', function (string $stage) {
    $response = $this->get(route('grp.org.procurement.org_agents.show.shopping.stage', [$this->organisation->slug, $this->orgAgent->slug, $stage]));
    $response->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/AgentOrderStage')
        ->where('stage', $stage)
        ->where('orgCurrency', $this->organisation->currency->code)
        ->has('rows.data')
        ->has('pageHead.subNavigation', 8)
        ->where('pageHead.subNavigation', fn ($navigation) => collect($navigation)->firstWhere('active', true)['number'] === App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage::countOurs($this->orgAgent, $stage))
        ->where('pageHead.subNavigation', fn ($navigation) => collect($navigation)->where('active', true)->pluck('route.parameters.2')->all() === [$stage])
        ->where('rows.meta.total', App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage::countOurs($this->orgAgent, $stage))
        ->missing('scope'));
})->with(App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage::STAGES);

test('a note copied onto several purchase orders of a stock delivery is listed once (HELP-3884)', function () {
    $supplier      = StoreSupplier::make()->action($this->orgAgent->agent, Supplier::factory()->definition());
    $orgSupplier   = OrgSupplier::where('supplier_id', $supplier->id)->where('org_agent_id', $this->orgAgent->id)->firstOrFail();
    $stockDelivery = StoreStockDelivery::make()->action($this->orgAgent, ['reference' => 'notes-'.Str::random(6), 'date' => date('Y-m-d')], strict: false);
    $note          = 'Should be fine '.Str::random(8);

    foreach (range(1, 3) as $ignored) {
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);
        $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);
        App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($purchaseOrder, ['body' => $note, 'author_name' => 'Buyer', 'created_at' => '2026-09-21 17:58:00'], strict: false);
    }
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($purchaseOrder, ['body' => 'Only on the last one', 'author_name' => 'Buyer', 'created_at' => '2026-09-22 09:00:00'], strict: false);

    $notes = collect(IndexProcurementNotes::run($stockDelivery)->items());

    expect($notes)->toHaveCount(2)
        ->and((int) $notes->firstWhere(fn ($row) => $row->body === $note)->number_documents)->toBe(3)
        ->and((int) $notes->firstWhere(fn ($row) => $row->body === 'Only on the last one')->number_documents)->toBe(1)
        ->and(IndexProcurementNotes::run($purchaseOrder)->total())->toBe(2);
});

test('a container on the way shows and is found by its shipper reference and tracking number (HELP-3884)', function () {
    $stages = App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage::class;

    $stockDelivery = StoreStockDelivery::make()->action($this->orgAgent, ['reference' => 'track-'.Str::random(6), 'date' => date('Y-m-d')], strict: false);
    $stockDelivery->update(['state' => StockDeliveryStateEnum::DISPATCHED, 'dispatched_at' => now()]);

    $tracking = 'MSKU'.random_int(1000000, 9999999);
    $shipper  = 'SHP-'.Str::random(8);

    StoreStockDelivery::make()->action($this->orgAgent, ['reference' => 'other-'.Str::random(6), 'date' => date('Y-m-d')], strict: false)
        ->update(['state' => StockDeliveryStateEnum::DISPATCHED, 'dispatched_at' => now()]);

    $stockDelivery = UpdateStockDelivery::make()->action($stockDelivery, [
        'shipper_reference' => $shipper,
        'tracking_number'   => $tracking,
        'shipping_agent'    => 'Ocean Forwarding',
    ]);

    expect($stockDelivery->data['tracking_number'])->toBe($tracking)
        ->and(collect(App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryData::run($stockDelivery)['blueprint'])->firstWhere('title', 'Tracking')['fields']['tracking_number']['value'])->toBe($tracking);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions', fn ($actions) => collect($actions)->contains(fn ($action) => ($action['label'] ?? null) === 'Settings' && $action['route']['name'] === 'grp.org.procurement.stock_deliveries.edit'))
            ->missing('tabs.navigation.showcase')
            ->where('box_stats.first_block.delivery.shipper_reference', $shipper)
            ->where('box_stats.first_block.delivery.tracking_number', $tracking)
            ->etc());

    $this->get(route('grp.org.procurement.stock_deliveries.edit', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EditModel')
            ->where('formData.args.updateRoute.name', 'grp.models.stock-delivery.update')
            ->where('formData.blueprint', fn ($blueprint) => array_keys(collect($blueprint)->firstWhere('title', 'Tracking')['fields']) === ['shipper_reference', 'tracking_number', 'container_number', 'shipping_agent'])
            ->etc());

    $stageUrl = route('grp.org.procurement.org_agents.show.shopping.stage', [$this->organisation->slug, $this->orgAgent->slug, $stages::ON_THE_WAY]);

    config(['query-builder.parameters.filter' => 'filter']);

    foreach ([$tracking, $shipper] as $search) {
        $this->get($stageUrl.'?'.http_build_query(['filter' => ['global' => $search]]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.id', $stockDelivery->id)
                ->where('rows.data.0.tracking_number', $tracking)
                ->where('rows.data.0.shipper_reference', $shipper)
                ->where('rows.data.0.shipping_agent', 'Ocean Forwarding')
                ->etc());
    }
});

test('agent order stage rejects an unknown stage', function () {
    $this->get(route('grp.org.procurement.org_agents.show.shopping.dashboard', [$this->organisation->slug, $this->orgAgent->slug]).'/nowhere')->assertNotFound();
});

test('a delivery from a supplier in the organisation country starts with shipping and duty not applicable and its invoice estimated', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'DOM-'.Str::random(6), [10, 4]);
    $stockDelivery->items()->orderBy('id')->get()->each(fn ($item, $index) => $item->updateQuietly(['net_amount' => [100, 40][$index]]));
    $stockDelivery->updateQuietly(['agent_id' => null, 'created_at' => StockDelivery::COSTS_BEFORE_PUT_AWAY_FROM]);
    $address = $stockDelivery->parent->supplier->address;

    $address->updateQuietly(['country_id' => App\Models\Helpers\Country::where('id', '!=', $this->organisation->country_id)->value('id')]);
    PrefillDomesticStockDeliveryCosts::run(StockDelivery::find($stockDelivery->id));
    expect($stockDelivery->costs()->exists())->toBeFalse()
        ->and($stockDelivery->supplierInvoice()->exists())->toBeFalse();

    $address->updateQuietly(['country_id' => $this->organisation->country_id]);
    PrefillDomesticStockDeliveryCosts::run(StockDelivery::find($stockDelivery->id));
    $stockDelivery = StockDelivery::find($stockDelivery->id);
    expect($stockDelivery->costsMissingForPutAway())->toBe([])
        ->and($stockDelivery->is_costed)->toBeFalse()
        ->and($stockDelivery->costs()->where('is_na', true)->pluck('type')->map->value->sort()->values()->all())->toBe(['duty', 'shipping'])
        ->and($stockDelivery->supplierInvoice->source)->toBe(StockDeliveryInvoiceSourceEnum::ESTIMATED)
        ->and((float) $stockDelivery->supplierInvoice->goods_amount)->toBe(140.0);

    $stockDelivery->items()->orderBy('id')->first()->updateQuietly(['net_amount' => 60, 'cost_items' => null]);
    PrefillDomesticStockDeliveryCosts::run(StockDelivery::find($stockDelivery->id));
    expect(SupplierInvoice::where('stock_delivery_id', $stockDelivery->id)->count())->toBe(1)
        ->and((float) $stockDelivery->supplierInvoice()->first()->goods_amount)->toBe(100.0)
        ->and($stockDelivery->costs()->count())->toBe(2);
});

test('a buyer records the terms agreed with a supplier and they are the terms in force for its products (HELP-3953)', function () {
    $supplier = StoreSupplier::make()->action($this->agent, Supplier::factory()->definition());
    $product  = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stock->id]));
    $eur      = App\Models\Helpers\Currency::where('code', 'EUR')->firstOrFail();
    $store    = route('grp.models.supplier.agreed_term.store', $supplier->id);

    $this->post($store, ['term_type' => 'incoterm', 'value_type' => 'text', 'value_text' => 'FOB Ningbo', 'agreed_on' => today()->toDateString(), 'show_on_po' => true, 'visibility' => 'supplier'])
        ->assertSessionHasNoErrors();
    $this->post($store, ['term_type' => 'packing_charge', 'value_type' => 'included', 'value_text' => 'in the FOB price', 'agreed_on' => today()->toDateString()])
        ->assertSessionHasNoErrors();
    $this->post($store, ['term_type' => 'payment_currency', 'value_type' => 'text', 'value_text' => 'USD', 'agreed_on' => today()->toDateString()])
        ->assertSessionHasNoErrors();

    $label = ['term_type' => 'label_charge', 'value_type' => 'per_unit', 'amount' => 0.02, 'currency_id' => $eur->id, 'agreed_on' => today()->toDateString()];
    $this->post($store, $label)->assertSessionHasErrors('evidence_message_id');
    $this->post($store, array_merge($label, ['attachments' => [Illuminate\Http\UploadedFile::fake()->create('labels-email.pdf', 20, 'application/pdf')]]))
        ->assertSessionHasNoErrors();
    $this->post($store, ['term_type' => 'incoterm', 'value_type' => 'text', 'agreed_on' => today()->toDateString()])
        ->assertSessionHasErrors('value_text');

    $terms = App\Actions\Procurement\SupplierAgreedTerm\GetEffectiveSupplierAgreedTerms::run($product, $this->organisation);

    expect($terms->map(fn ($term) => $term->term_type->value)->all())->toBe(['incoterm', 'packing_charge', 'label_charge', 'payment_currency'])
        ->and($terms->pluck('level')->unique()->all())->toBe(['supplier'])
        ->and($terms->firstWhere('term_type', App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermTypeEnum::LABEL_CHARGE)->valueLabel())->toBe('EUR 0.02 per unit')
        ->and($terms->firstWhere('term_type', App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermTypeEnum::PACKING_CHARGE)->valueLabel())->toBe('Included · in the FOB price')
        ->and($terms->firstWhere('term_type', App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermTypeEnum::LABEL_CHARGE)->attachments()->count())->toBe(1)
        ->and($terms->first()->agreed_by_user_id)->toBe($this->adminGuest->getUser()->id);
});

test('a changed term supersedes the old one, and a product or organisation term beats the supplier one (HELP-3953)', function () {
    $supplier = StoreSupplier::make()->action($this->agent, Supplier::factory()->definition());
    $product  = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stock->id]));
    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));
    $storeTerm = fn ($subject, array $data) => App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($subject, array_merge(['value_type' => 'text', 'agreed_on' => today()->toDateString()], $data));
    $inForce   = fn ($subject, $organisation) => App\Actions\Procurement\SupplierAgreedTerm\GetEffectiveSupplierAgreedTerms::run($subject, $organisation)->mapWithKeys(fn ($term) => [$term->term_type->value => $term->value_text.'@'.$term->level]);

    $first  = $storeTerm($supplier, ['term_type' => 'incoterm', 'value_text' => 'EXW Yiwu']);
    $second = $storeTerm($supplier, ['term_type' => 'incoterm', 'value_text' => 'FOB Ningbo']);
    $storeTerm($supplier, ['term_type' => 'lead_time_days', 'value_text' => '45']);
    $storeTerm($supplier, ['term_type' => 'lead_time_days', 'value_text' => '60', 'organisation_id' => $otherOrganisation->id]);
    $storeTerm($product, ['term_type' => 'incoterm', 'value_text' => 'DDP Sheffield']);

    expect($first->refresh()->state->value)->toBe('superseded')
        ->and($first->superseded_by_id)->toBe($second->id)
        ->and($first->valid_to->isToday())->toBeTrue()
        ->and($inForce($supplier, $this->organisation)->all())->toBe(['incoterm' => 'FOB Ningbo@supplier', 'lead_time_days' => '45@supplier'])
        ->and($inForce($supplier, $otherOrganisation)->all())->toBe(['incoterm' => 'FOB Ningbo@supplier', 'lead_time_days' => '60@supplier'])
        ->and($inForce($product, $this->organisation)->all())->toBe(['incoterm' => 'DDP Sheffield@supplier_product', 'lead_time_days' => '45@supplier']);

    $this->patch(route('grp.models.supplier_agreed_term.withdraw', $second->id))->assertSessionHasNoErrors();
    expect($second->refresh()->state->value)->toBe('withdrawn')
        ->and($inForce($supplier, $this->organisation)->has('incoterm'))->toBeFalse()
        ->and(App\Models\Procurement\SupplierAgreedTerm::where('subject_type', 'Supplier')->where('subject_id', $supplier->id)->count())->toBe(4);
});

test('notes on a supplier are categorised, can be pinned and resolved, and internal ones never reach the agent (HELP-3953)', function () {
    $supplier = StoreSupplier::make()->action($this->agent, Supplier::factory()->definition());
    $product  = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stock->id]));

    $otherOrganisation = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));

    $this->post(route('grp.models.supplier.procurement_note.store', $supplier->id), ['title' => 'Packing is included in FOB', 'category' => 'charges', 'severity' => 'critical', 'is_pinned' => true])
        ->assertSessionHasNoErrors();
    $this->post(route('grp.models.supplier.procurement_note.store', $supplier->id), ['title' => 'Check carton marks before loading', 'category' => 'agent', 'visibility' => 'agent'])
        ->assertSessionHasNoErrors();
    $this->post(route('grp.models.supplier-product.procurement_note.store', $product->id), ['title' => 'Glass must be 3 mm', 'category' => 'product'])
        ->assertSessionHasNoErrors();
    $this->post(route('grp.models.supplier.procurement_note.store', $supplier->id), ['title' => 'Only for the other organisation', 'organisation_id' => $otherOrganisation->id])
        ->assertSessionHasNoErrors();
    $this->post(route('grp.models.supplier.procurement_note.store', $supplier->id), ['category' => 'charges'])
        ->assertSessionHasErrors('body');

    $titles = fn (...$arguments) => collect(IndexProcurementNotes::run($supplier, ...$arguments)->items())->pluck('title')->all();

    expect($titles(organisation: $this->organisation))->toBe(['Packing is included in FOB', 'Glass must be 3 mm', 'Check carton marks before loading'])
        ->and($titles())->toContain('Only for the other organisation')
        ->and($titles(organisation: $this->organisation, forAgent: true))->toBe(['Check carton marks before loading']);

    $pinned = App\Models\Procurement\ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $supplier->id)->where('is_pinned', true)->firstOrFail();
    expect($pinned->category->colour())->toBe('red')
        ->and($pinned->organisation_id)->toBeNull()
        ->and($pinned->author_user_id)->toBe($this->adminGuest->getUser()->id);

    $this->patch(route('grp.models.procurement_note.update', $pinned->id), ['state' => 'resolved'])->assertSessionHasNoErrors();
    expect($pinned->refresh()->state->value)->toBe('resolved')
        ->and($pinned->is_pinned)->toBeFalse()
        ->and($pinned->resolved_by_user_id)->toBe($this->adminGuest->getUser()->id);
});

test('the purchase order document prints the agreed terms marked for it and never an internal one (HELP-3953)', function () {
    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $product     = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stock->id]));
    $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
        ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, ['reference' => 'TERMS'.Str::upper(Str::random(8))], strict: false);
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $product->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());

    $storeTerm = fn ($subject, array $data) => App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($subject, array_merge(['value_type' => 'text', 'agreed_on' => today()->toDateString()], $data));
    $storeTerm($supplier, ['term_type' => 'incoterm', 'value_text' => 'FOB Ningbo', 'show_on_po' => true, 'visibility' => 'supplier']);
    $storeTerm($supplier, ['term_type' => 'packing_charge', 'value_type' => 'included', 'show_on_po' => true, 'visibility' => 'supplier']);
    $storeTerm($supplier, ['term_type' => 'payment_terms', 'value_text' => 'We could go to 40% deposit if pushed', 'show_on_po' => true, 'visibility' => 'internal']);
    $storeTerm($supplier, ['term_type' => 'lead_time_days', 'value_text' => '45', 'show_on_po' => true, 'visibility' => 'agent']);
    $storeTerm($supplier, ['term_type' => 'minimum_order', 'value_text' => '500 units', 'visibility' => 'supplier']);
    $storeTerm($product, ['term_type' => 'packing_charge', 'value_type' => 'not_allowed', 'show_on_po' => true, 'visibility' => 'supplier']);

    $terms    = App\Actions\Procurement\SupplierAgreedTerm\GetPurchaseOrderAgreedTerms::class;
    $describe = fn ($terms) => $terms->map(fn ($term) => trim($term->term_type->value.' '.$term->valueLabel().' '.$term->product_code))->all();

    expect($describe($terms::run($purchaseOrder->refresh(), forDocument: true)))->toBe(['incoterm FOB Ningbo', 'packing_charge Included', 'packing_charge Not allowed '.$product->code])
        ->and($terms::run($purchaseOrder))->toHaveCount(6);

    $response = $this->get(route('grp.org.procurement.purchase_orders.pdf', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('the supplier page shows what was agreed, with its terms and notes tabs (HELP-3953)', function () {
    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
        ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);

    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($supplier, ['term_type' => 'incoterm', 'value_type' => 'text', 'value_text' => 'FOB Ningbo', 'agreed_on' => today()->toDateString()]);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($supplier, ['title' => 'Pays in USD only', 'category' => 'currency_payment', 'is_pinned' => true]);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($supplier, ['title' => 'Not pinned', 'category' => 'delivery']);

    $show = route('grp.org.procurement.org_suppliers.show', [$this->organisation->slug, $orgSupplier->slug]);

    $this->get($show)->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/OrgSupplier')
        ->where('agreement.terms.0.value', 'FOB Ningbo')
        ->where('agreement.terms.0.level', 'supplier')
        ->has('agreement.notes', 1)
        ->where('agreement.notes.0.category.colour', 'yellow')
        ->where('agreement.can_write', true)
        ->where('agreement.routes.store_term.name', 'grp.models.supplier.agreed_term.store'));

    $this->get($show.'?tab=agreed_terms')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('agreed_terms.data', 1)
        ->where('agreed_terms.data.0.in_force', true));

    $this->get($show.'?tab=notes')->assertInertia(fn (AssertableInertia $page) => $page->has('notes.data', 2));

    $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug]).'?tab=agreed_terms')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('SupplyChain/Supplier')
        ->has('agreed_terms.data', 1)
        ->where('agreement.terms.0.term_type', 'incoterm'));
});

test('a purchase order shows what was agreed and cannot be submitted before its critical notes are acknowledged (HELP-3953)', function () {
    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $product     = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stock->id]));
    $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
        ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, ['reference' => 'ACK'.Str::upper(Str::random(8))], strict: false);
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $product->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());

    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($supplier, ['term_type' => 'packing_charge', 'value_type' => 'included', 'agreed_on' => today()->toDateString()]);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($product, ['title' => 'Glass must be 3 mm, not 2 mm', 'category' => 'product', 'severity' => 'critical']);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($supplier, ['title' => 'Slow before Chinese New Year', 'category' => 'delivery']);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('agreement.terms.0.value', 'Included')
            ->has('agreement.notes', 1)
            ->where('agreement.notes.0.title', 'Glass must be 3 mm, not 2 mm')
            ->where('agreement.must_acknowledge', true));

    $this->patch(route('grp.models.purchase-order.submit', $purchaseOrder->id))->assertSessionHasErrors('critical_notes');
    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS);

    $this->patch(route('grp.models.purchase-order.submit', $purchaseOrder->id), ['critical_notes_acknowledged' => true])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED);

    $this->get(route('grp.supply-chain.supplier_products.show', [$product->slug]).'?tab=notes')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SupplyChain/SupplierProduct')
            ->where('agreement.terms.0.level', 'supplier')
            ->has('notes.data', 1));
});

test('the agent page lists its suppliers that carry warnings (HELP-3953)', function () {
    $agent    = $this->orgAgent->agent;
    $supplier = StoreSupplier::make()->action($agent, Supplier::factory()->definition());
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($supplier, ['title' => 'Added a packing fee last order', 'category' => 'incident', 'severity' => 'warning']);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($agent, ['title' => 'Check carton marks before loading', 'category' => 'agent', 'is_pinned' => true]);

    $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $this->orgAgent->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/OrgAgent')
            ->where('agreement.suppliers', fn ($suppliers) => collect($suppliers)->contains(fn ($row) => $row['code'] === $supplier->code && $row['warnings'] === 1 && $row['open_incidents'] === 1))
            ->where('agreement.notes', fn ($notes) => collect($notes)->contains(fn ($note) => $note['title'] === 'Check carton marks before loading')));

    $this->get(route('grp.supply-chain.agents.show', [$agent->slug]).'?tab=notes')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('SupplyChain/Agent')->has('notes.data'));
});

test('the handover sheet prints, and an order through an agent carries the notes marked for the agent (HELP-3953)', function () {
    $supplier    = StoreSupplier::make()->action($this->orgAgent->agent, Supplier::factory()->definition());
    $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('org_agent_id', $this->orgAgent->id)->firstOrFail();
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, [], strict: false);

    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($supplier, ['term_type' => 'incoterm', 'value_type' => 'text', 'value_text' => 'FOB Ningbo', 'agreed_on' => today()->toDateString()]);
    $storeNote = fn (array $data) => App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($supplier, $data);
    $storeNote(['title' => 'Check carton marks before loading', 'category' => 'agent', 'visibility' => 'agent']);
    $storeNote(['title' => 'Labels in English and Spanish', 'category' => 'packaging', 'visibility' => 'supplier']);
    $storeNote(['title' => 'They overcharged us twice', 'category' => 'charges']);

    $documentNotes = App\Actions\Procurement\SupplierAgreedTerm\UI\GetPurchaseOrderAgreement::make()->documentNotes($purchaseOrder->refresh())->pluck('title');

    expect($purchaseOrder->isAgentOrder())->toBeTrue()
        ->and($documentNotes->sort()->values()->all())->toBe(['Check carton marks before loading', 'Labels in English and Spanish']);

    $this->get(route('grp.org.procurement.purchase_orders.pdf', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $response = $this->get(route('grp.org.procurement.org_suppliers.show.agreement.pdf', [$orgSupplier->organisation->slug, $orgSupplier->slug]));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();

    $this->get(route('grp.org.procurement.org_suppliers.show', [$orgSupplier->organisation->slug, $orgSupplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('agreement.print_route.name', 'grp.org.procurement.org_suppliers.show.agreement.pdf'));

    $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('agreement.print_route.name', 'grp.supply-chain.suppliers.agreement.pdf'));
    $this->get(route('grp.supply-chain.suppliers.agreement.pdf', [$supplier->slug]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('an invoice charging for packing that was agreed included is flagged, blocks payment until purchasing accepts it with a reason, and opens an incident on the supplier (INI-103)', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'TERMS-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['source_id' => '1:'.Str::random(8), 'agent_id' => null, 'cost_shipping' => 0, 'cost_extra' => 0]);
    $supplier = $stockDelivery->parent->supplier;

    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($supplier, ['term_type' => 'packing_charge', 'value_type' => 'included', 'agreed_on' => today()->toDateString()]);
    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($supplier, ['term_type' => 'incoterm', 'value_type' => 'text', 'value_text' => 'FOB Ningbo', 'agreed_on' => today()->toDateString()]);

    $this->post(route('grp.models.stock-delivery.invoice.store', $stockDelivery->id), [
        'reference'    => 'TERMS-INV',
        'date'         => '2026-03-02',
        'goods_amount' => 1000,
        'charges'      => [
            ['description' => 'Packing', 'type' => 'packing_charge', 'amount' => 40],
            ['description' => 'Sea freight', 'type' => 'freight', 'amount' => 300],
            ['description' => 'Bank fee', 'type' => 'other', 'amount' => 15],
        ],
    ])->assertSessionHasNoErrors();

    $flags = App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms::make()->stockDelivery($stockDelivery->refresh());
    expect(collect($flags)->pluck('title')->all())->toBe(['Packing', 'Bank fee'])
        ->and($flags[0]['agreed'])->toBe('Included')
        ->and($flags[1]['agreed'])->toBe('Not agreed');

    $paymentRoute = route('grp.models.stock-delivery.supplier_payment.store', $stockDelivery->id);
    $this->post($paymentRoute, ['date' => date('Y-m-d'), 'amount' => 100, 'method' => 'cash'])->assertSessionHasErrors('agreed_terms');

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('agreed_term_differences.flags', 2)
            ->where('agreed_term_differences.flags.0.accepted', null));

    $acceptRoute = route('grp.models.stock-delivery.agreed_terms.accept', $stockDelivery->id);
    $this->post($acceptRoute, ['reasons' => [$flags[0]['key'] => 'no']])->assertSessionHasErrors('reasons.'.$flags[0]['key']);
    $this->post($acceptRoute, ['reasons' => [$flags[0]['key'] => 'Rush order, agreed by phone']])->assertSessionHasNoErrors();
    $this->post($paymentRoute, ['date' => date('Y-m-d'), 'amount' => 100, 'method' => 'cash'])->assertSessionHasErrors('agreed_terms');

    $this->post($acceptRoute, ['reasons' => [$flags[1]['key'] => 'Bank fee on this transfer only']])->assertSessionHasNoErrors();
    $this->post($paymentRoute, ['date' => date('Y-m-d'), 'amount' => 100, 'method' => 'cash'])->assertSessionHasNoErrors();

    $incident = App\Models\Procurement\ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $supplier->id)->where('category', 'incident')->orderBy('id')->first();
    expect($incident->incident_outcome->value)->toBe('open')
        ->and((float) $incident->incident_amount)->toBe(40.0)
        ->and($incident->body)->toContain('Rush order, agreed by phone');

    config(['query-builder.parameters' => (require config_path('query-builder.php'))['parameters']]);
    $this->get(route('grp.org.procurement.org_suppliers.index', [$this->organisation->slug]).'?filter[global]='.$supplier->code)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('data.data', fn ($rows) => collect($rows)->firstWhere('code', $supplier->code)['open_incidents'] === 2));
});

test('freight on an invoice is flagged when the agreed Incoterm leaves it to the supplier (INI-103)', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'CIF-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['source_id' => '1:'.Str::random(8), 'agent_id' => null]);
    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($stockDelivery->parent->supplier, ['term_type' => 'incoterm', 'value_type' => 'text', 'value_text' => 'CIF Felixstowe', 'agreed_on' => today()->toDateString()]);

    $this->post(route('grp.models.stock-delivery.invoice.store', $stockDelivery->id), [
        'reference'    => 'CIF-INV',
        'date'         => '2026-03-02',
        'goods_amount' => 1000,
        'charges'      => [['description' => 'Sea freight', 'type' => 'freight', 'amount' => 300]],
    ])->assertSessionHasNoErrors();

    $flags = App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms::make()->stockDelivery($stockDelivery->refresh());
    expect($flags)->toHaveCount(1)
        ->and($flags[0]['agreed'])->toContain('CIF Felixstowe');
});

test('a purchase order line costing more than the supplier product cannot be submitted until purchasing accepts it with a reason (INI-103)', function () {
    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $product     = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stock->id, 'cost' => 2]));
    $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
        ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, ['reference' => 'DIF'.Str::upper(Str::random(8))], strict: false);
    $purchaseOrder->update(['currency_id' => $product->currency_id]);
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $product->historicSupplierProduct, $this->orgStocks[0], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 10]));
    $purchaseOrder->purchaseOrderTransactions()->update(['unit_cost' => 2.5]);
    expect(App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms::make()->purchaseOrder($purchaseOrder->refresh()))->toBe([]);

    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($supplier, ['term_type' => 'incoterm', 'value_type' => 'text', 'value_text' => 'FOB Ningbo', 'agreed_on' => today()->toDateString()]);
    $flags = App\Actions\Procurement\SupplierAgreedTerm\CheckAgainstAgreedTerms::make()->purchaseOrder($purchaseOrder->refresh());
    expect($flags)->toHaveCount(1)
        ->and($flags[0]['amount'])->toBe(5.0);

    $this->patch(route('grp.models.purchase-order.submit', $purchaseOrder->id))->assertSessionHasErrors('agreed_terms');
    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS);

    $this->post(route('grp.models.purchase-order.agreed_terms.accept', $purchaseOrder->id), ['reasons' => [$flags[0]['key'] => 'New price list from March']])->assertSessionHasNoErrors();
    $this->patch(route('grp.models.purchase-order.submit', $purchaseOrder->id))->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and(App\Models\Procurement\ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $supplier->id)->where('purchase_order_id', $purchaseOrder->id)->count())->toBe(1);
});

test('goods in sees the packing and packaging notes of each product on a delivery, and a failed check opens an incident linked to the purchase order (INI-104)', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'GIC-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['state' => StockDeliveryStateEnum::RECEIVED]);
    $item          = $stockDelivery->items()->firstOrFail();
    $product       = $item->supplierProduct;
    $supplier      = $product->supplier;
    $purchaseOrder = StorePurchaseOrder::make()->action($stockDelivery->parent, ['reference' => 'GIC'.Str::upper(Str::random(8))], strict: false);
    $stockDelivery->purchaseOrders()->attach($purchaseOrder->id);

    $supplier->updateQuietly(['packing_method' => 'supplier_packs', 'packing_material' => 'pap_20']);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($product, ['title' => 'Labels in Spanish', 'body' => 'Every box', 'category' => 'packaging', 'severity' => 'critical']);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($supplier, ['title' => 'Pays in USD only', 'category' => 'currency_payment']);

    $checklist = App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryGoodsInChecklist::make()->forPage($stockDelivery->refresh());
    $packing   = $checklist['sections'][0]['checks'][0];
    $labels    = $checklist['sections'][1]['checks'][0];
    expect(collect($checklist['sections'])->pluck('key')->all())->toBe(['supplier:'.$supplier->id, 'supplier_product:'.$product->id])
        ->and($packing['detail'])->toBe('Supplier packs · Corrugated cardboard')
        ->and($labels['label'])->toBe('Labels in Spanish')
        ->and($checklist['sections'][1]['purchase_order'])->toBe($purchaseOrder->reference)
        ->and($checklist['total'])->toBe(2);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab=checklist')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('checklist.sections', 2)
            ->where('checklist.can_check', true)
            ->where('tabs.navigation.checklist.number', 2));

    $checkRoute = route('grp.models.stock-delivery.goods_in_check.store', $stockDelivery->id);
    $this->post($checkRoute, ['key' => $packing['key'], 'result' => 'passed'])->assertSessionHasNoErrors();
    $this->post($checkRoute, ['key' => $labels['key'], 'result' => 'failed'])->assertSessionHasErrors('body');
    $this->post($checkRoute, ['key' => 'supplier:0:packing', 'result' => 'passed'])->assertSessionHasErrors('key');
    $this->post($checkRoute, ['key' => $labels['key'], 'result' => 'failed', 'body' => 'Labels only in English on 4 cartons', 'incident_quantity' => 40])->assertSessionHasNoErrors();

    $incident = App\Models\Procurement\ProcurementNote::where('subject_type', 'SupplierProduct')->where('subject_id', $product->id)->where('category', 'incident')->sole();
    expect($incident->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($incident->organisation_id)->toBe($stockDelivery->organisation_id)
        ->and($incident->incident_type->value)->toBe('packing_labels')
        ->and((float) $incident->incident_quantity)->toBe(40.0)
        ->and($incident->incident_outcome->value)->toBe('open');

    $checklist = App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryGoodsInChecklist::make()->forPage($stockDelivery->refresh());
    expect($checklist['checked'])->toBe(2)
        ->and($checklist['failed'])->toBe(1)
        ->and($checklist['sections'][1]['checks'][0]['incident_id'])->toBe($incident->id);
});

test('goods in staff report incidents on a delivery but write no other procurement note (INI-104)', function () {
    $warehouse = $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    setPermissionsTeamId($warehouse->group_id);
    App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);
    $clerk = User::factory()->create(['group_id' => $warehouse->group_id]);
    $clerk->assignRole(App\Enums\SysAdmin\Authorisation\RolesEnum::getRoleName(App\Enums\SysAdmin\Authorisation\RolesEnum::GOODS_IN_CLERK->value, $warehouse));

    $stockDelivery = createStockDeliveryWithItems($this, 'GIN-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['state' => StockDeliveryStateEnum::RECEIVED]);
    App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($stockDelivery->items()->first()->supplierProduct, ['title' => 'Bubble wrap each piece', 'category' => 'packaging']);
    $noteRoute = route('grp.models.stock-delivery.note.store', $stockDelivery->id);

    $this->actingAs($clerk)->post($noteRoute, ['title' => 'Supplier is slow', 'category' => 'general'])->assertForbidden();
    $this->actingAs($clerk)->post($noteRoute, ['title' => 'Two cartons crushed', 'category' => 'incident', 'incident_type' => 'damaged'])->assertSessionHasNoErrors();

    $key = App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryGoodsInChecklist::run($stockDelivery)[0]['checks'][0]['key'];
    $this->actingAs($clerk)->post(route('grp.models.stock-delivery.goods_in_check.store', $stockDelivery->id), ['key' => $key, 'result' => 'failed', 'body' => 'No bubble wrap'])->assertSessionHasNoErrors();

    expect(App\Models\Procurement\ProcurementNote::where('author_user_id', $clerk->id)->where('category', 'incident')->count())->toBe(2)
        ->and(App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement::make()->noteForm($clerk, $stockDelivery->group, organisationId: $stockDelivery->organisation_id)['options']['categories'])->toBe([['value' => 'incident', 'label' => 'Incident', 'colour' => 'black']]);
});

test('a delivery received later than the lead time agreed opens one Late incident on the supplier for each late purchase order (INI-104)', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'LATE-'.Str::random(6), [10]);
    $supplier      = $stockDelivery->parent->supplier;
    $late          = StorePurchaseOrder::make()->action($stockDelivery->parent, ['reference' => 'LATE'.Str::upper(Str::random(8))], strict: false);
    $onTime        = StorePurchaseOrder::make()->action($stockDelivery->parent, ['reference' => 'ONTIME'.Str::upper(Str::random(8))], strict: false);
    $late->updateQuietly(['submitted_at' => now()->subDays(70)]);
    $onTime->updateQuietly(['submitted_at' => now()->subDays(50)]);
    $stockDelivery->purchaseOrders()->attach([$late->id, $onTime->id]);

    App\Actions\Procurement\SupplierAgreedTerm\StoreSupplierAgreedTerm::make()->action($supplier, ['term_type' => 'lead_time_days', 'value_type' => 'text', 'value_text' => '45 days from deposit', 'agreed_on' => today()->toDateString()]);

    ReceiveStockDelivery::make()->action($stockDelivery->refresh());

    $incidents = App\Models\Procurement\ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $supplier->id)->where('incident_type', 'late')->get();
    expect($incidents)->toHaveCount(1)
        ->and($incidents->first()->purchase_order_id)->toBe($late->id)
        ->and($incidents->first()->title)->toBe('Delivered 25 days late')
        ->and($incidents->first()->organisation_id)->toBe($stockDelivery->organisation_id);

    App\Actions\GoodsIn\StockDelivery\ReportLateStockDeliveryPurchaseOrders::run($stockDelivery->refresh());
    expect(App\Models\Procurement\ProcurementNote::where('purchase_order_id', $late->id)->where('incident_type', 'late')->count())->toBe(1);
});

test('the supplier incidents report counts each supplier\'s incidents and what was disputed and recovered (INI-104)', function () {
    $supplier = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $product  = StoreSupplierProduct::make()->action($supplier, array_merge(SupplierProduct::factory()->definition(), ['stock_id' => $this->stock->id]));
    $currency = $this->group->currency_id;
    $store    = fn ($subject, array $data) => App\Actions\Procurement\ProcurementNote\StoreProcurementNote::make()->action($subject, array_merge(['category' => 'incident', 'organisation_id' => $this->organisation->id], $data));

    $credited = $store($supplier, ['title' => 'Packing charged twice', 'incident_amount' => 120, 'incident_currency_id' => $currency]);
    $store($product, ['title' => 'Wrong colour', 'incident_amount' => 80, 'incident_currency_id' => $currency]);
    $store($supplier, ['title' => 'Late', 'incident_type' => 'late']);
    App\Actions\Procurement\ProcurementNote\UpdateProcurementNote::make()->action($credited, ['incident_outcome' => 'credited']);

    expect((float) $credited->refresh()->grp_incident_amount)->toBe(120.0);

    config(['query-builder.parameters' => (require config_path('query-builder.php'))['parameters']]);
    $this->get(route('grp.org.procurement.supplier_incidents.index', [$this->organisation->slug]).'?filter[global]='.$supplier->code)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Procurement/SupplierIncidents')
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['code'] === $supplier->code
                && $row['incidents'] === 3 && $row['open'] === 2 && (float) $row['disputed'] === 200.0 && (float) $row['recovered'] === 120.0)));

    $this->get(route('grp.org.procurement.dashboard', [$this->organisation->slug]))->assertOk();
});

test('purchase order product list only offers products of the supplier being ordered from', function () {
    [$orgSupplier, $ownProduct]           = createAgentOrgSupplierWithProduct($this);
    [$otherOrgSupplier, $foreignProduct] = createAgentOrgSupplierWithProduct($this);

    expect(fn () => StoreOrgSupplierProduct::make()->action($orgSupplier, $foreignProduct->supplierProduct))
        ->toThrow(InvalidArgumentException::class);

    $foreignProduct->updateQuietly(['org_supplier_id' => $orgSupplier->id]);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $rowIds        = collect(IndexPurchaseOrderOrgSupplierProducts::make()->handle($orgSupplier, $purchaseOrder)->items())->pluck('id');

    expect($rowIds)->toContain($ownProduct->id)
        ->not->toContain($foreignProduct->id);
});

test('number of sales quarters shown is saved on the user, between 4 and 8', function () {
    $user = $this->adminGuest->getUser();
    actingAs($user);

    $this->patch(route('grp.models.profile.update'), ['sales_quarters' => 7])->assertSessionHasNoErrors();
    expect($user->fresh()->settings['sales_quarters'])->toBe(7)
        ->and(GetLoggedUser::run($user->fresh())['settings']['sales_quarters'])->toBe(7);

    $this->patch(route('grp.models.profile.update'), ['sales_quarters' => 9])->assertSessionHasErrors('sales_quarters');
    expect($user->fresh()->settings['sales_quarters'])->toBe(7);
});
