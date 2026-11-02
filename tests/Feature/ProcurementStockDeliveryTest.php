<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\Stock\StoreStock;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Models\Goods\TradeUnit;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Procurement\OrgSupplierProducts\UI\GetOrgSupplierProductShowcase;
use App\Actions\SupplyChain\AspoDeposit\StoreAspoDeposit;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Actions\SupplyChain\AgentPayment\StoreAgentPayment;
use App\Models\SysAdmin\User;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\DeleteStockDeliveryCost;
use App\Actions\GoodsIn\StockDelivery\RepairStockDeliveryCostings;
use App\Actions\GoodsIn\StockDelivery\GetStockDeliveriesCostingReminder;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Actions\GoodsIn\StockDelivery\AddPurchaseOrderItemsToStockDelivery;
use App\Actions\GoodsIn\StockDelivery\PdfStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Exports\GoodsIn\StockDeliveryItemsExport;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreAgentStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateToReceived;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateFromGoodsIn;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsChecked;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemAsPlaced;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UI\IndexStockDeliveryItems;
use App\Models\Helpers\Audit;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStateToConfirmedStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpsertStockDeliveryItemPlaced;
use App\Actions\Inventory\Location\StoreLocation;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use Illuminate\Support\Str;
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
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\UI\Grp\Layout\GetOrganisationNavigation;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Actions\Transfers\Aurora\RepairAuroraDeletedStockDeliveries;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\Stock;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\UpdateStockDeliveryServiceInvoice;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\StoreStockDeliveryServiceInvoice;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\DeleteStockDeliveryServiceInvoice;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Helpers\CurrencyExchange;
use App\Actions\Helpers\CurrencyExchange\FetchCurrencyExchange;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\CurrencyExchange\WarmCurrencyExchanges;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Support\Facades\Cache;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\AgentPayment;
use App\Actions\SupplyChain\AgentPayment\StoreAgentLedgerEntry;
use App\Enums\SupplyChain\AgentPayment\AgentPaymentTypeEnum;
use App\Actions\SupplyChain\AgentPayment\ImportAgentLedgerExcel;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Carbon;
use App\Models\Goods\StockFamily;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\SupplyChain\SupplierStockFamily\GetProductionCalendarAdvice;
use App\Actions\Procurement\OrgSupplierProducts\NotifyPurchasingOfSupplierProductAvailability;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Notifications\SupplierProductAvailabilityChangedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use App\Actions\SupplyChain\Agent\UpdateAgent;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

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
            )
            ->where('dashboardCards', fn ($cards) => collect($cards)->pluck('label')->intersect(['Agents', 'Suppliers', 'Supplier Products'])->isEmpty()
                && collect($cards)->pluck('label')->contains('Open purchase orders'))
            ->missing('search_demand')
            ->missing('counterparties')
            ->loadDeferredProps(fn (AssertableInertia $page) => $page
                ->has('counterparties.currency')
                ->where('counterparties.cards', fn ($cards) => collect($cards)->contains(fn ($card) => $card['type'] === 'agent' && $card['name'] === $this->orgAgent->agent->name)));
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

test('procurement navigation keeps agents, free suppliers and purchase orders and drops the lists reached through them', function () {
    $navigation = GetOrganisationNavigation::run($this->adminGuest->getUser(), $this->organisation);

    expect(collect(data_get($navigation, 'procurement.topMenu.subSections'))->pluck('route.name'))
        ->toContain('grp.org.procurement.org_agents.index', 'grp.org.procurement.org_suppliers.index', 'grp.org.procurement.purchase_orders.index')
        ->not->toContain(
            'grp.org.procurement.shopping_list.index',
            'grp.org.procurement.org_agent_suppliers.index',
            'grp.org.procurement.org_supplier_products.index'
        );
});

test('UI show org supplier', function () {
    $this->withoutExceptionHandling();
    $this->orgSupplier->stats()->update([
        'number_org_supplier_products'         => 98,
        'number_current_org_supplier_products' => 86,
        'number_purchase_orders'               => 232,
        'number_stock_deliveries'               => 237,
    ]);

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
            ->where('pageHead.subNavigation.1.number', 86)
            ->where('pageHead.actions.0.label', __('Purchase Order'))
            ->where('pageHead.actions.0.route.name', 'grp.models.org-supplier.purchase-order.store')
            ->where('pageHead.actions.1.route.name', 'grp.org.procurement.org_suppliers.edit')
            ->where('showcase.stats.0.count', 86)
            ->where('showcase.stats.0.route.name', 'grp.org.procurement.org_suppliers.show.supplier_products.index')
            ->where('showcase.stats.1.route.name', 'grp.org.procurement.org_suppliers.show.purchase_orders.index')
            ->where('showcase.stats.2.route.name', 'grp.org.procurement.org_suppliers.show.stock_deliveries.index')
            ->has('tabs');
    });
});

test('UI Index org agents', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgAgents')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->where('agents', fn ($agents) => collect($agents)->contains(fn ($agent) => $agent['slug'] === $this->orgAgent->slug && is_array($agent['current']) && $agent['currency_code'] === $this->orgAgent->agent->organisation->currency->code))
            ->missing('cover')
            ->loadDeferredProps(fn (AssertableInertia $page) => $page->has('cover'));
    });
});

test('suspended agents leave the agents page for their own list and say so on their page', function () {
    $orgAgent = $this->orgAgent;
    $orgAgent->update(['status' => false]);

    $this->get(route('grp.org.procurement.org_agents.index', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('agents', fn ($agents) => !collect($agents)->contains('slug', $orgAgent->slug))
            ->where('suspended_count', fn ($count) => $count >= 1));

    $this->get(route('grp.org.procurement.org_agents.suspended', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/SuspendedOrgAgents')
            ->where('data.data', fn ($rows) => collect($rows)->contains('slug', $orgAgent->slug)));

    $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $orgAgent->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('suspended_alert.title', __('Suspended agent')));

    $orgAgent->update(['status' => true]);

    $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $orgAgent->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('suspended_alert', null));
});

test('agent card sections stay open or closed per user', function () {
    $user = auth()->user();
    $user->update(['settings' => array_merge($user->settings ?? [], ['tickets_list_mine' => 'assigned'])]);
    $closed = $this->orgAgent->id.'cover';

    $this->patch(route('grp.models.profile.update'), [
        'agent_card_sections' => [$closed => false, $this->orgAgent->id.'stock_delivery' => true, 'nonsense' => true],
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->settings)
        ->toMatchArray(['tickets_list_mine' => 'assigned'])
        ->and($user->fresh()->settings['agent_card_sections'])->toBe([$closed => false, $this->orgAgent->id.'stock_delivery' => true]);

    $this->get(route('grp.org.procurement.org_agents.index', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('card_sections.'.$closed, false));
});

test('UI show org agents', function () {
    $response = $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $this->orgAgent->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgAgent')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->where('pageHead.subNavigation.0.route.name', 'grp.org.procurement.org_agents.show')
            ->where('pageHead.subNavigation.1.route.name', 'grp.org.procurement.org_agents.show.shopping.dashboard')
            ->has('pageHead.subNavigation', 8)
            ->where('pageHead.meta.0.route.name', 'grp.org.procurement.org_agents.show.suppliers.index')
            ->where('pageHead.meta.1.route.name', 'grp.org.procurement.org_agents.show.supplier_products.index')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->orgAgent->agent->organisation->name)
                    ->etc()
            )
            ->has('pageHead.actions', 1)
            ->where('pageHead.actions.0.style', 'edit')
            ->where('pageHead.actions.0.route.name', 'grp.org.procurement.org_agents.show.edit')
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
            )
            ->where('tabs.current', 'index')
            ->has('index');
    });
});

test('UI index supplier products in org supplier shows live state counts and history tab', function () {
    $this->withoutExceptionHandling();

    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    $orgSupplier = $supplier->orgSuppliers()
        ->where('organisation_id', $this->organisation->id)
        ->firstOrFail();

    foreach (SupplierProductStateEnum::cases() as $state) {
        $supplierProductData = SupplierProduct::factory()->definition();
        data_set($supplierProductData, 'stock_id', $this->stock->id);
        data_set($supplierProductData, 'state', $state);

        StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    }

    $orgSupplier->stats()->update([
        'number_org_supplier_products'                     => 0,
        'number_org_supplier_products_state_active'        => 0,
        'number_org_supplier_products_state_discontinuing' => 0,
        'number_org_supplier_products_state_discontinued'  => 0,
    ]);

    $route = route('grp.org.procurement.org_suppliers.show.supplier_products.index', [
        $this->organisation->slug,
        $orgSupplier->slug,
    ]);

    $this->get($route)->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgSupplierProducts')
            ->where('tabs.current', 'index')
            ->where('pageHead.actions.0.label', __('Purchase Order'))
            ->where('pageHead.actions.0.route.name', 'grp.models.org-supplier.purchase-order.store')
            ->where('tabs.navigation', fn ($navigation) => $navigation->keys()->all() === ['index', 'history'])
            ->where('queryBuilderProps.index.elementGroups.state.elements', [
                'active'        => [__('Active'), 1],
                'discontinuing' => [__('Discontinuing'), 1],
                'discontinued'  => [__('Discontinued'), 1],
            ])
            ->has('index.data', 1)
            ->has('index.data.0.internal_code')
            ->where('queryBuilderProps.index.columns', fn ($columns) => collect($columns)->pluck('key')->contains('internal_code'))
            ->etc();
    });

    $this->get($route.'?tab=history')->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/OrgSupplierProducts')
            ->where('tabs.current', 'history')
            ->has('history')
            ->has('queryBuilderProps.history')
            ->etc();
    });
});

test('UI index stock deliveries in org supplier shows purchase order action', function () {
    $this->get(route('grp.org.procurement.org_suppliers.show.stock_deliveries.index', [
        $this->organisation->slug,
        $this->orgSupplier->slug,
    ]))->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->where('pageHead.actions.0.label', __('Purchase Order'))
            ->where('pageHead.actions.0.route.name', 'grp.models.org-supplier.purchase-order.store')
            ->etc();
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
                    ->where('title', $this->orgSupplierProduct->supplierProduct->code)
                    ->where('actions', fn ($actions) => collect($actions)->filter()->contains(
                        fn (array $action) => $action['route']['name'] === 'grp.supply-chain.supplier_products.edit'
                            && $action['route']['parameters']['supplierProduct'] === $this->orgSupplierProduct->supplierProduct->slug
                    ))
                    ->etc()
            )
            ->has('tabs');
    });

    $showcase = GetOrgSupplierProductShowcase::run($this->orgSupplierProduct);
    expect($showcase['composition'])->toBeArray();
});

test('UI purchase orders in org supplier product show product quantity and amount', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action(
        $this->orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'Product table test PO']),
        strict: false,
    );
    $purchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $this->orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        [
            'quantity_ordered' => 17.5,
            'net_amount'       => 246.75,
        ],
    );
    $secondPurchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $this->orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        [
            'quantity_ordered' => 2.5,
            'net_amount'       => 53.25,
        ],
    );

    $this->get(route('grp.org.procurement.org_supplier_products.show', [
        $this->organisation->slug,
        $this->orgSupplierProduct->slug,
        'tab' => 'purchase_orders',
    ]))->assertInertia(function (AssertableInertia $page) use ($purchaseOrder, $purchaseOrderTransaction, $secondPurchaseOrderTransaction) {
        $page
            ->component('Procurement/OrgSupplierProduct')
            ->where('queryBuilderProps.purchase_orders.columns', function ($columns) {
                $columnsByKey = $columns->keyBy('key');

                return $columnsByKey->has('reference')
                    && $columnsByKey->has('quantity_ordered')
                    && $columnsByKey->has('org_net_amount')
                    && ! $columnsByKey->has('number_current_purchase_order_transactions')
                    && ! $columnsByKey->has('org_total_cost');
            })
            ->where('purchase_orders.data', function ($rows) use ($purchaseOrder, $purchaseOrderTransaction, $secondPurchaseOrderTransaction) {
                $row = $rows->firstWhere('slug', $purchaseOrder->slug);

                return $row
                    && $row['reference'] === $purchaseOrder->reference
                    && (float) $row['quantity_ordered'] === (float) $purchaseOrderTransaction->quantity_ordered + (float) $secondPurchaseOrderTransaction->quantity_ordered
                    && (float) $row['org_net_amount'] === (float) $purchaseOrderTransaction->org_net_amount + (float) $secondPurchaseOrderTransaction->org_net_amount;
            })
            ->etc();
    });
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
            ->has('formData.args.updateRoute')
            ->where('formData.blueprint.0.fields.state.value', $this->orgSupplierProduct->fresh()->state)
            ->has('formData.blueprint.0.fields.state.options', 3);
    });
});

test('UI Index purchase orders', function () {
    $response = $this->get(route('grp.org.procurement.purchase_orders.index', [$this->organisation->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/PurchaseOrders')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('queryBuilderProps.default.elementGroups.attention.elements.unconfirmed_over_7_days')
            ->has('queryBuilderProps.default.elementGroups.attention.elements.unconfirmed_over_30_days')
            ->has('queryBuilderProps.default.elementGroups.attention.elements.past_expected_date');
    });

    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $overdue       = StorePurchaseOrder::make()->action($orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'Overdue attention PO']), strict: false);
    $overdue->forceFill([
        'agent_id'              => $this->agent->id,
        'date'                  => now()->addYear(),
        'state'                 => PurchaseOrderStateEnum::SUBMITTED,
        'submitted_at'          => now()->subDays(10),
        'estimated_received_at' => now()->subDay(),
    ])->saveQuietly();
    $draft = StorePurchaseOrder::make()->action($orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'Client draft PO']), strict: false);
    $draft->forceFill(['agent_id' => $this->agent->id, 'date' => now()->addYear()])->saveQuietly();

    $references = fn ($rows) => collect($rows)->pluck('reference');

    $this->get(route('grp.org.procurement.purchase_orders.index', [
        $this->organisation->slug,
        'elements' => ['attention' => 'unconfirmed_over_7_days,unconfirmed_over_30_days,past_expected_date'],
    ]))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/PurchaseOrders')
        ->where('data.data', fn ($rows) => $references($rows)->contains('Overdue attention PO')
            && !$references($rows)->contains('Client draft PO')
            && collect($rows)->every(fn ($row) => in_array($row['state'], ['submitted', 'confirmed'], true))));

    $this->get(route('grp.org.agent.purchase_orders.index', [$this->agent->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrders')
            ->where('data.data', fn ($rows) => $references($rows)->contains('Overdue attention PO')
                && !$references($rows)->contains('Client draft PO')));
});

test('UI index purchase orders for supplier shows supplier-specific columns and row details', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action(
        $this->orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'Supplier table test PO']),
        strict: false,
    );
    $this->orgSupplier->stats()->update([
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
            ->where('pageHead.actions.0.label', __('Purchase Order'))
            ->where('pageHead.actions.0.route.name', 'grp.models.org-supplier.purchase-order.store')
            ->where('data.data', function ($rows) use ($purchaseOrder) {
                $row = $rows->firstWhere('slug', $purchaseOrder->slug);

                return $row
                    && $row['state_label'] === PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value]
                    && $row['number_current_purchase_order_transactions'] === $purchaseOrder->number_current_purchase_order_transactions;
            })
            ->etc();
    });
});

test('purchase orders search matches a reference from the middle and the supplier name', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action(
        $this->orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'Equinox00162']),
        strict: false,
    );

    foreach (['00162', $purchaseOrder->parent_name] as $search) {
        $this->get(route('grp.org.procurement.purchase_orders.index', [
            $this->organisation->slug,
            'filter[global]' => $search,
        ]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($rows) => $rows->firstWhere('slug', $purchaseOrder->slug) !== null)
            ->etc());
    }
});

test('stock deliveries search matches a reference from the middle and the supplier name', function () {
    $stockDelivery = $this->stockDelivery;
    $stockDelivery->update(['reference' => 'Equinox00161']);

    foreach (['00161', $stockDelivery->parent_name] as $search) {
        $this->get(route('grp.org.procurement.stock_deliveries.index', [
            $this->organisation->slug,
            'filter[global]' => $search,
        ]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.data', fn ($rows) => $rows->firstWhere('slug', $stockDelivery->slug) !== null)
            ->etc());
    }
});

test('UI show purchase order', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $this->purchaseOrder->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/PurchaseOrder')
            ->where('data.data.is_partner', $this->purchaseOrder->parent_type === 'OrgPartner')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->purchaseOrder->reference)
                    ->where('model', 'Purchase Order')
                    ->etc()
            )
            ->has('tabs')
            ->has('box_stats')
            ->has('timelines')
            ->has('data');
    });
});

test('new purchase orders use the default warehouse address and show empty physical totals without warnings', function () {
    $address = Address::factory()->create([
        'group_id'       => $this->group->id,
        'address_line_1' => 'Default warehouse',
        'address_line_2' => 'One Example Road',
        'locality'       => 'Sheffield',
        'postal_code'    => 'S1 1AA',
    ]);

    $warehouse = $this->organisation->warehouses()->oldest('id')->first();

    if ($warehouse) {
        $warehouse->update(['address_id' => $address->id]);
    } else {
        Warehouse::factory()->create([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'address_id'      => $address->id,
        ]);
    }

    $purchaseOrder = StorePurchaseOrder::make()->action(
        $this->orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), [
            'reference' => 'PO-DEFAULT-WAREHOUSE-'.PurchaseOrder::max('id'),
            'data'      => [],
        ]),
        strict: false
    );

    expect(Arr::get($purchaseOrder->data, 'delivery_address'))->toBe($address->formatted_address);
    $purchaseOrder->update(['data' => []]);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrder')
            ->where('box_stats.first_block.delivery.delivery_address', $address->formatted_address)
            ->where('box_stats.second_block.total_items', 0)
            ->where('box_stats.second_block.weight', 0)
            ->where('box_stats.second_block.volume', 0)
            ->where('box_stats.second_block.is_weight_partial', false)
            ->where('box_stats.second_block.is_volume_partial', false)
            ->missing('tabs.navigation.showcase')
            ->has('showcase.blueprint')
            ->etc());
});

test('an address without a country formats its lines instead of failing', function () {
    $address = new Address(['address_line_1' => '1 Example Road', 'locality' => 'Trnava', 'postal_code' => '917 01']);

    expect($address->formatted_address)->toBe("1 Example Road\nTrnava\n917 01")
        ->and($address->getHtml())->toBe("1 Example Road<br />\nTrnava<br />\n917 01")
        ->and((new Address())->formatted_address)->toBe('');
});

test('purchase order submit availability follows its submittable items', function () {
    $purchaseOrder = StorePurchaseOrder::make()->action(
        $this->orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), [
            'reference' => 'PO-SUBMIT-GUARD-'.PurchaseOrder::max('id'),
        ]),
        strict: false
    );

    try {
        UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);
        $this->fail('An empty purchase order was submitted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('transactions');
    }

    expect($purchaseOrder->fresh()->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS);

    $assertSubmitAction = function (PurchaseOrder $order, bool $expected): void {
        $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $order->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pageHead.actions', fn ($actions) => collect($actions)->contains(
                    fn ($action) => Arr::get($action, 'key') === 'submit_purchase_order'
                ) === $expected)
                ->etc());
    };

    $assertSubmitAction($purchaseOrder, false);

    $transaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $this->orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        PurchaseOrderTransaction::factory()->definition()
    );

    $assertSubmitAction($purchaseOrder, true);

    $submittedPurchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder);

    expect($submittedPurchaseOrder->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and($transaction->fresh()->state)->toBe(PurchaseOrderTransactionStateEnum::SUBMITTED);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $submittedPurchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions', fn ($actions) => !collect($actions)->contains(fn ($action) => Arr::get($action, 'key') === 'confirm_purchase_order')
                && collect($actions)->contains(fn ($action) => Arr::get($action, 'key') === 'undo_submit_purchase_order'
                    && Arr::get($action, 'route.name') === 'grp.models.purchase-order.undo-submit'))
            ->etc());

    $confirmRoute = route('grp.models.purchase-order.confirm', [
        'purchaseOrder' => $submittedPurchaseOrder->id,
    ]);

    $this->patchJson($confirmRoute, [
        'estimated_receiving_date' => 'not-a-date',
    ])->assertUnprocessable()->assertJsonValidationErrors('estimated_receiving_date');

    expect($submittedPurchaseOrder->fresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED);

    $estimatedReceivingDate = now()->addWeeks(3)->toDateString();

    $this->patchJson($confirmRoute, [
        'estimated_receiving_date' => $estimatedReceivingDate,
    ])->assertSuccessful();

    expect($submittedPurchaseOrder->fresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED)
        ->and(Arr::get($submittedPurchaseOrder->fresh()->data, 'estimated_receiving_date'))->toBe($estimatedReceivingDate);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $submittedPurchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('delivery_items', 1)
            ->where('pageHead.actions', fn ($actions) => collect($actions)->contains(
                fn ($action) => Arr::get($action, 'key') === 'edit_estimated_delivery_date'
                    && Arr::get($action, 'estimated_receiving_date') === $estimatedReceivingDate
            ) && collect($actions)->contains(
                fn ($action) => Arr::get($action, 'key') === 'new_stock_delivery'
            ))
            ->etc());

    $changedEstimatedReceivingDate = now()->addWeeks(5)->toDateString();

    $this->patchJson(route('grp.models.purchase-order.update', [
        'purchaseOrder' => $submittedPurchaseOrder->id,
    ]), [
        'estimated_receiving_date' => $changedEstimatedReceivingDate,
    ])->assertSuccessful();

    expect(Arr::get($submittedPurchaseOrder->fresh()->data, 'estimated_receiving_date'))
        ->toBe($changedEstimatedReceivingDate);

    $cancelledPurchaseOrder = StorePurchaseOrder::make()->action(
        $this->orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), [
            'reference' => 'PO-CANCELLED-SUBMIT-GUARD-'.PurchaseOrder::max('id'),
        ]),
        strict: false
    );

    $cancelledTransaction = StorePurchaseOrderTransaction::make()->action(
        $cancelledPurchaseOrder,
        $this->orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        PurchaseOrderTransaction::factory()->definition()
    );

    $cancelledTransaction->update(['state' => PurchaseOrderTransactionStateEnum::CANCELLED]);

    $assertSubmitAction($cancelledPurchaseOrder, false);

    expect(fn () => UpdatePurchaseOrderStateToSubmitted::make()->action($cancelledPurchaseOrder))
        ->toThrow(ValidationException::class);
});
test('without a live exchange rate the latest stored one is used, never a rate of 1', function () {
    $pound = Currency::where('code', 'GBP')->firstOrFail();
    $euro  = Currency::where('code', 'EUR')->firstOrFail();
    Cache::forget('current-currency-exchange:GBP-EUR');
    FetchCurrencyExchange::shouldRun()->andThrow(new Exception('provider down'));

    CurrencyExchange::updateOrCreate(['currency_id' => $euro->id, 'date' => '2001-01-02'], ['exchange' => 1.1]);
    CurrencyExchange::updateOrCreate(['currency_id' => $euro->id, 'date' => '2001-01-03'], ['exchange' => 1.2]);

    expect(GetCurrencyExchange::run($pound, $euro))->toBe(CurrencyExchange::where('currency_id', $euro->id)->latest('date')->value('exchange') / 1.0)
        ->and(GetCurrencyExchange::run($pound, $euro))->not->toBe(1.0);

    Cache::forget('current-currency-exchange:GBP-EUR');
});

test('a currency pair once asked for is kept warm, so pages stop waiting for the provider', function () {
    $pound = Currency::where('code', 'GBP')->firstOrFail();
    $euro  = Currency::where('code', 'EUR')->firstOrFail();
    Cache::forget('current-currency-exchange:GBP-EUR');
    FetchCurrencyExchange::shouldRun()->times(2)->andReturn(['status' => 'success', 'exchange' => 1.15, 'source' => 'CB'], ['status' => 'success', 'exchange' => 1.17, 'source' => 'CB']);

    expect(GetCurrencyExchange::run($pound, $euro))->toBe(1.15)
        ->and(Cache::get(WarmCurrencyExchanges::PAIRS_KEY))->toContain('GBP-EUR');

    Cache::forever(WarmCurrencyExchanges::PAIRS_KEY, ['GBP-EUR']);

    expect(WarmCurrencyExchanges::run())->toBe(['GBP-EUR' => 1.17])
        ->and(GetCurrencyExchange::run($pound, $euro))->toBe(1.17);

    Cache::forget('current-currency-exchange:GBP-EUR');
    Cache::forget(WarmCurrencyExchanges::PAIRS_KEY);
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

test('UI Index stock deliveries shows the expected received date', function () {
    $stockDelivery = StoreStockDelivery::make()->action(
        $this->orgAgent,
        [
            'reference'   => 'EXPECTED-DATE-1',
            'date'        => date('Y-m-d'),
            'currency_id' => $this->organisation->currency_id,
        ],
        strict: false,
    );
    UpdateStockDelivery::make()->action($stockDelivery, ['estimated_receiving_date' => '2026-10-15']);

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.index', [$this->organisation->slug]).'?sort=estimated_receiving_date');

    $data = $response->viewData('page')['props']['data'];
    $row  = collect($data['data'] ?? $data)->firstWhere('id', $stockDelivery->id);
    expect($row['estimated_receiving_date'])->toBe('2026-10-15')
        ->and($row['parent_type'])->toBe('OrgAgent')
        ->and($row['parent_route_key'])->toBe($this->orgAgent->slug);
});

test('UI Index stock deliveries counts the items that have never been in stock', function () {
    $stockDelivery = StoreStockDelivery::make()->action(
        $this->orgAgent,
        [
            'reference'   => 'NEW-ITEMS-1',
            'date'        => date('Y-m-d'),
            'currency_id' => $this->organisation->currency_id,
        ],
        strict: false,
    );
    [$newOrgStock, $stockedOrgStock, $cancelledNewOrgStock] = OrgStock::where('organisation_id', $this->organisation->id)->limit(3)->get();
    $newOrgStock->update(['has_been_in_warehouse' => false]);
    $stockedOrgStock->update(['has_been_in_warehouse' => true]);
    $cancelledNewOrgStock->update(['has_been_in_warehouse' => false]);
    StoreStockDeliveryItem::make()->action($stockDelivery, null, $newOrgStock, ['unit_quantity' => 10, 'state' => StockDeliveryItemStateEnum::DISPATCHED], strict: false);
    StoreStockDeliveryItem::make()->action($stockDelivery, null, $stockedOrgStock, ['unit_quantity' => 10, 'state' => StockDeliveryItemStateEnum::DISPATCHED], strict: false);
    StoreStockDeliveryItem::make()->action($stockDelivery, null, $cancelledNewOrgStock, ['unit_quantity' => 10, 'state' => StockDeliveryItemStateEnum::CANCELLED], strict: false);

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.index', [$this->organisation->slug]).'?filter[global]=NEW-ITEMS-1');

    $data = $response->viewData('page')['props']['data'];
    $row  = collect($data['data'] ?? $data)->firstWhere('id', $stockDelivery->id);
    expect($row['number_new_org_stocks'])->toBe(1);

    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('box_stats.second_block.total_new_org_stocks', 1)->etc());

    $isNewByOrgStock = collect(IndexStockDeliveryItems::run($stockDelivery)->items())
        ->mapWithKeys(fn (StockDeliveryItem $item) => [$item->org_stock_id => StockDeliveryItemResource::make($item)->toArray(request())['is_new_org_stock']]);
    expect($isNewByOrgStock[$newOrgStock->id])->toBeTrue()
        ->and($isNewByOrgStock[$stockedOrgStock->id])->toBeFalse();
});

test('UI Index org agent stock deliveries shows deliveries with empty between filter', function () {
    $agentStockDelivery = StoreStockDelivery::make()->action(
        $this->orgAgent,
        [
            'reference'   => 'AGENT-DELIVERY-1',
            'date'        => date('Y-m-d'),
            'currency_id' => $this->organisation->currency_id,
        ],
        strict: false,
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

test('stock delivery tab counts follow the other selected filters, so In process matches the supplier list (HELP-4086)', function () {
    $this->withoutVite();
    $warehouse = $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    allowGoodsInOn($warehouse, $this->adminGuest->getUser());

    $expectedCount = fn (string $column, string $value, string $otherColumn, string $otherValue) => StockDelivery::where('organisation_id', $this->organisation->id)
        ->where($column, $value)->where($otherColumn, $otherValue)->count();

    $elements = $this->get(route('grp.org.warehouses.show.incoming.stock_deliveries.index', [$this->organisation->slug, $warehouse->slug]).'?elements[source]=OrgSupplier&elements[state]=in_process')
        ->assertOk()
        ->viewData('page')['props']['queryBuilderProps']['default']['elementGroups'];

    expect($elements['state']['elements']['in_process'][1])->toBe($expectedCount('state', 'in_process', 'parent_type', 'OrgSupplier'))
        ->and($elements['state']['elements']['dispatched'][1])->toBe($expectedCount('state', 'dispatched', 'parent_type', 'OrgSupplier'))
        ->and($elements['source']['elements']['OrgAgent'][1])->toBe($expectedCount('parent_type', 'OrgAgent', 'state', 'in_process'));
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
            ->has('queryBuilderProps.items.columns', 9)
            ->has(
                'queryBuilderProps.items.columns.0',
                fn (AssertableInertia $page) => $page
                    ->where('key', 'state_icon')
                    ->where('type', 'icon')
                    ->etc()
            )
            ->where('queryBuilderProps.items.columns.4.key', 'purchase_orders')
            ->has(
                'queryBuilderProps.items.columns.8',
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

    $warehouse      = $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    allowGoodsInOn($warehouse, $this->adminGuest->getUser());
    $procurementUrl = route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]);
    $goodsInUrl     = route('grp.org.warehouses.show.incoming.stock_deliveries.show', [$this->organisation->slug, $warehouse->slug, $stockDelivery->slug]);

    $this->withoutExceptionHandling();
    $this->withoutVite();

    $this->get($procurementUrl)->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has('costing.checklist')
            ->where('tabs.current', StockDeliveryTabsEnum::ITEMS->value)
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::ITEMS->value)
            ->missing('tabs.navigation.'.StockDeliveryTabsEnum::PENDING_ITEMS->value)
            ->missing('tabs.navigation.'.StockDeliveryTabsEnum::DONE_ITEMS->value);
    });

    $this->get($procurementUrl.'?tab='.StockDeliveryTabsEnum::PENDING_ITEMS->value)
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', StockDeliveryTabsEnum::ITEMS->value));

    $this->get($goodsInUrl)->assertInertia(function (AssertableInertia $page) use ($stockDelivery) {
        $page
            ->component('Procurement/StockDelivery')
            ->where('tabs.current', StockDeliveryTabsEnum::PENDING_ITEMS->value)
            ->where('pageHead.edit', false)
            ->where('costing', null)
            ->where('invoice_costing', [])
            ->where('service_invoices', null)
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::PENDING_ITEMS->value)
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::DONE_ITEMS->value)
            ->missing('tabs.navigation.'.StockDeliveryTabsEnum::ITEMS->value)
            ->missing('tabs.navigation.showcase')
            ->missing('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value)
            ->has(StockDeliveryTabsEnum::PENDING_ITEMS->value.'.data', 2);
    });

    $goodsInClerk = User::factory()->create(['group_id' => $warehouse->group_id]);
    $goodsInClerk->assignRole(RolesEnum::getRoleName(RolesEnum::GOODS_IN_CLERK->value, $warehouse));
    $this->actingAs($goodsInClerk)->get($goodsInUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('costing', null));
    $this->actingAs($this->adminGuest->getUser());

    $this->withExceptionHandling();
    $this->get($goodsInUrl.'?tab='.StockDeliveryTabsEnum::ITEMS->value)->assertNotFound();
    $this->withoutExceptionHandling();

    $doneResponse = $this->get($goodsInUrl.'?tab='.StockDeliveryTabsEnum::DONE_ITEMS->value);
    $doneResponse->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has(StockDeliveryTabsEnum::DONE_ITEMS->value.'.data', 0);
    });
});

test('UI stock delivery items offer a search over every warehouse location', function () {
    $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    $stockDelivery = createStockDeliveryWithItems($this, 'SEARCH-ANY-LOCATION', [10]);

    $this->withoutExceptionHandling();
    $this->withoutVite();
    $response = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::ITEMS->value);

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.searchLocationsRoute.name', 'grp.org.warehouses.show.infrastructure.locations.index.excluded_in_org_stock');
    });
});

test('UI stock delivery items tab does not query per row', function () {
    $this->withoutExceptionHandling();
    $this->withoutVite();

    $countQueries = function (StockDelivery $stockDelivery): int {
        $url = route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::ITEMS->value;
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    $oneItem    = $countQueries(createStockDeliveryWithItems($this, 'NO-N-PLUS-ONE-1', [10]));
    $threeItems = $countQueries(createStockDeliveryWithItems($this, 'NO-N-PLUS-ONE-3', [10, 10, 10]));

    expect($threeItems - $oneItem)->toBeLessThan(2);
});

test('UI stock delivery items without a supplier product sort by the SKO code', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'SORT-BY-SKO-CODE', [10, 10]);
    $stockDelivery->items()->update(['supplier_product_id' => null]);
    $skoCodes = $stockDelivery->items()->with('orgStock')->get()->pluck('orgStock.code')->sort()->values();

    $this->withoutExceptionHandling();
    $this->withoutVite();
    $url = route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::ITEMS->value;

    $this->get($url.'&items_sort=code')->assertInertia(
        fn (AssertableInertia $page) => $page->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.code', $skoCodes->first())
    );
    $this->get($url.'&items_sort=-code')->assertInertia(
        fn (AssertableInertia $page) => $page->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.code', $skoCodes->last())
    );
});

test('UI placed stock delivery costing tab shows every item on one page', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'COSTING-ONE-PAGE', [10, 10]);
    $stockDelivery->update(['state' => StockDeliveryStateEnum::PLACED]);

    $this->withoutExceptionHandling();
    $this->withoutVite();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab='.StockDeliveryTabsEnum::ITEMS->value)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.meta.per_page', config('ui.table.max_records_per_page'))
            ->has(StockDeliveryTabsEnum::ITEMS->value.'.data.0.updateCostRoute')
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.unit_quantity', fn ($unitQuantity) => (float) $unitQuantity === 10.0)
            ->has(StockDeliveryTabsEnum::ITEMS->value.'.data.0.cost_per_sko_org'));
});

test('goods in workers book in stock deliveries and only goods in supervisors unmark them as received', function () {
    $warehouse = $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    setPermissionsTeamId($warehouse->group_id);
    \App\Actions\Inventory\Warehouse\SeedWarehousePermissions::run($warehouse);

    $stockDelivery     = createStockDeliveryWithItems($this, 'GOODS-IN-BOOK-IN', [10]);
    $stockDeliveryItem = $stockDelivery->items()->firstOrFail();
    $this->withoutVite();

    $worker = User::factory()->create(['group_id' => $warehouse->group_id]);
    $worker->assignRole(RolesEnum::getRoleName(RolesEnum::GOODS_IN_CLERK->value, $warehouse));
    $supervisor = User::factory()->create(['group_id' => $warehouse->group_id]);
    $supervisor->assignRole(RolesEnum::getRoleName(RolesEnum::GOODS_IN_SUPERVISOR->value, $warehouse));

    $showRoute   = route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]);
    $actionKeys  = fn (User $user) => $this->actingAs($user)->get($showRoute)->assertOk()->viewData('page')['props']['pageHead']['actions'];
    $keys        = fn (array $actions) => collect($actions)->pluck('key')->filter()->values()->all();

    $this->actingAs($worker)
        ->get(route('grp.org.warehouses.show.incoming.stock_deliveries.index', [$this->organisation->slug, $warehouse->slug]))
        ->assertOk();

    expect($keys($actionKeys($worker)))->toBe(['receive_stock_delivery', 'documents']);

    $documentRouteParameters = [$this->organisation->slug, $stockDelivery->slug];
    $this->actingAs($worker)->get(route('grp.org.procurement.stock_deliveries.pdf', $documentRouteParameters))->assertOk();
    $this->actingAs($worker)->get(route('grp.org.procurement.stock_deliveries.csv', $documentRouteParameters))->assertOk();
    $this->actingAs(User::factory()->create(['group_id' => $warehouse->group_id]))->get(route('grp.org.procurement.stock_deliveries.pdf', $documentRouteParameters))->assertForbidden();

    $this->actingAs($worker)->patch(route('grp.models.stock-delivery.receive', $stockDelivery->id), ['reason' => 'Invoice not sent yet'])->assertRedirect();
    expect($stockDelivery->refresh()->state)->toBe(StockDeliveryStateEnum::RECEIVED)
        ->and($keys($actionKeys($worker)))->toBe(['book_in_link', 'documents'])
        ->and($keys($actionKeys($supervisor)))->toBe(['book_in_link', 'unreceive_stock_delivery', 'documents']);

    $this->actingAs($worker)->patch(route('grp.models.stock-delivery.unreceive', $stockDelivery->id))->assertForbidden();
    $this->actingAs($supervisor)->patch(route('grp.models.stock-delivery.unreceive', $stockDelivery->id))->assertRedirect();
    expect($stockDelivery->refresh()->state)->not->toBe(StockDeliveryStateEnum::RECEIVED);

    $this->actingAs($worker)->patch(route('grp.models.stock-delivery.receive', $stockDelivery->id), ['reason' => 'Invoice not sent yet'])->assertRedirect();
    $checkResponse = $this->actingAs($worker)->patch(route('grp.models.stock-delivery-item.set-checked', $stockDeliveryItem->id), ['unit_quantity_checked' => 10]);
    expect($checkResponse->status())->not->toBe(403)
        ->and($stockDeliveryItem->refresh()->state)->toBe(StockDeliveryItemStateEnum::CHECKED);

    actingAs($this->adminGuest->getUser());
});

test('payments to an agent carry their method and bank statement, and the agent staff record theirs and pay deposits too (HELP-3872)', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'AGENT-PAY-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['agent_id' => $this->agent->id]);
    $statement  = fn (string $name) => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, '%PDF-1.4 '.$name.' '.Str::random(12));
    $storeRoute = route('grp.models.stock-delivery.agent_payment.store', $stockDelivery->id);

    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 80, 'method' => 'bank_transfer'])->assertSessionHasErrors('bank_statement');
    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 80, 'bank_statement' => $statement('a.pdf')])->assertSessionHasErrors('method');
    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 80, 'method' => 'bank_transfer', 'reference' => 'TT-80', 'bank_statement' => $statement('a.pdf')])
        ->assertRedirect()->assertSessionHasNoErrors();

    $organisationPayment = $stockDelivery->agentPayments()->latest('id')->firstOrFail();
    expect($organisationPayment->method->value)->toBe('bank_transfer')
        ->and($organisationPayment->media_id)->not->toBeNull()
        ->and($stockDelivery->attachments()->wherePivot('scope', 'Bank statement')->count())->toBe(1);

    setPermissionsTeamId($this->group->id);
    $agentClerk = agentPaymentsClerk($this->agent->organisation);
    expect($agentClerk->authTo("procurement.{$this->organisation->id}.edit"))->toBeFalse();
    actingAs($agentClerk);

    $this->post($storeRoute, ['date' => date('Y-m-d'), 'amount' => 20, 'method' => 'cash'])->assertRedirect()->assertSessionHasNoErrors();
    $agentPayment = $stockDelivery->agentPayments()->latest('id')->firstOrFail();
    expect($agentPayment->created_by)->toBe($agentClerk->id);

    $panel = \App\Actions\SupplyChain\AgentInvoice\UI\GetAgentContainerInvoiceData::run($this->agent->organisation, $stockDelivery->refresh());
    $rows  = collect($panel['payments'])->keyBy('id');
    expect($panel['payment_store_route'])->not->toBeNull()
        ->and($rows[$organisationPayment->id]['method'])->toBe('Bank transfer')
        ->and($rows[$organisationPayment->id]['bank_statement'])->not->toBeNull()
        ->and($rows[$organisationPayment->id]['delete_route'])->toBeNull()
        ->and($rows[$agentPayment->id]['delete_route'])->not->toBeNull();

    $this->delete(route('grp.models.agent_payment.delete', $organisationPayment->id))->assertForbidden();
    $this->delete(route('grp.models.agent_payment.delete', $agentPayment->id))->assertRedirect();
    expect($stockDelivery->agentPayments()->pluck('id')->all())->toBe([$organisationPayment->id]);

    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action(
        $orgSupplier,
        array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'AGENT-DEP-'.uniqid()]),
        strict: false
    );
    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED, 'cost_total' => 500]);
    expect($purchaseOrder->isAgentOrder())->toBeTrue();

    $this->post(route('grp.models.purchase-order.payment.confirm', $purchaseOrder->id), ['amount' => 500, 'paid_at' => date('Y-m-d'), 'method' => 'bank_transfer', 'bank_statement' => $statement('dep.pdf')])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->deposit_paid_by_id)->toBe($agentClerk->id);

    actingAs($this->adminGuest->getUser());
});

test('the agent keeps each client\'s money in a ledger: transfers in, supplier payments and expenses out, and the wallet balance worked out from them (HELP-4097)', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'AGENT-LEDGER-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['agent_id' => $this->agent->id]);
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'AGENT-LEDGER-'.uniqid()]), strict: false);
    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED]);

    setPermissionsTeamId($this->group->id);
    actingAs(agentPaymentsClerk($this->agent->organisation));

    $ledger     = route('grp.org.agent.accounting.ledger.index', $this->agent->organisation->slug);
    $walletOf   = fn () => collect($this->get($ledger)->assertOk()->viewData('page')['props']['wallets'])
        ->first(fn (array $wallet) => $wallet['organisation_id'] === $this->organisation->id && $wallet['currency_code'] === $this->agent->organisation->currency->code)
        ?? ['received' => 0, 'paid' => 0, 'balance' => 0];
    $before     = $walletOf();
    $storeRoute = route('grp.models.agent.ledger_entry.store', $this->agent->id);
    $entry      = ['organisation_id' => $this->organisation->id, 'date' => date('Y-m-d')];

    $this->post($storeRoute, $entry + ['type' => 'transfer_received', 'amount' => 10000, 'original_amount' => 1300, 'reference' => 'TT-LEDGER'])->assertSessionHasNoErrors();
    $this->post($storeRoute, $entry + ['type' => 'supplier_deposit', 'amount' => 3000])->assertSessionHasErrors('purchase_order_id');
    $this->post($storeRoute, $entry + ['type' => 'supplier_deposit', 'amount' => 3000, 'purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();
    $this->post($storeRoute, $entry + ['type' => 'packaging', 'amount' => 500, 'stock_delivery_id' => $stockDelivery->id, 'notes' => 'Hang tags'])->assertSessionHasNoErrors();
    $this->post($storeRoute, ['organisation_id' => $this->agent->organisation_id, 'date' => date('Y-m-d'), 'type' => 'packaging', 'amount' => 1])->assertSessionHasErrors('organisation_id');
    $this->post($storeRoute, $entry + ['type' => 'packaging', 'amount' => 5, 'org_stock_id' => $this->orgStocks[1]->id])->assertSessionHasErrors('org_stock_id');
    $this->post($storeRoute, $entry + ['type' => 'inspection', 'amount' => 40, 'reference' => 'INSP-'.Str::random(6)])->assertSessionHasNoErrors();
    $inspection = AgentPayment::where('agent_id', $this->agent->id)->where('type', AgentPaymentTypeEnum::INSPECTION)->latest('id')->firstOrFail();

    $transfer = AgentPayment::where('agent_id', $this->agent->id)->where('reference', 'TT-LEDGER')->latest('id')->firstOrFail();
    $deposit  = AgentPayment::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
    expect((float) $transfer->original_amount)->toBe(1300.0)
        ->and($transfer->original_currency_id)->toBe($this->organisation->currency_id)
        ->and($transfer->currency_id)->toBe($this->agent->organisation->currency_id)
        ->and($deposit->supplier_id)->toBe($purchaseOrder->supplier_id)
        ->and($stockDelivery->agentPayments()->count())->toBe(0)
        ->and($stockDelivery->agentLedgerEntries()->count())->toBe(1);

    $after = $walletOf();
    expect($after['received'] - $before['received'])->toEqual(10000)
        ->and($after['paid'] - $before['paid'])->toEqual(3540)
        ->and($after['balance'] - $before['balance'])->toEqual(6460);

    $unallocated = collect($this->get($ledger.'?elements[unallocated]=unallocated&filter[global]='.$inspection->reference)->viewData('page')['props']['data']['data']);
    expect($unallocated->pluck('id')->all())->toBe([$inspection->id]);

    $this->get($ledger.'?filter[global]=TT-LEDGER')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Org/Procurement/AgentLedger')
        ->where('data.data.0.money_in', fn ($amount) => (float) $amount === 10000.0)
        ->where('data.data.0.delete_route.name', 'grp.models.agent_payment.delete'));

    actingAs($this->adminGuest->getUser());
});

test('an agent\'s Excel balance sheet comes into the ledger as opening lines plus one line per row, with the rows to review flagged, and only when applied (HELP-4097)', function () {
    $orgAgent      = createFreshOrgAgent($this);
    $agent         = $orgAgent->agent;
    $orderNumber   = (string) random_int(100000, 999999);
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, []);
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 3]));
    $purchaseOrder->update(['agent_order_reference' => "LEDGER-$orderNumber", 'date' => '2026-01-20']);
    $newOrderNumber   = (string) random_int(100000, 999999);
    [$newOrgSupplier, $newOrgSupplierProduct] = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $newPurchaseOrder = StorePurchaseOrder::make()->action($newOrgSupplier, []);
    StorePurchaseOrderTransaction::make()->action($newPurchaseOrder, $newOrgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[1], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 2]));
    $newPurchaseOrder->update(['reference' => "LEDGER-$newOrderNumber"]);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $balance     = $spreadsheet->getActiveSheet()->setTitle('Balance');
    $balance->setCellValue('I1', 3650);
    $balance->fromArray([
        ['2025-12-01', 1000, null, 'Notes / Exchange rate', null, null, null, null, null, null],
        ['2025-12-05', null, 400, 'payment to shop', null, null, null, null, 'OLD', '12'],
        ['2026-02-01', 5000, null, 'Notes / Exchange rate', null, null, null, 'TT-5000', null, null],
        ['2026-02-03', null, 1200, 'payment to shop', null, null, null, null, $this->orgStocks[0]->code, $orderNumber],
        ['2026-02-04', null, 300, 'payment to shop', null, null, null, null, 'ZZZ-NONE', $orderNumber],
        [null, null, 200, 'Hang tags', 1, 200, null, null, null, null],
        ['2026-02-05', null, 100, 'inland charge -999999', null, null, 100, null, null, null],
        ['2026-02-06', null, 50, 'Taxi to the factory', null, null, 50, null, null, null],
        ['2026-02-07', null, 70, 'payment to shop', null, null, null, null, $this->orgStocks[0]->code, '777'],
        ['2026-02-08', null, 30, 'payment to shop', null, null, null, null, $this->orgStocks[1]->code, $newOrderNumber],
    ], null, 'A7');
    $fund = $spreadsheet->createSheet()->setTitle('Fund');
    $fund->fromArray([['2026-01-30', 650, 'EURO', null, null, 5000]], null, 'A5');
    $path = tempnam(sys_get_temp_dir(), 'ledger').'.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

    $import = fn (bool $apply) => ImportAgentLedgerExcel::make()->handle($agent, $this->organisation, $path, Carbon::parse('2026-01-01'), 'LEDGER-#', $apply);

    $dryRun = $import(false);
    expect(AgentPayment::where('agent_id', $agent->id)->count())->toBe(0)
        ->and($dryRun['rows'])->toBe(10)
        ->and($dryRun['opening'])->toBe(['received' => 1000.0, 'paid' => 400.0])
        ->and($dryRun['balance'])->toBe(3650.0)
        ->and($dryRun['excel_balance'])->toBe(3650.0);

    $import(true);
    $lines = AgentPayment::where('agent_id', $agent->id)->where('organisation_id', $this->organisation->id)->get()->keyBy(fn (AgentPayment $line) => $line->data['row'] ?? ($line->type->isMoneyIn() ? 'opening_in' : 'opening_out'));

    expect($lines)->toHaveCount(10)
        ->and((float) $lines['opening_in']->amount)->toBe(1000.0)
        ->and($lines['opening_in']->date->toDateString())->toBe('2025-12-31')
        ->and((float) $lines[9]->original_amount)->toBe(650.0)
        ->and($lines[10]->type)->toBe(AgentPaymentTypeEnum::SUPPLIER_PAYMENT)
        ->and($lines[10]->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($lines[10]->needs_review)->toBeFalse()
        ->and($lines[11]->review_reason)->toBe('Not tied to one order')
        ->and($lines[12]->type)->toBe(AgentPaymentTypeEnum::PACKAGING)
        ->and($lines[12]->review_reason)->toBe('No date in the Excel')
        ->and($lines[12]->date->toDateString())->toBe('2026-02-04')
        ->and($lines[13]->type)->toBe(AgentPaymentTypeEnum::INLAND_CHARGE)
        ->and($lines[14]->review_reason)->toBe('Expense type unclear')
        ->and($lines[15]->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($lines[15]->data['linked_by'])->toBe('code')
        ->and($lines[15]->needs_review)->toBeFalse()
        ->and($lines[16]->purchase_order_id)->toBe($newPurchaseOrder->id)
        ->and($lines[16]->needs_review)->toBeFalse()
        ->and(fn () => $import(true))->toThrow(Exception::class);

    setPermissionsTeamId($this->group->id);
    actingAs(agentOrganisationAdmin($agent->organisation, 'agt-lg-'.Str::lower(Str::random(6))));

    $update = route('grp.models.agent_payment.update', $lines[11]->id);
    $this->patch($update, ['date' => '2026-02-04', 'type' => 'transfer_received'])->assertSessionHasErrors('type');
    $this->patch($update, ['date' => '2026-02-04', 'type' => 'supplier_payment'])->assertSessionHasNoErrors();
    expect($lines[11]->refresh()->needs_review)->toBeFalse();
    $this->patch($update, ['date' => '2026-02-04', 'type' => 'supplier_payment', 'purchase_order_id' => $purchaseOrder->id])->assertSessionHasNoErrors();

    expect($lines[11]->refresh()->needs_review)->toBeFalse()
        ->and($lines[11]->supplier_id)->toBe($purchaseOrder->supplier_id)
        ->and((float) $lines[11]->amount)->toBe(300.0);

    $this->get(route('grp.org.agent.accounting.ledger.index', [$agent->organisation->slug, 'elements' => ['review' => 'needs_review']]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('data.data', 2));

    actingAs($this->adminGuest->getUser());
});

test('an agent invoices a container from its lines, its charges are the ledger lines of the container with its agent fee, it takes off what was paid in advance and can not dispatch it without the invoice', function () {
    $orgAgent      = createFreshOrgAgent($this);
    $agent         = $orgAgent->agent;
    $stockDelivery = createStockDeliveryWithItems($this, 'AGENT-INV-'.Str::random(6), [10, 4]);
    $stockDelivery->updateQuietly(['agent_id' => $agent->id]);
    $agent->organisation->update(['currency_id' => $stockDelivery->currency_id]);
    $orgAgent->update(['agent_fee_percentage' => 5]);
    [$first, $second] = $stockDelivery->items()->orderBy('id')->get()->all();
    $first->updateQuietly(['net_amount' => 100]);
    $second->updateQuietly(['net_amount' => 40]);

    setPermissionsTeamId($this->group->id);
    actingAs(agentOrganisationAdmin($agent->organisation, 'agt-inv-'.Str::lower(Str::random(6))));
    $this->patch(route('grp.models.stock-delivery.dispatch', $stockDelivery->id))->assertSessionHasErrors('invoice');

    $invoice = StoreAgentInvoice::make()->handle($agent, $stockDelivery->refresh());
    $fee     = AgentPayment::where('stock_delivery_id', $stockDelivery->id)->where('type', AgentPaymentTypeEnum::AGENT_FEE)->sole();

    expect($invoice->number_lines)->toBe(2)
        ->and((float) $invoice->goods_amount)->toBe(140.0)
        ->and($invoice->lines[0]['unit_price'])->toEqual(10)
        ->and($stockDelivery->refresh()->data['invoice_number'])->toBe($invoice->reference)
        ->and((float) $fee->amount)->toBe(7.0)
        ->and($invoice->charges)->toHaveCount(1)
        ->and((float) $invoice->total_amount)->toBe(147.0);

    $ledgerLine = fn (array $data) => StoreAgentLedgerEntry::make()->action($agent, $data + ['organisation_id' => $stockDelivery->organisation_id, 'date' => date('Y-m-d'), 'stock_delivery_id' => $stockDelivery->id]);
    $ledgerLine(['type' => 'other_expense', 'amount' => 12.5, 'notes' => 'Commission']);
    $packaging = $ledgerLine(['type' => 'packaging', 'amount' => 3, 'org_stock_id' => $first->org_stock_id]);

    $invoice = $invoice->refresh();
    expect($invoice->charges)->toHaveCount(3)
        ->and(collect($invoice->charges)->firstWhere('ledger_id', $packaging->id))->toMatchArray(['type' => 'packing_charge', 'amount' => 3.0, 'org_stock_id' => $first->org_stock_id])
        ->and((float) $invoice->total_amount)->toBe(162.5);

    StoreAgentPayment::make()->handle($stockDelivery, ['date' => now()->toDateString(), 'amount' => 50, 'reference' => 'TT-1']);

    $again = StoreAgentInvoice::make()->handle($agent, $stockDelivery->refresh());

    expect($again->id)->toBe($invoice->id)
        ->and($again->number)->toBe($invoice->number)
        ->and(AgentPayment::where('stock_delivery_id', $stockDelivery->id)->where('type', AgentPaymentTypeEnum::AGENT_FEE)->count())->toBe(1)
        ->and((float) $again->total_amount)->toBe(162.5)
        ->and($again->paidAmount())->toBe(50.0)
        ->and($again->balanceDue())->toBe(112.5);

    $summary = ShowStockDelivery::make()->getInvoiceSummary($stockDelivery->refresh());

    expect($summary)->toMatchArray(['kind' => 'agent', 'source' => 'agent', 'reference' => $again->reference, 'goods' => 140.0, 'charges' => 22.5, 'total' => 162.5, 'paid' => 50.0, 'balance_due' => 112.5])
        ->and($summary['org_currency'])->toBe($stockDelivery->organisation->currency->code);

    \App\Actions\SupplyChain\AgentPayment\DeleteAgentPayment::make()->handle($packaging);
    expect((float) $again->refresh()->total_amount)->toBe(159.5);

    $again->update(['source' => \App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum::ACTUAL]);
    $typedFromPaper = $ledgerLine(['type' => 'other_expense', 'amount' => 4]);
    expect((float) $again->refresh()->total_amount)->toBe(159.5);
    \App\Actions\SupplyChain\AgentPayment\DeleteAgentPayment::make()->handle($typedFromPaper);
    $again->update(['source' => \App\Enums\SupplyChain\StockDeliveryInvoice\StockDeliveryInvoiceSourceEnum::AGENT]);

    $second->updateQuietly(['unit_quantity' => 5]);
    $this->patch(route('grp.models.stock-delivery.dispatch', $stockDelivery->id))->assertSessionHasErrors('invoice');
    $second->updateQuietly(['unit_quantity' => 4]);

    $this->patch(route('grp.models.stock-delivery.dispatch', $stockDelivery->id))->assertSessionHasErrors('reason');
    $this->patch(route('grp.models.stock-delivery.dispatch', $stockDelivery->id), ['reason' => 'Packing list comes with the goods'])->assertSessionHasNoErrors();

    expect(fn () => StoreAgentInvoice::make()->handle($agent, $stockDelivery->refresh()))->toThrow(ValidationException::class);
});

test('only the agent marks its container as dispatched, unless the agent lets buyers do it, and then only once the buyer has entered the invoice (HELP-3947, HELP-4284)', function () {
    $this->withoutVite();
    $agent         = createFreshOrgAgent($this)->agent;
    $stockDelivery = createStockDeliveryWithItems($this, 'AGENT-PAPER-'.Str::random(6), [10]);
    $stockDelivery->updateQuietly(['agent_id' => $agent->id]);
    $showPage = fn () => $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]));
    $dispatch = fn () => $this->patch(route('grp.models.stock-delivery.dispatch', $stockDelivery->id), ['reason' => 'Packing list comes with the goods']);

    $showPage()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('box_stats.invoice_entry.is_agent', true)
        ->where('pageHead.actions', fn ($actions) => !str_contains(json_encode($actions), 'dispatch_stock_delivery')));
    $dispatch()->assertSessionHasErrors('state');

    UpdateAgent::make()->action($agent, ['buyers_manage_stock_deliveries' => true]);
    expect($agent->refresh()->settings['buyers_manage_stock_deliveries'])->toBeTrue();

    $showPage()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('pageHead.actions', fn ($actions) => str_contains(json_encode($actions), 'dispatch_stock_delivery')));
    $dispatch()->assertSessionHasErrors('invoice');

    $this->post(route('grp.models.stock-delivery.invoice.store', $stockDelivery->id), [
        'reference'    => 'AW CHINA-143',
        'date'         => '2026-09-30',
        'goods_amount' => 340511.24,
        'charges'      => [],
        'attachments'  => [\Illuminate\Http\UploadedFile::fake()->create('INR INVOICE.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
    ])->assertSessionHasNoErrors();

    $dispatch()->assertSessionHasNoErrors();

    expect($stockDelivery->refresh()->state)->toBe(StockDeliveryStateEnum::DISPATCHED)
        ->and($stockDelivery->is_costed)->toBeFalse();
});

test('an agent opens a shipment that goes as a full container, LCL, by air or as a parcel, and its pages call them shipments (HELP-4292)', function () {
    $this->withoutVite();
    $orgAgent     = createFreshOrgAgent($this);
    $agent        = $orgAgent->agent;
    $billOfLading = \App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum::labels()['Bill of lading'];

    $byAir = StoreAgentStockDelivery::make()->action($agent, $orgAgent, ['delivery_type' => 'air', 'container_size' => '20ft']);
    $full  = StoreAgentStockDelivery::make()->action($agent, $orgAgent, []);

    expect($byAir->data['delivery_type'])->toBe('air')
        ->and($byAir->data)->not->toHaveKey('container_size')
        ->and($full->data['delivery_type'])->toBe('container')
        ->and($full->data['container_size'])->toBe('40ft')
        ->and(DispatchStockDelivery::missingEvidence($byAir))->not->toContain($billOfLading);

    $shared = UpdateStockDelivery::make()->action($full, ['delivery_type' => 'lcl']);
    expect($shared->refresh()->data['delivery_type'])->toBe('lcl')
        ->and($shared->data['container_size'] ?? null)->toBeNull()
        ->and(DispatchStockDelivery::missingEvidence($shared))->toContain($billOfLading);

    setPermissionsTeamId($this->group->id);
    actingAs(agentOrganisationAdmin($agent->organisation, 'agt-ship-'.Str::lower(Str::random(6))));

    $this->get(route('grp.org.agent.stock_deliveries.create', $agent->organisation->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('title', __('New shipment'))
            ->where('formData.blueprint.0.fields.delivery_type.options', fn ($options) => collect($options)->pluck('value')->all() === ['container', 'lcl', 'air', 'parcel'])
            ->where('formData.blueprint.0.fields.container_size.showIf', ['field' => 'delivery_type', 'value' => 'container']));

    $this->get(route('grp.org.agent.stock_deliveries.current', $agent->organisation->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.title', __('Current shipments')));

    expect(collect(\App\Actions\UI\Grp\Layout\GetOrganisationsLayout::run(auth()->user())[$agent->organisation->slug]['agent_orders']['topMenu']['subSections'])->pluck('route.name'))->toContain('grp.org.agent.stock_deliveries.index');

    $this->get(route('grp.org.agent.stock_deliveries.show', [$agent->organisation->slug, $byAir->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.model', __('Shipment'))
            ->where('box_stats.first_block.delivery.type_label', __('Air freight')));

    $this->patch(route('grp.models.stock-delivery.update', $byAir->id), ['delivery_type' => 'parcel'])->assertSessionHasNoErrors();
    $byAir->updateQuietly(['state' => StockDeliveryStateEnum::DISPATCHED]);
    $this->patch(route('grp.models.stock-delivery.update', $byAir->id), ['delivery_type' => 'air'])->assertSessionHasErrors('delivery_type');
    expect($byAir->refresh()->data['delivery_type'])->toBe('parcel');
});

test('stock delivery counts under and over delivered items', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'UNDER-OVER', [10, 10, 10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
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

    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 8, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id]);

    expect((float) $stockDeliveryItem->unit_quantity_placed)->toBe(8.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED)
        ->and($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN)
        ->and($stockDelivery->fresh()->booked_in_at)->not->toBeNull();
});

test('the warehouse puts away a delivery without costs, procurement is told once and reminded daily with a reason after 5 working days', function () {
    Notification::fake();
    $user = $this->adminGuest->getUser();
    Cache::forget(GetStockDeliveriesCostingReminder::cacheKey($user));

    $stockDelivery = createStockDeliveryWithItems($this, 'NO-COSTS-PUT-AWAY', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    expect($stockDelivery->fresh()->costsMissing())->toBe(['Agent or supplier invoice', 'Shipping', 'Duty']);

    $this->patch(route('grp.models.stock-delivery-item.place', $stockDeliveryItem->id), ['quantity' => 4, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id])
        ->assertSessionHasNoErrors();
    $this->patch(route('grp.models.stock-delivery-item.place', $stockDeliveryItem->id), ['quantity' => 6, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id])
        ->assertSessionHasNoErrors();

    $stockDelivery->refresh();
    expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::BOOKED_IN)
        ->and($stockDelivery->audits()->where('event', 'booked_in_without_costs')->count())->toBe(1)
        ->and($stockDelivery->costsMissing())->toBe(['Agent or supplier invoice', 'Shipping', 'Duty'])
        ->and(StockDelivery::whereKey($stockDelivery->id)->withCostsMissing()->exists())->toBeTrue();

    Notification::assertNotSentTo($user, \App\Notifications\StockDeliveryBookedInWithoutCostsNotification::class);
    expect(GetStockDeliveriesCostingReminder::make()->deliveries($user))->toBe([]);
    $user->assignRole("procurement-clerk-{$this->organisation->id}");
    actingAs($user);

    $reminder = GetStockDeliveriesCostingReminder::run($user);
    $listed   = collect($reminder['deliveries'])->firstWhere('id', $stockDelivery->id);
    expect($listed['needs_reason'])->toBeFalse();

    $stockDelivery->update(['booking_in_at' => null, 'booked_in_at' => now()->subWeekdays(6)]);
    $listed = collect(GetStockDeliveriesCostingReminder::make()->deliveries($user))->firstWhere('id', $stockDelivery->id);
    expect($listed['days'])->toBe(6)
        ->and($listed['needs_reason'])->toBeTrue();

    $acknowledge = fn (array $reasons) => $this->post(route('grp.models.costing_reminder.acknowledge'), ['reasons' => $reasons]);
    $acknowledge([])->assertSessionHasErrors("reasons.$stockDelivery->id");
    expect(GetStockDeliveriesCostingReminder::run($user))->not->toBeNull();
    $this->withoutVite()->get(route('grp.clocking_employees.index'))
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('costing_reminder', null));

    $reasons     = collect(GetStockDeliveriesCostingReminder::make()->deliveries($user))->where('needs_reason', true)->mapWithKeys(fn (array $delivery) => [$delivery['id'] => 'Forwarder has not sent the bill'])->all();
    $acknowledge = fn (array $reasons, array $remindOn = []) => $this->post(route('grp.models.costing_reminder.acknowledge'), ['reasons' => $reasons, 'remind_on' => $remindOn]);
    $acknowledge($reasons, [$stockDelivery->id => now()->addMonth()->addDay()->toDateString()])->assertSessionHasErrors("remind_on.$stockDelivery->id");
    $acknowledge($reasons, [$stockDelivery->id => now()->addDays(10)->toDateString()])->assertSessionHasNoErrors();

    $audit = $stockDelivery->audits()->where('event', 'costing_late_reason')->latest('id')->first();
    expect($audit->new_values['reason'])->toBe('Forwarder has not sent the bill')
        ->and($audit->new_values['remind_on'])->toBe(now()->addDays(10)->toDateString())
        ->and(GetStockDeliveriesCostingReminder::run($user))->toBeNull()
        ->and(collect(GetStockDeliveriesCostingReminder::make()->deliveries($user))->pluck('id'))->not->toContain($stockDelivery->id);

    $this->travel(10)->days();
    expect(collect(GetStockDeliveriesCostingReminder::make()->deliveries($user))->pluck('id'))->toContain($stockDelivery->id);
    $this->travelBack();

    \Illuminate\Support\Facades\Queue::fake();
    $stockDeliveryItem = $stockDeliveryItem->fresh();
    $stockDelivery     = \App\Actions\GoodsIn\StockDelivery\StartStockDeliveryCosting::make()->action($stockDelivery->fresh());
    \App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, 'amount' => $stockDeliveryItem->net_amount, 'received_at' => now()]);
    \App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::SHIPPING->value, 'amount' => 50, 'received_at' => now()]);
    \App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'is_na' => true]);

    expect($stockDelivery->fresh()->is_costed)->toBeTrue()
        ->and($stockDelivery->fresh()->costsMissing())->toBe([]);
    \App\Actions\Inventory\OrgStock\Stock\RebuildOrgStockHistoriesSince::assertPushed();
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
    $stockDelivery->items()->first()->update(['net_amount' => 50]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 8]);

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::CHECKED);

    $warehouse      = $this->organisation->warehouses()->oldest('id')->first() ?? createWarehouse();
    allowGoodsInOn($warehouse, $this->adminGuest->getUser());
    $procurementUrl = route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]);
    $goodsInUrl     = route('grp.org.warehouses.show.incoming.stock_deliveries.show', [$this->organisation->slug, $warehouse->slug, $stockDelivery->slug]);

    $this->withoutExceptionHandling();

    $this->get($procurementUrl)->assertInertia(fn (AssertableInertia $page) => $page->missing('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value));

    $this->get($goodsInUrl.'?tab='.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value)->assertInertia(function (AssertableInertia $page) {
        $page
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value)
            ->where(StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value.'.read_only', true)
            ->has(StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value.'.data', 1);
    });

    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 8, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id]);

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);

    $response = $this->get($procurementUrl.'?tab='.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value);

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDelivery')
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value)
            ->where(StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value.'.read_only', false)
            ->has(
                StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value.'.data',
                1,
                fn (AssertableInertia $page) => $page
                    ->where('difference_units', -2)
                    ->where('difference_percentage', -20)
                    ->where('difference_skos', -0.2)
                    ->where('difference_amount', -10)
                    ->has('currency_code')
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
            ->has('tabs.navigation', 8)
            ->has('tabs.navigation.'.StockDeliveryTabsEnum::CHECKLIST->value)
            ->missing('tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value);
    });

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 10, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id]);

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKED_IN);

    $response = $this->get($url, [
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Procurement/StockDelivery',
        'X-Inertia-Partial-Data'      => implode(',', [StockDeliveryTabsEnum::ITEMS->value, 'tabs', 'queryBuilderProps']),
    ]);

    $response->assertOk()
        ->assertJsonPath('props.queryBuilderProps.items.elementGroups.state.elements.placed.1', 1)
        ->assertJsonCount(9, 'props.tabs.navigation')
        ->assertJsonPath(
            'props.tabs.navigation.'.StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->value.'.title',
            StockDeliveryTabsEnum::UNDER_OVER_DELIVERED->blueprint()['title']
        );
});

test('stock delivery item can not be placed beyond the checked quantity', function () {
    $stockDelivery     = createStockDeliveryWithItems($this, 'OVER-PLACED', [10]);
    $stockDelivery     = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery     = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    $stockDeliveryItem = $stockDelivery->items()->first();
    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => 8]);

    UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 9, 'location_org_stock_id' => createLocationOrgStockFor($this, $stockDeliveryItem)->id]);
})->throws(ValidationException::class);

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

test('placed stock delivery item can have more checked and booked in while the delivery is booking in', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'BOOK-MORE', [10, 5]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->orderBy('id')->skip(1)->first(), ['unit_quantity_checked' => 5]);
    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->orderBy('id')->first(), ['unit_quantity_checked' => 10]);
    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);
    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, ['location_org_stock_id' => $locationOrgStock->id]);

    expect($stockDelivery->fresh()->state)->toBe(StockDeliveryStateEnum::BOOKING_IN);

    $resource = StockDeliveryItemResource::make($stockDeliveryItem)->toArray(request());
    expect($resource['checkedRoute'])->not->toBeNull()
        ->and(collect($resource['locations'])->pluck('location_code'))->toContain($locationOrgStock->location->code);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => 12]);
    expect($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::CHECKED);

    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, ['location_org_stock_id' => $locationOrgStock->id]);
    expect((float) $stockDeliveryItem->unit_quantity_placed)->toBe(12.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED);
});

test('stock delivery item is booked in to a location the org stock did not have and gets associated to it', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-NEW-LOCATION', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $warehouse         = Warehouse::where('organisation_id', $this->organisation->id)->first() ?? StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $location          = StoreLocation::make()->action($warehouse, Location::factory()->definition());

    expect(LocationOrgStock::where('org_stock_id', $stockDeliveryItem->org_stock_id)->where('location_id', $location->id)->exists())->toBeFalse();

    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 4, 'location_id' => $location->id]);
    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 6, 'location_id' => $location->id]);

    $locationOrgStock = LocationOrgStock::where('org_stock_id', $stockDeliveryItem->org_stock_id)->where('location_id', $location->id)->first();

    expect($locationOrgStock)->not->toBeNull()
        ->and((float) $locationOrgStock->quantity)->toBe(10.0)
        ->and($stockDeliveryItem->sowings()->where('location_id', $location->id)->count())->toBe(2)
        ->and((float) $stockDeliveryItem->unit_quantity_placed)->toBe(10.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED);
});

test('stock delivery item booked in with set as picking location flags that location as the picking location', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-SET-PICKING', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    $warehouse         = Warehouse::where('organisation_id', $this->organisation->id)->first() ?? StoreWarehouse::make()->action($this->organisation, Warehouse::factory()->definition());
    $location          = StoreLocation::make()->action($warehouse, Location::factory()->definition());

    LocationOrgStock::where('org_stock_id', $stockDeliveryItem->org_stock_id)->update([
        'default_wholesale_picking_location'    => false,
        'default_dropshipping_picking_location' => false,
    ]);

    expect((new StockDeliveryItemResource($stockDeliveryItem))->resolve()['has_picking_location'])->toBeFalse();

    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, [
        'quantity'                => 4,
        'location_id'             => $location->id,
        'set_as_picking_location' => true,
    ]);

    $locationOrgStock = LocationOrgStock::where('org_stock_id', $stockDeliveryItem->org_stock_id)->where('location_id', $location->id)->first();

    $firstByCode = StoreLocation::make()->action($warehouse, array_merge(Location::factory()->definition(), ['code' => '0-first-by-code']));
    \App\Actions\Inventory\LocationOrgStock\StoreLocationOrgStock::make()->action($stockDeliveryItem->orgStock, $firstByCode, []);

    $resource = (new StockDeliveryItemResource($stockDeliveryItem->fresh()))->resolve();

    expect($locationOrgStock->default_wholesale_picking_location)->toBeTrue()
        ->and($locationOrgStock->default_dropshipping_picking_location)->toBeTrue()
        ->and($resource['has_picking_location'])->toBeTrue()
        ->and($resource['locations']->first()->location_id)->toBe($location->id);
});

test('stock delivery item is checked and placed in SKOs while its quantities stay in units', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-IN-SKOS', [72]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = $stockDelivery->items()->first();
    $packedIn          = $stockDeliveryItem->orgStock->packed_in;
    $stockDeliveryItem->orgStock->update(['packed_in' => 6]);
    $stockDeliveryItem = $stockDeliveryItem->fresh();

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['sko_quantity_checked' => 12]);
    expect((float) $stockDeliveryItem->unit_quantity_checked)->toBe(72.0);

    $locationOrgStock  = createLocationOrgStockFor($this, $stockDeliveryItem);
    $stockDeliveryItem = UpsertStockDeliveryItemPlaced::make()->action($stockDeliveryItem, ['quantity' => 4, 'location_org_stock_id' => $locationOrgStock->id]);

    expect((float) $locationOrgStock->fresh()->quantity)->toBe(4.0)
        ->and((float) $stockDeliveryItem->unit_quantity_placed)->toBe(24.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::CHECKED);

    $stockDeliveryItem = SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, ['location_org_stock_id' => $locationOrgStock->id]);

    expect((float) $locationOrgStock->fresh()->quantity)->toBe(12.0)
        ->and((float) $stockDeliveryItem->unit_quantity_placed)->toBe(72.0)
        ->and($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::PLACED);

    $stockDeliveryItem->orgStock->update(['packed_in' => $packedIn]);
});

test('stock delivery item that did not arrive is checked as zero SKOs', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'CHECK-ZERO-SKOS', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['sko_quantity_checked' => 0]);

    expect($stockDeliveryItem->state)->toBe(StockDeliveryItemStateEnum::NOT_RECEIVED);
});

test('stock delivery item can not be placed without a location', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'PLACE-ALL-NO-LOCATION', [10]);
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery);
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);

    $stockDeliveryItem = SetStockDeliveryItemCheckedQuantity::make()->action($stockDelivery->items()->first(), ['unit_quantity_checked' => 10]);
    SetStockDeliveryItemAsPlaced::make()->action($stockDeliveryItem, []);
})->throws(ValidationException::class);

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

    allowGoodsInOn($locationOrgStock->warehouse, $this->adminGuest->getUser());

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('queryBuilderProps.items.columns.3.key', 'quantity')
            ->where('queryBuilderProps.items.columns.4.key', 'purchase_orders')
            ->where('queryBuilderProps.items.columns.5.key', 'checked_quantity')
            ->where('queryBuilderProps.items.columns.6.key', 'placed_quantity')
            ->where('pageHead.actions', fn ($actions) => collect($actions)->contains('key', 'book_in_link')));

    $response = $this->get(route('grp.org.warehouses.show.incoming.stock_deliveries.show', [
        $this->organisation->slug,
        $locationOrgStock->warehouse->slug,
        $stockDelivery->slug,
    ]));

    $response->assertInertia(function (AssertableInertia $page) use ($locationOrgStock) {
        $page
            ->component('Procurement/StockDelivery')
            ->where('queryBuilderProps.pending_items.columns.4.key', 'sowings')
            ->where('queryBuilderProps.pending_items.columns.5.key', 'checked_unit')
            ->where('queryBuilderProps.pending_items.columns.6.key', 'placement')
            ->has(
                StockDeliveryTabsEnum::PENDING_ITEMS->value.'.data',
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
                    ->where('blueprint.1.fields.purchase_order_reference_format.updateRoute.name', 'grp.models.org_supplier.update')
                    ->has('blueprint.1.fields.purchase_order_last_number')
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

test('a supplier without an agent is switched off and on from its edit page', function () {
    $supplier    = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier = OrgSupplier::where('organisation_id', $this->organisation->id)->where('supplier_id', $supplier->id)->first()
        ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $orgSupplier->update(['status' => false]);

    $blueprint = $this->get(route('grp.org.procurement.org_suppliers.edit', [$this->organisation->slug, $orgSupplier->slug]))
        ->viewData('page')['props']['formData']['blueprint'];
    $statusField = collect($blueprint)->pluck('fields.status')->filter()->first();

    expect($statusField['value'])->toBeFalse()
        ->and($statusField['updateRoute'])->toBe(['name' => 'grp.models.org_supplier.update', 'parameters' => $orgSupplier->id]);

    $this->patch(route('grp.models.org_supplier.update', $orgSupplier->id), ['status' => true])->assertSessionHasNoErrors();

    expect($orgSupplier->refresh()->status)->toBeTrue();
});

test('agent organisation manages its own supplier identity', function () {
    $ownAgent = StoreAgent::make()->action($this->group, Agent::factory()->definition());

    StoreOrgAgent::make()->action($ownAgent->organisation, $ownAgent, []);

    $ownSupplier = StoreSupplier::make()->action($ownAgent, Supplier::factory()->definition());

    $orgSupplier = OrgSupplier::where('organisation_id', $ownAgent->organisation_id)
        ->where('supplier_id', $ownSupplier->id)
        ->firstOrFail();

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.agent.org_suppliers.edit', [$ownAgent->organisation->slug, $orgSupplier->slug]));

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

test('current supplier sku cost distrusts implausible supplier cost and falls back to sku_value', function () {
    $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
        ['stock_id' => $this->stock->id, 'supplier_product_id' => $this->supplierProduct->id],
        ['available' => true]
    );
    OrgStockHasOrgSupplierProduct::firstOrCreate(
        ['org_stock_id' => $this->orgStocks[0]->id, 'org_supplier_product_id' => $this->orgSupplierProduct->id],
        ['stock_has_supplier_product_id' => $stockHasSupplierProduct->id, 'status' => true, 'local_priority' => 0]
    );
    OrgStockHasOrgSupplierProduct::where('org_stock_id', $this->orgStocks[0]->id)
        ->update(['status' => DB::raw('org_supplier_product_id = '.$this->orgSupplierProduct->id)]);
    $this->supplierProduct->updateQuietly(['is_available' => true]);
    $this->orgSupplierProduct->updateQuietly(['is_available' => true]);

    $orgStock = $this->orgStocks[0]->fresh();
    $orgStock->updateQuietly(['sku_value' => 4, 'packed_in' => 1]);

    $exchange = \App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange::run(
        $this->supplierProduct->currency,
        $orgStock->organisation->currency
    );

    $this->supplierProduct->updateQuietly(['cost' => 5 / $exchange, 'extra_costs' => 0]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost::run($orgStock);
    expect((float) $orgStock->fresh()->current_supplier_sku_cost)->toEqualWithDelta(5.0, 0.01);

    $this->supplierProduct->updateQuietly(['cost' => 237 / $exchange]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost::run($orgStock->fresh());
    expect((float) $orgStock->fresh()->current_supplier_sku_cost)->toEqualWithDelta(4.0, 0.001);
});

test('current supplier sku cost skips a preferred supplier product that is no longer available', function () {
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    SyncStockTradeUnits::run($stock, [$tradeUnit->id => ['quantity' => 1]]);
    $orgStock = createOrgStocks($this->organisation, [$stock])[0];
    $orgStock->updateQuietly(['sku_value' => 1, 'packed_in' => 1]);

    $exchange = \App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange::run(
        $this->orgSupplier->supplier->currency,
        $orgStock->organisation->currency
    );

    $link = function (string $code, float $cost, int $priority) use ($tradeUnit, $stock, $orgStock, $exchange) {
        $supplierProduct = StoreSupplierProduct::make()->action($this->orgSupplier->supplier, [
            'code'             => $code,
            'name'             => $code,
            'cost'             => $cost / $exchange,
            'trade_units'      => [$tradeUnit->id],
            'units_per_pack'   => 1,
            'units_per_carton' => 10
        ]);
        $supplierProduct->updateQuietly(['extra_costs' => 0]);
        $orgSupplierProduct = OrgSupplierProduct::where('org_supplier_id', $this->orgSupplier->id)
            ->where('supplier_product_id', $supplierProduct->id)->first()
            ?? StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);
        $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
            ['stock_id' => $stock->id, 'supplier_product_id' => $supplierProduct->id],
            ['available' => true]
        );
        OrgStockHasOrgSupplierProduct::updateOrCreate(
            ['org_stock_id' => $orgStock->id, 'org_supplier_product_id' => $orgSupplierProduct->id],
            ['stock_has_supplier_product_id' => $stockHasSupplierProduct->id, 'status' => true, 'local_priority' => $priority]
        );

        return $supplierProduct;
    };

    $oldPreferred = $link('ini-060-old', 0.5, 10);
    $link('ini-060-current', 1.2, 0);

    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost::run($orgStock->fresh());
    expect((float) $orgStock->fresh()->current_supplier_sku_cost)->toEqualWithDelta(0.5, 0.01);

    $oldPreferred->updateQuietly(['is_available' => false]);
    \App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentSupplierSkuCost::run($orgStock->fresh());
    expect((float) $orgStock->fresh()->current_supplier_sku_cost)->toEqualWithDelta(1.2, 0.01);
});

test('an agent sees and prints only the published labels of the SKOs it buys for us', function () {
    $this->orgSupplierProduct->updateQuietly(['org_agent_id' => $this->orgAgent->id, 'state' => 'active']);
    $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
        ['stock_id' => $this->stock->id, 'supplier_product_id' => $this->supplierProduct->id],
        ['available' => true]
    );
    $orgStock = $this->orgStocks[0];
    OrgStockHasOrgSupplierProduct::updateOrCreate(
        ['org_stock_id' => $orgStock->id, 'org_supplier_product_id' => $this->orgSupplierProduct->id],
        ['stock_has_supplier_product_id' => $stockHasSupplierProduct->id, 'status' => true, 'local_priority' => 0]
    );

    $foreignStock    = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]));
    $foreignOrgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($this->organisation, $foreignStock);

    $layout   = ['orientation' => 'portrait', 'columns' => 1, 'rows' => 1, 'page_margin' => 5, 'gap' => 0, 'fields' => [
        ['source' => 'batch_code', 'text' => 'EXAMPLE-1', 'x' => 0.1, 'y' => 0.1, 'font_size' => 8, 'color' => '#000000'],
        ['source' => 'expiry_date', 'text' => '01/01/2030', 'x' => 0.1, 'y' => 0.3, 'font_size' => 8, 'color' => '#000000'],
    ]];
    $newLabel = fn ($orgStock, string $name, string $state) => \App\Models\Production\ArtefactLabel::create([
        'group_id'        => $orgStock->group_id,
        'organisation_id' => $orgStock->organisation_id,
        'org_stock_id'    => $orgStock->id,
        'stock_id'        => $orgStock->stock_id,
        'name'            => $name,
        'layout'          => $layout,
        'state'           => $state,
    ]);
    $published = $newLabel($orgStock, 'Box', 'published');
    $newLabel($orgStock, 'Draft', 'raw');
    $foreign = $newLabel($foreignOrgStock, 'Not theirs', 'published');

    expect(\App\Actions\Procurement\AgentLabel\GetAgentOrgStocks::run($this->agent)->pluck('org_stocks.id')->all())
        ->toContain($orgStock->id)
        ->not->toContain($foreignOrgStock->id);

    $agentOrganisation = $this->agent->organisation;
    actingAs($this->adminGuest->getUser());

    get(route('grp.org.agent.agent_labels.index', $agentOrganisation->slug))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($orgStock, $published) {
            $page->component('Org/Procurement/AgentLabels')
                ->where('data.org_stocks', fn ($orgStocks) => collect($orgStocks)->pluck('id')->contains($orgStock->id)
                    && collect(collect($orgStocks)->firstWhere('id', $orgStock->id)['labels'])->pluck('id')->all() === [$published->id]
                    && collect($orgStocks)->firstWhere('id', $orgStock->id)['labels'][0]['run_sources'] === ['batch_code', 'expiry_date']);
        });

    get(route('grp.org.agent.agent_labels.pdf', [$agentOrganisation->slug, $orgStock->id, $published->id, 'batch_code' => 'B-7', 'expiry_date' => '2027-09-24']))
        ->assertOk();
    get(route('grp.org.agent.agent_labels.pdf', [$agentOrganisation->slug, $orgStock->id, $published->id, 'batch_code' => 'B-7']))
        ->assertStatus(422);
    get(route('grp.org.agent.agent_labels.pdf', [$agentOrganisation->slug, $foreignOrgStock->id, $foreign->id]))
        ->assertNotFound();
    get(route('grp.org.procurement.agent_labels.index', $this->organisation->slug))
        ->assertNotFound();
});

test('agent organisation creates a supplier under its own agent', function () {
    $ownAgent = StoreAgent::make()->action($this->group, Agent::factory()->definition());

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.agent.org_suppliers.create_new', [$ownAgent->organisation->slug]))
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

    test('shopping list item routes resolve the item behind the organisation segment', function () {
        $item         = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 10]);
        $organisation = $this->agent->organisation->slug;

        $this->patch(route('grp.org.agent.shopping_list.update', [$organisation, $item->id]), ['quantity_units' => 25])
            ->assertRedirect();
        expect((float) $item->fresh()->quantity_units)->toBe(25.0);

        $this->delete(route('grp.org.agent.shopping_list.destroy', [$organisation, $item->id]))
            ->assertRedirect();
        expect(ShoppingListItem::find($item->id))->toBeNull();
    });

    test('cherry pick full quantity_units creates or reuses purchase order and marks item ordered', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 60]);
        $quantityOrderedBefore = \App\Models\Procurement\PurchaseOrderTransaction::where('org_supplier_product_id', $this->orgSupplierProduct->id)->pluck('quantity_ordered', 'id');

        $result = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item->id],
        ]);

        expect($result['picked'])->toBe(1)
            ->and($result['skipped'])->toBe([])
            ->and($result['purchase_orders'])->toHaveCount(1);

        $purchaseOrder = $result['purchase_orders'][0];
        $orgSupplier   = OrgSupplier::where('organisation_id', $this->orgSupplierProduct->organisation_id)
            ->where('supplier_id', $this->supplierProduct->supplier_id)
            ->whereNotNull('org_agent_id')
            ->firstOrFail();
        expect($purchaseOrder->parent_type)->toBe('OrgSupplier')
            ->and($purchaseOrder->parent_id)->toBe($orgSupplier->id);

        $item = $item->fresh();
        expect($item->state)->toBe(ShoppingListItemStateEnum::ORDERED)
            ->and($item->purchase_order_transaction_id)->not->toBeNull();

        $transaction = $item->purchase_order_transaction_id
            ? \App\Models\Procurement\PurchaseOrderTransaction::find($item->purchase_order_transaction_id)
            : null;
        expect($transaction)->not->toBeNull()
            ->and((float) $transaction->quantity_ordered - (float) ($quantityOrderedBefore[$transaction->id] ?? 0))->toBe(60.0);
    });

    test('cherry pick partial quantity_units splits the line and keeps parent created_at', function () {
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

    test('cherry pick puts agent lines on the org supplier in process purchase order and reuses it', function () {
        $item1 = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 30]);
        $again = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 20]);

        expect($again->id)->toBe($item1->id)
            ->and((float) $again->quantity_units)->toBe(20.0)
            ->and(ShoppingListItem::where('org_supplier_product_id', $this->orgSupplierProduct->id)->where('state', ShoppingListItemStateEnum::OPEN)->count())->toBe(1);

        $result = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item1->id],
        ]);

        expect($result['purchase_orders'])->toHaveCount(1)
            ->and($result)->not->toHaveKey('agent_supplier_purchase_orders');
        $purchaseOrder = $result['purchase_orders'][0];
        expect($purchaseOrder->state)->toBe(PurchaseOrderStateEnum::IN_PROCESS)
            ->and($purchaseOrder->parent_type)->toBe('OrgSupplier')
            ->and($purchaseOrder->supplier_id)->toBe($this->supplierProduct->supplier_id);

        $linesBefore = $purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $this->supplierProduct->id)->count();
        $item2       = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 15]);

        $result2 = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item2->id],
        ]);

        expect($item2->id)->not->toBe($item1->id)
            ->and($result2['purchase_orders'])->toHaveCount(1)
            ->and($result2['purchase_orders'][0]->id)->toBe($purchaseOrder->id);

        $transaction1 = \App\Models\Procurement\PurchaseOrderTransaction::find($item1->fresh()->purchase_order_transaction_id);
        $transaction2 = \App\Models\Procurement\PurchaseOrderTransaction::find($item2->fresh()->purchase_order_transaction_id);
        expect($transaction1->purchase_order_id)->toBe($purchaseOrder->id)
            ->and($transaction2->id)->toBe($transaction1->id)
            ->and($purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $this->supplierProduct->id)->count())->toBe($linesBefore);

        $transaction1->replicate()->fill(['quantity_ordered' => 7])->save();

        $dryRun = collect(App\Actions\Procurement\PurchaseOrder\MergeDuplicateDraftPurchaseOrderLines::make()->handle(dryRun: true))->firstWhere('purchase_order', $purchaseOrder->reference);
        expect($dryRun['kept'])->toBe(7.0)
            ->and($purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $this->supplierProduct->id)->count())->toBe($linesBefore + 1);

        App\Actions\Procurement\PurchaseOrder\MergeDuplicateDraftPurchaseOrderLines::make()->handle();

        $joined = $purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $this->supplierProduct->id)->get();
        expect($joined)->toHaveCount(1)
            ->and($joined->first()->id)->toBe($transaction1->id)
            ->and((float) $joined->first()->quantity_ordered)->toBe(7.0)
            ->and($item2->fresh()->purchase_order_transaction_id)->toBe($transaction1->id);
    });

    test('shopping list board exposes the open agent purchase orders for a supplier', function () {
        $item = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 30]);

        $result = CherryPickShoppingListItems::make()->action($this->agent, [
            ['id' => $item->id],
        ]);

        $secondItem = StoreShoppingListItem::make()->action($this->orgSupplierProduct, ['quantity_units' => 15]);

        $response = $this->get(route('grp.org.agent.shopping_list.board', [$this->agent->organisation->slug]));

        $response->assertInertia(function (AssertableInertia $page) use ($secondItem, $result) {
            $agents = $page->toArray()['props']['agents'];
            $supplier = collect($agents[0]['suppliers'])->firstWhere('supplier_id', $secondItem->supplier_id);
            expect($supplier)->not->toHaveKey('open_agent_supplier_purchase_order')
                ->and(collect($supplier['open_agent_purchase_orders'])->pluck('id')->all())->toContain($result['purchase_orders'][0]->id);
        });
    });

    test('shopping list board renders for group and agent organisation', function () {
        $this->withoutExceptionHandling();

        $this->get(route('grp.supply-chain.shopping_list.board'))
            ->assertInertia(function (AssertableInertia $page) {
                $page->component('SupplyChain/ShoppingListBoard');
            });

        $this->get(route('grp.org.agent.shopping_list.board', [$this->agent->organisation->slug]))
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

    test('the agent or supplier invoice waiting for its invoice takes an estimate that lets put-away through until the real invoice replaces it', function () {
        $stockDelivery = $this->costingStockDelivery;
        $stockDelivery->update(['created_at' => StockDelivery::COSTS_REQUIRED_FROM]);

        $invoice = StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'                   => StockDeliveryCostTypeEnum::AGENT_INVOICE->value,
            'is_waiting_for_invoice' => true,
        ]);
        expect($stockDelivery->refresh()->costsMissing())->toContain('Agent or supplier invoice');

        $invoice = UpdateStockDeliveryCost::make()->action($invoice, ['amount' => 13320]);
        expect($invoice->is_estimate)->toBeTrue()
            ->and($invoice->is_waiting_for_invoice)->toBeTrue()
            ->and($invoice->received_at)->not->toBeNull()
            ->and($stockDelivery->refresh()->costsMissing())->not->toContain('Agent or supplier invoice');

        $invoice = UpdateStockDeliveryCost::make()->action($invoice, [
            'amount'                 => 6500,
            'received_at'            => today()->toDateString(),
            'is_waiting_for_invoice' => false,
        ]);
        expect($invoice->is_estimate)->toBeFalse()
            ->and($invoice->is_waiting_for_invoice)->toBeFalse()
            ->and((float) $invoice->amount)->toBe(6500.0);
    });

    test('costs in another currency are converted to the delivery currency', function () {
        $stockDelivery = $this->costingStockDelivery;
        $otherCurrency = Currency::whereNotIn('id', [$stockDelivery->currency_id, $stockDelivery->organisation->currency_id])->first();

        $shipping = StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'        => StockDeliveryCostTypeEnum::SHIPPING->value,
            'amount'      => 100,
            'currency_id' => $otherCurrency->id,
            'exchange'    => 1.5,
        ]);

        expect($shipping->currency_id)->toBe($otherCurrency->id)
            ->and($shipping->amountInDeliveryCurrency())->toBe(150.0);

        $shipping = UpdateStockDeliveryCost::make()->action($shipping, ['currency_id' => $stockDelivery->currency_id]);

        expect($shipping->currency_id)->toBeNull()
            ->and($shipping->exchange)->toBeNull()
            ->and($shipping->amountInDeliveryCurrency())->toBe(100.0);
    });

    test('changing the invoice exchange rate reconverts the items to the organisation currency', function () {
        $stockDelivery = $this->costingStockDelivery;
        $orgStock      = OrgStock::where('organisation_id', $stockDelivery->organisation_id)->first();
        $item          = StoreStockDeliveryItem::make()->action($stockDelivery, null, $orgStock, ['unit_quantity' => 10, 'state' => StockDeliveryItemStateEnum::IN_PROCESS], strict: false);
        $item          = UpdateStockDeliveryItem::make()->action($item, ['net_amount' => 40], strict: false);

        UpdateStockDelivery::make()->action($stockDelivery, ['org_exchange' => 2.5]);

        $item->refresh();
        expect((float) $stockDelivery->refresh()->org_exchange)->toBe(2.5)
            ->and((float) $item->org_exchange)->toBe(2.5)
            ->and((float) $item->org_net_amount)->toBe(100.0);
    });

    test('a cost in the organisation currency keeps its amount through the invoice exchange rate', function () {
        $stockDelivery = $this->costingStockDelivery;
        $stockDelivery->update([
            'currency_id'  => Currency::where('id', '!=', $stockDelivery->organisation->currency_id)->value('id'),
            'org_exchange' => 0.75,
        ]);
        $orgStock = OrgStock::where('organisation_id', $stockDelivery->organisation_id)->first();
        $item     = StoreStockDeliveryItem::make()->action($stockDelivery, null, $orgStock, ['unit_quantity' => 10, 'state' => StockDeliveryItemStateEnum::IN_PROCESS], strict: false);
        UpdateStockDeliveryItem::make()->action($item, ['net_amount' => 40], strict: false);

        $shipping = StoreStockDeliveryCost::make()->action($stockDelivery, [
            'type'        => StockDeliveryCostTypeEnum::SHIPPING->value,
            'amount'      => 589.98,
            'currency_id' => $stockDelivery->organisation->currency_id,
            'exchange'    => 1.3,
        ]);

        expect(round($shipping->amountInDeliveryCurrency() * 0.75, 2))->toBe(589.98)
            ->and(round((float) $stockDelivery->items()->sum('cost_shipping'), 2))->toBe(786.64);

        UpdateStockDelivery::make()->action($stockDelivery, ['org_exchange' => 0.8]);

        expect(round($shipping->fresh()->amountInDeliveryCurrency() * 0.8, 2))->toBe(589.98)
            ->and(round((float) $stockDelivery->items()->sum('cost_shipping'), 2))->toBe(737.48);
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

    test('partner delivery costs itself from its line values when booked in', function () {
        $orgStock      = OrgStock::where('organisation_id', $this->orgPartner->organisation_id)->first();
        $stockDelivery = StoreStockDelivery::make()->action($this->orgPartner, [
            'reference' => 'PARTNER-COSTING-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
            'state'     => StockDeliveryStateEnum::CHECKED,
        ], strict: false);
        $item = StoreStockDeliveryItem::make()->action($stockDelivery, null, $orgStock, [
            'unit_quantity'        => 72,
            'unit_quantity_placed' => 72,
            'state'                => StockDeliveryItemStateEnum::PLACED,
        ], strict: false);

        $item = UpdateStockDeliveryItem::make()->action($item, ['net_amount' => 134.64], strict: false);

        expect((float) $item->net_amount)->toBe(134.64)
            ->and((float) $item->org_net_amount)->toEqualWithDelta(134.64 * ($item->org_exchange ?? 1), 0.01);

        UpdateStockDeliveryStateFromGoodsIn::run($stockDelivery->refresh());

        $stockDelivery->refresh();
        expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::PLACED)
            ->and($stockDelivery->is_costed)->toBeTrue()
            ->and((float) $stockDelivery->cost_items)->toBe(134.64)
            ->and((float) $item->refresh()->cost_items)->toBe(134.64)
            ->and($item->is_costed)->toBeTrue();
    });

    test('a partner line marked not received by mistake can be checked after the delivery costed itself', function () {
        [$placedStock, $missingStock] = OrgStock::where('organisation_id', $this->orgPartner->organisation_id)->limit(2)->get();
        $stockDelivery = StoreStockDelivery::make()->action($this->orgPartner, [
            'reference' => 'PARTNER-NOT-RECEIVED-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
            'state'     => StockDeliveryStateEnum::CHECKED,
        ], strict: false);
        StoreStockDeliveryItem::make()->action($stockDelivery, null, $placedStock, [
            'unit_quantity'         => 10,
            'unit_quantity_checked' => 10,
            'unit_quantity_placed'  => 10,
            'state'                 => StockDeliveryItemStateEnum::PLACED,
        ], strict: false);
        $missing = StoreStockDeliveryItem::make()->action($stockDelivery, null, $missingStock, [
            'unit_quantity' => 160,
            'state'         => StockDeliveryItemStateEnum::RECEIVED,
        ], strict: false);

        $missing = SetStockDeliveryItemCheckedQuantity::make()->action($missing, ['unit_quantity_checked' => 0]);

        expect($missing->state)->toBe(StockDeliveryItemStateEnum::NOT_RECEIVED)
            ->and($stockDelivery->refresh()->state)->toBe(StockDeliveryStateEnum::PLACED)
            ->and($stockDelivery->is_costed)->toBeTrue()
            ->and($missing->canBeReceivedAfterAll())->toBeTrue();

        $missing = SetStockDeliveryItemAsChecked::make()->action($missing);

        $stockDelivery->refresh();
        expect($missing->state)->toBe(StockDeliveryItemStateEnum::CHECKED)
            ->and((float) $missing->unit_quantity_checked)->toBe(160.0)
            ->and($stockDelivery->state)->toBe(StockDeliveryStateEnum::BOOKING_IN)
            ->and($stockDelivery->is_costed)->toBeFalse()
            ->and($stockDelivery->placed_at)->toBeNull()
            ->and($stockDelivery->booked_in_at)->toBeNull();
    });
});

describe('stock delivery service invoices', function () {
    beforeEach(function () {
        $orgSupplier = OrgSupplier::first();
        $this->serviceInvoiceStockDeliveries = collect(['A', 'B'])->map(fn (string $suffix) => StoreStockDelivery::make()->action($orgSupplier, [
            'reference' => 'SVC-'.$suffix.'-'.StockDelivery::withTrashed()->max('id'),
            'date'      => date('Y-m-d'),
        ], strict: false));
        $this->serviceInvoiceOrganisation = $this->serviceInvoiceStockDeliveries->first()->organisation;
    });

    test('a freight bill split over two containers fills both shipping rows and completes costing once duty is in', function () {
        [$first, $second] = $this->serviceInvoiceStockDeliveries->all();
        $organisation     = $this->serviceInvoiceOrganisation;

        foreach ([$first, $second] as $stockDelivery) {
            StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, 'amount' => 1000, 'received_at' => now()]);
        }

        StoreStockDeliveryServiceInvoice::make()->action($organisation, [
            'type'         => StockDeliveryServiceInvoiceTypeEnum::FREIGHT->value,
            'issuer'       => 'Sea Forwarder',
            'reference'    => 'SF-1',
            'date'         => '2026-10-01',
            'currency_id'  => $organisation->currency_id,
            'total_amount' => 300,
            'allocations'  => [
                ['stock_delivery_id' => $first->id, 'amount' => 100],
                ['stock_delivery_id' => $second->id, 'amount' => 200],
            ],
        ]);

        $firstShipping  = $first->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->sole();
        $secondShipping = $second->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->sole();

        expect((float) $firstShipping->amount)->toBe(100.0)
            ->and((float) $secondShipping->amount)->toBe(200.0)
            ->and($firstShipping->received_at)->not->toBeNull()
            ->and($firstShipping->from_service_invoices)->toBeTrue()
            ->and($firstShipping->label)->toBe('Sea Forwarder SF-1')
            ->and($first->refresh()->is_costed)->toBeFalse()
            ->and($second->refresh()->is_costed)->toBeFalse();

        $costsBeforeTax = StockDeliveryCost::whereIn('stock_delivery_id', [$first->id, $second->id])->count();
        StoreStockDeliveryServiceInvoice::make()->action($organisation, [
            'type'         => StockDeliveryServiceInvoiceTypeEnum::TAX->value,
            'issuer'       => 'Customs Broker',
            'date'         => '2026-10-02',
            'currency_id'  => $organisation->currency_id,
            'total_amount' => 500,
            'allocations'  => [['stock_delivery_id' => $first->id], ['stock_delivery_id' => $second->id]],
        ]);
        expect(StockDeliveryCost::whereIn('stock_delivery_id', [$first->id, $second->id])->count())->toBe($costsBeforeTax);

        $duty = StoreStockDeliveryServiceInvoice::make()->action($organisation, [
            'type'         => StockDeliveryServiceInvoiceTypeEnum::DUTY->value,
            'issuer'       => 'Customs Broker',
            'date'         => '2026-10-02',
            'currency_id'  => $organisation->currency_id,
            'total_amount' => 90.01,
            'allocations'  => [['stock_delivery_id' => $first->id], ['stock_delivery_id' => $second->id]],
        ]);

        $dutyRows = StockDeliveryCost::whereIn('stock_delivery_id', [$first->id, $second->id])->where('type', StockDeliveryCostTypeEnum::DUTY)->get();
        expect($dutyRows)->toHaveCount(2)
            ->and(round($dutyRows->sum(fn (StockDeliveryCost $cost) => (float) $cost->amount), 2))->toBe(90.01)
            ->and(round($duty->stockDeliveries->sum('pivot.amount'), 2))->toBe(90.01)
            ->and($first->refresh()->is_costed)->toBeTrue()
            ->and($second->refresh()->is_costed)->toBeTrue();

        $this->patch(route('grp.models.stock-delivery-cost.update', $firstShipping->id), ['amount' => 1])
            ->assertSessionHasErrors('type');
        expect((float) $firstShipping->refresh()->amount)->toBe(100.0);

        $this->get(route('grp.org.procurement.service_invoices.index', [$organisation->slug, 'search' => 'Sea Forwarder']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Org/Procurement/StockDeliveryServiceInvoices')
                ->where('data.data.0.issuer', 'Sea Forwarder')
                ->where('data.data.0.allocations.1.amount', 200));
    });

    test('service invoices of every type fill the row of their kind and deleting them puts those rows back to pending', function () {
        $stockDelivery = $this->serviceInvoiceStockDeliveries->first();
        $organisation  = $this->serviceInvoiceOrganisation;

        StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::SHIPPING->value, 'amount' => 50, 'received_at' => now()]);
        $handDuty = StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value, 'amount' => 40, 'received_at' => now()]);

        $billData = fn (StockDeliveryServiceInvoiceTypeEnum $type, float $total) => [
            'type'         => $type->value,
            'issuer'       => 'Port Agent',
            'date'         => '2026-10-03',
            'currency_id'  => $organisation->currency_id,
            'total_amount' => $total,
            'allocations'  => [['stock_delivery_id' => $stockDelivery->id]],
        ];
        $freight  = StoreStockDeliveryServiceInvoice::make()->action($organisation, $billData(StockDeliveryServiceInvoiceTypeEnum::FREIGHT, 120));
        $haulage  = StoreStockDeliveryServiceInvoice::make()->action($organisation, $billData(StockDeliveryServiceInvoiceTypeEnum::LOCAL_TRANSPORT, 20));
        $handling = StoreStockDeliveryServiceInvoice::make()->action($organisation, $billData(StockDeliveryServiceInvoiceTypeEnum::PORT_CHARGES, 30));
        $levy     = StoreStockDeliveryServiceInvoice::make()->action($organisation, $billData(StockDeliveryServiceInvoiceTypeEnum::ANTI_DUMPING, 15));

        $shipping = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::SHIPPING)->sole();
        $extra    = $stockDelivery->costs()->where('stock_delivery_service_invoice_id', $handling->id)->sole();
        expect((float) $shipping->amount)->toBe(140.0)
            ->and((float) $handDuty->refresh()->amount)->toBe(15.0)
            ->and($handDuty->from_service_invoices)->toBeTrue()
            ->and($shipping->from_service_invoices)->toBeTrue()
            ->and($extra->type)->toBe(StockDeliveryCostTypeEnum::EXTRA)
            ->and((float) $extra->amount)->toBe(30.0);

        UpdateStockDeliveryServiceInvoice::make()->action($handling, ['total_amount' => 35]);
        UpdateStockDeliveryServiceInvoice::make()->action($handling->refresh(), ['reference' => 'PA-9']);
        expect((float) $extra->refresh()->amount)->toBe(35.0)
            ->and($extra->label)->toBe('Port Agent PA-9')
            ->and(array_column(StoreStockDeliveryServiceInvoice::largestRemainderSplit([1, 2, 3], [1 => 1, 2 => 1, 3 => 0], 0.05), 'amount'))->toBe([0.03, 0.02, 0.0]);

        DeleteStockDeliveryServiceInvoice::make()->action($freight);
        DeleteStockDeliveryServiceInvoice::make()->action($haulage);
        DeleteStockDeliveryServiceInvoice::make()->action($handling);
        DeleteStockDeliveryServiceInvoice::make()->action($levy);

        $shipping->refresh();
        expect($shipping->amount)->toBeNull()
            ->and($shipping->received_at)->toBeNull()
            ->and($shipping->from_service_invoices)->toBeFalse()
            ->and($stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->exists())->toBeFalse()
            ->and($handDuty->refresh()->amount)->toBeNull()
            ->and(StockDeliveryServiceInvoice::withTrashed()->find($freight->id)->trashed())->toBeTrue();
    });
    test('the invoice of a checklist row is sent to the AI to be read, or kept on the row for an extra, without touching amounts (HELP-4329)', function () {
        \Illuminate\Support\Facades\Queue::fake();
        $stockDelivery = $this->serviceInvoiceStockDeliveries->first();
        $attach        = fn (array $data) => $this->post(route('grp.models.stock-delivery.cost.invoice.attach', $stockDelivery->id), $data);
        $pdf           = fn (string $name) => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, '%PDF-1.4 '.$name.' '.Str::random(12));
        $queuedReads   = fn (string $reader) => \Illuminate\Support\Facades\Queue::pushed(\Lorisleiva\Actions\Decorators\JobDecorator::class, fn ($job) => $job->getAction() instanceof $reader)->count();
        $dutyBill      = '%PDF-1.4 duty-bill '.Str::random(12);

        $attach(['type' => StockDeliveryCostTypeEnum::DUTY->value, 'attachments' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('duty-bill.pdf', $dutyBill)]])->assertSessionHasNoErrors();
        $attach(['type' => StockDeliveryCostTypeEnum::DUTY->value, 'attachments' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('duty-bill.pdf', $dutyBill)]])->assertSessionHasNoErrors();
        $attach(['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, 'attachments' => [$pdf('agent-invoice.pdf')]])->assertSessionHasNoErrors();
        $attach(['type' => StockDeliveryCostTypeEnum::AGENT_INVOICE->value, 'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('agent-invoice.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]])->assertSessionHasNoErrors();
        $attach(['type' => StockDeliveryCostTypeEnum::EXTRA->value, 'attachments' => [$pdf('extra.pdf')]])->assertSessionHasErrors('cost');

        $dutyBillMedia = $stockDelivery->attachments()->wherePivot('scope', 'Service invoice')->sole();
        expect($queuedReads(\App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryServiceInvoice::class))->toBe(1)
            ->and($queuedReads(\App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::class))->toBe(2)
            ->and(\App\Actions\GoodsIn\StockDelivery\ReadStockDeliveryInvoice::reading($stockDelivery, $dutyBillMedia)['state'])->toBe('reading')
            ->and($stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::DUTY)->exists())->toBeFalse();
        StoreStockDeliveryCost::make()->action($stockDelivery, ['type' => StockDeliveryCostTypeEnum::DUTY->value]);

        $stockDelivery->created_at = '2026-10-01';
        expect($stockDelivery->costsMissing())->toBe([]);
        $stockDelivery->update(['created_at' => StockDelivery::COSTS_REQUIRED_FROM]);
        expect($stockDelivery->costsMissing())->toBe(['Agent or supplier invoice', 'Shipping', 'Duty'])
            ->and(StockDelivery::whereKey($stockDelivery->id)->withCostsMissing()->exists())->toBeTrue();

        $this->patch(route('grp.models.stock-delivery.receive', $stockDelivery->id))->assertSessionDoesntHaveErrors(['reason']);
        $stockDelivery->update(['created_at' => '2026-10-01']);

        $storeCost = fn (array $data) => $this->post(route('grp.models.stock-delivery.cost.store', $stockDelivery->id), ['type' => StockDeliveryCostTypeEnum::EXTRA->value] + $data);
        $storeCost(['label' => 'Anything'])->assertSessionHasErrors('category');
        $storeCost(['category' => StockDeliveryServiceInvoiceTypeEnum::FREIGHT->value])->assertSessionHasErrors('category');
        $storeCost(['category' => StockDeliveryServiceInvoiceTypeEnum::FINE->value])->assertSessionHasNoErrors();
        expect($stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::EXTRA)->sole()->category)->toBe(StockDeliveryServiceInvoiceTypeEnum::FINE);

        $duty = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::DUTY)->sole();

        expect($duty->amount)->toBeNull()
            ->and($duty->received_at)->toBeNull()
            ->and($duty->attachments()->count())->toBe(0)
            ->and($stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::AGENT_INVOICE)->exists())->toBeFalse()
            ->and($stockDelivery->attachments()->wherePivot('scope', 'Invoice')->count())->toBe(2)
            ->and($stockDelivery->refresh()->is_costed)->toBeFalse();

        $this->patch(route('grp.models.stock-delivery-cost.update', $duty->id), ['is_waiting_for_invoice' => true])->assertSessionHasNoErrors();
        $this->patch(route('grp.models.stock-delivery-cost.update', $duty->id), ['invoice_expected_in_days' => 3])->assertSessionHasNoErrors();
        expect($duty->refresh()->is_waiting_for_invoice)->toBeTrue()
            ->and($duty->invoice_expected_at->toDateString())->toBe(now()->addDays(3)->toDateString())
            ->and($duty->waitingLabel())->toBe('Invoice expected in 3 days')
            ->and($duty->is_estimate)->toBeFalse();

        $this->patch(route('grp.models.stock-delivery-cost.update', $duty->id), ['amount' => 55])->assertSessionHasNoErrors();
        expect($duty->refresh()->is_estimate)->toBeTrue()
            ->and($duty->is_waiting_for_invoice)->toBeTrue()
            ->and($duty->received_at)->not->toBeNull();

        $this->patch(route('grp.models.stock-delivery-cost.update', $duty->id), ['is_waiting_for_invoice' => false, 'amount' => 58])->assertSessionHasNoErrors();
        expect($duty->refresh()->is_estimate)->toBeFalse()
            ->and((float) $duty->amount)->toBe(58.0);
        $this->patch(route('grp.models.stock-delivery-cost.update', $duty->id), ['is_waiting_for_invoice' => true, 'invoice_expected_in_days' => 3])->assertSessionHasNoErrors();

        $this->patch(route('grp.models.stock-delivery-cost.update', $duty->id), ['label' => 'Broker says zero rated'])->assertSessionHasNoErrors();
        $this->patch(route('grp.models.stock-delivery-cost.update', $duty->id), ['is_na' => true])->assertSessionHasNoErrors();
        expect($duty->refresh()->is_waiting_for_invoice)->toBeFalse()
            ->and($duty->invoice_expected_at)->toBeNull()
            ->and($duty->is_na)->toBeTrue()
            ->and($stockDelivery->refresh()->is_costed)->toBeFalse();

        $this->get(route('grp.org.procurement.stock_deliveries.show', [$stockDelivery->organisation->slug, $stockDelivery->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('costing.checklist.0.type', 'agent_invoice')
                ->where('costing.checklist.0.attachments.0.name', fn (string $name) => str_contains($name, 'agent-invoice'))
                ->where('costing.checklist.2.uploadRoute.parameters.cost', $duty->id)
                ->where('costing.checklist.3.label', 'Fines')
                ->where('costing.checklist.2.category_label', 'Duty')
                ->where('costing.checklist.2.note', 'Broker says zero rated')
                ->where('costing.checklist.2.history', fn ($history) => collect($history)->contains(fn (array $entry) => collect($entry['changes'])->contains(fn (array $change) => $change['field'] === 'Note' && $change['new'] === 'Broker says zero rated')))
                ->etc());
    });
});

describe('supplier deposits', function () {
    beforeEach(function () {
        [$orgSupplier] = createAgentOrgSupplierWithProduct($this, createFreshOrgAgent($this));

        $this->depositPurchaseOrder = StorePurchaseOrder::make()->action(
            $orgSupplier,
            array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'ASPO-DEP-'.uniqid()]),
            strict: false
        );
    });

    test('deposit store on an agent purchase order records the deposit against the order', function () {
        $this->post(route('grp.models.purchase-order.deposit.store', $this->depositPurchaseOrder->id), ['amount' => 50, 'reference' => 'DEP-ROUTE'])
            ->assertSessionHasNoErrors();

        $deposit = $this->depositPurchaseOrder->deposits()->firstOrFail();

        expect($deposit->purchase_order_id)->toBe($this->depositPurchaseOrder->id)
            ->and($deposit->agent_id)->toBe($this->depositPurchaseOrder->agent_id)
            ->and($deposit->purchaseOrder->is($this->depositPurchaseOrder))->toBeTrue()
            ->and((float) $deposit->amount)->toBe(50.0)
            ->and($this->depositPurchaseOrder->deposits()->count())->toBe(1);
    });

    test('deposit store refuses a purchase order that is not through an agent', function () {
        $supplier         = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
        $orgSupplier      = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
        $independentOrder = StorePurchaseOrder::make()->action(
            $orgSupplier,
            array_merge(PurchaseOrder::factory()->definition(), ['reference' => 'NO-AGENT-DEP-'.uniqid()]),
            strict: false
        );

        expect($independentOrder->isAgentOrder())->toBeFalse();

        $this->post(route('grp.models.purchase-order.deposit.store', $independentOrder->id), ['amount' => 50])
            ->assertSessionHasErrors('amount');

        expect(fn () => StoreAspoDeposit::make()->action($independentOrder, ['amount' => 50]))->toThrow(ValidationException::class)
            ->and($independentOrder->deposits()->count())->toBe(0);
    });

    test('deposit lifecycle: pending to paid to supplier', function () {
        $deposit = StoreAspoDeposit::make()->action($this->depositPurchaseOrder, [
            'amount' => 300,
        ]);

        expect($deposit)->toBeInstanceOf(\App\Models\SupplyChain\AspoDeposit::class)
            ->and($deposit->agent_id)->toBe($this->depositPurchaseOrder->agent_id)
            ->and($deposit->state->value)->toBe('pending')
            ->and($deposit->currency_id)->toBe($this->depositPurchaseOrder->currency_id);

        $paid = \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        expect($paid->state->value)->toBe('paid_to_supplier')
            ->and($paid->paid_to_supplier_at)->not->toBeNull();

        $refunded = \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($paid, ['state' => 'refunded']);
        expect($refunded->state->value)->toBe('refunded')
            ->and($refunded->refunded_at)->not->toBeNull();
    });

    test('deposit request consolidation auto-settles when all items paid', function () {
        $depositOne = StoreAspoDeposit::make()->action($this->depositPurchaseOrder, ['amount' => 100]);
        $depositTwo = StoreAspoDeposit::make()->action($this->depositPurchaseOrder, ['amount' => 150]);

        $depositRequest = \App\Actions\SupplyChain\DepositRequest\StoreDepositRequest::make()->action($this->depositPurchaseOrder->agent, [
            'currency_id' => $this->depositPurchaseOrder->currency_id,
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
        $deposit = StoreAspoDeposit::make()->action($this->depositPurchaseOrder, ['amount' => 100]);
        \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        $deliveryOne = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-APP-1-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        $deliveryTwo = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-APP-2-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        placeDeliveryUnderAgentOrder($deliveryOne, $this->depositPurchaseOrder);
        placeDeliveryUnderAgentOrder($deliveryTwo, $this->depositPurchaseOrder);

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
        $deposit = StoreAspoDeposit::make()->action($this->depositPurchaseOrder, ['amount' => 300]);
        \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        $delivery = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-SETTLE-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        placeDeliveryUnderAgentOrder($delivery, $this->depositPurchaseOrder);

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
        $deposit = StoreAspoDeposit::make()->action($this->depositPurchaseOrder, ['amount' => 300]);
        \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        $delivery = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-UNAPPLY-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        placeDeliveryUnderAgentOrder($delivery, $this->depositPurchaseOrder);

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

    test('accounting clerk gets view-only procurement access, and edit access only on supplier/agent payments and stock delivery costing (HELP-3437)', function () {
        $deposit = StoreAspoDeposit::make()->action($this->depositPurchaseOrder, ['amount' => 300]);
        \App\Actions\SupplyChain\AspoDeposit\UpdateAspoDepositState::make()->action($deposit, ['state' => 'paid_to_supplier']);

        $delivery = StoreStockDelivery::make()->action($this->orgSupplier, [
            'reference' => 'DEP-ACC-CLERK-'.StockDelivery::count(),
            'date'      => date('Y-m-d'),
        ], strict: false);
        placeDeliveryUnderAgentOrder($delivery, $this->depositPurchaseOrder);

        setPermissionsTeamId($this->group->id);

        $accountingClerk = procurementAccountingClerk($this->organisation);

        expect($accountingClerk->authTo("procurement.{$this->organisation->id}.view"))->toBeTrue()
            ->and($accountingClerk->authTo("procurement.{$this->organisation->id}.edit"))->toBeFalse();

        actingAs($accountingClerk);

        $this->get(route('grp.org.procurement.dashboard', [$this->organisation->slug]))
            ->assertOk();

        $this->patch(route('grp.models.purchase-order.update', $this->purchaseOrder->id), [
            'payment_terms' => '30 days',
        ])->assertForbidden();

        $this->post(route('grp.models.stock-delivery.deposit.apply', $delivery->id), [
            'aspo_deposit_id' => $deposit->id,
            'amount'          => 100,
        ])->assertRedirect();

        expect((float) $delivery->depositApplications()->sum('amount'))->toBe(100.0);

        $this->withoutVite();
        $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $delivery->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('costing.can_edit', true)
                ->where('costing.can_edit_payments', true));

        $this->patch(route('grp.models.stock-delivery.update', $delivery->id), ['org_exchange' => 0.75])->assertRedirect();
        expect((float) $delivery->refresh()->org_exchange)->toBe(0.75);

        $this->patch(route('grp.models.stock-delivery.update', $delivery->id), ['org_exchange' => 0.8, 'incoterm' => 'FOB'])->assertForbidden();

        actingAs($this->adminGuest->getUser());
    });

    test('deposits are paid on the purchase order until nothing is left of its total, each with its bank statement (HELP-3872)', function () {
        $purchaseOrder = $this->depositPurchaseOrder;
        $statement     = fn (string $name) => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, '%PDF-1.4 '.$name.' '.Str::random(12));
        $confirmRoute  = route('grp.models.purchase-order.payment.confirm', $purchaseOrder->id);
        $payment       = fn (array $overrides = []) => array_merge(['amount' => 300, 'paid_at' => date('Y-m-d'), 'method' => 'bank_transfer', 'reference' => 'SWIFT-123', 'bank_statement' => $statement('deposit.pdf')], $overrides);

        $purchaseOrder->update(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'cost_total' => 1000, 'deposit_amount' => null, 'deposit_paid_at' => null]);
        $totalToPay = \App\Actions\Procurement\PurchaseOrder\SyncPurchaseOrderDepositPaid::totalToPay($purchaseOrder);

        $this->post($confirmRoute, $payment())->assertSessionHasErrors('paid_at');

        $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SUBMITTED]);

        $this->post($confirmRoute, $payment(['bank_statement' => null]))->assertSessionHasErrors('bank_statement');
        $this->post($confirmRoute, $payment(['method' => null]))->assertSessionHasErrors('method');
        $this->post($confirmRoute, $payment(['amount' => $totalToPay + 1]))->assertSessionHasErrors('amount');
        expect($purchaseOrder->depositPayments()->count())->toBe(0);

        setPermissionsTeamId($this->group->id);
        $accountingClerk = procurementAccountingClerk($this->organisation);
        expect($accountingClerk->authTo("procurement.{$this->organisation->id}.edit"))->toBeFalse();
        actingAs($accountingClerk);

        $paidLastWeek = now()->subDays(5)->toDateString();
        $this->post($confirmRoute, $payment(['paid_at' => $paidLastWeek]))->assertSessionHasErrors('reason');
        $this->post($confirmRoute, $payment(['paid_at' => $paidLastWeek, 'reason' => 'Bank statement arrived late']))->assertRedirect()->assertSessionHasNoErrors();

        $purchaseOrder->refresh();
        expect($purchaseOrder->deposit_paid_at->toDateString())->toBe($paidLastWeek)
            ->and($purchaseOrder->deposit_paid_by_id)->toBe($accountingClerk->id)
            ->and($purchaseOrder->balance_paid_at)->toBeNull()
            ->and((float) $purchaseOrder->deposit_amount)->toBe(300.0)
            ->and(Audit::where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toBe('Bank statement arrived late');

        $left = round($totalToPay - 300, 2);
        $this->post($confirmRoute, $payment(['amount' => $left + 1, 'bank_statement' => $statement('too-much.pdf')]))->assertSessionHasErrors('amount');
        $this->post($confirmRoute, $payment(['amount' => $left, 'bank_statement' => $statement('early.pdf')]))->assertSessionHasErrors('reason');

        \Illuminate\Support\Facades\Notification::fake();
        actingAs($this->adminGuest->getUser());
        $this->patch(route('grp.models.purchase-order.stage', $purchaseOrder->id), ['field' => 'qc_passed_at', 'date' => now($purchaseOrder->organisation->timezone?->name)->toDateString(), 'reason' => 'QC done at the supplier'])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Notification::assertSentTo($accountingClerk, \App\Notifications\PurchaseOrderBalanceDueNotification::class);
        actingAs($accountingClerk);

        $this->post($confirmRoute, $payment(['amount' => $left, 'bank_statement' => $statement('second.pdf')]))->assertRedirect()->assertSessionHasNoErrors();

        $purchaseOrder->refresh();
        $deposits = $purchaseOrder->depositPayments()->orderBy('id')->get();
        expect($purchaseOrder->deposit_paid_at->toDateString())->toBe($paidLastWeek)
            ->and($purchaseOrder->balance_paid_at->toDateString())->toBe(date('Y-m-d'))
            ->and((float) $purchaseOrder->deposit_amount)->toBe($totalToPay)
            ->and($purchaseOrder->deposit_paid_by_id)->toBe($accountingClerk->id)
            ->and($deposits)->toHaveCount(2)
            ->and($deposits[0]->method->value)->toBe('bank_transfer')
            ->and($deposits[0]->media_id)->not->toBeNull()
            ->and($purchaseOrder->attachments()->wherePivot('scope', 'Bank statement')->wherePivot('sub_scope', 'deposit')->count())->toBe(2);

        $this->post($confirmRoute, $payment(['amount' => 10, 'bank_statement' => $statement('again.pdf')]))->assertSessionHasErrors('paid_at');

        actingAs($this->adminGuest->getUser());

        $this->withoutVite();
        $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('payments.can_confirm', true)
                ->where('payments.total', fn ($amount) => (float) $amount === $totalToPay)
                ->where('payments.paid', fn ($amount) => (float) $amount === $totalToPay)
                ->where('payments.left', fn ($amount) => (float) $amount === 0.0)
                ->where('payments.deposits.0.method', 'Bank transfer')
                ->where('payments.deposits.0.reference', 'SWIFT-123')
                ->where('payments.deposits.0.paid_by', $accountingClerk->contact_name)
                ->where('payments.deposits.0.bank_statement', fn ($statement) => $statement !== null)
                ->etc());

        $this->delete(route('grp.models.purchase-order.payment.undo', [$purchaseOrder->id, $deposits[1]->id]))->assertSessionHasErrors('reason');
        expect($purchaseOrder->depositPayments()->count())->toBe(2);

        $this->delete(route('grp.models.purchase-order.payment.undo', [$purchaseOrder->id, $deposits[1]->id]), ['reason' => 'Paid to the wrong account'])->assertRedirect()->assertSessionHasNoErrors();
        $undone = \App\Models\Procurement\PurchaseOrderDepositPayment::withTrashed()->findOrFail($deposits[1]->id);
        expect($undone->undo_reason)->toBe('Paid to the wrong account')
            ->and($undone->undone_by_id)->toBe(auth()->id())
            ->and(Audit::where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toBe('Paid to the wrong account')
            ->and($purchaseOrder->refresh()->deposit_paid_at->toDateString())->toBe($paidLastWeek)
            ->and($purchaseOrder->balance_paid_at)->toBeNull()
            ->and($purchaseOrder->depositPayments()->count())->toBe(1)
            ->and((float) $purchaseOrder->deposit_amount)->toBe(300.0);
    });
});

test('port arrival, customs release and a corrected received date follow the stage rules', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'CUSTOMS-STAGE-'.Str::random(6), [2]);
    $route         = route('grp.models.stock-delivery.customs.update', $stockDelivery->id);
    $today         = now()->toDateString();

    $this->patch($route, ['arrived_at' => $today, 'customs_released_at' => $today])->assertSessionHasNoErrors();
    $this->patch($route, ['arrived_at' => now()->subDays(5)->toDateString()])->assertSessionHasErrors('reason');
    $this->patch($route, ['arrived_at' => null])->assertSessionHasErrors('reason');
    $this->patch($route, ['received_at' => $today])->assertSessionHasErrors('received_at');

    $stockDelivery->updateQuietly(['state' => StockDeliveryStateEnum::RECEIVED, 'received_at' => now()->subDay()]);
    $this->patch($route, ['received_at' => now()->subDays(3)->toDateString()])->assertSessionHasErrors('reason');
    $this->patch($route, ['received_at' => now()->subDays(3)->toDateString(), 'reason' => 'Booked in a few days late'])->assertSessionHasNoErrors();

    expect($stockDelivery->refresh()->received_at->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($stockDelivery->arrived_at->toDateString())->toBe($today)
        ->and(Audit::where('auditable_type', 'StockDelivery')->where('auditable_id', $stockDelivery->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toBe('Booked in a few days late');
});

test('staff rename a stock delivery public id from its settings and the url keeps working', function () {
    $delivery = StoreStockDelivery::make()->action($this->orgSupplier, [
        'reference' => 'SD-RENAME-'.StockDelivery::count(),
        'date'      => date('Y-m-d'),
    ], strict: false);
    $other = StoreStockDelivery::make()->action($this->orgSupplier, [
        'reference' => 'SD-TAKEN-'.StockDelivery::count(),
        'date'      => date('Y-m-d'),
    ], strict: false);
    $slug = $delivery->slug;

    expect(\App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryData::run($delivery)['blueprint'][0]['fields']['reference']['type'])->toBe('input');

    $this->patch(route('grp.models.stock-delivery.update', $delivery->id), ['reference' => 'MSKU1234567'])->assertRedirect();
    expect($delivery->refresh()->reference)->toBe('MSKU1234567')
        ->and($delivery->slug)->toBe($slug);

    $this->patch(route('grp.models.stock-delivery.update', $delivery->id), ['reference' => $other->reference])->assertSessionHasErrors('reference');
    $this->patch(route('grp.models.stock-delivery.update', $delivery->id), ['reference' => 'PO 12/34'])->assertSessionHasErrors('reference');
    expect($delivery->refresh()->reference)->toBe('MSKU1234567');
});

describe('org supplier sub pages navigation', function () {
    test('previous and next arrows keep the current sub page', function () {
        $first  = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['code' => 'navaaa', 'name' => 'Nav AAA']));
        $second = StoreSupplier::make()->action($this->group, array_merge(Supplier::factory()->definition(), ['code' => 'navzzz', 'name' => 'Nav ZZZ']));

        $firstOrgSupplier  = $first->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
        $secondOrgSupplier = $second->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

        $routeNames = [
            'grp.org.procurement.org_suppliers.show.supplier_products.index',
            'grp.org.procurement.org_suppliers.show.purchase_orders.index',
            'grp.org.procurement.org_suppliers.show.stock_deliveries.index',
        ];

        foreach ($routeNames as $routeName) {
            $this->get(route($routeName, [$this->organisation->slug, $firstOrgSupplier->slug]))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('navigation.next.route.name', $routeName)
                    ->where('navigation.next.route.parameters.orgSupplier', $secondOrgSupplier->slug)
                    ->etc());

            $this->get(route($routeName, [$this->organisation->slug, $secondOrgSupplier->slug]))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('navigation.previous.route.name', $routeName)
                    ->where('navigation.previous.route.parameters.orgSupplier', $firstOrgSupplier->slug)
                    ->etc());
        }
    });
});

test('agent organisation products list one row per supplier product with its data checklist and group currency cost', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);

    $otherOrganisation = Organisation::where('id', '!=', $this->organisation->id)->orderBy('id')->firstOrFail();
    $otherOrgSupplier  = $orgSupplier->replicate()
        ->fill(['organisation_id' => $otherOrganisation->id, 'slug' => $orgSupplier->slug.'-'.Str::lower(Str::random(6))]);
    $otherOrgSupplier->saveQuietly();
    $orgSupplierProduct->replicate()
        ->fill([
            'organisation_id' => $otherOrganisation->id,
            'org_supplier_id' => $otherOrgSupplier->id,
            'slug'            => $orgSupplierProduct->slug.'-'.Str::lower(Str::random(6)),
        ])
        ->saveQuietly();

    $code              = $orgSupplierProduct->supplierProduct->code;
    $agentOrganisation = $orgSupplier->orgAgent->agent->organisation;

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.agent.org_supplier_products.index', [$agentOrganisation->slug, 'index_filter[global]' => $code]));

    $rows = collect($response->viewData('page')['props']['index']['data'])->where('code', $code);

    expect($rows)->toHaveCount(1)
        ->and($rows->first())->not->toHaveKeys(['organisations', 'family_code'])
        ->and($rows->first()['completeness_checks'])->toHaveKeys(['cbm', 'carton_dimensions', 'carton_weight', 'carton_net_weight', 'moq', 'lead_time', 'lead_time_confirmed'])
        ->and($rows->first()['group_currency_code'])->toBe($this->group->currency->code);
});

test('HELP-4019 an agent sees its product by our code with what to fix, and never the landed costs', function () {
    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $agentOrganisation       = $orgSupplierProduct->orgAgent->agent->organisation;
    $orgSupplierProduct->supplierProduct->update(['cbm' => 0.5, 'minimum_carton_order' => 4, 'extra_costs' => 0.2]);
    StockHasSupplierProduct::firstOrCreate(['stock_id' => $this->stocks[0]->id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id]);

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.agent.org_supplier_products.show', [$agentOrganisation->slug, $orgSupplierProduct->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.title', $this->stocks[0]->code)
            ->where('showcase.agent.internal_code', $this->stocks[0]->code)
            ->has('showcase.agent.checks', 9)
            ->where('showcase.agent.key.moq.units', 400)
            ->where('showcase.agent.key.moq.value', 40000)
            ->where('showcase.agent.containers.40ft', 116)
            ->missing('showcase.costs.extra_costs_percentage')
            ->missing('showcase.costs.delivered_unit_cost')
            ->missing('showcase.supplierProductInfo.unit_expense')
            ->etc());
});

test('HELP-4019 an agent saves carton size, packaging and availability on the product, and contacts on its supplier', function () {
    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $agentOrganisation       = $orgSupplierProduct->orgAgent->agent->organisation;
    NotifyPurchasingOfSupplierProductAvailability::shouldRun()->once();

    $this->patch(route('grp.models.org.org_supplier_product.agent_details.update', [$agentOrganisation->id, $orgSupplierProduct->id]), [
        'carton_dimensions'   => ['l' => 50, 'w' => 40, 'h' => 30],
        'carton_weight'       => 12000,
        'carton_net_weight'   => 11000,
        'packaging_terms'     => ['retail_box' => 'us'],
        'availability_status' => 'unavailable',
        'unavailable_reason'  => 'factory_holiday',
        'available_from'      => '2027-02-20',
    ])->assertSessionHasNoErrors();

    $supplierProduct = $orgSupplierProduct->supplierProduct->refresh();
    expect((float) $supplierProduct->cbm)->toBe(0.06)
        ->and($supplierProduct->is_available)->toBeFalse()
        ->and($supplierProduct->packaging_terms)->toBe(['retail_box' => 'us']);

    $this->patch(route('grp.models.org.org_supplier_product.agent_details.update', [$agentOrganisation->id, $orgSupplierProduct->id]), [
        'availability_status' => 'limited',
    ])->assertSessionHasErrors('unavailable_reason');

    $this->patch(route('grp.models.org.org_supplier_product.supplier_contacts.update', [$agentOrganisation->id, $orgSupplierProduct->id]), [
        'wechat' => 'factory_wx_01',
    ])->assertSessionHasNoErrors();
    expect($supplierProduct->supplier->refresh()->wechat)->toBe('factory_wx_01');
});

test('HELP-4019 purchasing leads of the buying organisations hear when a product availability changes', function () {
    [, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    Notification::fake();

    $lead = $this->adminGuest->getUser();
    setPermissionsTeamId($this->group->id);
    $lead->givePermissionTo(Permission::where('name', "org-supervisor.{$orgSupplierProduct->organisation_id}.procurement")->firstOrFail());

    $supplierProduct = UpdateSupplierProduct::make()->action($orgSupplierProduct->supplierProduct, ['availability_status' => 'unavailable', 'unavailable_reason' => 'quality']);

    expect(NotifyPurchasingOfSupplierProductAvailability::run($supplierProduct, null))->toBeGreaterThanOrEqual(1);
    Notification::assertSentTo($lead, SupplierProductAvailabilityChangedNotification::class);
});

test('HELP-4019 an agent products list opens on the data to fix, then by supplier with its families, then the products by our code', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $agentOrganisation                  = $orgSupplierProduct->orgAgent->agent->organisation;
    $supplierProduct                    = $orgSupplierProduct->supplierProduct;
    StockHasSupplierProduct::firstOrCreate(['stock_id' => $this->stocks[0]->id, 'supplier_product_id' => $supplierProduct->id]);
    $supplierProduct->update(['cbm' => null, 'estimated_lead_time_days' => 30]);

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.agent.org_supplier_products.index', $agentOrganisation->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tabs.current', 'dashboard')
            ->has('dashboard.fill_rates', 11)
            ->where('dashboard.kpis.total', fn ($total) => $total >= 1)
            ->where('dashboard.kpis.missing_cbm', fn ($missing) => $missing >= 1)
            ->etc());

    $response = $this->get(route('grp.org.agent.org_supplier_products.index', [$agentOrganisation->slug, 'tab' => 'suppliers', 'suppliers_filter[global]' => $orgSupplier->supplier->code]));
    $row      = collect($response->viewData('page')['props']['suppliers']['data'])->firstWhere('id', $supplierProduct->supplier_id);
    expect($row['number_products'])->toBe(1)
        ->and($row['number_missing_cbm'])->toBe(1)
        ->and($row['number_missing_lead_time'])->toBe(0)
        ->and($row['families'])->toHaveCount(1)
        ->and($row['bought_amount'])->toEqual(0)
        ->and($row['contacts_update_route']['name'])->toBe('grp.models.org.agent_supplier.contacts.update');

    $this->get(route('grp.org.agent.org_supplier_products.index', [$agentOrganisation->slug, 'tab' => 'suppliers', 'suppliers_sort' => '-bought_amount']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('suppliers.data', fn ($rows) => collect($rows)->pluck('bought_amount')->sliding(2)->every(fn ($pair) => $pair->first() >= $pair->last()))
            ->etc());

    $response = $this->get(route('grp.org.agent.org_supplier_products.index', [
        $agentOrganisation->slug,
        'tab'                     => 'index',
        'index_filter[supplier]'  => $supplierProduct->supplier_id,
        'index_filter[focus]'     => 'missing_cbm',
    ]));
    $rows = collect($response->viewData('page')['props']['index']['data']);
    expect($rows)->toHaveCount(1)
        ->and($rows->first()['internal_code'])->toBe($this->stocks[0]->code)
        ->and($rows->first()['lead_time_days'])->toBe(30);

    $response = $this->get(route('grp.org.agent.org_supplier_products.index', [
        $agentOrganisation->slug,
        'tab'                     => 'index',
        'index_filter[supplier]'  => $supplierProduct->supplier_id,
        'index_filter[focus]'     => 'missing_lead_time',
    ]));
    expect($response->viewData('page')['props']['index']['data'])->toBeEmpty();
});

test('HELP-4019 an agent fills in the contacts of its own suppliers only', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $agentOrganisation = $orgSupplier->orgAgent->agent->organisation;

    $this->patch(route('grp.models.org.agent_supplier.contacts.update', [$agentOrganisation->id, $orgSupplier->supplier_id]), [
        'wechat' => 'agent_wx_02',
        'email'  => 'factory@example.com',
    ])->assertSessionHasNoErrors();

    expect($orgSupplier->supplier->refresh()->wechat)->toBe('agent_wx_02');

    $otherAgentOrgSupplier = createAgentOrgSupplierWithProduct($this, createFreshOrgAgent($this))[0];
    $this->patch(route('grp.models.org.agent_supplier.contacts.update', [$agentOrganisation->id, $otherAgentOrgSupplier->supplier_id]), [
        'wechat' => 'not_mine',
    ])->assertNotFound();
});

test('HELP-4019 production calendar advice: order before the factory stops, a good month, a month it can not produce', function () {
    $today = Carbon::parse('2026-10-15');

    expect(GetProductionCalendarAdvice::run([12 => 'cannot_produce'], 40, $today))
        ->toMatchArray(['status' => GetProductionCalendarAdvice::ORDER_BEFORE_STOP, 'arrives_on' => '2026-11-24'])
        ->and(GetProductionCalendarAdvice::run([10 => 'best_to_order'], null, $today)['status'])->toBe(GetProductionCalendarAdvice::GOOD_TIME)
        ->and(GetProductionCalendarAdvice::run([10 => 'cannot_produce', 3 => 'best_to_order'], 30, $today))
        ->toMatchArray(['status' => GetProductionCalendarAdvice::CANNOT_PRODUCE, 'arrives_on' => null])
        ->and(GetProductionCalendarAdvice::run([], 30, $today)['status'])->toBe(GetProductionCalendarAdvice::NOT_SET);
});

test('HELP-4019 an agent sets a family at its supplier, and the products and the order now panel follow it', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $agentOrganisation                  = $orgSupplier->orgAgent->agent->organisation;
    $supplierProduct                    = $orgSupplierProduct->supplierProduct;
    StockHasSupplierProduct::firstOrCreate(['stock_id' => $this->stocks[0]->id, 'supplier_product_id' => $supplierProduct->id]);
    $stockFamily = $this->stocks[0]->stockFamily ?? StoreStockFamily::make()->action($this->group, StockFamily::factory()->definition());
    $this->stocks[0]->update(['stock_family_id' => $stockFamily->id]);
    $supplierProduct->update(['minimum_carton_order' => null, 'estimated_lead_time_days' => null]);

    $this->patch(route('grp.models.org.agent_supplier.family.update', [$agentOrganisation->id, $orgSupplier->supplier_id, $stockFamily->id]), [
        'minimum_carton_order'     => 6,
        'estimated_lead_time_days' => 45,
        'production_calendar'      => [now()->month => 'best_to_order'],
        'packaging_terms'          => ['retail_box' => 'supplier'],
    ])->assertSessionHasNoErrors();

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.agent.org_supplier_products.show', [$agentOrganisation->slug, $orgSupplierProduct->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showcase.agent.key.moq.cartons', 6)
            ->where('showcase.agent.key.moq.source', 'family')
            ->where('showcase.agent.key.lead_time.days', 45)
            ->where('showcase.agent.packaging.terms.retail_box.source', 'family')
            ->where('showcase.agent.production_calendar.advice.status', GetProductionCalendarAdvice::GOOD_TIME)
            ->etc());

    $this->get(route('grp.org.agent.org_supplier_products.index', $agentOrganisation->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.order_now', fn ($rows) => collect($rows)->contains(fn ($row) => $row['supplier_id'] === $orgSupplier->supplier_id && $row['stock_family_id'] === $stockFamily->id))
            ->etc());
});

test('stock deliveries deleted in Aurora before being received are deleted, nothing newer than the Aurora copy is touched (HELP-4085)', function () {
    $prefix          = $this->organisation->id.':';
    $makeFromAurora  = function (int $key, StockDeliveryStateEnum $state) use ($prefix) {
        $stockDelivery = StoreStockDelivery::make()->action(
            $this->orgSupplier,
            ['reference' => 'AURORA-DELETED-'.$key, 'date' => date('Y-m-d')],
            strict: false,
        );
        $stockDelivery->forceFill(['source_id' => $prefix.$key, 'state' => $state])->save();

        return $stockDelivery;
    };

    $deletedInAurora  = $makeFromAurora(900001, StockDeliveryStateEnum::DISPATCHED);
    $stillInAurora    = $makeFromAurora(900002, StockDeliveryStateEnum::DISPATCHED);
    $alreadyReceived  = $makeFromAurora(900003, StockDeliveryStateEnum::PLACED);
    $newerThanAurora  = $makeFromAurora(900009, StockDeliveryStateEnum::DISPATCHED);

    $result = RepairAuroraDeletedStockDeliveries::make()->deleteMissingFromAurora($this->organisation, collect([900002, 900005]));

    expect($result['deleted'])->toBe([$deletedInAurora->id.' AURORA-DELETED-900001'])
        ->and(StockDelivery::find($deletedInAurora->id))->toBeNull()
        ->and(StockDelivery::withTrashed()->find($deletedInAurora->id)->data['deletion']['reason'])->toBe('Deleted in Aurora')
        ->and(StockDelivery::find($stillInAurora->id))->not->toBeNull()
        ->and(StockDelivery::find($alreadyReceived->id))->not->toBeNull()
        ->and(StockDelivery::find($newerThanAurora->id))->not->toBeNull();
});

test('an agent fills a container with part of a line, packs a product inside another and downloads it as CSV and PDF with or without the CBM (HELP-4023)', function () {
    [$orgSupplierOne, $orgSupplierProductOne] = createAgentOrgSupplierWithProduct($this);
    [$orgSupplierTwo, $orgSupplierProductTwo] = createAgentOrgSupplierWithProduct($this);
    $orgSupplierProductOne->supplierProduct->update(['cbm' => 0.5]);
    $orgSupplierProductTwo->supplierProduct->update(['cbm' => 0.5]);

    $orderOne = createConfirmedAgentOrder($this, $orgSupplierOne, $orgSupplierProductOne, 0, 200);
    $orderTwo = createConfirmedAgentOrder($this, $orgSupplierTwo, $orgSupplierProductTwo, 1, 100);
    $lineOne  = $orderOne->purchaseOrderTransactions()->firstOrFail();
    $lineTwo  = $orderTwo->purchaseOrderTransactions()->firstOrFail();

    $container = StoreStockDeliveryFromPurchaseOrder::make()->action($orderOne->refresh(), [
        'purchase_order_transaction_ids' => [$lineOne->id],
        'unit_quantities'                => [$lineOne->id => 50],
    ]);
    AddPurchaseOrderItemsToStockDelivery::make()->action($container, [
        'items' => [['purchase_order_transaction_id' => $lineTwo->id, 'unit_quantity' => 100]],
    ]);

    $container->refresh();
    $inner = $container->items()->whereRaw("(data->>'purchase_order_transaction_id')::bigint = ?", [$lineOne->id])->firstOrFail();
    $outer = $container->items()->whereRaw("(data->>'purchase_order_transaction_id')::bigint = ?", [$lineTwo->id])->firstOrFail();
    $awaiting = AddPurchaseOrderItemsToStockDelivery::addableItems($container)->get()->keyBy('id');

    expect($container->number_stock_delivery_items)->toBe(2)
        ->and((float) $inner->unit_quantity)->toBe(50.0)
        ->and((float) $awaiting[$lineOne->id]->quantity_awaiting_delivery)->toBe(150.0)
        ->and($awaiting->has($lineTwo->id))->toBeFalse()
        ->and(ShowStockDelivery::make()->getStockDeliveryWeightAndVolume($container)['volume'])->toBe(0.75);

    setPermissionsTeamId($this->group->id);
    actingAs(agentOrganisationAdmin($this->orgAgent->agent->organisation, 'agt-ctr-'.Str::lower(Str::random(6))));

    $packedInside = fn (StockDeliveryItem $item, ?int $outerId) => $this->patchJson(route('grp.models.stock-delivery-item.packed-inside', $item->id), ['packed_inside_stock_delivery_item_id' => $outerId]);

    $this->patchJson(route('grp.models.stock-delivery-item.cbm-in-container', $outer->id), ['cbm_in_container' => 0.6])->assertOk();
    $packedInside($inner, $inner->id)->assertUnprocessable();
    $packedInside($inner, $outer->id)->assertOk();
    $packedInside($outer, $inner->id)->assertUnprocessable();

    $rows = PdfStockDelivery::rows($container)->keyBy('code');
    $innerCode = $orgSupplierProductOne->supplierProduct->code;
    $outerCode = $orgSupplierProductTwo->supplierProduct->code;

    expect(ShowStockDelivery::make()->getStockDeliveryWeightAndVolume($container)['volume'])->toBe(0.6)
        ->and($rows[$innerCode])->toMatchArray(['units' => 50.0, 'skos' => round(50 / $inner->unitsPerSko(), 2), 'cartons' => 0.5, 'cbm' => 0.0, 'packed_inside' => $outerCode])
        ->and($rows[$outerCode])->toMatchArray(['cbm' => 0.6, 'contains' => $innerCode])
        ->and((new StockDeliveryItemsExport($container))->headings())->toContain('cbm', 'packed_inside');

    $this->withoutVite();
    $agentPageActions = collect($this->get(route('grp.org.agent.stock_deliveries.show', [$this->orgAgent->agent->organisation->slug, $container->slug]))->assertOk()->viewData('page')['props']['pageHead']['actions']);
    expect($agentPageActions->pluck('key')->first())->toBe('delete_stock_delivery')
        ->and($agentPageActions->pluck('key')->last())->toBe('dispatch_stock_delivery')
        ->and(collect($agentPageActions->firstWhere('key', 'documents')['button'])->pluck('key')->all())->toBe(['csv', 'action']);

    $this->get(route('grp.org.agent.stock_deliveries.csv', [$this->orgAgent->agent->organisation->slug, $container->slug]))->assertOk();
    $this->get(route('grp.org.agent.stock_deliveries.pdf', [$this->orgAgent->agent->organisation->slug, $container->slug]))->assertOk();

    $this->patch(route('grp.models.stock-delivery.update', $container->id), ['hide_cbm' => true])->assertSessionHasNoErrors();

    expect((new StockDeliveryItemsExport($container->refresh()))->headings())->not->toContain('cbm')
        ->and(PdfStockDelivery::showsCbm($container))->toBeFalse();

    $packedInside($inner, null)->assertOk();

    expect(ShowStockDelivery::make()->getStockDeliveryWeightAndVolume($container)['volume'])->toBe(0.85);

    $setShippingState = fn (string $state, array $items) => $this->patchJson(route('grp.models.stock-delivery.items.shipping-state', $container->id), ['state' => $state, 'items' => $items]);

    $setShippingState('confirmed', [$inner->id])->assertOk();
    $setShippingState('confirmed', [$inner->id, $outer->id])->assertJsonValidationErrors('items.0');
    $setShippingState('received', [$outer->id])->assertJsonValidationErrors('state');
    expect($outer->refresh()->state)->toBe(StockDeliveryItemStateEnum::IN_PROCESS);
    $setShippingState('ready_to_ship', [$inner->id])->assertOk();
    expect($inner->refresh()->state)->toBe(StockDeliveryItemStateEnum::READY_TO_SHIP);

    $this->patchJson(route('grp.models.stock-delivery-item.confirm', $outer->id))->assertOk();
    $this->patchJson(route('grp.models.stock-delivery-item.ready-to-ship', $outer->id))->assertOk();
    expect($outer->refresh()->state)->toBe(StockDeliveryItemStateEnum::READY_TO_SHIP)
        ->and($container->refresh()->state)->toBe(StockDeliveryStateEnum::READY_TO_SHIP);

    actingAs($this->adminGuest->getUser());
});

test('a product shipped from two purchase orders is one row of its container, listing both orders, on the items tab, the PDF and the CSV (HELP-4290)', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $orderOne = createConfirmedAgentOrder($this, $orgSupplier, $orgSupplierProduct, 0, 300);
    $orderTwo = createConfirmedAgentOrder($this, $orgSupplier, $orgSupplierProduct, 0, 100);
    $lineOne  = $orderOne->purchaseOrderTransactions()->firstOrFail();
    $lineTwo  = $orderTwo->purchaseOrderTransactions()->firstOrFail();

    $container = StoreStockDeliveryFromPurchaseOrder::make()->action($orderOne->refresh(), [
        'purchase_order_transaction_ids' => [$lineOne->id],
        'unit_quantities'                => [$lineOne->id => 300],
    ]);
    AddPurchaseOrderItemsToStockDelivery::make()->action($container, [
        'items' => [['purchase_order_transaction_id' => $lineTwo->id, 'unit_quantity' => 100]],
    ]);
    $container->refresh();
    $code = $orgSupplierProduct->supplierProduct->code;

    $rows = PdfStockDelivery::rows($container);
    expect($container->number_stock_delivery_items)->toBe(2)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['code' => $code, 'units' => 400.0, 'cartons' => 4.0])
        ->and((new StockDeliveryItemsExport($container))->array())->toHaveCount(1);

    $this->withoutVite();
    $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $container->slug]).'?tab='.StockDeliveryTabsEnum::ITEMS->value)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('box_stats.second_block.total_items', 1)
            ->has(StockDeliveryTabsEnum::ITEMS->value.'.data', 1)
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.code', $code)
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.unit_quantity', 400)
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.item_ids', $container->items()->orderBy('id')->pluck('id')->all())
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.confirm_item_ids', $container->items()->orderBy('id')->pluck('id')->all())
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.cbmInContainerRoute', null)
            ->where(StockDeliveryTabsEnum::ITEMS->value.'.data.0.purchase_orders', [
                ['purchase_order_reference' => $orderOne->reference, 'purchase_order_slug' => $orderOne->slug, 'unit_quantity' => '300.0000'],
                ['purchase_order_reference' => $orderTwo->reference, 'purchase_order_slug' => $orderTwo->slug, 'unit_quantity' => '100.0000'],
            ]));
});
