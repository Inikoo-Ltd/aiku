<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use Illuminate\Support\Facades\Event;
use App\Events\BroadcastPurchaseOrderLastEdited;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\Procurement\ProcurementNote\StoreProcurementNote;
use App\Models\Procurement\ProcurementNote;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Models\Goods\TradeUnit;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderDeliveryStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\User;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionQuantityQueryStateEnum;
use App\Actions\Transfers\Aurora\RepairAuroraPurchaseOrderBuyers;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\Procurement\PurchaseOrder\ImportPurchaseOrderTransactions;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Actions\GoodsIn\StockDelivery\CancelStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateToReceived;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Transfers\Aurora\FetchAuroraStockDeliveryItems;
use App\Jobs\BoundedUniqueJobDecorator;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use Illuminate\Support\Facades\Queue;
use App\Models\Helpers\Audit;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderStages;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderCompliance;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItemBySelectedPurchaseOrderTransaction;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStateToCheckedStockDeliveryItem;
use App\Actions\GoodsIn\StockDeliveryItem\UpdateStockDeliveryItem;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplier\UI\GetAgentSuppliersDashboard;
use App\Actions\Procurement\PurchaseOrderChase\StorePurchaseOrderChase;
use App\Actions\SupplyChain\SupplierChangeRequest\DecideSupplierChangeRequest;
use App\Actions\SupplyChain\SupplierChangeRequest\StoreSupplierChangeRequest;
use App\Enums\SupplyChain\SupplierChangeRequest\SupplierChangeRequestStateEnum;
use App\Enums\SupplyChain\Supplier\SupplierPackingMethodEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Models\SupplyChain\SupplierChangeRequest;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\OrgSupplier\UpdateOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\UpdateOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\DeletePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\RevertPurchaseOrderToSubmitted;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrderOrgSupplierProducts;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderData;
use App\Actions\Procurement\PurchaseOrder\UI\ShowPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToCancelled;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToInProcess;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderTransactionQuantity;
use App\Actions\Procurement\PurchaseOrderTransaction\CancelPurchaseOrderTransaction;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Actions\GoodsIn\StockDelivery\UpdatePurchaseOrdersDeliveryStateFromStockDelivery;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Actions\HumanResources\Employee\StoreEmployee;
use Illuminate\Support\Str;
use App\Actions\Procurement\OrgAgent\UpdateOrgAgent;
use App\Models\HumanResources\JobPosition;
use App\Actions\SysAdmin\User\StoreUser;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use App\Actions\SupplyChain\Agent\HydrateAgents;
use App\Actions\SupplyChain\Agent\UI\GetAgentCleanHandoverScore;
use App\Actions\SupplyChain\Agent\Search\ReindexAgentSearch;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\HydrateSuppliers;
use App\Actions\SupplyChain\Supplier\Search\ReindexSupplierSearch;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Goods\Stock;
use App\Models\SysAdmin\Organisation;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Http\Resources\Procurement\StockDeliveryItemResource;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Actions\Procurement\PurchaseOrderTransaction\UI\IndexPurchaseOrderTransactions;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Actions\SupplyChain\Supplier\MergeSuppliers;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Inertia\Testing\AssertableInertia;

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
        ->and($purchaseOrder->supplier_id)->toBe($supplier->id)
        ->and($purchaseOrder->org_exchange)->not->toBeNull()
        ->and($purchaseOrder->grp_exchange)->not->toBeNull()
        ->and($purchaseOrder->agent_id)->toBeNull()
        ->and($purchaseOrder->isAgentOrder())->toBeFalse();


    return $purchaseOrder;
})->depends('attach supplier product to organisation');

test('create purchase order through an agent', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $orgAgent      = $orgSupplier->orgAgent;
    $liveBefore    = $orgAgent->purchaseOrders()->count();

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    expect($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class)
        ->and($purchaseOrder->parent_type)->toBe('OrgSupplier')
        ->and($purchaseOrder->parent_id)->toBe($orgSupplier->id)
        ->and($purchaseOrder->supplier_id)->toBe($orgSupplier->supplier_id)
        ->and($purchaseOrder->agent_id)->toBe($orgAgent->agent_id)
        ->and($purchaseOrder->isAgentOrder())->toBeTrue()
        ->and($orgAgent->purchaseOrders()->count())->toBe($liveBefore + 1)
        ->and($orgAgent->stats->refresh()->number_purchase_orders)->toBe($orgAgent->purchaseOrders()->count())
        ->and($orgAgent->agent->stats->refresh()->number_purchase_orders)->toBe($orgAgent->agent->purchaseOrders()->count());

    $freshOrgAgent = createFreshOrgAgent($this);
    $agentFormat   = 'AGT'.Str::upper(Str::random(5)).'-%03d-ES';
    UpdateOrgAgent::make()->action($freshOrgAgent, ['purchase_order_reference_format' => $agentFormat, 'purchase_order_last_number' => 200]);
    [$firstOrgSupplier]  = createAgentOrgSupplierWithProduct($this, $freshOrgAgent->refresh());
    [$secondOrgSupplier] = createAgentOrgSupplierWithProduct($this, $freshOrgAgent);
    $firstOrder          = StorePurchaseOrder::make()->action($firstOrgSupplier, []);
    $firstOrder->updateQuietly(['agent_order_reference' => sprintf($agentFormat, 202)]);
    $firstOrder->delete();
    $secondOrder = StorePurchaseOrder::make()->action($secondOrgSupplier, []);

    expect($firstOrder->reference)->toBe(sprintf($agentFormat, 201))
        ->and($secondOrder->reference)->toBe(sprintf($agentFormat, 203))
        ->and($secondOrder->agent_order_reference)->toBeNull();

    return $purchaseOrder;
});

test('agent purchase order format without a suffix gets the organisation code, one with a suffix is kept', function () {
    $freshOrgAgent = createFreshOrgAgent($this);
    $prefix        = 'AGT'.Str::upper(Str::random(5));
    $suffix        = '-'.Str::upper($freshOrgAgent->organisation->code);
    UpdateOrgAgent::make()->action($freshOrgAgent, ['purchase_order_reference_format' => $prefix.'%05d', 'purchase_order_last_number' => 94]);
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this, $freshOrgAgent->refresh());

    expect(UpdateOrgAgent::make()->nextPurchaseOrderReference($freshOrgAgent))->toBe($prefix.'00095'.$suffix)
        ->and(StorePurchaseOrder::make()->action($orgSupplier, [])->reference)->toBe($prefix.'00095'.$suffix);

    UpdateOrgAgent::make()->action($freshOrgAgent, ['purchase_order_reference_format' => $prefix.'%05d-ES']);

    expect(UpdateOrgAgent::make()->nextPurchaseOrderReference($freshOrgAgent->refresh()))->toBe($prefix.'00096-ES');
});

test('add item to agent purchase order', function (PurchaseOrder $purchaseOrder) {
    $orgSupplierProduct = OrgSupplierProduct::where('org_supplier_id', $purchaseOrder->parent_id)->firstOrFail();

    $purchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        PurchaseOrderTransaction::factory()->definition()
    );

    expect($purchaseOrderTransaction->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($purchaseOrderTransaction->supplier_product_id)->toBe($orgSupplierProduct->supplier_product_id)
        ->and($purchaseOrder->purchaseOrderTransactions()->count())->toBe(1);

    return $purchaseOrder;
})->depends('create purchase order through an agent');

test('agent login can propose a ready date but not set management-only clean handover fields', function (PurchaseOrder $purchaseOrder) {
    $organisation = $purchaseOrder->agent->organisation;
    $orgAdmin     = JobPosition::where('organisation_id', $organisation->id)->where('code', 'org-admin')->firstOrFail();
    $employee     = StoreEmployee::make()->action($organisation, [
        'worker_number'   => 'agent-clerk',
        'alias'           => 'agent-clerk',
        'contact_name'    => 'Agent Clerk',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $orgAdmin->slug, 'scopes' => []]],
    ]);
    $agentUser = StoreUser::make()->action($employee, [
        'username'       => 'agent-clerk',
        'password'       => Str::random(32),
        'status'         => true,
        'reset_password' => false,
    ]);
    expect($agentUser->hasGroupAccess())->toBeFalse();

    $notes = $purchaseOrder->refresh()->notes;

    actingAs($agentUser);

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), [
        'proposed_ready_at'  => '2026-10-01',
        'approved_ready_at'  => '2026-10-02',
        'sample_approved_at' => '2026-09-10',
        'produced_at'        => '2026-09-28',
        'notes'              => 'rewritten by the agent',
    ])->assertSessionHasNoErrors();

    $purchaseOrder->refresh();
    expect($purchaseOrder->proposed_ready_at->toDateString())->toBe('2026-10-01')
        ->and($purchaseOrder->approved_ready_at)->toBeNull()
        ->and($purchaseOrder->notes)->toBe($notes)
        ->and($purchaseOrder->sample_approved_at)->toBeNull()
        ->and($purchaseOrder->produced_at)->toBeNull();

    actingAs($this->adminGuest->getUser());
})->depends('create purchase order through an agent');

test('agent manager only sees their own agent organisation', function () {
    $organisation = $this->agent->organisation;
    \App\Actions\SysAdmin\Organisation\Seeders\SeedOrganisationPermissions::run($organisation);
    \App\Actions\SysAdmin\Organisation\Seeders\SeedJobPositions::run($organisation);

    $agentManager = JobPosition::where('organisation_id', $organisation->id)->where('code', 'agt-m')->firstOrFail();
    $employee     = StoreEmployee::make()->action($organisation, [
        'worker_number'   => 'agent-manager',
        'alias'           => 'agent-manager',
        'contact_name'    => 'Agent Manager',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $agentManager->slug, 'scopes' => []]],
    ]);
    $agentPassword = Str::random(32);
    $agentUser     = StoreUser::make()->action($employee, [
        'username'       => 'agent-manager',
        'password'       => $agentPassword,
        'status'         => true,
        'reset_password' => false,
    ]);

    expect($agentUser->roles()->pluck('name')->all())->toBe(['agent-manager-'.$organisation->id])
        ->and($agentUser->authTo('agent.'.$organisation->id))->toBeTrue()
        ->and($agentUser->worksOnlyForAgents())->toBeTrue()
        ->and(array_keys(\App\Actions\UI\Grp\Layout\GetGroupNavigation::run($agentUser)))->toBe(['tickets'])
        ->and(array_keys(\App\Actions\UI\Grp\Layout\GetOrganisationsLayout::run($agentUser)))->toBe([$organisation->slug])
        ->and(array_keys(\App\Actions\UI\Grp\Layout\GetOrganisationsLayout::run($agentUser)[$organisation->slug]))->toBe(['agent_orders', 'agent_suppliers', 'agent_products', 'agent_warehouse', 'agent_accounting', 'agent_reports', 'hr', 'agent_settings'])
        ->and(collect(\App\Actions\UI\Grp\Layout\GetOrganisationsLayout::run($agentUser)[$organisation->slug]['agent_suppliers']['topMenu']['subSections'])->pluck('root')->all())->toBe([
            'grp.org.agent.org_suppliers.',
        ]);

    auth()->logout();
    $this->post(route('grp.login.store'), ['username' => 'agent-manager', 'password' => $agentPassword])
        ->assertRedirect(route('grp.org.agent.orders.index', $organisation->slug));

    actingAs($agentUser);

    $this->get(route('grp.org.agent.purchase_orders.index', $organisation->slug))->assertOk();
    $groupTicket = \App\Actions\Helpers\Ticket\StoreTicket::make()->action($this->group, ['subject' => 'HS codes for customs', 'reporter_type' => 'User', 'reporter_id' => $agentUser->id]);
    expect($groupTicket->organisation_id)->toBeNull();
    $this->get(route('grp.org.tickets.show', [$organisation->slug, $groupTicket->reference]))->assertOk();
    $this->get(route('grp.org.agent.sales.dashboard', $organisation->slug))
        ->assertInertia(fn ($page) => $page
            ->component('Org/Procurement/AgentSales')
            ->missing('sales')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('sales.year', now()->year)
                ->has('sales.organisations.0.this_year', now()->month)
                ->has('sales.organisations.0.last_year', 12)
                ->has('sales.organisations', OrgAgent::where('agent_id', $this->agent->id)->count())
                ->has('sales.organisations.0.problems.out')
                ->has('sales.top_sellers')
                ->has('feedback.customers.reviews')
                ->has('feedback.customers.recent')
                ->has('feedback.purchasing')
                ->has('feedback.warehouse.lines')
                ->has('sales.out.rows')
                ->where('sales.organisations.0.sold', fn ($sold) => $sold >= 0)));
    $this->get(route('grp.org.agent.sales.dashboard', $this->organisation->slug))->assertForbidden();
    $this->get(route('grp.org.agent.sales.suppliers.index', $organisation->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Org/Procurement/AgentSalesSuppliers')->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->intersect(['rating', 'refund_percent', 'short_percent'])->count() === 3)->has('data.meta.total')->has('currency'));
    $this->get(route('grp.org.agent.sales.products.index', [$organisation->slug, 'elements' => ['problem' => 'out']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Org/Procurement/AgentSalesProducts')->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->pluck('key')->intersect(['rating', 'refund_percent', 'short_percent'])->count() === 3)->has('data.meta.total')->has('currency'));
    $this->get(route('grp.org.agent.sales.products.index', [$organisation->slug, 'dateInterval' => 'ytd']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('queryBuilderProps.default.dateInterval', 'ytd')->has('data.meta.total'));
    $this->get(route('grp.org.agent.sales.suppliers.index', [$organisation->slug, 'dateInterval' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('queryBuilderProps.default.dateInterval', 'all')->has('data.meta.total'));
    $this->get(route('grp.org.agent.sales.suppliers.index', [$organisation->slug, 'dateInterval' => 'nonsense']))
        ->assertInertia(fn ($page) => $page->where('queryBuilderProps.default.dateInterval', '1y'));
    $this->get(route('grp.org.agent.sales.products.index', [$organisation->slug, 'measure' => 'sales', 'currency' => 'EUR']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('valuation.measure', 'sales')
            ->where('valuation.currency', 'EUR')
            ->where('queryBuilderProps.default.columns', fn ($columns) => collect($columns)->firstWhere('key', 'sold')['label'] === __('Sold, wholesale'))
            ->has('data.meta.total'));
    $this->get(route('grp.org.agent.sales.suppliers.index', [$organisation->slug, 'measure' => 'nonsense', 'currency' => 'XYZ']))
        ->assertInertia(fn ($page) => $page->where('valuation.measure', 'cost')->where('valuation.currency', 'agent'));
    $this->get(route('grp.org.agent.sales.dashboard', [$organisation->slug, 'measure' => 'sales', 'currency' => 'GBP']))
        ->assertInertia(fn ($page) => $page
            ->where('valuation.measure', 'sales')
            ->loadDeferredProps(fn ($reload) => $reload->where('sales.valuation.currency', 'GBP')->where('sales.organisations.0.sold', fn ($sold) => $sold >= 0)));
    $salesSupplier = Supplier::where('agent_id', $this->agent->id)->firstOrFail();
    $this->get(route('grp.org.agent.sales.suppliers.show', [$organisation->slug, $salesSupplier->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Org/Procurement/AgentSales')
            ->where('supplier.code', $salesSupplier->code)
            ->loadDeferredProps(fn ($reload) => $reload->has('sales.organisations')->has('feedback.warehouse.worst')));
    $this->get(route('grp.org.agent.sales.products.index', [$organisation->slug, 'supplier' => $salesSupplier->id]))->assertOk();
    $foreignSupplier = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $this->get(route('grp.org.agent.sales.suppliers.show', [$organisation->slug, $foreignSupplier->slug]))->assertNotFound();
    $this->get(route('grp.org.agent.sales.suppliers.index', $this->organisation->slug))->assertForbidden();
    $warehouseSupplierProduct = \App\Models\SupplyChain\SupplierProduct::where('agent_id', $this->agent->id)->firstOrFail();
    \App\Models\Procurement\AgentWarehouseStock::create([
        'group_id'            => $this->agent->group_id,
        'agent_id'            => $this->agent->id,
        'organisation_id'     => $this->organisation->id,
        'supplier_product_id' => $warehouseSupplierProduct->id,
        'state'               => \App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER,
        'quantity'            => 12,
    ]);
    $this->get(route('grp.org.agent.warehouse.index', [$organisation->slug, 'filter' => ['state' => 'packed_for_next_container', 'organisation' => $this->organisation->slug]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Org/Procurement/AgentWarehouseStocks')
            ->where('data.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['code'] === $warehouseSupplierProduct->code && $row['state'] === 'packed_for_next_container'))
            ->where('filterOptions.organisation', fn ($options) => collect($options)->contains(fn ($option) => $option['value'] === $this->organisation->slug && $option['count'] > 0))
            ->where('cards.0.code', $this->organisation->code)
            ->where('cards.0.slug', $this->organisation->slug)
            ->where('cards.0.states.1.units', 12)
            ->where('cards.0.products.0.code', $warehouseSupplierProduct->code));
    $this->get(route('grp.org.agent.warehouse.index', [$organisation->slug, 'filter' => ['state' => 'received']]))
        ->assertInertia(fn ($page) => $page->where('data.data', fn ($rows) => collect($rows)->doesntContain(fn ($row) => $row['code'] === $warehouseSupplierProduct->code)));
    $containerList = writeChinaContainerList('Slovakia AWChina-95.xlsx', [["G14 {$warehouseSupplierProduct->code}", 120, 3, true], ['NOPE-999', 10, 1, true], [$warehouseSupplierProduct->code, 120, 0, true]]);
    $imported = \App\Actions\Procurement\AgentWarehouseStock\ImportAgentWarehouseStocksFromContainerList::run($this->agent, $this->organisation, $containerList);
    unlink($containerList);
    expect($imported['unmatched'])->toBe(['NOPE-999'])
        ->and(\App\Models\Procurement\AgentWarehouseStock::where('agent_id', $this->agent->id)->where('organisation_id', $this->organisation->id)->where('notes', 'AWChina-95')->get(['supplier_product_id', 'quantity', 'notes'])->map(fn ($stock) => [$stock->supplier_product_id, (float) $stock->quantity, $stock->notes])->all())
        ->toBe([[$warehouseSupplierProduct->id, 360.0, 'AWChina-95']])
        ->and(\App\Models\Procurement\AgentWarehouseStock::where('agent_id', $this->agent->id)->where('organisation_id', $this->organisation->id)->whereNull('notes')->exists())->toBeTrue();
    $this->get(route('grp.org.agent.warehouse.index', $this->organisation->slug))->assertForbidden();
    $this->get(route('grp.org.agent.sales.products.index', $this->organisation->slug))->assertForbidden();
    $this->get(route('grp.org.agent.org_suppliers.index', [$organisation->slug, 'tab' => 'index']))
        ->assertInertia(fn ($page) => $page->where('index.meta.total', Supplier::where('agent_id', $this->agent->id)->where('status', true)->count()));
    $this->get(route('grp.org.agent.org_suppliers.index', $organisation->slug))
        ->assertInertia(fn ($page) => $page
            ->where('tabs.current', 'dashboard')
            ->missing('index')
            ->where('tabs.navigation.index.title', 'Supplier list')
            ->where('tabs.navigation.index.number', Supplier::where('agent_id', $this->agent->id)->where('status', true)->count())
            ->missing('dashboard.kpis.active_suppliers')
            ->has('dashboard.chase.rows')
            ->has('dashboard.stages', 6));

    [$chaseSupplier] = createAgentOrgSupplierWithProduct($this);
    $chased          = tap(StorePurchaseOrder::make()->action($chaseSupplier, PurchaseOrder::factory()->definition()))
        ->update(['state' => PurchaseOrderStateEnum::CONFIRMED, 'submitted_at' => now()->subDays(60), 'estimated_received_at' => now()->subDays(3)]);
    $chaseList = fn (array $elements) => collect($this->get(route('grp.org.agent.purchase_orders.index', [
        $organisation->slug,
        'elements' => ['organisation' => $chased->organisation->slug, ...$elements],
        'filter'   => ['global' => $chased->reference],
    ]))->viewData('page')['props']['data']['data'])->pluck('slug')->all();
    expect($chaseList(['attention' => 'past_eta,unconfirmed_over_14_days', 'progress' => 'confirmed']))->toBe([$chased->slug])
        ->and($chaseList(['progress' => 'dispatched']))->toBe([]);

    $incomingList = fn () => collect($this->get(route('grp.org.agent.incoming_purchase_orders.index', [
        $organisation->slug,
        'elements' => ['organisation' => $chased->organisation->slug],
        'filter'   => ['global' => $chased->reference],
    ]))->assertOk()->viewData('page')['props']['data']['data'])->pluck('slug')->all();
    expect($incomingList())->toBe([]);
    $this->get(route('grp.org.agent.incoming_purchase_orders.index', $organisation->slug))
        ->assertInertia(fn ($page) => $page->where('queryBuilderProps.default.elementGroups', fn ($groups) => collect($groups)->keys()->all() === ['attention']
            && collect($groups['attention']['elements'])->keys()->intersect(['in_production', 'past_eta'])->isEmpty()));
    $incomingCount = fn () => $this->getJson(route('grp.org.agent.incoming_purchase_orders.count', $organisation->slug))->assertOk()->json('count');
    $countBefore   = $incomingCount();
    $chased->update(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $this->get(route('grp.org.agent.incoming_purchase_orders.show', [$organisation->slug, $chased->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Procurement/PurchaseOrder')
            ->where('breadcrumbs', fn ($breadcrumbs) => collect($breadcrumbs)->pluck('simple.route.name')->filter()->values()->slice(-2)->values()->all() === ['grp.org.agent.incoming_purchase_orders.index', 'grp.org.agent.incoming_purchase_orders.show']));
    expect($incomingList())->toBe([$chased->slug])
        ->and($incomingCount())->toBe($countBefore + 1);
    $chasedSupplierSlug = $chaseSupplier->supplier->slug;
    $this->get(route('grp.org.agent.incoming_purchase_orders.index', [$organisation->slug, 'filter' => ['supplier' => $chasedSupplierSlug]]))
        ->assertInertia(fn ($page) => $page
            ->where('data.data', fn ($rows) => collect($rows)->pluck('slug')->all() === [$chased->slug])
            ->where('data.data.0.supplier_purchase_orders', 1)
            ->where('filterOptions.supplier', fn ($options) => collect($options)->contains(fn ($option) => $option['value'] === $chasedSupplierSlug && $option['count'] === 1)));
    $organisationCounts = fn (string $state) => collect($this->get(route('grp.org.agent.purchase_orders.index', [
        $organisation->slug,
        'elements' => ['state' => $state],
        'filter'   => ['global' => $chased->reference],
    ]))->viewData('page')['props']['filterOptions']['organisation'])->pluck('count', 'value')->all();
    $this->get(route('grp.org.agent.purchase_orders.index', [$organisation->slug, 'filter' => ['global' => $chased->reference]]))
        ->assertInertia(fn ($page) => $page->where('data.data.0.organisation_code', $chased->organisation->code));
    expect($organisationCounts('submitted'))->toBe([$chased->organisation->slug => 1])
        ->and($organisationCounts('cancelled'))->toBe([]);
    $chased->update(['production_started_at' => now()]);
    expect($incomingList())->toBe([])
        ->and($incomingCount())->toBe($countBefore);
    $chased->update(['state' => PurchaseOrderStateEnum::CONFIRMED, 'production_started_at' => null]);

    $backlog = $this->get(route('grp.org.agent.orders.index', $organisation->slug))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Org/Procurement/AgentOrdersBacklog'))
        ->viewData('page')['props']['data'];
    $clientSlug = $chased->organisation->slug;
    expect(collect($backlog['dimension']['items'])->pluck('label', 'key')->get($clientSlug))->toBe(strtoupper($chased->organisation->code))
        ->and($backlog['data'][$clientSlug]['po_in_factory']['value'] + $backlog['data'][$clientSlug]['po_in_factory']['shared'])->toBeGreaterThanOrEqual(1)
        ->and($backlog['data'][$clientSlug]['po_submitted']['shared'] + $backlog['data'][$clientSlug]['po_in_factory']['shared'])->toBe($backlog['data'][$clientSlug]['wh_received']['shared']);
    $drillDown = fn (string $key) => route(
        $backlog['data'][$clientSlug][$key]['route_target']['name'],
        Arr::except($backlog['data'][$clientSlug][$key]['route_target']['parameters'], '_query')
    ).'?'.http_build_query($backlog['data'][$clientSlug][$key]['route_target']['parameters']['_query']);
    $drilledPurchaseOrders = collect($this->get($drillDown('po_in_factory'))->assertOk()->viewData('page')['props']['data']['data']);
    expect($drilledPurchaseOrders->pluck('slug'))->toContain($chased->slug)
        ->and($drilledPurchaseOrders->pluck('organisation_code')->unique()->values()->all())->toBe([$chased->organisation->code])
        ->and($drilledPurchaseOrders)->toHaveCount($backlog['data'][$clientSlug]['po_in_factory']['value'] + $backlog['data'][$clientSlug]['po_in_factory']['shared']);
    expect($backlog['data'][$clientSlug]['po_in_factory']['lines'])->toBeGreaterThanOrEqual($chased->refresh()->number_current_purchase_order_transactions);
    $linesUrl = fn (array $target) => route($target['name'], Arr::except($target['parameters'], '_query')).'?'.http_build_query($target['parameters']['_query']);
    $this->get($linesUrl($backlog['data'][$clientSlug]['po_in_factory']['lines_route']))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Org/Procurement/AgentWarehouseProducts')
            ->where('data.total', $backlog['data'][$clientSlug]['po_in_factory']['lines']));
    $this->get($linesUrl($backlog['totals']['sd_dispatched']['lines_route']))->assertOk()
        ->assertInertia(fn ($page) => $page->where('data.total', $backlog['totals']['sd_dispatched']['lines']));
    $this->get(route('grp.org.agent.orders.lines', [$organisation->slug, 'stage' => 'nope']))->assertNotFound();
    $this->get(route('grp.org.agent.orders.lines', [$organisation->slug, 'stage' => ['po_submitted']]))->assertNotFound();
    $this->get(route('grp.org.agent.orders.lines', [$organisation->slug, 'stage' => 'po_in_factory', 'factory' => ['made']]))->assertOk();
    $this->get(route('grp.org.agent.stock_deliveries.index', [$organisation->slug, 'filter' => ['organisation' => ['x']]]))->assertOk();
    $this->get(route('grp.org.agent.stock_deliveries.index', [$organisation->slug, 'filter' => ['organisation' => $this->agent->organisation->slug]]))
        ->assertInertia(fn ($page) => $page->where('pageHead.model', ''));
    $transitIcon = fn () => collect(\App\Actions\Procurement\PurchaseOrder\UI\ShowAgentOrdersBacklog::make()->handle($this->agent, $organisation->refresh())['metrics'])->firstWhere('key', 'transit')['items'][0]['icon'];
    expect($transitIcon())->toBe('fal fa-ship');
    \App\Actions\Procurement\Settings\UpdateProcurementSettings::make()->handle($organisation, ['default_delivery_type' => 'air']);
    expect($transitIcon())->toBe('fal fa-plane')
        ->and(\App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum::usualFor($organisation->refresh()))->toBe(\App\Enums\GoodsIn\StockDelivery\StockDeliveryTypeEnum::AIR);
    $this->get(route('grp.org.agent.settings.edit', [$organisation->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('formData.blueprint.2.fields.default_delivery_type.value', 'air')->missing('formData.0'));
    \App\Actions\Procurement\Settings\UpdateProcurementSettings::make()->handle($organisation, ['default_delivery_type' => null]);
    expect($backlog['data'][$clientSlug]['po_submitted']['route_target']['name'])->toBe('grp.org.agent.incoming_purchase_orders.index');
    $this->get($linesUrl($backlog['data'][$clientSlug]['po_submitted']['route_target']))->assertOk();
    $clientRow = collect($backlog['dimension']['items'])->firstWhere('key', $clientSlug);
    expect(collect($this->get($linesUrl($clientRow['route_target']))->assertOk()->viewData('page')['props']['data']['data'])->pluck('organisation_code')->unique()->values()->all())->toBe([$chased->organisation->code]);
    $warehouseTarget = $backlog['data'][$clientSlug]['wh_received']['route_target'];
    expect($warehouseTarget['name'])->toBe('grp.org.agent.warehouse.index')
        ->and($backlog['data'][$clientSlug]['wh_received']['lines'])->toBe(\App\Models\Procurement\AgentWarehouseStock::where('agent_id', $this->agent->id)->where('organisation_id', $chased->organisation_id)->where('quantity', '>', 0)->count());
    $this->get($linesUrl($warehouseTarget))->assertOk();

    $factoryParts = collect($backlog['data'][$clientSlug]['po_in_factory']['breakdown']);
    expect($factoryParts->sum('value'))->toBe($backlog['data'][$clientSlug]['po_in_factory']['value'] + $backlog['data'][$clientSlug]['po_in_factory']['shared']);
    $notStarted     = $factoryParts->first(fn ($part) => $part['route_target']['parameters']['_query']['elements[factory]'] === 'not_started');
    $notStartedList = collect($this->get(route($notStarted['route_target']['name'], Arr::except($notStarted['route_target']['parameters'], '_query')).'?'.http_build_query($notStarted['route_target']['parameters']['_query']))
        ->assertOk()->viewData('page')['props']['data']['data']);
    expect($notStartedList->pluck('slug'))->toContain($chased->slug)
        ->and($notStartedList)->toHaveCount($notStarted['value']);
    $this->get($drillDown('sd_dispatched'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('pageHead.model', $chased->organisation->name));

    $panel = $this->getJson(route('grp.org.agent.purchase_orders.chase_panel', [$organisation->slug, $chased->id]))->assertOk()->json();
    $markable = collect($panel['stages'])->pluck('marks')->flatten(1)->pluck('field')->unique()->values()->all();
    expect($panel['can_edit'])->toBeTrue()
        ->and($markable)->not->toBeEmpty()
        ->and(array_diff($markable, PurchaseOrderStageEnum::AGENT_MARKABLE_FIELDS))->toBe([]);
    $this->postJson(route('grp.models.purchase-order.chase.store', $chased->id), ['channel' => 'wechat', 'contact' => 'Mr Li', 'promised_at' => now()->addDays(10)->toDateString()])->assertCreated();
    expect($chased->refresh()->estimated_received_at->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($chaseList(['attention' => 'past_eta']))->toBe([])
        ->and($chased->chases()->first()->user_id)->toBe($agentUser->id);
    $this->postJson(route('grp.models.supplier.change_request.store', $chaseSupplier->supplier_id), ['type' => 'deactivate', 'reason' => 'No longer answers'])->assertCreated();
    $this->patchJson(route('grp.models.supplier_change_request.decide', SupplierChangeRequest::where('supplier_id', $chaseSupplier->supplier_id)->value('id')), ['approve' => true])->assertForbidden();

    $orgSupplierOfAnotherOrganisation = OrgSupplier::where('organisation_id', '!=', $organisation->id)
        ->whereHas('supplier', fn ($query) => $query->where('agent_id', $this->agent->id))
        ->firstOrFail();
    $this->get(route('grp.org.agent.org_suppliers.show.supplier_products.index', [$organisation->slug, $orgSupplierOfAnotherOrganisation->slug]))->assertOk();
    expect(collect(\App\Actions\Procurement\OrgSupplier\UI\GetOrgSupplierShowcase::run($orgSupplierOfAnotherOrganisation, $organisation)['stats'])->pluck('route.parameters.0')->unique()->all())
        ->toBe([$organisation->slug]);

    $ownOrgAgent      = OrgAgent::where('agent_id', $this->agent->id)->where('organisation_id', '!=', $organisation->id)->firstOrFail();
    [$ownOrgSupplier] = createAgentOrgSupplierWithProduct($this, $ownOrgAgent);
    $ownPurchaseOrder = StorePurchaseOrder::make()->action($ownOrgSupplier, PurchaseOrder::factory()->definition());
    $this->get(route('grp.org.agent.org_agents.show', [$organisation->slug, $ownOrgAgent->slug]))
        ->assertInertia(fn ($page) => $page->where('pageHead.subNavigation', fn ($items) => collect($items)->pluck('route.parameters.0')->unique()->values()->all() === [$organisation->slug]));
    $this->get(route('grp.org.agent.purchase_orders.pdf', [$organisation->slug, $ownPurchaseOrder->slug]))->assertOk();
    $labelsUrl = $this->get(route('grp.org.agent.purchase_orders.labels', [$organisation->slug, $ownPurchaseOrder->slug]))->assertRedirect()->headers->get('Location');
    expect($labelsUrl)->toContain('purchase-order-labels/'.$ownPurchaseOrder->id)
        ->and($this->get($labelsUrl)->status())->not->toBe(403);

    $ownPurchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $directPurchaseOrder = StorePurchaseOrder::make()->action($ownOrgSupplier, PurchaseOrder::factory()->definition());
    $directPurchaseOrder->updateQuietly(['agent_id' => null, 'agent_order_reference' => null, 'state' => PurchaseOrderStateEnum::SUBMITTED]);
    $draftPurchaseOrder = StorePurchaseOrder::make()->action($ownOrgSupplier, PurchaseOrder::factory()->definition());
    $draftPurchaseOrder->updateQuietly(['agent_id' => null, 'agent_order_reference' => null]);
    $this->get(route('grp.org.agent.purchase_orders.pdf', [$organisation->slug, $directPurchaseOrder->slug]))->assertNotFound();
    $this->get(route('grp.org.agent.purchase_orders.labels', [$organisation->slug, $directPurchaseOrder->slug]))->assertNotFound();
    expect(collect($this->get(route('grp.org.agent.org_suppliers.show.purchase_orders.index', [$organisation->slug, $ownOrgSupplier->slug]))
        ->assertOk()->viewData('page')['props']['data']['data'])->pluck('slug')->all())->toBe([$ownPurchaseOrder->slug]);
    $this->get(route('grp.org.agent.org_suppliers.show.purchase-orders.show', [$organisation->slug, $ownOrgSupplier->slug, $ownPurchaseOrder->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('pageHead.actions', fn ($actions) => collect($actions)->contains('key', 'labels')));
    $this->get(route('grp.org.agent.org_suppliers.show.purchase-orders.show', [$organisation->slug, $ownOrgSupplier->slug, $directPurchaseOrder->slug]))->assertNotFound();

    $this->artisan('procurement:link_agent_supplier_purchase_orders', ['agent' => $this->agent->slug, '--dry-run' => true])->assertExitCode(0);
    expect($directPurchaseOrder->refresh()->agent_id)->toBeNull();
    $this->artisan('procurement:link_agent_supplier_purchase_orders', ['agent' => $this->agent->slug])->assertExitCode(0);
    expect($directPurchaseOrder->refresh()->agent_id)->toBe($this->agent->id)
        ->and($directPurchaseOrder->agent_order_reference)->toBeNull()
        ->and($directPurchaseOrder->data['agent_linked_at'])->not->toBeNull()
        ->and($draftPurchaseOrder->refresh()->agent_id)->toBeNull();
    $this->get(route('grp.org.agent.org_suppliers.show.purchase-orders.show', [$organisation->slug, $ownOrgSupplier->slug, $directPurchaseOrder->slug]))->assertOk();
    $directPurchaseOrder->delete();
    $draftPurchaseOrder->delete();
    $ownPurchaseOrder->delete();

    $foreignAgent    = StoreAgent::make()->action($this->group, Agent::factory()->definition());
    $foreignOrgAgent = StoreOrgAgent::make()->action($ownOrgAgent->organisation, $foreignAgent, []);
    $this->get(route('grp.org.agent.org_agents.show.purchase-orders.index', [$organisation->slug, $foreignOrgAgent->slug]))->assertNotFound();
    $this->get(route('grp.org.agent.org_agents.show.suppliers.index', [$organisation->slug, $foreignOrgAgent->slug]))->assertNotFound();

    $this->get(route('grp.org.hr.employees.index', $organisation->slug))->assertOk();
    $this->get(route('grp.org.hr.employees.show.users.show', [$organisation->slug, $employee->slug, $agentUser->slug]))
        ->assertInertia(fn ($page) => $page->where('pageHead.actions.0', false)->where('pageHead.subNavigation', []));

    $this->get(route('grp.org.accounting.invoices.index', $this->organisation->slug))->assertForbidden();
    $this->get(route('grp.org.procurement.purchase_orders.index', $this->organisation->slug))->assertForbidden();
    $this->get(route('grp.overview.crm.customers.index'))->assertForbidden();
    $this->get(route('grp.dashboard.catalogue.show'))->assertForbidden();
    $this->get(route('grp.supply-chain.dashboard'))->assertForbidden();
    $this->get(route('grp.tickets.index'))->assertOk();
    $this->get(route('grp.projects.index'))->assertForbidden();

    $orgAdmin    = JobPosition::where('organisation_id', $organisation->id)->where('code', 'org-admin')->firstOrFail();
    $agentClerk  = JobPosition::where('organisation_id', $organisation->id)->where('code', 'agt-c')->firstOrFail();
    $createProps = json_encode($this->get(route('grp.org.hr.employees.create', $organisation->slug))->viewData('page')['props']);
    expect($createProps)->not->toContain('"code":"org-admin"')->toContain('"code":"agt-c"');

    $this->post(route('grp.models.org.employee.store', $organisation->id), [
        'worker_number'   => 'agent-new',
        'alias'           => 'agent-new',
        'contact_name'    => 'Agent New',
        'state'           => EmployeeStateEnum::WORKING->value,
        'type'            => EmployeeTypeEnum::EMPLOYEE->value,
        'employment_type' => EmploymentTypeEnum::FULL_TIME->value,
        'positions'       => [['slug' => $agentClerk->slug, 'scopes' => []], ['slug' => $orgAdmin->slug, 'scopes' => []]],
        'username'        => 'agent-new',
        'password'        => Str::random(32),
    ])->assertRedirect();
    expect(\App\Models\HumanResources\Employee::where('alias', 'agent-new')->firstOrFail()->jobPositions()->pluck('code')->all())->toBe(['agt-c']);

    $administrator = StoreEmployee::make()->action($organisation, [
        'worker_number'   => 'agent-admin',
        'alias'           => 'agent-admin',
        'contact_name'    => 'Agent Admin',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
        'positions'       => [['slug' => $orgAdmin->slug, 'scopes' => []]],
    ]);
    $this->patch(route('grp.models.employee.update', $administrator->id), [
        'job_positions' => [['slug' => $agentClerk->slug, 'scopes' => []]],
    ])->assertRedirect();
    expect($administrator->jobPositions()->pluck('code')->sort()->values()->all())->toBe(['agt-c', 'org-admin']);

    actingAs($this->adminGuest->getUser());
    expect(json_encode($this->get(route('grp.org.hr.employees.create', $organisation->slug))->viewData('page')['props']))->toContain('"code":"org-admin"');
    $this->get(route('grp.overview.crm.customers.index'))->assertOk();
    $this->get(route('grp.org.agent.purchase_orders.index', $organisation->slug))->assertOk();
});

test('supply chain shows how a supplier and an agent sell, this month and year to date', function () {
    $supplierCurrency = $this->supplier->currency->code;
    $groupCurrency    = $this->group->currency->code;

    actingAs($this->adminGuest->getUser());

    $this->get(route('grp.supply-chain.suppliers.sales', $this->supplier->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Org/Procurement/AgentSales')
            ->where('seller', 'supplier')
            ->where('links', null)
            ->where('valuation.agent_currency', $supplierCurrency)
            ->where('valuation.currencies', [$groupCurrency])
            ->where('pageHead.subNavigation', fn ($navigation) => collect($navigation)->contains(fn ($item) => ($item['route']['name'] ?? null) === 'grp.supply-chain.suppliers.sales'))
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('sales.currency', $supplierCurrency)
                ->where('sales.month', now()->month)
                ->has('sales.organisations', OrgSupplier::where('supplier_id', $this->supplier->id)->count())
                ->has('feedback.purchasing')));

    $this->get(route('grp.supply-chain.suppliers.sales', [$this->supplier->slug, 'currency' => $groupCurrency, 'measure' => 'sales']))
        ->assertInertia(fn ($page) => $page
            ->where('valuation.measure', 'sales')
            ->loadDeferredProps(fn ($reload) => $reload->where('sales.currency', $groupCurrency)));
    $this->get(route('grp.supply-chain.suppliers.sales', [$this->supplier->slug, 'currency' => $supplierCurrency === 'EUR' ? 'JPY' : 'EUR']))
        ->assertInertia(fn ($page) => $page->where('valuation.currency', 'agent'));

    $this->get(route('grp.supply-chain.agents.show.sales', $this->agent->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('seller', 'agent')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('sales.organisations', OrgAgent::where('agent_id', $this->agent->id)->count())
                ->has('sales.organisations.0.month_this_year', now()->day)
                ->has('sales.organisations.0.month_last_year', now()->subYearNoOverflow()->daysInMonth)));

    $freeSupplier = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $this->get(route('grp.supply-chain.suppliers.sales', $freeSupplier->slug))
        ->assertInertia(fn ($page) => $page->loadDeferredProps(fn ($reload) => $reload->has('sales.organisations', OrgSupplier::where('supplier_id', $freeSupplier->id)->count())->where('sales.products', 0)->where('sales.selling', 0)));
});

test('a supplier sees only its own sales at cost and its own purchase orders through its private link', function () {
    \Illuminate\Support\Facades\Notification::fake();

    [$orgSupplier]      = independentOrgSupplierFixture($this);
    [$otherOrgSupplier] = independentOrgSupplierFixture($this);
    $supplier           = $orgSupplier->supplier;

    $placeOrder = function (OrgSupplier $orgSupplier, string $state): PurchaseOrder {
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
        $purchaseOrder->update(['state' => $state, 'submitted_at' => $state === 'in_process' ? null : now()]);

        return $purchaseOrder;
    };
    $sent    = $placeOrder($orgSupplier, 'submitted');
    $draft   = $placeOrder($orgSupplier, 'in_process');
    $foreign = $placeOrder($otherOrgSupplier, 'submitted');

    actingAs($this->adminGuest->getUser());
    $this->postJson(route('grp.models.supplier.share_links.store', $supplier->id), ['months' => 2])->assertUnprocessable();
    $url = $this->postJson(route('grp.models.supplier.share_links.store', $supplier->id), ['months' => 3, 'email' => 'buyer@supplier.test'])
        ->assertOk()
        ->json('url');
    $link = \App\Models\SupplyChain\SupplierShareLink::where('supplier_id', $supplier->id)->sole();
    expect($link->token_hash)->not->toContain(basename($url))
        ->and($link->expires_at->isSameDay(now()->addMonthsNoOverflow(3)))->toBeTrue();

    $this->get(route('grp.supply-chain.suppliers.share', [$supplier->slug, 'tab' => 'direct_links']))
        ->assertInertia(fn ($page) => $page->where('direct_links.data.0.email', 'buyer@supplier.test')->where('direct_links.data.0.live', true));

    \Illuminate\Support\Facades\Auth::logout();

    $this->get($url)
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page
            ->component('SupplyChain/SupplierShare')
            ->where('needs_code', true)
            ->where('email', 'b****@supplier.test')
            ->missing('purchase_orders')
            ->missing('sales')
            ->where('layout', []));
    expect($this->get($url)->headers->get('Cache-Control'))->toContain('no-store');

    $this->post(route('grp.supplier_share.send_code', basename($url)))->assertRedirect();
    $code = null;
    \Illuminate\Support\Facades\Notification::assertSentOnDemand(
        \App\Notifications\SupplierShareCodeNotification::class,
        function ($notification, $channels, $notifiable) use (&$code) {
            $code = $notification->code;

            return $notifiable->routes['mail'] === 'buyer@supplier.test';
        }
    );

    $this->post(route('grp.supplier_share.verify', basename($url)), ['code' => $code === '111111' ? '222222' : '111111'])->assertSessionHasErrors('code');
    $this->post(route('grp.supplier_share.verify', basename($url)), ['code' => $code])->assertRedirect($url);

    $this->get($url.'?measure=sales&currency=GBP')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('needs_code', false)
            ->where('supplier.code', $supplier->code)
            ->where('valuation.measure', 'supplier')
            ->where('valuation.currency', 'agent')
            ->where('valuation.currencies', [])
            ->where('purchase_orders.data', fn ($orders) => collect($orders)->pluck('reference')->all() === [$sent->reference])
            ->where('layout', [])
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('sales.currency', $supplier->currency->code)
                ->where('sales.valuation.measure', 'supplier')
                ->has('sales.organisations', OrgSupplier::where('supplier_id', $supplier->id)->count())
                ->has('feedback.purchasing')));
    expect($link->fresh()->number_views)->toBe(1)
        ->and($link->views()->count())->toBe(1)
        ->and($draft->reference)->not->toBe($sent->reference)
        ->and($foreign->reference)->not->toBe($sent->reference);

    $this->get(route('grp.supplier_share.show', str_repeat('a', 48)))->assertNotFound();
    $this->get(route('grp.supplier_share.show', 'short'))->assertNotFound();

    actingAs($this->adminGuest->getUser());
    $this->post(route('grp.models.supplier_share_link.revoke', $link->id))->assertRedirect();
    \Illuminate\Support\Facades\Auth::logout();
    $this->get($url)->assertNotFound();

    $openUrl = \App\Actions\SupplyChain\SupplierShareLink\StoreSupplierShareLink::make()->handle($supplier, ['months' => 1])['url'];
    $this->get($openUrl)->assertInertia(fn ($page) => $page->where('needs_code', false)->has('purchase_orders.data', 1));
    \App\Models\SupplyChain\SupplierShareLink::where('supplier_id', $supplier->id)->whereNull('email')->update(['expires_at' => now()->subMinute()]);
    $this->get($openUrl)->assertNotFound();

    $this->post(route('grp.models.supplier.share_links.store', $supplier->id), ['months' => 1])->assertRedirect(route('grp.login.show'));
});

test('the supplier contact email gets a private link by email, an old or any other email gets the same answer and nothing', function () {
    \Illuminate\Support\Facades\Notification::fake();

    [$orgSupplier] = independentOrgSupplierFixture($this);
    $supplier      = $orgSupplier->supplier;

    actingAs($this->adminGuest->getUser());
    \App\Actions\SupplyChain\Supplier\UpdateSupplier::make()->action($supplier, ['email' => 'old@supplier.test']);
    \App\Actions\SupplyChain\Supplier\UpdateSupplier::make()->action($supplier, ['email' => 'Owner@Supplier.test']);
    $contacts = \App\Models\SupplyChain\SupplierPortalContact::where('supplier_id', $supplier->id)->get()->keyBy('email');
    expect($contacts['owner@supplier.test']->revoked_at)->toBeNull()
        ->and($contacts['old@supplier.test']->revoked_at)->not->toBeNull();
    $contact = $contacts['owner@supplier.test'];
    \App\Actions\SupplyChain\Supplier\UpdateSupplier::make()->action($supplier, ['extra_emails' => ['Second@Supplier.test']]);
    expect(\App\Models\SupplyChain\SupplierPortalContact::where('supplier_id', $supplier->id)->whereNull('revoked_at')->pluck('email')->sort()->values()->all())
        ->toBe(['owner@supplier.test', 'second@supplier.test']);
    $this->post(route('grp.models.supplier.portal_emails.store', $supplier->id), ['email' => 'Third@Supplier.test'])->assertRedirect();
    \App\Actions\SupplyChain\SupplierPortalContact\RevokeSupplierPortalContact::run($contact);
    $this->post(route('grp.models.supplier.portal_emails.store', $supplier->id), ['email' => 'owner@supplier.test'])->assertRedirect();
    expect($supplier->fresh()->data['extra_emails'])->toBe(['Second@Supplier.test', 'third@supplier.test'])
        ->and($contact->fresh()->revoked_at)->toBeNull();
    $this->get(route('grp.supply-chain.suppliers.share', $supplier->slug))
        ->assertInertia(fn ($page) => $page->where('self_log_in.data', fn ($rows) => collect($rows)->contains('email', 'owner@supplier.test')));

    \Illuminate\Support\Facades\Auth::logout();

    $this->get(route('grp.supplier_portal.login'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->component('SupplyChain/SupplierPortalLogin')->where('link_requested', false));

    $this->post(route('grp.supplier_portal.send_link'), ['email' => 'stranger@supplier.test'])->assertRedirect()->assertSessionHas('link_requested', true);
    $this->post(route('grp.supplier_portal.send_link'), ['email' => 'old@supplier.test'])->assertRedirect()->assertSessionHas('link_requested', true);
    \Illuminate\Support\Facades\Notification::assertNothingSent();

    $this->post(route('grp.supplier_portal.send_link'), ['email' => 'OWNER@supplier.test'])->assertRedirect()->assertSessionHas('link_requested', true);
    $url = null;
    \Illuminate\Support\Facades\Notification::assertSentOnDemand(
        \App\Notifications\SupplierPortalLinkNotification::class,
        function ($notification, $channels, $notifiable) use (&$url) {
            $url = $notification->url;

            return $notifiable->routes['mail'] === 'owner@supplier.test';
        }
    );
    $link = \App\Models\SupplyChain\SupplierShareLink::where('supplier_portal_contact_id', $contact->id)->sole();
    expect($link->expires_at->isSameDay(now()->addDays(\App\Actions\SupplyChain\SupplierPortalContact\SendSupplierPortalLink::DAYS)))->toBeTrue()
        ->and($link->email)->toBeNull()
        ->and($contact->fresh()->number_links_sent)->toBe(1);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('needs_code', false)
            ->where('company.name', $supplier->name)
            ->where('agent', null)
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('summary.currency', $supplier->currency->code)
                ->has('summary.ranking')
                ->has('summary.country')));

    \App\Actions\SupplyChain\Supplier\UpdateSupplier::make()->action($supplier, ['email' => 'new-owner@supplier.test']);
    expect($contact->fresh()->revoked_at)->not->toBeNull()
        ->and($link->fresh()->revoked_at)->not->toBeNull();
    $this->get($url)->assertNotFound();

    $this->post(route('grp.supplier_portal.send_link'), ['email' => 'owner@supplier.test'])->assertRedirect()->assertSessionHas('link_requested', true);
    \Illuminate\Support\Facades\Notification::assertSentOnDemandTimes(\App\Notifications\SupplierPortalLinkNotification::class, 1);
});

test('a supplier behind an agent sees its sales at its own prices, its agent and its ranking in its country', function () {
    [$orgSupplier, $supplierProduct, $orgSupplierProduct] = independentOrgSupplierFixture($this);
    $supplier                                              = $orgSupplier->supplier;
    $supplier->update(['agent_id' => $this->agent->id]);
    $supplier->address->update(['country_code' => 'IN']);

    [$stock]    = createStocks($this->group);
    [$orgStock] = createOrgStocks($this->organisation, [$stock]);
    \App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $orgSupplierProduct);
    $orgStock->update(['sku_value' => 2]);
    $pricePerSko = (float) $supplierProduct->fresh()->cost * ($supplierProduct->fresh()->units_per_pack ?: 1);

    $timeSeriesId = DB::table('org_stock_time_series')->insertGetId([
        'org_stock_id' => $orgStock->id,
        'frequency'    => 'monthly',
        'from'         => now()->subYears(2)->startOfYear(),
        'to'           => now(),
        'data'         => '{}',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    foreach ([[now()->subMonthNoOverflow(), 20], [now()->subMonthsNoOverflow(13), 10]] as [$month, $cogs]) {
        DB::table('org_stock_time_series_records')->insert([
            'org_stock_time_series_id'    => $timeSeriesId,
            'frequency'                   => 'M',
            'period'                      => $month->format('Y-m'),
            'cogs_org_currency'           => $cogs,
            'cogs_grp_currency'           => $cogs,
            'sales_grp_currency_external' => 50,
            'from'                        => $month->copy()->startOfMonth(),
            'to'                          => $month->copy()->endOfMonth(),
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);
    }

    $summary = \App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalSummary::make()->handle($supplier->fresh());
    expect($summary['sales'])->toBe(round(10 * $pricePerSko, 2))
        ->and($summary['previous'])->toBe(round(5 * $pricePerSko, 2))
        ->and($summary['change'])->toBe(100.0)
        ->and($summary['country']['code'])->toBe('IN')
        ->and($summary['ranking']['position'])->toBeGreaterThanOrEqual(1)
        ->and($summary['ranking']['of'])->toBeGreaterThanOrEqual($summary['ranking']['position']);

    [, , $secondSourceProduct] = independentOrgSupplierFixture($this);
    \App\Actions\Inventory\OrgStockHasOrgSupplierProduct\AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $secondSourceProduct);
    expect(\App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalSummary::make()->handle($supplier->fresh())['sales'])->toBe(round(5 * $pricePerSko, 2));
    DB::table('org_stock_has_org_supplier_products')->where('org_supplier_product_id', $secondSourceProduct->id)->update(['status' => false]);

    $url = \App\Actions\SupplyChain\SupplierShareLink\StoreSupplierShareLink::make()->handle($supplier->fresh(), ['months' => 1])['url'];
    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('agent.name', $this->agent->name)
            ->where('valuation.measure', 'supplier')
            ->loadDeferredProps(fn ($reload) => $reload->where('sales.top_sellers.0.sold', fn ($sold) => abs($sold - 10 * $pricePerSko) < 0.5)));
});

test('PO journey board marks stages on an agent purchase order and keeps QC for management', function (PurchaseOrder $purchaseOrder) {
    $route = route('grp.models.purchase-order.journey_stage', $purchaseOrder->id);
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(30), 'deposit_paid_at' => null, 'production_started_at' => null]);

    $this->patch($route, ['stage' => 'in_production', 'date' => '2026-09-20'])->assertSessionHasErrors('reason');
    $this->patch($route, ['stage' => 'in_production', 'date' => '2026-09-20', 'reason' => 'Started before the deposit'])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->production_started_at->toDateString())->toBe('2026-09-20');

    actingAs(User::where('username', 'agent-clerk')->firstOrFail());
    $this->patch($route, ['stage' => 'ready', 'date' => now()->toDateString(), 'reason' => 'x'])->assertForbidden();
    actingAs($this->adminGuest->getUser());

    $this->patch($route, ['stage' => 'deposit_paid', 'date' => now()->toDateString()])->assertSessionHasErrors('stage');
    expect($purchaseOrder->refresh()->deposit_paid_at)->toBeNull()
        ->and(Audit::where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toBe('Started before the deposit');

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['qc_passed_at' => now()->toDateString(), 'notes' => 'qc via edit page'])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->qc_passed_at)->toBeNull();

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'deposit_paid_at' => null, 'production_started_at' => null]);
})->depends('create purchase order through an agent');

test('purchase order stages go in order and ask a reason to skip, backdate or clear', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(5)]);
    $route = route('grp.models.purchase-order.stage', $purchaseOrder->id);
    $today = now($purchaseOrder->organisation->timezone?->name)->toDateString();
    $user  = $this->adminGuest->getUser();

    $this->patch($route, ['field' => 'production_started_at', 'date' => $today])->assertSessionHasErrors('reason');
    expect($purchaseOrder->refresh()->production_started_at)->toBeNull()
        ->and(collect(GetPurchaseOrderStages::make()->forUi($purchaseOrder, ['production_started_at']))->firstWhere('key', 'in_production')['marks'][0])
        ->toMatchArray(['label' => __('Confirm and Start Production'), 'confirms_order' => true]);

    $this->patch($route, ['field' => 'deposit_paid_at', 'date' => $today])->assertSessionHasErrors('field');
    $purchaseOrder->refresh()->updateQuietly(['deposit_paid_at' => now()]);
    $this->patch($route, ['field' => 'production_started_at', 'date' => $today])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED)
        ->and($purchaseOrder->confirmed_at)->not->toBeNull();
    $this->patch($route, ['field' => 'qc_passed_at', 'date' => $today])->assertSessionHasErrors('reason');
    $this->patch($route, ['field' => 'production_started_at', 'date' => now()->subDays(3)->toDateString()])->assertSessionHasErrors('reason');
    $this->patch($route, ['field' => 'production_started_at', 'date' => now()->addDays(2)->toDateString()])->assertSessionHasErrors('date');

    $this->patch($route, ['field' => 'qc_passed_at', 'date' => $today, 'reason' => 'Repeat order, no sample'])->assertSessionHasNoErrors();

    $purchaseOrder->refresh();
    expect($purchaseOrder->qc_passed_at)->not->toBeNull()
        ->and($purchaseOrder->stageMarkUserId('qc_passed_at'))->toBe($user->id)
        ->and($purchaseOrder->stageMarkUserId('production_started_at'))->toBe($user->id)
        ->and(Audit::where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toBe('Repeat order, no sample');

    $this->patch($route, ['field' => 'qc_passed_at', 'date' => null])->assertSessionHasErrors('reason');
    $this->patch($route, ['field' => 'qc_passed_at', 'date' => null, 'reason' => 'Marked by mistake'])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->qc_passed_at)->toBeNull()
        ->and($purchaseOrder->stage_marks)->not->toHaveKey('qc_passed_at');

    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stages.stages.0.reached', true)
            ->where('stages.stages.2.current', true)
            ->where('stages.stages.2.by', $user->contact_name ?: $user->username)
            ->where('stages.stages.3.reached', false)
            ->where('stages.stages.3.marks', fn ($marks) => collect($marks)->firstWhere('field', 'qc_passed_at')['skipped'] === [__('Sample approved'), __('Production photo'), __('QC report')]));
});

test('a direct order owes no deposit, an order without deliveries follows its own delivery state, and stamps follow who wrote the date', function () {
    $supplier = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'DIRECT-'.Str::upper(Str::random(8)),
        'name'             => 'Direct supplier product',
        'cost'             => 100,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier->refresh(), PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(5), 'deposit_amount' => null]);
    $route    = route('grp.models.purchase-order.stage', $purchaseOrder->id);
    $today    = now($purchaseOrder->organisation->timezone?->name)->toDateString();
    $tomorrow = now($purchaseOrder->organisation->timezone?->name)->addDay()->toDateString();

    expect($purchaseOrder->refresh()->isAgentOrder())->toBeFalse();
    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stages.stages.0.current', true)
            ->where('stages.stages.1.reached', false)
            ->where('stages.stages.1.not_required', true)
            ->where('stages.stages.1.unpaid', false));
    $this->patch($route, ['field' => 'production_started_at', 'date' => $today])->assertSessionHasNoErrors();
    $this->patch($route, ['field' => 'production_started_at', 'date' => $tomorrow])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->production_started_at->toDateString())->toBe($tomorrow)
        ->and($purchaseOrder->stageMarkUserId('production_started_at'))->toBe($this->adminGuest->getUser()->id);

    \Illuminate\Support\Facades\Auth::logout();
    UpdatePurchaseOrder::make()->action($purchaseOrder, ['production_started_at' => now()->subDay()->toDateString()]);
    expect($purchaseOrder->refresh()->stage_marks)->not->toHaveKey('production_started_at');
    actingAs($this->adminGuest->getUser());

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SETTLED, 'delivery_state' => PurchaseOrderDeliveryStateEnum::PLACED, 'settled_at' => now()->subDays(2)]);
    $stages = GetPurchaseOrderStages::run($purchaseOrder->refresh());
    expect($purchaseOrder->stockDeliveries()->count())->toBe(0)
        ->and($stages['shipped']['recorded'])->toBeTrue()
        ->and($stages['received']['recorded'])->toBeTrue()
        ->and($stages['received']['date']->toDateString())->toBe(now()->subDays(2)->toDateString());
});

test('the tracker records the ready date, production done, handover and compliance, agents mark the handover and add their own order number, and the buffer follows the organisation', function (PurchaseOrder $purchaseOrder) {
    $purchaseOrder->refresh()->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(5), 'proposed_ready_at' => now()->addDays(30), 'approved_ready_at' => null, 'produced_at' => null]);
    $route    = route('grp.models.purchase-order.stage', $purchaseOrder->id);
    $today    = now($purchaseOrder->organisation->timezone?->name)->toDateString();
    $readyDay = now()->addDays(40)->toDateString();
    $agent    = User::where('username', 'agent-clerk')->firstOrFail();

    $this->patch($route, ['field' => 'approved_ready_at', 'date' => $readyDay])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->approved_ready_at->toDateString())->toBe($readyDay);

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['handed_over_at' => $today, 'compliance_complete_at' => $today, 'balance_paid_at' => $today, 'notes' => 'dates via edit page'])->assertSessionHasNoErrors();
    expect($purchaseOrder->refresh()->handed_over_at)->toBeNull()
        ->and($purchaseOrder->compliance_complete_at)->toBeNull()
        ->and($purchaseOrder->balance_paid_at)->toBeNull();

    actingAs($agent);
    $this->patch($route, ['field' => 'produced_at', 'date' => $today])->assertSessionHasNoErrors();
    $this->patch($route, ['field' => 'approved_ready_at', 'date' => $readyDay])->assertSessionHasErrors('field');
    $this->patch($route, ['field' => 'handed_over_at', 'date' => $today])->assertSessionHasNoErrors();
    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['agent_reference' => 'AG-PO-7781'])->assertSessionHasNoErrors();
    actingAs($this->adminGuest->getUser());

    $this->patch($route, ['field' => 'compliance_complete_at', 'date' => $today])->assertSessionHasNoErrors();

    $purchaseOrder->refresh();
    expect($purchaseOrder->produced_at)->not->toBeNull()
        ->and($purchaseOrder->stageMarkUserId('produced_at'))->toBe($agent->id)
        ->and($purchaseOrder->stageMarkUserId('handed_over_at'))->toBe($agent->id)
        ->and($purchaseOrder->agent_reference)->toBe('AG-PO-7781')
        ->and($purchaseOrder->compliance_complete_at)->not->toBeNull()
        ->and(collect(GetPurchaseOrderStages::make()->forUi($purchaseOrder, []))->firstWhere('key', 'submitted')['note'])->toContain('AG-PO-7781');

    $eta = now()->addDays(30)->toDateString();
    $purchaseOrder->updateQuietly(['estimated_received_at' => $eta, 'data' => array_merge($purchaseOrder->data ?? [], ['estimated_receiving_date' => $eta])]);
    $settings = $purchaseOrder->organisation->settings;
    \App\Actions\SysAdmin\Organisation\UpdateOrganisation::make()->action($purchaseOrder->organisation, ['compliance_buffer_days' => 14]);
    expect(GetPurchaseOrderCompliance::run($purchaseOrder->refresh())['deadline'])->toBe(now()->addDays(16)->toDateString());
    $purchaseOrder->organisation->update(['settings' => $settings]);

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'approved_ready_at' => null, 'produced_at' => null, 'handed_over_at' => null, 'compliance_complete_at' => null, 'agent_reference' => null]);
})->depends('create purchase order through an agent');

test('a purchase order with no deposit needed waits at submitted and passes the deposit stage once production starts', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(5), 'deposit_amount' => 0]);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stages.stages.0.current', true)
            ->where('stages.stages.1.reached', false)
            ->where('stages.stages.1.not_required', true));

    $startedDay = now()->subDays(3)->toDateString();
    $this->patch(route('grp.models.purchase-order.stage', $purchaseOrder->id), ['field' => 'production_started_at', 'date' => $startedDay, 'reason' => 'Agent told us late'])->assertSessionHasNoErrors();

    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED)
        ->and($purchaseOrder->confirmed_at->toDateString())->toBe($startedDay);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stages.stages.1.reached', true)
            ->where('stages.stages.1.not_required', true)
            ->where('stages.stages.1.marks', []));
});

test('container list lines not filled green are still being made, so their purchase order is marked in production', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder                      = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(5)]);
    $code = $orgSupplierProduct->supplierProduct->code;

    $containerList = writeChinaContainerList('UK AWChina-777.xlsx', [[$code, 100, 2, true], [$code, 100, 5, false]]);
    $imported      = \App\Actions\Procurement\AgentWarehouseStock\ImportAgentWarehouseStocksFromContainerList::run($orgSupplier->orgAgent->agent, $purchaseOrder->organisation, $containerList);
    unlink($containerList);

    expect(collect($imported['rows'])->pluck('quantity')->all())->toBe([200.0])
        ->and($imported['in_production'])->toBe([['code' => $code, 'units' => 500.0, 'purchase_order' => $purchaseOrder->reference]])
        ->and($purchaseOrder->refresh()->production_started_at)->not->toBeNull()
        ->and(Audit::where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toContain('AWChina-777');
});

test('stock spreadsheet In Production lines mark their open purchase order as in production', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder                      = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(5), 'production_started_at' => null]);
    $code = $orgSupplierProduct->supplierProduct->code;

    $line = fn (string $code, string $units) => '"'.implode('","', array_replace(array_fill(0, 30, ''), [2 => $code, 7 => $units, 26 => 'NEW ORDER'])).'"';
    \Illuminate\Support\Facades\Http::fake(fn (\Illuminate\Http\Client\Request $request) => \Illuminate\Support\Facades\Http::response(
        str_contains($request->url(), 'htmlview') ? '{name: "In Production"}' : implode("\n", ['"header"', $line($code, '1,200'), $line('NOT-A-PRODUCT', '5')])
    ));

    $imported = \App\Actions\Procurement\AgentWarehouseStock\ImportAgentWarehouseStocksFromSheet::run($orgSupplier->orgAgent->agent, $purchaseOrder->organisation, 'sheet-id');

    expect($imported['in_production'])->toBe([
        ['code' => $code, 'units' => 1200.0, 'purchase_order' => $purchaseOrder->reference],
        ['code' => 'NOT-A-PRODUCT', 'units' => 5.0, 'purchase_order' => null],
    ])
        ->and($imported['rows'])->toBe([])
        ->and($purchaseOrder->refresh()->production_started_at)->not->toBeNull()
        ->and(Audit::where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toContain('In Production tab');
});

test('agent marks only its own stages and the order shows its slowest delivery', function (PurchaseOrder $purchaseOrder) {
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::CONFIRMED, 'submitted_at' => now()->subDays(5), 'deposit_paid_at' => null, 'production_started_at' => null]);
    $route = route('grp.models.purchase-order.stage', $purchaseOrder->id);
    $today = now($purchaseOrder->organisation->timezone?->name)->toDateString();
    $agent = User::where('username', 'agent-clerk')->firstOrFail();

    actingAs($agent);
    $this->patch($route, ['field' => 'deposit_paid_at', 'date' => $today])->assertSessionHasErrors('field');
    $this->patch($route, ['field' => 'production_started_at', 'date' => $today])->assertSessionHasErrors('reason');
    $this->patch($route, ['field' => 'production_started_at', 'date' => $today, 'reason' => 'Supplier started before the deposit'])->assertSessionHasNoErrors();
    $this->patch($route, ['field' => 'qc_passed_at', 'date' => $today, 'reason' => 'x'])->assertSessionHasErrors('field');
    $this->patch($route, ['field' => 'sample_approved_at', 'date' => $today])->assertSessionHasErrors('field');
    actingAs($this->adminGuest->getUser());

    expect($purchaseOrder->refresh()->stageMarkUserId('production_started_at'))->toBe($agent->id);

    $stages = GetPurchaseOrderStages::run($purchaseOrder);
    expect($stages['deposit_paid']['reached'])->toBeTrue()
        ->and($stages['deposit_paid']['recorded'])->toBeFalse()
        ->and(collect(GetPurchaseOrderStages::make()->forUi($purchaseOrder, []))->firstWhere('key', 'deposit_paid'))->toMatchArray(['not_required' => false, 'unpaid' => true])
        ->and($stages['in_production']['recorded'])->toBeTrue()
        ->and($stages['shipped']['reached'])->toBeFalse()
        ->and($stages['shipped']['deliveries'])->toBe(['reached' => 0, 'total' => $purchaseOrder->stockDeliveries()->count()]);

    actingAs($agent);
    $this->patch($route, ['field' => 'handed_over_at', 'date' => $today])->assertSessionHasNoErrors();
    actingAs($this->adminGuest->getUser());

    expect($purchaseOrder->refresh()->handed_over_at)->not->toBeNull()
        ->and($purchaseOrder->stageMarkUserId('handed_over_at'))->toBe($agent->id);

    $purchaseOrder->refresh()->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'deposit_paid_at' => null, 'production_started_at' => null, 'handed_over_at' => null]);
})->depends('create purchase order through an agent');

test('stage gates ask for the sample photo, production photo and QC report', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(5), 'deposit_paid_at' => now()->subDays(4), 'production_started_at' => now()->subDays(3)]);
    $stageRoute  = route('grp.models.purchase-order.stage', $purchaseOrder->id);
    $attachRoute = route('grp.models.purchase-order.attachment.attach', $purchaseOrder->id);
    $file        = fn (string $name) => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, '%PDF-1.4 '.$name.' '.Str::random(12));
    $today       = now($purchaseOrder->organisation->timezone?->name)->toDateString();

    $this->patch($stageRoute, ['field' => 'sample_approved_at', 'date' => $today])->assertSessionHasErrors('reason');
    $this->post($attachRoute, ['attachments' => [$file('sample.pdf')], 'scope' => 'Sample photo'])->assertSessionHasNoErrors();
    $this->patch($stageRoute, ['field' => 'sample_approved_at', 'date' => $today])->assertSessionHasNoErrors();

    $this->patch($stageRoute, ['field' => 'qc_passed_at', 'date' => $today])->assertSessionHasErrors('reason');
    $this->post($attachRoute, ['attachments' => [$file('line.pdf')], 'scope' => 'Production photo'])->assertSessionHasNoErrors();
    $this->patch($stageRoute, ['field' => 'qc_passed_at', 'date' => $today])->assertSessionHasErrors('reason');
    $this->post($attachRoute, ['attachments' => [$file('qc.pdf')], 'scope' => 'QC report'])->assertSessionHasNoErrors();
    $this->patch($stageRoute, ['field' => 'qc_passed_at', 'date' => $today])->assertSessionHasNoErrors();

    expect($purchaseOrder->refresh()->qc_passed_at)->not->toBeNull();

    $this->withoutVite();
    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stages.stages.2.evidence.0.count', 1)
            ->where('stages.stages.2.evidence.1.count', 1)
            ->where('stages.stages.3.evidence.0.count', 1)
            ->where('stages.stages.3.reached', true));
});

test('a delivery ships with its packing list and bill of lading, or with a reason', function () {
    $stockDelivery = createStockDeliveryWithItems($this, 'SHIP-DOCS-'.Str::random(6), [2]);
    $stockDelivery->updateQuietly(['data' => array_merge($stockDelivery->data ?? [], ['delivery_type' => 'container'])]);
    $route = route('grp.models.stock-delivery.dispatch', $stockDelivery->id);

    $this->patch($route)->assertSessionHasErrors('reason');

    $this->post(route('grp.models.stock-delivery.attachment.attach', $stockDelivery->id), [
        'attachments' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('packing.pdf', '%PDF-1.4 packing '.Str::random(12))],
        'scope'       => 'Packing list',
    ])->assertSessionHasNoErrors();
    expect(DispatchStockDelivery::missingEvidence($stockDelivery->refresh()))->toBe([__('Bill of lading')]);

    $this->patch($route)->assertSessionHasErrors('reason');
    $this->patch($route, ['reason' => 'The bill of lading comes by courier'])->assertSessionHasNoErrors();

    expect($stockDelivery->refresh()->state)->toBe(StockDeliveryStateEnum::DISPATCHED)
        ->and(Audit::where('auditable_type', 'StockDelivery')->where('auditable_id', $stockDelivery->id)->where('event', 'stage_override')->latest('id')->first()?->new_values['reason'])->toBe('The bill of lading comes by courier');
});

test('agents upload evidence, edit the shipping details of their containers and dispatch them', function (PurchaseOrder $purchaseOrder) {
    $agentUser     = User::where('username', 'agent-clerk')->firstOrFail();
    $stockDelivery = createStockDeliveryWithItems($this, 'AGENT-SHIP-'.Str::random(6), [3]);
    $stockDelivery->updateQuietly(['agent_id' => $purchaseOrder->agent_id]);
    $file          = fn (string $name) => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, '%PDF-1.4 '.$name.' '.Str::random(12));

    actingAs($agentUser);
    $this->post(route('grp.models.purchase-order.attachment.attach', $purchaseOrder->id), ['attachments' => [$file('sample.pdf')], 'scope' => 'Sample photo'])->assertSessionHasNoErrors();
    $sample = $purchaseOrder->attachments()->wherePivot('scope', 'Sample photo')->get()->last();
    $this->delete(route('grp.models.purchase-order.attachment.detach', [$purchaseOrder->id, $sample->id]))->assertForbidden();

    $this->patch(route('grp.models.stock-delivery.update', $stockDelivery->id), ['container_number' => 'MSKU1234567', 'tracking_number' => 'BL-998877'])->assertSessionHasNoErrors();
    $this->patch(route('grp.models.stock-delivery.update', $stockDelivery->id), ['container_number' => 'MSKU7654321', 'delivery_address' => 'Somewhere else'])->assertForbidden();
    $this->post(route('grp.models.stock-delivery.attachment.attach', $stockDelivery->id), ['attachments' => [$file('packing.pdf')], 'scope' => 'Packing list'])->assertSessionHasNoErrors();
    $this->patch(route('grp.models.stock-delivery.dispatch', $stockDelivery->id))->assertSessionHasErrors('invoice');
    actingAs($this->adminGuest->getUser());

    expect($stockDelivery->refresh()->data['container_number'])->toBe('MSKU1234567')
        ->and($stockDelivery->data['tracking_number'])->toBe('BL-998877')
        ->and(DispatchStockDelivery::missingEvidence($stockDelivery))->toBe([])
        ->and($purchaseOrder->attachments()->wherePivot('scope', 'Sample photo')->count())->toBeGreaterThan(0);
})->depends('create purchase order through an agent');

test('stages carry expected dates, late orders reach the weekly summary and agent changes reach the buyer', function (PurchaseOrder $purchaseOrder) {
    \Illuminate\Support\Facades\Notification::fake();
    $agentUser = User::where('username', 'agent-clerk')->firstOrFail();
    $admin     = $this->adminGuest->getUser();

    $purchaseOrder->updateQuietly([
        'state'                 => PurchaseOrderStateEnum::SUBMITTED,
        'created_at'            => now()->subDays(200),
        'submitted_at'          => now()->subDays(200),
        'deposit_paid_at'       => null,
        'production_started_at' => null,
        'buyer_id'              => $admin->id,
    ]);

    $expected = GetPurchaseOrderStages::make()->expectedDates($purchaseOrder->refresh());
    expect($expected)->toHaveKeys(['submitted', 'deposit_paid', 'shipped', 'received']);

    $stages = collect(GetPurchaseOrderStages::make()->forUi($purchaseOrder, []))->keyBy('key');
    expect($stages['deposit_paid']['days_late'])->toBeGreaterThan(0)
        ->and($stages['submitted']['days_late'])->toBe(0);

    $route = route('grp.models.purchase-order.stage', $purchaseOrder->id);
    $today = now($purchaseOrder->organisation->timezone?->name)->toDateString();

    $purchaseOrder->refresh()->updateQuietly(['deposit_paid_at' => now()]);
    $this->patch($route, ['field' => 'production_started_at', 'date' => $today])->assertSessionHasNoErrors();
    \Illuminate\Support\Facades\Notification::assertNothingSent();
    $purchaseOrder->refresh()->updateQuietly(['production_started_at' => null]);

    actingAs($agentUser);
    $this->patch($route, ['field' => 'production_started_at', 'date' => $today])->assertSessionHasNoErrors();
    actingAs($admin);

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $admin,
        \App\Notifications\PurchaseOrderStageChangedByAgentNotification::class,
        fn ($notification) => $notification->purchaseOrder->is($purchaseOrder) && str_contains($notification->change, __('Production started')) && $notification->byEmail === (bool) $admin->email
    );

    $settings = $admin->settings;
    $admin->update(['settings' => array_merge($settings ?? [], ['notifications' => ['purchase_order_stage_by_agent' => []]])]);
    actingAs($agentUser);
    $this->patch($route, ['field' => 'production_started_at', 'date' => null, 'reason' => 'Marked on the wrong order'])->assertSessionHasNoErrors();
    actingAs($admin);
    \Illuminate\Support\Facades\Notification::assertSentTo(
        $admin,
        \App\Notifications\PurchaseOrderStageChangedByAgentNotification::class,
        fn ($notification) => str_contains($notification->change, __('cleared')) && $notification->byEmail === false && $notification->via($admin) === ['database']
    );
    $admin->update(['settings' => $settings]);

    $purchaseOrder->refresh()->updateQuietly(['buyer_id' => $agentUser->id, 'deposit_paid_at' => now()->subDays(190), 'production_started_at' => null, 'produced_at' => null, 'qc_passed_at' => null, 'handed_over_at' => null]);
    \App\Actions\Procurement\PurchaseOrder\SendStuckPurchaseOrdersSummary::run($purchaseOrder->group);

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $agentUser,
        \App\Notifications\StuckPurchaseOrdersSummaryNotification::class,
        fn ($notification) => $notification->ownOrdersOnly && collect($notification->orders)->contains('reference', $purchaseOrder->reference)
    );

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'created_at' => now(), 'submitted_at' => null, 'buyer_id' => null, 'deposit_paid_at' => null]);
})->depends('create purchase order through an agent');

test('a purchase order lists the compliance documents of its products, due by the arrival estimate less a buffer', function (PurchaseOrder $purchaseOrder) {
    \Illuminate\Support\Facades\Notification::fake();
    $agentUser = User::where('username', 'agent-clerk')->firstOrFail();
    $orgStock  = $purchaseOrder->purchaseOrderTransactions()->firstOrFail()->orgStock;
    $tradeUnit = $orgStock->tradeUnits()->first() ?? tap(\App\Models\Goods\TradeUnit::orderBy('id')->firstOrFail(), fn ($tradeUnit) => $orgStock->tradeUnits()->attach($tradeUnit->id, ['quantity' => 1]));
    $other     = \App\Models\Goods\TradeUnit::whereNotIn('id', GetPurchaseOrderCompliance::make()->tradeUnits($purchaseOrder)->pluck('id'))->firstOrFail();
    $eta       = now()->addDays(17)->toDateString();
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::CONFIRMED, 'buyer_id' => $agentUser->id, 'estimated_received_at' => $eta, 'data' => array_merge($purchaseOrder->data ?? [], ['estimated_receiving_date' => $eta])]);
    $before    = collect(GetPurchaseOrderCompliance::make()->documents($tradeUnit->refresh())['documents'])->firstWhere('scope', 'cpsr')['count'];
    $file      = fn (string $name) => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, '%PDF-1.4 '.$name.' '.Str::random(12));

    actingAs($agentUser);
    $this->post(route('grp.models.purchase-order.compliance.attach', [$purchaseOrder->id, $tradeUnit->id]), ['attachments' => [$file('cpsr.pdf')], 'scope' => 'cpsr'])->assertSessionHasNoErrors();
    $this->post(route('grp.models.purchase-order.compliance.attach', [$purchaseOrder->id, $other->id]), ['attachments' => [$file('cpsr.pdf')], 'scope' => 'cpsr'])->assertSessionHasErrors('scope');
    $this->post(route('grp.models.purchase-order.compliance.attach', [$purchaseOrder->id, $tradeUnit->id]), ['attachments' => [$file('photo.pdf')], 'scope' => 'other'])->assertSessionHasErrors('scope');
    actingAs($this->adminGuest->getUser());

    $compliance = GetPurchaseOrderCompliance::run($purchaseOrder->refresh());
    $product    = collect($compliance['products'])->firstWhere('trade_unit_id', $tradeUnit->id);
    expect($compliance['eta'])->toBe($eta)
        ->and($compliance['deadline'])->toBe(now()->addDays(7)->toDateString())
        ->and($compliance['missing'])->toBe(0)
        ->and(collect($product['documents'])->firstWhere('scope', 'cpsr')['count'])->toBe($before + 1);

    expect(\App\Actions\Procurement\PurchaseOrder\SendComplianceDocumentsReminders::run())->toBe(0);
    \Illuminate\Support\Facades\Notification::assertNothingSent();

    $this->mock(GetPurchaseOrderCompliance::class)->makePartial()
        ->shouldReceive('requiredDocuments')->andReturn([\App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum::TEST_REPORTS])
        ->shouldReceive('supplierOnlyDocuments')->andReturn([\App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum::TEST_REPORTS]);

    expect(\App\Actions\Procurement\PurchaseOrder\SendComplianceDocumentsReminders::make()->remind($purchaseOrder))->toBeTrue()
        ->and(GetPurchaseOrderStages::make()->skippedBy($purchaseOrder, 'qc_passed_at', GetPurchaseOrderStages::run($purchaseOrder)))->toContain(__(':document for :code', ['document' => __('Test Reports'), 'code' => $tradeUnit->code]));
    \Illuminate\Support\Facades\Notification::assertSentTo($agentUser, \App\Notifications\ComplianceDocumentsReminderNotification::class);

    $this->withoutVite();
    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]).'?tab=compliance')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('compliance_summary.deadline', now()->addDays(7)->toDateString())
            ->where('compliance.data', fn ($rows) => collect($rows)->contains('id', $tradeUnit->id)));

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS, 'buyer_id' => null]);
})->depends('create purchase order through an agent');

test('an order is stamped online once all its products are placed and live, and keeps that date', function (PurchaseOrder $purchaseOrder) {
    $purchaseOrder->updateQuietly(['online_at' => null]);
    $onlineDay = now()->subDays(3)->toDateString();
    $ribbon    = fn (string $state, ?string $doneAt) => ['id' => $purchaseOrder->id, 'segments' => [['key' => 'products_online', 'state' => $state, 'done_at' => $doneAt]]];

    $this->mock(\App\Actions\SupplyChain\UI\ShowSupplyChainPurchaseOrderJourney::class)
        ->shouldReceive('ribbons')->andReturn(collect([$ribbon('future', null)]), collect([$ribbon('done', $onlineDay)]), collect([$ribbon('done', now()->toDateString())]));

    expect(\App\Actions\Procurement\PurchaseOrder\StampPurchaseOrdersOnline::run($purchaseOrder->group))->toBe(0)
        ->and($purchaseOrder->refresh()->online_at)->toBeNull()
        ->and(\App\Actions\Procurement\PurchaseOrder\StampPurchaseOrdersOnline::run($purchaseOrder->group))->toBe(1)
        ->and($purchaseOrder->refresh()->online_at->toDateString())->toBe($onlineDay)
        ->and(\App\Actions\Procurement\PurchaseOrder\StampPurchaseOrdersOnline::run($purchaseOrder->group))->toBe(0)
        ->and($purchaseOrder->refresh()->online_at->toDateString())->toBe($onlineDay);

    $received = collect(GetPurchaseOrderStages::make()->forUi($purchaseOrder, []))->firstWhere('key', 'received');
    expect(collect($received['steps'])->firstWhere('key', 'online_at')['date']->toDateString())->toBe($onlineDay);

    $purchaseOrder->updateQuietly(['online_at' => null]);
})->depends('create purchase order through an agent');

test('aurora purchase order buyers are filled from the Aurora main buyer', function (PurchaseOrder $purchaseOrder) {
    $employee = StoreEmployee::make()->action($this->organisation, [
        'worker_number'   => 'po-buyer',
        'alias'           => 'po-buyer',
        'contact_name'    => 'Po Buyer',
        'state'           => EmployeeStateEnum::WORKING,
        'type'            => EmployeeTypeEnum::EMPLOYEE,
        'employment_type' => EmploymentTypeEnum::FULL_TIME,
    ]);
    $employee->updateQuietly(['source_id' => $this->organisation->id.':4242']);
    $buyer = StoreUser::make()->action($employee, [
        'username'       => 'po-buyer',
        'password'       => Str::random(32),
        'status'         => true,
        'reset_password' => false,
    ]);
    $director = $this->adminGuest->getUser();

    $purchaseOrder->updateQuietly(['buyer_id' => null, 'source_id' => $this->organisation->id.':9001']);

    $repairFor = fn (int $staffKey) => new class ($staffKey, $director->username) extends RepairAuroraPurchaseOrderBuyers {
        public function __construct(private readonly int $staffKey, private readonly string $directorUsername)
        {
        }

        protected function auroraBuyers(Organisation $organisation): array
        {
            return ['9001' => ['staff_key' => $this->staffKey, 'name' => 'Someone']];
        }

        protected function auroraUserHandles(Organisation $organisation): array
        {
            return [77 => strtoupper($this->directorUsername)];
        }
    };

    expect($repairFor(4242)->handle($this->organisation, dryRun: true)['filled'])->toBe(1)
        ->and($purchaseOrder->fresh()->buyer_id)->toBeNull();

    $repairFor(4242)->handle($this->organisation);
    expect($purchaseOrder->fresh()->buyer_id)->toBe($buyer->id);

    PurchaseOrder::whereKey($purchaseOrder->id)->update(['buyer_id' => null]);
    $repairFor(77)->handle($this->organisation);
    expect($purchaseOrder->fresh()->buyer_id)->toBe($director->id);
})->depends('create purchase order independent supplier');

test('update agent purchase order delivery estimate and deposit', function (PurchaseOrder $purchaseOrder) {
    $updated = UpdatePurchaseOrder::make()->action($purchaseOrder->refresh(), [
        'deposit_amount'          => 100.50,
        'deposit_paid_at'         => now()->subDay()->toDateString(),
        'estimated_delivery_days' => 14,
    ]);

    expect((float) $updated->deposit_amount)->toBe(100.50)
        ->and($updated->deposit_paid_at)->not->toBeNull()
        ->and($updated->estimated_delivery_days)->toBe(14);
})->depends('create purchase order through an agent');

test('clean handover score', function () {
    $orgAgent      = createFreshOrgAgent($this);
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $agent         = $orgAgent->agent;
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 10])
    );
    $purchaseOrder = $purchaseOrder->refresh();

    $updated = UpdatePurchaseOrder::make()->action(
        $purchaseOrder,
        [
            'proposed_ready_at'      => now()->subDays(12),
            'approved_ready_at'      => now()->subDays(10),
            'handed_over_at'         => now()->subDays(5),
            'qc_passed_at'           => now()->subDays(6),
            'compliance_complete_at' => now()->subDays(6),
        ]
    );

    $calculator = GetAgentCleanHandoverScore::make();
    $quarterKey = now()->subDays(10)->year.'-Q'.now()->subDays(10)->quarter;

    expect($calculator->isCleanHandover($updated))->toBeTrue();

    $score        = $calculator->handle($agent);
    $currentScore = collect($score['quarters'])->firstWhere('quarter', $quarterKey);

    expect($currentScore['number_pos'])->toBe(1)
        ->and($currentScore['number_clean'])->toBe(1)
        ->and($currentScore['chs'])->toEqualWithDelta(100.0, 0.01)
        ->and($currentScore['commission_rate'])->toEqualWithDelta(3.0, 0.01)
        ->and($score['hygiene']['avg_ready_date_padding_days'])->toEqualWithDelta(2.0, 0.1)
        ->and($score['hygiene']['handed_over_missing_checks'])->toBe(0);

    $updated = UpdatePurchaseOrder::make()->action(
        $updated,
        [
            'handed_over_at' => now()->subDays(1),
            'qc_passed_at'   => null,
        ]
    );

    expect($calculator->isCleanHandover($updated))->toBeFalse();

    $score        = $calculator->handle($agent);
    $currentScore = collect($score['quarters'])->firstWhere('quarter', $quarterKey);

    expect($currentScore['number_clean'])->toBe(0)
        ->and($currentScore['chs'])->toEqualWithDelta(0.0, 0.01)
        ->and($currentScore['commission_rate'])->toEqualWithDelta(2.0, 0.01)
        ->and($score['hygiene']['handed_over_missing_checks'])->toBe(1);

    UpdatePurchaseOrder::make()->action(
        $updated,
        [
            'chs_excluded'         => true,
            'chs_exclusion_reason' => 'AW-approved scope change',
        ]
    );

    $score        = $calculator->handle($agent);
    $currentScore = collect($score['quarters'])->firstWhere('quarter', $quarterKey);

    expect($currentScore['number_pos'])->toBe(0)
        ->and($currentScore['chs'])->toBeNull()
        ->and($score['hygiene']['exclusion_rate'])->toEqualWithDelta(100.0, 0.01);
});

test('UI agent organisation dashboard shows clean handover score', function () {
    $this->withoutExceptionHandling();

    $this->get(route('grp.org.dashboard.show', [$this->agent->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/OrganisationDashboard')
            ->has('cleanHandover.quarters')
            ->has('cleanHandover.hygiene')
            ->where('isAgentOrganisation', true)
            ->missing('agentPurchaseOrders')
            ->loadDeferredProps('default', fn (AssertableInertia $reload) => $reload
                ->has('agentPurchaseOrders.currency')
                ->has('agentPurchaseOrders.summary.unconfirmed')
                ->has('agentPurchaseOrders.summary.overdue')
                ->has('agentPurchaseOrders.summary.confirmed_without_container')
                ->has('agentPurchaseOrders.summary.routes.unconfirmed.name')
                ->has('agentPurchaseOrders.attention')
                ->has('agentPurchaseOrders.suppliers')
                ->has('agentPurchaseOrders.organisations')
                ->has('agentPurchaseOrders.containers', 4)));

    $this->get(route('grp.org.dashboard.show', [$this->organisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard/OrganisationDashboard')
            ->where('cleanHandover', null)
            ->where('isAgentOrganisation', false)
            ->where('agentPurchaseOrders', null));
});

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

test('an agent purchase order line keeps its amounts in the organisation and group currency when its net amount changes', function (PurchaseOrder $purchaseOrder) {
    $purchaseOrderTransaction = $purchaseOrder->purchaseOrderTransactions()->first();
    expect($purchaseOrderTransaction)->not->toBeNull();

    UpdatePurchaseOrderTransaction::make()->action(
        $purchaseOrderTransaction,
        ['net_amount' => 360],
        strict: false
    );

    $purchaseOrderTransaction->refresh();
    expect((float)$purchaseOrderTransaction->net_amount)->toBe(360.0)
        ->and((float)$purchaseOrderTransaction->org_net_amount)->toBe(360.0 * ($purchaseOrderTransaction->org_exchange ?? 1))
        ->and((float)$purchaseOrderTransaction->grp_net_amount)->toBe(360.0 * ($purchaseOrderTransaction->grp_exchange ?? 1));
})->depends('add item to agent purchase order');

test('a delivery date typed on a purchase order is the date everywhere', function (PurchaseOrder $purchaseOrder) {
    $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder->refresh(), ['estimated_receiving_date' => '2027-03-15']);

    expect($purchaseOrder->estimated_received_at->toDateString())->toBe('2027-03-15')
        ->and($purchaseOrder->estimatedReceivingDate())->toBe('2027-03-15');

    UpdatePurchaseOrder::make()->action($purchaseOrder->refresh(), ['estimated_receiving_date' => null]);

    expect($purchaseOrder->refresh()->estimated_received_at)->toBeNull()
        ->and($purchaseOrder->estimatedReceivingDate())->toBeNull();
})->depends('add item to agent purchase order');

test('UI agent supplier links stay inside the agent', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $orgAgent      = $orgSupplier->orgAgent;
    $parameters    = [$orgAgent->organisation->slug, $orgAgent->slug, $orgSupplier->slug];

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.procurement.org_agents.show.suppliers.show', $parameters))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.subNavigation.0.route.name', 'grp.org.procurement.org_agents.show.suppliers.show')
            ->where('pageHead.subNavigation.1.route.name', 'grp.org.procurement.org_agents.show.suppliers.show.supplier_products.index')
            ->where('pageHead.subNavigation.1.route.parameters', $parameters)
            ->where('pageHead.subNavigation.2.route.name', 'grp.org.procurement.org_agents.show.suppliers.show.purchase_orders.index')
            ->where('pageHead.subNavigation.3.route.name', 'grp.org.procurement.org_agents.show.suppliers.show.stock_deliveries.index')
            ->where('showcase.stats.0.route.name', 'grp.org.procurement.org_agents.show.suppliers.show.supplier_products.index')
            ->where('showcase.stats.1.route.name', 'grp.org.procurement.org_agents.show.suppliers.show.purchase_orders.index')
            ->where('showcase.stats.2.route.name', 'grp.org.procurement.org_agents.show.suppliers.show.stock_deliveries.index')
            ->where('showcase.stats.2.route.parameters', $parameters)
            ->etc());

    foreach (['supplier_products', 'purchase_orders', 'stock_deliveries'] as $page) {
        $props = $this->get(route("grp.org.procurement.org_agents.show.suppliers.show.$page.index", $parameters))
            ->assertOk()
            ->viewData('page')['props'];

        expect(json_encode($props['breadcrumbs']))->toContain('grp.org.procurement.org_agents.show.suppliers.index')
            ->and($props['pageHead']['subNavigation'][1]['route']['name'])->toBe('grp.org.procurement.org_agents.show.suppliers.show.supplier_products.index');
    }
});

test('UI index org agent purchase orders lists the orders through the agent', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $orgAgent      = $orgSupplier->orgAgent;
    $reference     = 'AGTIDX'.Str::upper(Str::random(8));
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, array_merge(PurchaseOrder::factory()->definition(), ['reference' => $reference]));

    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.org_agents.show.purchase-orders.index', [$orgAgent->organisation->slug, $orgAgent->slug]).'?filter[global]='.$reference);

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/PurchaseOrders')
        ->has('data'));

    $data = $response->viewData('page')['props']['data'];
    $row  = collect($data['data'] ?? $data)->firstWhere('slug', $purchaseOrder->slug);

    expect($row)->not->toBeNull()
        ->and($row['parent_type'])->toBe('OrgSupplier')
        ->and($row['agent_slug'])->toBe($orgAgent->agent->slug)
        ->and($row['org_agent_slug'])->toBe($orgAgent->slug);
});

test('agent suppliers dashboard counts open orders, the orders to chase and their stage, for one buying company', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $agent         = $orgSupplier->orgAgent->agent;
    $buyer         = $orgSupplier->organisation;
    $dashboard     = fn (?Organisation $buyer = null) => GetAgentSuppliersDashboard::run($agent, $agent->organisation, $buyer);
    $stage         = fn (array $dashboard, string $key) => collect($dashboard['stages'])->firstWhere('key', $key)['value'];
    $otherBuyer    = OrgAgent::where('agent_id', $agent->id)->where('organisation_id', '!=', $buyer->id)->first()?->organisation;
    $before        = $dashboard($buyer);
    $beforeAll     = $dashboard();
    $beforeOther   = $otherBuyer ? $dashboard($otherBuyer) : null;

    $order = fn (array $data) => tap(StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition()))->update($data);
    $order(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(20), 'estimated_received_at' => null]);
    $order(['state' => PurchaseOrderStateEnum::CONFIRMED, 'submitted_at' => now()->subDays(60), 'estimated_received_at' => now()->subDays(3)]);
    $order(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(2), 'estimated_received_at' => now()->addDays(30)]);
    $order(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(25), 'estimated_received_at' => now()->addDays(30), 'handed_over_at' => now()->subDay()]);

    $after = $dashboard($buyer);

    expect($after['kpis']['open']['value'] - $before['kpis']['open']['value'])->toBe(4)
        ->and($after['kpis']['open']['without_eta'] - $before['kpis']['open']['without_eta'])->toBe(1)
        ->and($after['kpis']['chase']['past_eta'] - $before['kpis']['chase']['past_eta'])->toBe(1)
        ->and($after['kpis']['chase']['unconfirmed'] - $before['kpis']['chase']['unconfirmed'])->toBe(2)
        ->and($stage($after, 'submitted') - $stage($before, 'submitted'))->toBe(2)
        ->and($stage($after, 'confirmed') - $stage($before, 'confirmed'))->toBe(1)
        ->and($stage($after, 'at_factory') - $stage($before, 'at_factory'))->toBe(1)
        ->and($dashboard()['kpis']['open']['value'] - $beforeAll['kpis']['open']['value'])->toBe(4)
        ->and($after['filters']['currencies']->first())->toBe($agent->organisation->group->currency->code)
        ->and($after['filters']['buyer'])->toBe($buyer->slug);

    if ($otherBuyer) {
        expect($dashboard($otherBuyer)['kpis']['open']['value'])->toBe($beforeOther['kpis']['open']['value']);
    }
});

test('a chase with a later promised date moves the expected date, an earlier one leaves it', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = tap(StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition()))
        ->update(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()->subDays(40), 'estimated_received_at' => now()->addDays(20)]);

    StorePurchaseOrderChase::make()->action($purchaseOrder, ['channel' => 'phone', 'promised_at' => now()->addDays(5)->toDateString()]);
    expect($purchaseOrder->refresh()->estimated_received_at->toDateString())->toBe(now()->addDays(20)->toDateString());

    StorePurchaseOrderChase::make()->action($purchaseOrder, ['channel' => 'email', 'promised_at' => now()->addDays(30)->toDateString(), 'note' => 'Factory holiday']);
    expect($purchaseOrder->refresh()->estimated_received_at->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($purchaseOrder->chases()->count())->toBe(2);

    $purchaseOrder->update(['state' => PurchaseOrderStateEnum::SETTLED]);
    expect(fn () => StorePurchaseOrderChase::make()->action($purchaseOrder, ['channel' => 'phone']))->toThrow(ValidationException::class);
});

test('directors approve deactivating a dormant supplier or keep it with a reason, one suggestion at a time', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $supplier      = $orgSupplier->supplier;

    $request = StoreSupplierChangeRequest::make()->action($supplier, ['type' => 'deactivate', 'reason' => 'Nothing ordered in three years']);
    expect(fn () => StoreSupplierChangeRequest::make()->action($supplier, ['type' => 'deactivate', 'reason' => 'Again']))->toThrow(ValidationException::class)
        ->and(fn () => DecideSupplierChangeRequest::make()->action($request, ['approve' => false]))->toThrow(ValidationException::class);

    DecideSupplierChangeRequest::make()->action($request, ['approve' => false, 'decision_note' => 'Seasonal supplier']);
    expect($request->refresh()->state)->toBe(SupplierChangeRequestStateEnum::REJECTED)
        ->and($supplier->refresh()->status)->toBeTrue();

    $open = tap(StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition()))->update(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $second = StoreSupplierChangeRequest::make()->action($supplier, ['type' => 'deactivate', 'reason' => 'Really gone']);
    expect(fn () => DecideSupplierChangeRequest::make()->action($second, ['approve' => true]))->toThrow(ValidationException::class)
        ->and($second->refresh()->state)->toBe(SupplierChangeRequestStateEnum::PENDING);

    $open->update(['state' => PurchaseOrderStateEnum::CANCELLED]);
    DecideSupplierChangeRequest::make()->action($second, ['approve' => true]);
    expect($second->refresh()->state)->toBe(SupplierChangeRequestStateEnum::APPROVED)
        ->and($supplier->refresh()->status)->toBeFalse()
        ->and($supplier->archived_at)->not->toBeNull()
        ->and($orgSupplier->refresh()->status)->toBeFalse();
});

test('agent suppliers dashboard counts logged issues by type and the packing of suppliers ordered from', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    [$packagingOrgSupplier]             = createAgentOrgSupplierWithProduct($this);
    $agent     = $orgSupplier->orgAgent->agent;
    $supplier  = $orgSupplier->supplier;
    $dashboard = fn () => GetAgentSuppliersDashboard::run($agent, $agent->organisation);
    $before    = $dashboard();

    tap(StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition()))
        ->update(['state' => PurchaseOrderStateEnum::SETTLED, 'submitted_at' => now()->subMonths(2)]);
    StoreProcurementNote::make()->action($supplier, ['body' => 'Broken on arrival', 'category' => 'incident', 'incident_type' => 'damaged', 'incident_quantity' => 12]);
    StoreProcurementNote::make()->action($orgSupplierProduct->supplierProduct, ['body' => 'Wrong colour', 'category' => 'incident', 'incident_type' => 'wrong_item']);

    expect(fn () => UpdateSupplier::make()->action($supplier, ['packing_method' => 'third_party']))->toThrow(ValidationException::class);
    UpdateSupplier::make()->action($supplier, ['packing_method' => 'third_party', 'packing_material' => 'pap_20', 'packaging_supplier_id' => $packagingOrgSupplier->supplier_id]);

    $after = $dashboard();
    expect($after['kpis']['issues']['value'] - $before['kpis']['issues']['value'])->toBe(2)
        ->and($after['kpis']['issues']['open'] - $before['kpis']['issues']['open'])->toBe(2)
        ->and($after['packing']['split']['third_party'] - $before['packing']['split']['third_party'])->toBe(1)
        ->and(collect($after['packing']['packaging'])->pluck('code'))->toContain($packagingOrgSupplier->supplier->code)
        ->and(collect($after['packing']['unknown'])->pluck('id'))->not->toContain($supplier->id)
        ->and($supplier->refresh()->packing_method)->toBe(SupplierPackingMethodEnum::THIRD_PARTY)
        ->and(ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $supplier->id)->first()->incident_type)->toBe(ProcurementNoteIncidentTypeEnum::DAMAGED);
});

test('supplier score weighs on time, accuracy with logged issues and factory speed, from the minimum of received orders', function () {
    $dashboard = GetAgentSuppliersDashboard::make();

    expect($dashboard->score(received: 7, onTime: 100, linesChecked: 100, linesWrong: 0, accuracyIssues: 0, daysOver: 0))->toBeNull()
        ->and($dashboard->score(received: 10, onTime: 100, linesChecked: 100, linesWrong: 0, accuracyIssues: 0, daysOver: 0))->toBe(100)
        ->and($dashboard->score(received: 10, onTime: 80, linesChecked: 100, linesWrong: 1, accuracyIssues: 1, daysOver: 4))->toBe(81)
        ->and($dashboard->score(received: 10, onTime: 60, linesChecked: 0, linesWrong: 0, accuracyIssues: 5, daysOver: 30))->toBe(30)
        ->and($dashboard->medianFactoryDays(collect([
            (object) ['confirmed_at' => '2026-01-01', 'handed_over_at' => '2026-01-21'],
            (object) ['confirmed_at' => '2026-02-01', 'handed_over_at' => '2026-03-03'],
            (object) ['confirmed_at' => '2026-03-01', 'handed_over_at' => null],
        ])))->toBe(25);
});

test('approving a deactivation closes the other suggestions on that supplier, and nothing merges into an archived one', function () {
    [$orgSupplier]      = createAgentOrgSupplierWithProduct($this);
    [$otherOrgSupplier] = createAgentOrgSupplierWithProduct($this);
    $supplier = $orgSupplier->supplier;
    $other    = $otherOrgSupplier->supplier;
    $other->update(['currency_id' => $supplier->currency_id]);

    $merge        = StoreSupplierChangeRequest::make()->action($other, ['type' => 'merge', 'target_supplier_id' => $supplier->id, 'reason' => 'Same factory']);
    $deactivation = StoreSupplierChangeRequest::make()->action($supplier, ['type' => 'deactivate', 'reason' => 'Closed down']);
    DecideSupplierChangeRequest::make()->action($deactivation, ['approve' => true]);

    expect($merge->refresh()->state)->toBe(SupplierChangeRequestStateEnum::REJECTED)
        ->and($merge->decision_note)->toContain($supplier->code)
        ->and(fn () => DecideSupplierChangeRequest::make()->action($merge, ['approve' => true]))->toThrow(ValidationException::class);

    $late = SupplierChangeRequest::create(['group_id' => $other->group_id, 'agent_id' => $other->agent_id, 'supplier_id' => $other->id, 'target_supplier_id' => $supplier->id, 'type' => 'merge', 'state' => 'pending', 'reason' => 'Raised before the deactivation']);
    expect(fn () => DecideSupplierChangeRequest::make()->action($late, ['approve' => true]))->toThrow(ValidationException::class)
        ->and($other->refresh()->status)->toBeTrue();
});

test('merging a duplicate supplier moves its products and orders to the supplier kept and archives it', function () {
    [$keptOrgSupplier, $keptProduct]           = createAgentOrgSupplierWithProduct($this);
    [$duplicateOrgSupplier, $duplicateProduct] = createAgentOrgSupplierWithProduct($this);
    $kept      = $keptOrgSupplier->supplier;
    $duplicate = $duplicateOrgSupplier->supplier;
    $duplicate->update(['currency_id' => $kept->currency_id]);
    $order = StorePurchaseOrder::make()->action($duplicateOrgSupplier, PurchaseOrder::factory()->definition());

    $request = StoreSupplierChangeRequest::make()->action($duplicate, ['type' => 'merge', 'target_supplier_id' => $kept->id, 'reason' => 'Same factory entered twice']);
    DecideSupplierChangeRequest::make()->action($request, ['approve' => true]);

    expect($request->refresh()->state)->toBe(SupplierChangeRequestStateEnum::APPROVED)
        ->and($duplicateProduct->supplierProduct->refresh()->supplier_id)->toBe($kept->id)
        ->and($duplicateProduct->refresh()->org_supplier_id)->toBe($keptOrgSupplier->id)
        ->and($order->refresh()->supplier_id)->toBe($kept->id)
        ->and($order->parent_id)->toBe($keptOrgSupplier->id)
        ->and($order->parent_name)->toBe($kept->name)
        ->and($duplicate->refresh()->status)->toBeFalse()
        ->and($duplicate->data['merged_into_supplier_id'])->toBe($kept->id)
        ->and($duplicateOrgSupplier->refresh()->status)->toBeFalse()
        ->and($kept->refresh()->stats->number_current_supplier_products)->toBe(2);

    [$otherOrgSupplier] = createAgentOrgSupplierWithProduct($this);
    $other              = $otherOrgSupplier->supplier;
    $other->update(['currency_id' => $kept->currency_id]);
    $other->supplierProducts()->first()->update(['code' => $keptProduct->supplierProduct->code]);
    $clash = StoreSupplierChangeRequest::make()->action($other, ['type' => 'merge', 'target_supplier_id' => $kept->id, 'reason' => 'Same factory']);
    expect(fn () => DecideSupplierChangeRequest::make()->action($clash, ['approve' => true]))->toThrow(ValidationException::class)
        ->and($other->refresh()->status)->toBeTrue();
});

test('merging a duplicate supplier folds its empty supplier order on a shared agent order into the kept one', function () {
    [$keptOrgSupplier]                         = createAgentOrgSupplierWithProduct($this);
    [$duplicateOrgSupplier, $duplicateProduct] = createAgentOrgSupplierWithProduct($this);
    $kept      = $keptOrgSupplier->supplier;
    $duplicate = $duplicateOrgSupplier->supplier;
    $duplicate->update(['currency_id' => $kept->currency_id]);

    $order    = StorePurchaseOrder::make()->action($keptOrgSupplier, PurchaseOrder::factory()->definition());
    $line     = StorePurchaseOrderTransaction::make()->action($order, $duplicateProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());
    $newAspo  = fn (Supplier $supplier, array $extra = []) => AgentSupplierPurchaseOrder::create(array_merge([
        'group_id'       => $this->group->id,
        'reference'      => 'ASPO'.Str::upper(Str::random(8)),
        'purchase_order_id' => $order->id,
        'supplier_id'    => $supplier->id,
        'currency_id'    => $order->currency_id,
        'state'          => AgentSupplierPurchaseOrderStateEnum::SUBMITTED,
        'delivery_state' => AgentSupplierPurchaseOrderDeliveryStateEnum::CONFIRMED,
        'date'           => now(),
    ], $extra));
    $keptAspo      = $newAspo($kept);
    $duplicateAspo = $newAspo($duplicate, ['estimated_received_at' => '2026-11-01 00:00:00']);
    PurchaseOrderTransaction::whereKey($line->id)->update(['agent_supplier_purchase_order_id' => $duplicateAspo->id]);

    $duplicateAspo->update(['is_costed' => true]);
    expect(fn () => MergeSuppliers::run($duplicate, $kept))->toThrow(ValidationException::class);

    $duplicateAspo->update(['is_costed' => false]);
    MergeSuppliers::run($duplicate, $kept);

    expect(AgentSupplierPurchaseOrder::find($duplicateAspo->id))->toBeNull()
        ->and($line->refresh()->agent_supplier_purchase_order_id)->toBe($keptAspo->id)
        ->and($keptAspo->refresh()->estimated_received_at->toDateString())->toBe('2026-11-01')
        ->and($duplicate->refresh()->data['merged_into_supplier_id'])->toBe($kept->id);
});

test('UI show agent purchase order', function (PurchaseOrder $purchaseOrder) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Procurement/PurchaseOrder')
        ->has('title')
        ->has('breadcrumbs')
        ->has('deposits.list')
        ->has('box_stats.first_block.clean_handover')
        ->where('box_stats.first_block.orderer.via_agent.slug', $purchaseOrder->parent->orgAgent->slug)
        ->where('routes.updatePurchaseOrderRoute.name', 'grp.models.purchase-order.update'));
})->depends('add item to agent purchase order');

test('UI edit agent purchase order has the clean handover section', function (PurchaseOrder $purchaseOrder) {
    $this->withoutExceptionHandling();

    $props = json_encode($this->get(route('grp.org.procurement.purchase_orders.edit', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))->viewData('page')['props']);

    expect($props)->toContain('proposed_ready_at');
})->depends('add item to agent purchase order');

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

test('all supplier products list shows the other open purchase orders each product is on', function (PurchaseOrder $purchaseOrder) {
    /** @var OrgSupplier $orgSupplier */
    $orgSupplier = $purchaseOrder->parent;
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);

    $newPurchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    $rows = collect(IndexPurchaseOrderOrgSupplierProducts::make()->handle($orgSupplier, $newPurchaseOrder)->items())->keyBy('id');

    $orgSupplierProductId = $purchaseOrder->purchaseOrderTransactions()->first()->org_supplier_product_id;
    expect($rows->get($orgSupplierProductId)->other_open_purchase_orders->pluck('reference')->all())->toBe([$purchaseOrder->reference]);

    $ownRows = collect(IndexPurchaseOrderOrgSupplierProducts::make()->handle($orgSupplier, $purchaseOrder)->items())->keyBy('id');
    expect($ownRows->get($orgSupplierProductId)->other_open_purchase_orders)->toBeEmpty();

    $transaction       = $purchaseOrder->purchaseOrderTransactions()->first();
    $supplierProductId = $transaction->supplier_product_id;
    $stockHasSupplierProductId = DB::table('stock_has_supplier_products')->insertGetId([
        'stock_id'            => DB::table('org_stocks')->where('id', $transaction->org_stock_id)->value('stock_id'),
        'supplier_product_id' => $supplierProductId,
    ]);
    $transaction->updateQuietly(['org_supplier_product_id' => null, 'supplier_product_id' => null]);
    $rowMatchedByOrgStock = collect(IndexPurchaseOrderOrgSupplierProducts::make()->handle($orgSupplier, $newPurchaseOrder)->items())->keyBy('id')->get($orgSupplierProductId);
    $transaction->updateQuietly(['org_supplier_product_id' => $orgSupplierProductId, 'supplier_product_id' => $supplierProductId]);
    DB::table('stock_has_supplier_products')->delete($stockHasSupplierProductId);
    $newPurchaseOrder->forceDelete();
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS]);

    expect($rowMatchedByOrgStock->org_stock_id)->toBe($transaction->org_stock_id)
        ->and($rowMatchedByOrgStock->other_open_purchase_orders->pluck('reference')->all())->toContain($purchaseOrder->reference);
})->depends('add more items to purchase order');

test('add items search finds a supplier product by its SKO code', function (PurchaseOrder $purchaseOrder) {
    /** @var OrgSupplier $orgSupplier */
    $orgSupplier = $purchaseOrder->parent;
    $transaction = $purchaseOrder->purchaseOrderTransactions()->first();
    $orgStock    = OrgStock::find($transaction->org_stock_id);
    $linkId      = DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $transaction->supplier_product_id]);

    $search = fn (string $value) => collect(IndexPurchaseOrderOrgSupplierProducts::make()->handle($orgSupplier, $purchaseOrder)->items())->pluck('id');

    request()->merge(['filter' => ['global' => $orgStock->code]]);
    $foundBySkoCode = $search($orgStock->code);
    request()->merge(['filter' => ['global' => 'zz-no-such-code']]);
    $foundByNonsense = $search('zz-no-such-code');
    request()->offsetUnset('filter');
    DB::table('stock_has_supplier_products')->delete($linkId);

    expect($foundBySkoCode)->toContain($transaction->org_supplier_product_id)
        ->and($foundByNonsense)->toBeEmpty();
})->depends('add more items to purchase order');

test('all supplier products list leaves out products whose SKO is discontinuing or discontinued or the supplier does not have, unless already ordered', function (PurchaseOrder $purchaseOrder) {
    /** @var OrgSupplier $orgSupplier */
    $orgSupplier = $purchaseOrder->parent;
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $newPurchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $listed           = fn (PurchaseOrder $order) => collect(IndexPurchaseOrderOrgSupplierProducts::make()->handle($orgSupplier, $order)->items())->pluck('id');

    $transaction          = $purchaseOrder->purchaseOrderTransactions()->first();
    $orgSupplierProductId = $transaction->org_supplier_product_id;
    $orgStock             = OrgStock::find($transaction->org_stock_id);
    $supplierProduct      = \App\Models\SupplyChain\SupplierProduct::find($transaction->supplier_product_id);
    $linkId               = DB::table('stock_has_supplier_products')->insertGetId(['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $supplierProduct->id]);
    $originalState        = $orgStock->state;

    $listedWhenAllGood = $listed($newPurchaseOrder);

    $orgStock->updateQuietly(['state' => OrgStockStateEnum::DISCONTINUED]);
    $listedWhenDiscontinued = $listed($newPurchaseOrder);
    $listedOnOrderHavingIt  = $listed($purchaseOrder);
    $orgStock->updateQuietly(['state' => OrgStockStateEnum::DISCONTINUING]);
    $listedWhenDiscontinuing = $listed($newPurchaseOrder);
    $orgStock->updateQuietly(['state' => $originalState]);

    $supplierProduct->updateQuietly(['is_available' => false]);
    $listedWhenSupplierUnavailable = $listed($newPurchaseOrder);
    $supplierProduct->updateQuietly(['is_available' => true]);

    DB::table('stock_has_supplier_products')->delete($linkId);
    $newPurchaseOrder->forceDelete();
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS]);

    expect($listedWhenAllGood)->toContain($orgSupplierProductId)
        ->and($listedWhenDiscontinued)->not->toContain($orgSupplierProductId)
        ->and($listedOnOrderHavingIt)->toContain($orgSupplierProductId)
        ->and($listedWhenDiscontinuing)->not->toContain($orgSupplierProductId)
        ->and($listedWhenSupplierUnavailable)->not->toContain($orgSupplierProductId);
})->depends('add more items to purchase order');


test('adding a product to a purchase order creates the missing org stock', function () {
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    SyncStockTradeUnits::run($stock, [$tradeUnit->id => ['quantity' => 1]]);

    $supplierProduct = StoreSupplierProduct::make()->action($this->orgSupplier->supplier, [
        'code'             => 'no-org-stock',
        'name'             => 'Product without org stock',
        'cost'             => 12,
        'trade_units'      => [$tradeUnit->id],
        'units_per_pack'   => 1,
        'units_per_carton' => 10
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);
    $purchaseOrder      = $this->orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first()
        ?? StorePurchaseOrder::make()->action($this->orgSupplier, PurchaseOrder::factory()->definition());

    expect(OrgStock::where('organisation_id', $this->organisation->id)->where('stock_id', $stock->id)->exists())->toBeFalse();

    $this->post(route('grp.models.purchase-order.transaction.store', [$purchaseOrder->id, $orgSupplierProduct->id]), ['quantity_ordered' => 20])
        ->assertSessionHasNoErrors();

    $orgStock = OrgStock::where('organisation_id', $this->organisation->id)->where('stock_id', $stock->id)->first();
    $line     = $purchaseOrder->purchaseOrderTransactions()->where('org_stock_id', $orgStock?->id)->first();

    expect($orgStock)->not->toBeNull()
        ->and($line->org_stock_id)->toBe($orgStock->id)
        ->and($line->org_supplier_product_id)->toBe($orgSupplierProduct->id)
        ->and((float)$line->net_amount)->toBe(240.0);

    $this->post(route('grp.models.purchase-order.transaction.store', [$purchaseOrder->id, $orgSupplierProduct->id]), ['quantity_ordered' => 5])
        ->assertSessionHasErrors('org_supplier_product');

    $otherSupplier           = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $otherOrgSupplier        = $otherSupplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $otherSupplierProduct    = StoreSupplierProduct::make()->action($otherSupplier, [
        'code'             => 'other-supplier-product',
        'name'             => 'Other supplier product',
        'cost'             => 5,
        'trade_units'      => [$tradeUnit->id],
        'units_per_pack'   => 1,
        'units_per_carton' => 10
    ]);
    $otherOrgSupplierProduct = $otherOrgSupplier->orgSupplierProducts()->where('supplier_product_id', $otherSupplierProduct->id)->first()
        ?? StoreOrgSupplierProduct::make()->action($otherOrgSupplier, $otherSupplierProduct);

    $this->post(route('grp.models.purchase-order.transaction.store', [$purchaseOrder->id, $otherOrgSupplierProduct->id]), ['quantity_ordered' => 5])
        ->assertSessionHasErrors('org_supplier_product');

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::CONFIRMED]);
    $this->patch(route('grp.models.purchase-order.transaction.update', [$purchaseOrder->id, $line->id]), ['unit_cost' => 1])
        ->assertSessionHasErrors('purchase_order_transaction');
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::IN_PROCESS]);

    expect((float)$line->refresh()->net_amount)->toBe(240.0);
});

test('a supplier product is ordered in SKOs short of a whole carton', function () {
    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    SyncStockTradeUnits::run($stock, [$tradeUnit->id => ['quantity' => 1]]);

    $supplierProduct = StoreSupplierProduct::make()->action($this->orgSupplier->supplier, [
        'code'             => 'oil-25kg-carton',
        'name'             => 'Oil in 25kg cartons',
        'cost'             => 16,
        'trade_units'      => [$tradeUnit->id],
        'units_per_pack'   => 1,
        'units_per_carton' => 25
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($this->orgSupplier, $supplierProduct);
    $purchaseOrder      = $this->orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first()
        ?? StorePurchaseOrder::make()->action($this->orgSupplier, PurchaseOrder::factory()->definition());

    $this->post(route('grp.models.purchase-order.transaction.store', [$purchaseOrder->id, $orgSupplierProduct->id]), ['quantity_ordered' => 10])
        ->assertSessionHasNoErrors();

    $line = $purchaseOrder->purchaseOrderTransactions()->where('org_supplier_product_id', $orgSupplierProduct->id)->first();

    expect((float)$line->quantity_ordered)->toBe(10.0)
        ->and((float)$line->net_amount)->toBe(160.0);
});

test('staff add notes to a purchase order and read them on its stock delivery', function () {
    $purchaseOrder = $this->purchaseOrder;
    $stockDelivery = $this->stockDelivery;
    $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);

    $this->post(route('grp.models.purchase-order.note.store', $purchaseOrder->id), ['body' => 'Booked on vessel, ETD 20/9'])
        ->assertSessionHasNoErrors();
    $this->post(route('grp.models.stock-delivery.note.store', $stockDelivery->id), ['body' => 'Bill of lading received'])
        ->assertSessionHasNoErrors();
    $this->post(route('grp.models.purchase-order.note.store', $purchaseOrder->id), ['body' => ''])
        ->assertSessionHasErrors('body');

    $purchaseOrderNotes = collect(IndexProcurementNotes::run($purchaseOrder)->items())->map(fn ($note) => $note->body);
    $stockDeliveryNotes = collect(IndexProcurementNotes::run($stockDelivery)->items())->map(fn ($note) => $note->body);

    expect($purchaseOrderNotes->all())->toContain('Booked on vessel, ETD 20/9')
        ->not->toContain('Bill of lading received')
        ->and($stockDeliveryNotes->all())->toContain('Booked on vessel, ETD 20/9', 'Bill of lading received');

    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]).'?tab=notes')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrder')
            ->has('notes.data')
            ->where('note_form.can_write', true)
            ->has('note_form.options.categories', 8)
            ->where('note_store_route.name', 'grp.models.purchase-order.note.store'));

    $this->post(route('grp.models.purchase-order.note.store', $purchaseOrder->id), ['title' => 'Charged 40 packing', 'category' => 'incident', 'severity' => 'warning', 'incident_amount' => 40, 'incident_currency_id' => $purchaseOrder->currency_id])
        ->assertSessionHasNoErrors();
    $incident = App\Models\Procurement\ProcurementNote::where('subject_type', 'PurchaseOrder')->where('subject_id', $purchaseOrder->id)->where('category', 'incident')->latest('id')->firstOrFail();

    expect($incident->incident_outcome->value)->toBe('open')
        ->and($incident->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($incident->organisation_id)->toBe($purchaseOrder->organisation_id)
        ->and(collect(IndexProcurementNotes::run($purchaseOrder, forAgent: true)->items())->pluck('id')->all())->not->toContain($incident->id);
});

test('purchase order pdf downloads', function () {
    $purchaseOrder = $this->purchaseOrder;

    $response = $this->get(route('grp.org.procurement.purchase_orders.pdf', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('a purchase order line shows our SKO code next to the supplier code when they differ, on the page and the pdf', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder                      = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $line                               = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());
    $supplierCode                       = $orgSupplierProduct->supplierProduct->code;
    $skoCode                            = $this->orgStocks[0]->code;

    expect($skoCode)->not->toBe($supplierCode);

    $row = (new \App\Http\Resources\Procurement\PurchaseOrderTransactionResource($line->refresh()))->toArray(request());
    expect($row['code'])->toBe($supplierCode)
        ->and($row['org_stock_code'])->toBe($skoCode);

    $pdfAction = PdfPurchaseOrder::make();
    $html      = view('procurement.templates.pdf.purchase-order', (fn () => $this->viewData($purchaseOrder->refresh()))->call($pdfAction))->render();
    expect($html)->toContain($supplierCode)->toContain($skoCode);
});

test('the agent prints a client purchase order under its own company, addressed to the supplier, without the client details', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition())->refresh();
    $client        = $purchaseOrder->organisation;
    $agentCompany  = $this->agent->organisation;

    expect($purchaseOrder->isAgentOrder())->toBeTrue()
        ->and($client->id)->not->toBe($agentCompany->id);

    $render = function (bool $forAgent) use ($purchaseOrder): string {
        $pdfAction           = PdfPurchaseOrder::make();
        $pdfAction->forAgent = $forAgent;

        return view('procurement.templates.pdf.purchase-order', (fn () => $this->viewData($purchaseOrder))->call($pdfAction))->render();
    };

    expect($render(false))->toContain(e($client->name))->toContain('For supplier');
    expect($render(true))->toContain(e($agentCompany->name))
        ->toContain(e($orgSupplier->supplier->name))
        ->not->toContain(e($client->name))
        ->not->toContain('For supplier');
});

test('suppliers set to receive purchase orders by email get an email button on their purchase orders', function () {
    $supplier      = StoreSupplier::make()->action($this->group, Supplier::factory()->definition());
    $orgSupplier   = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $this->organisation->id)->first()
        ?? StoreOrgSupplier::make()->action($this->organisation, $supplier);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, ['reference' => 'EMAIL'.Str::upper(Str::random(8))], strict: false);

    UpdateSupplier::make()->action($orgSupplier->supplier, ['po_by_email' => true, 'po_email' => 'orders@supplier.test']);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions', fn ($actions) => collect($actions)->contains(
                fn ($action) => ($action['key'] ?? null) === 'email_to_supplier' && str_starts_with($action['mailto'], 'mailto:orders@supplier.test?subject=')
            )));

    UpdateSupplier::make()->action($orgSupplier->supplier, ['po_by_email' => false]);
});

test('searching a purchase order tab table keeps that tab when the url has no tab', function () {
    $purchaseOrder = $this->purchaseOrder;
    $showUrl       = route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]);

    $this->get($showUrl.'?products_filter%5Bglobal%5D=orgbokg&products_sort=code')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'products'));

    $this->get($showUrl.'?notesPage=2')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'notes'));

    $this->get($showUrl.'?tab=history&products_filter%5Bglobal%5D=orgbokg')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tabs.current', 'history'));
});

test('purchase order page has an edit button to change its reference', function () {
    $purchaseOrder = $this->purchaseOrder;

    $this->get(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.actions', fn ($actions) => collect($actions)->contains(
                fn ($action) => ($action['style'] ?? null) === 'edit' && $action['route']['name'] === 'grp.org.procurement.purchase_orders.edit'
            )));
});

test('purchase order needs a product available in both the supplier product and the org supplier product', function () {
    $supplier        = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier     = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'UNAV',
        'name'             => 'Availability switches',
        'cost'             => 200,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);
    $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->where('org_supplier_id', $orgSupplier->id)->sole();

    UpdateOrgSupplierProduct::make()->action($orgSupplierProduct, ['is_available' => false]);
    expect(fn () => StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition()))
        ->toThrow(ValidationException::class);

    UpdateSupplierProduct::make()->action($supplierProduct, ['is_available' => false]);
    UpdateSupplierProduct::make()->action($supplierProduct, ['is_available' => true]);
    expect($orgSupplierProduct->refresh()->is_available)->toBeFalse();

    UpdateOrgSupplierProduct::make()->action($orgSupplierProduct, ['is_available' => true]);
    UpdateSupplierProduct::make()->action($supplierProduct, ['is_available' => false]);
    expect($orgSupplierProduct->refresh()->is_available)->toBeTrue()
        ->and(fn () => StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition()))
        ->toThrow(ValidationException::class);

    UpdateSupplierProduct::make()->action($supplierProduct, ['is_available' => true]);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    expect($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class);

    DeletePurchaseOrder::make()->action($purchaseOrder);
});

test('reactivating a discontinued supplier product makes it available again unless told otherwise', function () {
    $supplier        = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'REACT',
        'name'             => 'Reactivated',
        'cost'             => 200,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);

    UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::DISCONTINUED->value]);
    expect($supplierProduct->refresh()->is_available)->toBeFalse();

    UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::ACTIVE->value]);
    expect($supplierProduct->refresh()->is_available)->toBeTrue();

    UpdateSupplierProduct::make()->action($supplierProduct, ['is_available' => false]);
    UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::DISCONTINUING->value]);
    expect($supplierProduct->refresh()->is_available)->toBeFalse();

    UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::DISCONTINUED->value]);
    UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::ACTIVE->value, 'is_available' => false]);
    expect($supplierProduct->refresh()->is_available)->toBeFalse();
});

test('discontinuing a supplier product together with a historic field still discontinues it in every organisation', function () {
    $supplier        = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'STATECBM',
        'name'             => 'State with CBM',
        'cost'             => 200,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100
    ]);

    UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::DISCONTINUED->value, 'cbm' => 0.123]);

    expect($supplierProduct->refresh()->orgSupplierProducts)->not->toBeEmpty()
        ->and($supplierProduct->orgSupplierProducts->pluck('state')->unique()->values()->all())->toBe([SupplierProductStateEnum::DISCONTINUED->value]);
});

test('a supplier product without any SKO is refused on a purchase order with how to create its SKO', function () {
    $supplier           = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier        = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'NOSKO-1',
        'name'             => 'Made before the new supplier product form',
        'cost'             => 400,
        'units_per_pack'   => 1,
        'units_per_carton' => 1,
    ]);
    $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->where('org_supplier_id', $orgSupplier->id)->sole();
    $purchaseOrder      = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    expect(fn () => StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($purchaseOrder, $orgSupplierProduct, ['quantity_ordered' => 1]))
        ->toThrow(ValidationException::class, 'NOSKO-1 has no SKO yet');

    DeletePurchaseOrder::make()->action($purchaseOrder);
});

test('a supplier product made unavailable, here or for every organisation, is refused on a purchase order', function () {
    $supplier           = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier        = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'GONE-1',
        'name'             => 'No longer ordered',
        'cost'             => 400,
        'units_per_pack'   => 1,
        'units_per_carton' => 1,
    ]);
    $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->where('org_supplier_id', $orgSupplier->id)->sole();
    $purchaseOrder      = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    UpdateOrgSupplierProduct::make()->action($orgSupplierProduct, ['is_available' => false]);
    expect(fn () => StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($purchaseOrder, $orgSupplierProduct->refresh(), ['quantity_ordered' => 1]))
        ->toThrow(ValidationException::class, 'GONE-1 is not available from this supplier');

    UpdateOrgSupplierProduct::make()->action($orgSupplierProduct, ['is_available' => true]);
    UpdateSupplierProduct::make()->action($supplierProduct, ['is_available' => false]);
    expect(fn () => StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($purchaseOrder, $orgSupplierProduct->refresh(), ['quantity_ordered' => 1]))
        ->toThrow(ValidationException::class, 'GONE-1 is not available from this supplier');

    DeletePurchaseOrder::make()->action($purchaseOrder);
});

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

test('a second purchase order for a supplier with an open one names the open purchase order', function () {
    $openPurchaseOrder = $this->orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->first()
        ?? StorePurchaseOrder::make()->action($this->orgSupplier, PurchaseOrder::factory()->definition());

    try {
        StorePurchaseOrder::make()->action($this->orgSupplier, PurchaseOrder::factory()->definition());
        $this->fail('A second open purchase order was created');
    } catch (ValidationException $exception) {
        expect($exception->errors()['purchase_order'][0])->toContain($openPurchaseOrder->reference);
    }
});

test('purchase orders are numbered with the org supplier format, skipping references already used', function () {
    $supplier    = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $orderData   = Arr::except(PurchaseOrder::factory()->definition(), 'reference');

    expect(UpdateOrgSupplier::make()->nextPurchaseOrderReference($orgSupplier))->toBe($supplier->code.'-0001');

    UpdateOrgSupplier::make()->action($orgSupplier, [
        'purchase_order_reference_format' => 'Camacho-%04d_UK',
        'purchase_order_last_number'      => 33,
    ]);
    $orgSupplier->refresh();

    expect(UpdateOrgSupplier::make()->nextPurchaseOrderReference($orgSupplier))->toBe('Camacho-0034_UK')
        ->and(StorePurchaseOrder::make()->action($orgSupplier, $orderData, strict: false)->reference)->toBe('Camacho-0034_UK');

    StorePurchaseOrder::make()->action($orgSupplier, array_merge($orderData, ['reference' => 'Camacho-0035_UK']), strict: false);

    expect(UpdateOrgSupplier::make()->nextPurchaseOrderReference($orgSupplier))->toBe('Camacho-0036_UK');

    expect(StorePurchaseOrder::make()->action($orgSupplier, $orderData, strict: false)->reference)->toBe('Camacho-0036_UK')
        ->and($orgSupplier->purchaseOrderSerialReference()->value('serial'))->toBe(36);

    UpdateOrgSupplier::make()->action($orgSupplier, ['purchase_order_reference_format' => null]);
    $orgSupplier->refresh();

    expect($orgSupplier->purchaseOrderSerialReference)->toBeNull()
        ->and(StorePurchaseOrder::make()->action($orgSupplier, $orderData, strict: false)->reference)->toBe($supplier->code.'-0001');
});

test('a supplier without its own number format is numbered from its code, carrying on from its Aurora number', function () {
    $supplier    = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $orderData   = Arr::except(PurchaseOrder::factory()->definition(), 'reference');

    StorePurchaseOrder::make()->action($orgSupplier, array_merge($orderData, ['reference' => 'Brow Farm-003']), strict: false)
        ->update(['source_id' => '4:'.fake()->unique()->numberBetween(900000, 999999), 'created_at' => now()->subDay()]);
    StorePurchaseOrder::make()->action($orgSupplier, array_merge($orderData, ['reference' => 'Conti-AWChina-071-AROMA']), strict: false);

    expect(UpdateOrgSupplier::make()->nextPurchaseOrderReference($orgSupplier))->toBe($supplier->code.'-0004')
        ->and(StorePurchaseOrder::make()->action($orgSupplier, $orderData, strict: false)->reference)->toBe($supplier->code.'-0004')
        ->and($orgSupplier->purchaseOrderSerialReference()->value('format'))->toBe($supplier->code.'-%04d');
});

test('a supplier whose last Aurora reference ends in a tracking number is numbered from its Aurora order count', function () {
    $supplier    = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $orderData   = Arr::except(PurchaseOrder::factory()->definition(), 'reference');

    foreach (['3PDirect-058_AWA' => 2, '3PDirect-8174841_AW' => 1] as $reference => $daysAgo) {
        StorePurchaseOrder::make()->action($orgSupplier, array_merge($orderData, ['reference' => $reference]), strict: false)
            ->update(['source_id' => '4:'.fake()->unique()->numberBetween(900000, 999999), 'created_at' => now()->subDays($daysAgo)]);
    }

    expect(UpdateOrgSupplier::make()->nextPurchaseOrderReference($orgSupplier))->toBe($supplier->code.'-0003');
});

test('stock deliveries are numbered with the org supplier delivery format, skipping references already used', function () {
    $supplier    = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    expect(UpdateOrgSupplier::make()->nextStockDeliveryReference($orgSupplier))->toStartWith('SD');

    UpdateOrgSupplier::make()->action($orgSupplier, [
        'stock_delivery_reference_format' => 'Camacho-D%04d_UK',
        'stock_delivery_last_number'      => 7,
    ]);
    $orgSupplier->refresh();

    StoreStockDelivery::make()->action($orgSupplier, ['reference' => 'Camacho-D0008_UK', 'date' => date('Y-m-d')]);

    expect(UpdateOrgSupplier::make()->nextStockDeliveryReference($orgSupplier))->toBe('Camacho-D0009_UK')
        ->and(UpdateOrgSupplier::make()->newProcurementReference($orgSupplier, SerialReferenceModelEnum::STOCK_DELIVERY))->toBe('Camacho-D0009_UK')
        ->and(UpdateOrgSupplier::make()->nextPurchaseOrderReference($orgSupplier))->toBe($supplier->code.'-0001');

    UpdateOrgSupplier::make()->action($orgSupplier, ['stock_delivery_reference_format' => null]);

    expect($orgSupplier->serialReferences()->where('model', SerialReferenceModelEnum::STOCK_DELIVERY)->exists())->toBeFalse();
});

test('procurement number formats and typed references only take letters, numbers, - and _', function (string $badFormat) {
    $orgSupplier = $this->purchaseOrder->parent;

    expect(fn () => UpdateOrgSupplier::make()->action($orgSupplier, ['purchase_order_reference_format' => $badFormat]))
        ->toThrow(ValidationException::class)
        ->and(fn () => UpdatePurchaseOrder::make()->action($this->purchaseOrder, ['reference' => sprintf($badFormat, 1)]))
        ->toThrow(ValidationException::class);
})->with(['PO %04d', 'PO/%04d', 'PO-%04d URGENT', 'PÖ-%04d']);

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


test('agreed line price survives quantity changes and can update the supplier price', function ($purchaseOrder) {
    $item = $purchaseOrder->purchaseOrderTransactions()->first();

    $this->patch(route('grp.models.purchase-order.transaction.update', [$purchaseOrder->id, $item->id]), [
        'unit_cost'            => 3.5,
        'update_supplier_cost' => true,
    ])->assertSessionHasNoErrors();

    $item->refresh();
    expect((float)$item->unit_cost)->toBe(3.5)
        ->and((float)$item->net_amount)->toBe(3.5 * (float)$item->quantity_ordered)
        ->and((float)$item->supplierProduct->refresh()->cost)->toBe(3.5);

    UpdatePurchaseOrderTransaction::make()->action($item, ['quantity_ordered' => 10]);
    $item->refresh();

    expect((float)$item->net_amount)->toBe(35.0);
})->depends('add item to purchase order');

test('update purchase order', function ($purchaseOrder) {
    $dataToUpdate  = [
        'reference' => 'PO-12345bis',
    ];
    $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, $dataToUpdate);
    $this->assertModelExists($purchaseOrder);
})->depends('create purchase order independent supplier');

test('editing a purchase order or its lines broadcasts who edited it and when, and its page shows the last edit', function (PurchaseOrder $purchaseOrder) {
    Event::fake([BroadcastPurchaseOrderLastEdited::class]);
    PurchaseOrder::enableAuditing();
    PurchaseOrderTransaction::enableAuditing();
    $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrder->id);
    $user          = $this->adminGuest->getUser();
    actingAs($user);

    UpdatePurchaseOrder::make()->action($purchaseOrder, ['notes' => 'edited '.now()->timestamp]);

    $expectedUser = $user->contact_name ?: $user->username;
    Event::assertDispatched(
        BroadcastPurchaseOrderLastEdited::class,
        fn (BroadcastPurchaseOrderLastEdited $event) => $event->purchaseOrderId === $purchaseOrder->id && $event->lastEdit['user'] === $expectedUser
    );
    expect(BroadcastPurchaseOrderLastEdited::lastEdit($purchaseOrder))->toMatchArray(['user' => $expectedUser]);

    $item = $purchaseOrder->purchaseOrderTransactions()->first();
    UpdatePurchaseOrderTransaction::make()->action($item, ['quantity_ordered' => (float) $item->quantity_ordered + 1]);

    Event::assertDispatched(
        BroadcastPurchaseOrderLastEdited::class,
        fn (BroadcastPurchaseOrderLastEdited $event) => $event->purchaseOrderId === $purchaseOrder->id
    );
})->depends('add item to purchase order');

test('UI edit purchase order sets reference and delivery address', function ($purchaseOrder) {
    $purchaseOrder->refresh();

    $this->get(route('grp.org.procurement.purchase_orders.edit', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EditModel')
            ->where('formData.blueprint.0.fields.reference.value', $purchaseOrder->reference));

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), [
        'reference'        => 'AWChina-93-SK-VAL270011',
        'delivery_address' => 'CTPark Trnava, Zavar, Slovakia',
    ])->assertSessionHasNoErrors();

    $purchaseOrder->refresh();
    expect($purchaseOrder->reference)->toBe('AWChina-93-SK-VAL270011')
        ->and($purchaseOrder->data['delivery_address'])->toBe('CTPark Trnava, Zavar, Slovakia');
})->depends('create purchase order independent supplier');

test('update purchase order deposit retrospectively', function ($purchaseOrder) {
    $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder, [
        'deposit_amount'  => 1500.50,
        'deposit_paid_at' => '2025-01-15',
        'balance_paid_at' => null,
    ]);

    expect($purchaseOrder->refresh()->deposit_amount)->toBe('1500.50')
        ->and($purchaseOrder->deposit_paid_at->toDateString())->toBe('2025-01-15')
        ->and($purchaseOrder->balance_paid_at)->toBeNull();
})->depends('create purchase order independent supplier');

test('submit agent purchase order estimates its delivery from the supplier product lead time', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

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

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());

    expect($purchaseOrder->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and($purchaseOrder->isAgentOrder())->toBeTrue()
        ->and($purchaseOrder->estimated_delivery_days)->toBe(21)
        ->and($purchaseOrder->estimated_received_at)->not->toBeNull()
        ->and($purchaseOrderTransaction->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::SUBMITTED);

    CancelPurchaseOrderTransaction::make()->action($purchaseOrderTransaction->refresh());
    expect((float) $purchaseOrder->refresh()->cost_total)->toBe(0.0);
});

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
        ->and(Arr::get($purchaseOrder->data, 'estimated_receiving_date'))->toBeNull()
        ->and($purchaseOrder->estimated_received_at?->toDateString())->toBe(
            $purchaseOrder->estimated_delivery_days === null ? null : $purchaseOrder->submitted_at->clone()->addDays($purchaseOrder->estimated_delivery_days)->toDateString()
        );

    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED);

    return $purchaseOrder;
})->depends('change purchase order state to confirmed');

test('change purchase order state to cancelled', function ($purchaseOrder) {
    $purchaseOrder->refresh();
    expect($purchaseOrder->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED)
        ->and(collect(ShowPurchaseOrder::make()->getActions($purchaseOrder, false))->pluck('key'))->toContain('cancel_purchase_order');

    $stockDelivery = StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh());

    expect(collect(ShowPurchaseOrder::make()->getActions($purchaseOrder->refresh(), false))->pluck('key'))->not->toContain('cancel_purchase_order')
        ->and(fn () => UpdatePurchaseOrderStateToCancelled::make()->action($purchaseOrder->refresh()))->toThrow(HttpException::class);

    $stockDelivery->update(['state' => StockDeliveryStateEnum::RECEIVED]);
    CancelStockDelivery::make()->action($stockDelivery->refresh());

    expect($purchaseOrder->refresh()->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED)
        ->and(collect(ShowPurchaseOrder::make()->getActions($purchaseOrder, false))->pluck('key'))->toContain('cancel_purchase_order');

    $this->patch(route('grp.models.purchase-order.cancel', $purchaseOrder->id), ['counterparty_informed' => 'no'])
        ->assertSessionHasErrors('counterparty_informed');
    expect($purchaseOrder->refresh()->state)->toEqual(PurchaseOrderStateEnum::CONFIRMED);

    $this->patch(route('grp.models.purchase-order.cancel', $purchaseOrder->id), ['counterparty_informed' => 'yes'])
        ->assertSessionHasNoErrors();
    $purchaseOrder->refresh();

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


test('a stock delivery has a delivery date button that changes only its own date', function (StockDelivery $stockDelivery) {
    expect(collect(ShowStockDelivery::make()->getActions($stockDelivery->refresh()))->pluck('key'))->toContain('edit_estimated_delivery_date');

    $stockDelivery = UpdateStockDelivery::make()->action($stockDelivery, ['estimated_receiving_date' => '2027-04-20']);

    expect($stockDelivery->refresh()->data['estimated_receiving_date'])->toBe('2027-04-20');

    $agentPurchaseOrder = PurchaseOrder::whereNotNull('agent_id')->where('parent_type', 'OrgSupplier')
        ->whereDoesntHave('stockDeliveries')
        ->latest('id')
        ->firstOrFail();
    $originalState = $agentPurchaseOrder->state;
    $agentPurchaseOrder->update(['state' => PurchaseOrderStateEnum::CONFIRMED]);

    expect(collect(ShowPurchaseOrder::make()->getActions($agentPurchaseOrder, false))->pluck('key'))->toContain('edit_estimated_delivery_date')
        ->and(ShowPurchaseOrder::make()->getTimeline($agentPurchaseOrder))->toHaveKey('estimated_delivery');

    $stockDelivery->purchaseOrders()->attach($agentPurchaseOrder);

    expect(collect(ShowPurchaseOrder::make()->getActions($agentPurchaseOrder, false))->pluck('key'))->not->toContain('edit_estimated_delivery_date')
        ->and(ShowPurchaseOrder::make()->getTimeline($agentPurchaseOrder))->not->toHaveKey('estimated_delivery');

    $stockDelivery->purchaseOrders()->detach($agentPurchaseOrder);
    $agentPurchaseOrder->update(['state' => $originalState]);
})->depends('update supplier delivery');

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

test('stock delivery item without a supplier product shows the SKO code and name', function (StockDelivery $stockDelivery) {
    $stockDeliveryItem = $stockDelivery->items()->first();
    $stockDeliveryItem->supplier_product_id = null;
    $stockDeliveryItem->setAttribute('org_stock_code', $stockDeliveryItem->orgStock->code);
    $stockDeliveryItem->setAttribute('org_stock_name', $stockDeliveryItem->orgStock->name);

    $row = StockDeliveryItemResource::make($stockDeliveryItem)->toArray(request());

    expect($row['code'])->toBe($stockDeliveryItem->orgStock->code)
        ->and($row['name'])->toBe($stockDeliveryItem->orgStock->name);
})->depends('create supplier delivery items');

test('aurora fetch moves an item to the stock delivery it now belongs to', function (StockDelivery $stockDelivery) {
    $newStockDelivery = StoreStockDelivery::make()->action($stockDelivery->parent, [
        'reference' => 'SP-01-1',
        'date'      => date('Y-m-d')
    ], strict: false);
    $stockDeliveryItem = $stockDelivery->items()->first();

    FetchAuroraStockDeliveryItems::make()->moveToStockDelivery($stockDeliveryItem, $newStockDelivery);

    expect($stockDeliveryItem->refresh()->stock_delivery_id)->toBe($newStockDelivery->id)
        ->and($newStockDelivery->items()->count())->toBe(1);

    FetchAuroraStockDeliveryItems::make()->moveToStockDelivery($stockDeliveryItem, $stockDelivery);

    expect($stockDeliveryItem->refresh()->stock_delivery_id)->toBe($stockDelivery->id);
})->depends('create supplier delivery items');

test('stock delivery hydrators queue once per delivery, not once for all deliveries', function (StockDelivery $stockDelivery) {
    Queue::fake();
    $otherStockDelivery = StoreStockDelivery::make()->action($stockDelivery->parent, [
        'reference' => 'SP-02',
        'date'      => date('Y-m-d')
    ], strict: false);

    StockDeliveriesHydrateItems::dispatch($stockDelivery);
    StockDeliveriesHydrateItems::dispatch($otherStockDelivery);
    StockDeliveriesHydrateCosts::dispatch($stockDelivery);
    StockDeliveriesHydrateCosts::dispatch($otherStockDelivery);

    $pushedFor = fn (string $hydrator) => Queue::pushed(BoundedUniqueJobDecorator::class, fn (BoundedUniqueJobDecorator $job) => $job->getAction() instanceof $hydrator)->count();

    expect($pushedFor(StockDeliveriesHydrateItems::class))->toBe(2)
        ->and($pushedFor(StockDeliveriesHydrateCosts::class))->toBe(2);
})->depends('create supplier delivery items');

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

    OrgSupplierHydrateOrgSupplierProducts::run($orgSupplier);
    $activeCount = $orgSupplier->stats->refresh()->number_org_supplier_products_state_active;
    $discontinuingCount = $orgSupplier->stats->number_org_supplier_products_state_discontinuing;

    UpdateOrgSupplierProduct::make()->action($orgSupplierProduct, [
        'state' => OrgSupplierProductStateEnum::DISCONTINUING,
    ]);

    $orgSupplier->stats->refresh();

    expect($orgSupplier->stats->number_org_supplier_products_state_active)->toBe($activeCount - 1)
        ->and($orgSupplier->stats->number_org_supplier_products_state_discontinuing)->toBe($discontinuingCount + 1);
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

    $selectedTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        array_merge(PurchaseOrderTransaction::factory()->definition(), [
            'quantity_ordered' => 11,
        ])
    );

    $excludedTransaction = StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $orgSupplierProduct->supplierProduct->historicSupplierProduct,
        $this->orgStocks[0],
        array_merge(PurchaseOrderTransaction::factory()->definition(), [
            'quantity_ordered' => 22,
        ])
    );

    $purchaseOrder = UpdatePurchaseOrder::make()->action($purchaseOrder->refresh(), [
        'delivery_type'             => 'container',
        'incoterm'                  => 'FOB',
        'estimated_production_date' => '2026-07-27',
        'estimated_receiving_date'  => '2026-08-31',
    ]);

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

    expect(fn () => StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh(), [
        'purchase_order_transaction_ids' => [PurchaseOrderTransaction::max('id') + 1],
    ]))->toThrow(ValidationException::class);

    expect($purchaseOrder->stockDeliveries()->count())->toBe(0);

    $response = $this->post(route('grp.models.purchase-order.stock-delivery.store', [
        'purchaseOrder' => $purchaseOrder->id,
    ]), [
        'purchase_order_transaction_ids' => [$selectedTransaction->id],
    ]);

    $stockDelivery = $purchaseOrder->stockDeliveries()->latest('stock_deliveries.id')->first();

    $response->assertRedirect(route('grp.org.procurement.stock_deliveries.show', [
        'organisation' => $stockDelivery->organisation->slug,
        'stockDelivery' => $stockDelivery->slug,
    ]));

    $stockDeliveryItem = $stockDelivery->items()->first();

    expect($stockDelivery)->toBeInstanceOf(StockDelivery::class)
        ->and($stockDelivery->state)->toEqual(StockDeliveryStateEnum::IN_PROCESS)
        ->and($stockDelivery->parent_id)->toBe($orgSupplier->id)
        ->and($stockDelivery->number_purchase_orders)->toBe(1)
        ->and($stockDelivery->items()->count())->toBe(1)
        ->and($stockDeliveryItem->state)->toEqual(StockDeliveryItemStateEnum::IN_PROCESS)
        ->and((float) $stockDeliveryItem->unit_quantity)->toBe(11.0)
        ->and(Arr::get($stockDeliveryItem->data, 'purchase_order_transaction_id'))->toBe($selectedTransaction->id)
        ->and($excludedTransaction->fresh()->state)->toBe(PurchaseOrderTransactionStateEnum::CONFIRMED)
        ->and(Arr::get($stockDelivery->data, 'delivery_type'))->toBe('container')
        ->and(Arr::get($stockDelivery->data, 'incoterm'))->toBe('FOB')
        ->and(Arr::get($stockDelivery->data, 'estimated_dispatched_date'))->toBe('2026-07-27')
        ->and(Arr::get($stockDelivery->data, 'estimated_receiving_date'))->toBe('2026-08-31');

    $this->withoutExceptionHandling();
    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrder')
            ->has('stock_delivery_timelines', 1)
            ->where('stock_delivery_timelines.0.reference', $stockDelivery->reference)
            ->where('stock_delivery_timelines.0.state', $stockDelivery->state->value)
            ->has('stock_delivery_timelines.0.state_icon')
            ->where('stock_delivery_timelines.0.timeline.dispatched.timestamp', '2026-07-27')
            ->where('stock_delivery_timelines.0.timeline.received.timestamp', '2026-08-31'));

    return $stockDelivery;
});

test('purchase order delivered in two parts stays open for the remaining items', function () {
    $supplier    = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $historicSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'SPLIT-PO',
        'name'             => 'Split delivery',
        'cost'             => 10,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]))->supplierProduct->historicSupplierProduct;

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $firstLine     = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $historicSupplierProduct, $this->orgStocks[0], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 10]));
    $secondLine    = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $historicSupplierProduct, $this->orgStocks[1], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 20]));
    $thirdLine     = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $historicSupplierProduct, $this->orgStocks[2], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 30]));

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

    $place = function (StockDelivery $stockDelivery): void {
        $stockDelivery->items()->update(['state' => StockDeliveryItemStateEnum::PLACED]);
        $stockDelivery->update(['state' => StockDeliveryStateEnum::PLACED]);
        UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($stockDelivery->refresh());
    };

    $firstDelivery = StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh(), ['purchase_order_transaction_ids' => [$firstLine->id]]);

    expect(StoreStockDeliveryFromPurchaseOrder::transactionsAwaitingDelivery($purchaseOrder)->pluck('id')->all())->toEqualCanonicalizing([$secondLine->id, $thirdLine->id]);

    $place($firstDelivery);

    request()->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'test', []));
    $table = new \App\InertiaTable\InertiaTable(request());
    IndexPurchaseOrderTransactions::make()->tableStructure($purchaseOrder->refresh())($table);

    expect((new ReflectionProperty(\App\InertiaTable\InertiaTable::class, 'columns'))->getValue($table)->pluck('key'))->toContain('actions');

    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED)
        ->and($firstLine->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::SETTLED)
        ->and($secondLine->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::CONFIRMED);

    $secondDelivery = StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh(), ['purchase_order_transaction_ids' => [$secondLine->id]]);

    expect($secondDelivery->items()->pluck('org_stock_id')->all())->toBe([$this->orgStocks[1]->id])
        ->and(fn () => CancelPurchaseOrderTransaction::make()->action($secondLine->refresh()))->toThrow(HttpException::class);

    $cancelRoutes = IndexPurchaseOrderTransactions::run($purchaseOrder->refresh())->getCollection()
        ->mapWithKeys(fn (PurchaseOrderTransaction $line) => [$line->id => (new \App\Http\Resources\Procurement\PurchaseOrderTransactionResource($line))->toArray(request())['cancelRoute']]);

    expect($cancelRoutes[$firstLine->id])->toBeNull()
        ->and($cancelRoutes[$secondLine->id])->toBeNull()
        ->and($cancelRoutes[$thirdLine->id])->not->toBeNull();

    $place($secondDelivery);

    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED);

    CancelPurchaseOrderTransaction::make()->action($thirdLine->refresh());

    expect($thirdLine->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::CANCELLED)
        ->and($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::SETTLED);
});

test('a settled purchase order reopens only when a delivery takes back lines it had settled', function () {
    $supplier    = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $historicSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'REOPEN-PO',
        'name'             => 'Reopen check',
        'cost'             => 10,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 1,
        'units_per_carton' => 10,
    ]))->supplierProduct->historicSupplierProduct;

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $line          = StorePurchaseOrderTransaction::make()->action($purchaseOrder, $historicSupplierProduct, $this->orgStocks[0], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 10]));
    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

    $delivery = StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh());
    $delivery->items()->update(['state' => StockDeliveryItemStateEnum::PLACED]);
    $delivery->update(['state' => StockDeliveryStateEnum::PLACED]);
    UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($delivery->refresh());

    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::SETTLED)
        ->and($line->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::SETTLED);

    $line->update(['state' => PurchaseOrderTransactionStateEnum::CONFIRMED]);
    $leftover = StoreStockDelivery::make()->action($orgSupplier, ['reference' => 'REOPEN-LEFTOVER', 'date' => date('Y-m-d')]);
    $leftover->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);
    $leftover->update(['state' => StockDeliveryStateEnum::IN_PROCESS]);
    UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($leftover->refresh());

    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::SETTLED)
        ->and($purchaseOrder->delivery_state)->toBe(PurchaseOrderDeliveryStateEnum::PLACED);

    $line->update(['state' => PurchaseOrderTransactionStateEnum::SETTLED]);
    $delivery->items()->update(['state' => StockDeliveryItemStateEnum::DISPATCHED]);
    $delivery->update(['state' => StockDeliveryStateEnum::DISPATCHED]);
    UpdatePurchaseOrdersDeliveryStateFromStockDelivery::run($delivery->refresh());

    expect($purchaseOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED)
        ->and($purchaseOrder->delivery_state)->toBe(PurchaseOrderDeliveryStateEnum::DISPATCHED)
        ->and($line->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::CONFIRMED);
});

test('purchase order with an open aurora stock delivery refuses a second one', function (StockDelivery $stockDelivery) {
    $stockDelivery->update(['source_id' => '1:999999', 'state' => StockDeliveryStateEnum::DISPATCHED]);
    $purchaseOrder = $stockDelivery->purchaseOrders()->first();

    expect(fn () => StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh()))
        ->toThrow(ValidationException::class)
        ->and($purchaseOrder->stockDeliveries()->count())->toBe(1);

    $stockDelivery->update(['source_id' => null, 'state' => StockDeliveryStateEnum::IN_PROCESS]);
})->depends('create stock delivery from purchase order');

test('stock delivery from a confirmed agent order is created under the org agent and another order of the same agent joins it', function () {
    [$orgSupplierOne, $orgSupplierProductOne] = createAgentOrgSupplierWithProduct($this);
    [$orgSupplierTwo, $orgSupplierProductTwo] = createAgentOrgSupplierWithProduct($this);
    $orgAgent = $orgSupplierOne->orgAgent;

    $orderOne = createConfirmedAgentOrder($this, $orgSupplierOne, $orgSupplierProductOne, 0, 10);
    $orderTwo = createConfirmedAgentOrder($this, $orgSupplierTwo, $orgSupplierProductTwo, 1, 20);

    expect($orderOne->isAgentOrder())->toBeTrue()
        ->and($orderTwo->isAgentOrder())->toBeTrue()
        ->and($orderTwo->parent->org_agent_id)->toBe($orgAgent->id);

    $deliveryOne = StoreStockDeliveryFromPurchaseOrder::make()->action($orderOne->refresh());

    expect($deliveryOne->parent_type)->toBe('OrgAgent')
        ->and($deliveryOne->parent_id)->toBe($orgAgent->id)
        ->and($deliveryOne->state)->toBe(StockDeliveryStateEnum::IN_PROCESS)
        ->and($deliveryOne->number_purchase_orders)->toBe(1)
        ->and($orderOne->stockDeliveries()->pluck('stock_deliveries.id')->all())->toBe([$deliveryOne->id]);

    $notAgentDelivery = StoreStockDelivery::make()->action($orgSupplierTwo, [
        'reference' => 'NOT-AGENT-DELIVERY-'.StockDelivery::count().'-'.uniqid(),
        'date'      => date('Y-m-d'),
    ], strict: false);

    expect(fn () => StoreStockDeliveryFromPurchaseOrder::make()->action($orderTwo->refresh(), ['stock_delivery_id' => $notAgentDelivery->id]))
        ->toThrow(ValidationException::class)
        ->and($orderTwo->stockDeliveries()->count())->toBe(0);

    $joined = StoreStockDeliveryFromPurchaseOrder::make()->action($orderTwo->refresh(), ['stock_delivery_id' => $deliveryOne->id]);

    expect($joined->is($deliveryOne))->toBeTrue()
        ->and($deliveryOne->purchaseOrders()->pluck('purchase_orders.id')->all())->toEqualCanonicalizing([$orderOne->id, $orderTwo->id])
        ->and($deliveryOne->refresh()->number_purchase_orders)->toBe(2)
        ->and($deliveryOne->items()->count())->toBe(2)
        ->and($orderTwo->stockDeliveries()->pluck('stock_deliveries.id')->all())->toBe([$deliveryOne->id]);
});

test('a stock delivery only takes the paid deposits of its own purchase orders in its own currency', function () {
    [$orgSupplierOne, $orgSupplierProductOne] = createAgentOrgSupplierWithProduct($this);
    [$orgSupplierTwo, $orgSupplierProductTwo] = createAgentOrgSupplierWithProduct($this);

    $orderOne = createConfirmedAgentOrder($this, $orgSupplierOne, $orgSupplierProductOne, 0, 10);
    $orderTwo = createConfirmedAgentOrder($this, $orgSupplierTwo, $orgSupplierProductTwo, 1, 20);

    $delivery = StoreStockDeliveryFromPurchaseOrder::make()->action($orderOne->refresh());

    $ownDeposit     = createPaidAspoDeposit($orderOne);
    $otherOrderDeposit = createPaidAspoDeposit($orderTwo);
    $otherCurrency  = Currency::where('id', '!=', $orderOne->currency_id)->firstOrFail();
    $foreignDeposit = createPaidAspoDeposit($orderOne, ['currency_id' => $otherCurrency->id]);

    expect($delivery->agent_id)->toBe($orderOne->agent_id)
        ->and(offeredDepositIds($delivery))->toContain($ownDeposit->id)
        ->and(offeredDepositIds($delivery))->not->toContain($otherOrderDeposit->id)
        ->and(offeredDepositIds($delivery))->not->toContain($foreignDeposit->id);

    foreach ([$otherOrderDeposit, $foreignDeposit] as $refused) {
        expect(fn () => \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($delivery->refresh(), [
            'aspo_deposit_id' => $refused->id,
            'amount'          => 10,
        ]))->toThrow(ValidationException::class);
    }

    expect($delivery->depositApplications()->count())->toBe(0);

    $application = \App\Actions\GoodsIn\StockDelivery\ApplyStockDeliveryDeposit::make()->action($delivery->refresh(), [
        'aspo_deposit_id' => $ownDeposit->id,
        'amount'          => 40,
    ]);

    expect((float) $application->amount)->toBe(40.0);

    StoreStockDeliveryFromPurchaseOrder::make()->action($orderTwo->refresh(), ['stock_delivery_id' => $delivery->id]);

    expect(offeredDepositIds($delivery->refresh()))->toContain($ownDeposit->id)
        ->and(offeredDepositIds($delivery))->toContain($otherOrderDeposit->id)
        ->and(offeredDepositIds($delivery))->not->toContain($foreignDeposit->id);
});

test('an agent order joining a stock delivery fills the fields still empty and keeps the ones already set', function () {
    [$orgSupplierOne, $orgSupplierProductOne] = createAgentOrgSupplierWithProduct($this);
    [$orgSupplierTwo, $orgSupplierProductTwo] = createAgentOrgSupplierWithProduct($this);

    $orderOne = createConfirmedAgentOrder($this, $orgSupplierOne, $orgSupplierProductOne, 0, 10);
    $orderTwo = createConfirmedAgentOrder($this, $orgSupplierTwo, $orgSupplierProductTwo, 1, 20);

    $orderOne->update(['data' => array_merge($orderOne->data ?? [], [
        'incoterm'       => null,
        'port_of_export' => 'Shenzhen',
        'port_of_import' => null,
    ])]);
    $orderTwo->update(['data' => array_merge($orderTwo->data ?? [], [
        'incoterm'       => 'CIF',
        'port_of_export' => 'Ningbo',
        'port_of_import' => 'Felixstowe',
    ])]);

    $delivery = StoreStockDeliveryFromPurchaseOrder::make()->action($orderOne->refresh());

    expect(Arr::get($delivery->data, 'port_of_export'))->toBe('Shenzhen')
        ->and(Arr::get($delivery->data, 'incoterm'))->toBeNull();

    $joined = StoreStockDeliveryFromPurchaseOrder::make()->action($orderTwo->refresh(), ['stock_delivery_id' => $delivery->id]);

    expect(Arr::get($joined->data, 'incoterm'))->toBe('CIF')
        ->and(Arr::get($joined->data, 'port_of_import'))->toBe('Felixstowe')
        ->and(Arr::get($joined->data, 'port_of_export'))->toBe('Shenzhen');
});

test('a parcel purchase order keeps its parcel reference and hands it to its stock delivery as the tracking number', function () {
    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this);
    $order = createConfirmedAgentOrder($this, $orgSupplier, $orgSupplierProduct, 0, 10);

    $field = fn () => GetPurchaseOrderData::run($order->refresh())['blueprint'][0]['fields']['parcel_reference'];
    expect($field()['hidden'])->toBeTrue();

    UpdatePurchaseOrder::make()->action($order, ['delivery_type' => 'parcel', 'parcel_reference' => '1Z999AA10123456784']);

    expect($field())->toMatchArray(['hidden' => false, 'value' => '1Z999AA10123456784']);

    $delivery = StoreStockDeliveryFromPurchaseOrder::make()->action($order->refresh());

    expect(Arr::get($delivery->data, 'tracking_number'))->toBe('1Z999AA10123456784');
});

test('the stock delivery timeline takes each purchase order step from the earliest of its linked orders', function () {
    [$orgSupplierOne, $orgSupplierProductOne] = createAgentOrgSupplierWithProduct($this);
    [$orgSupplierTwo, $orgSupplierProductTwo] = createAgentOrgSupplierWithProduct($this);

    $orderOne = createConfirmedAgentOrder($this, $orgSupplierOne, $orgSupplierProductOne, 0, 10);
    $orderTwo = createConfirmedAgentOrder($this, $orgSupplierTwo, $orgSupplierProductTwo, 1, 20);

    $orderOne->update(['created_at' => '2026-03-10 09:00:00', 'submitted_at' => '2026-03-12 09:00:00']);
    $orderTwo->update(['created_at' => '2026-03-05 09:00:00', 'submitted_at' => '2026-03-08 09:00:00']);

    $delivery = StoreStockDeliveryFromPurchaseOrder::make()->action($orderOne->refresh());
    StoreStockDeliveryFromPurchaseOrder::make()->action($orderTwo->refresh(), ['stock_delivery_id' => $delivery->id]);

    $timeline = \App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery::make()->getTimeline($delivery->refresh());

    expect($timeline['purchase_order_'.PurchaseOrderStateEnum::IN_PROCESS->value]['timestamp']->toDateTimeString())->toBe('2026-03-05 09:00:00')
        ->and($timeline['purchase_order_'.PurchaseOrderStateEnum::SUBMITTED->value]['timestamp']->toDateTimeString())->toBe('2026-03-08 09:00:00');
});

test('a money request takes off what the agent already paid the supplier, a transfer can not pay more than is due and removing it opens the request again, and only the agent sets its fee (HELP-4097)', function () {
    $orgAgent          = createFreshOrgAgent($this);
    $order             = UpdatePurchaseOrderStateToSubmitted::make()->action(collect(createAgentPurchaseOrders($this, $orgAgent, 1))->first()->refresh())->refresh();
    $agent             = $orgAgent->agent;
    $agentOrganisation = $agent->organisation;
    $client            = $order->organisation;
    $agentOrganisation->update(['currency_id' => $order->currency_id]);
    $order->update(['cost_extra' => 50]);
    \App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts::run($order);
    $order->refresh();

    $advanceDue = fn () => (float) (collect(\App\Actions\SupplyChain\DepositRequest\GetAgentMoneyRequestLines::run($agent->refresh(), $client)['orders'])->firstWhere('purchase_order_id', $order->id)['advance_due'] ?? 0);
    $advance    = round((float) $order->cost_items * 0.3, 2);
    $paid       = round($advance / 2, 2);

    \App\Actions\SupplyChain\AgentPayment\StoreAgentLedgerEntry::make()->action($agent, ['organisation_id' => $client->id, 'type' => 'supplier_deposit', 'date' => date('Y-m-d'), 'amount' => $paid, 'purchase_order_id' => $order->id]);
    expect(abs($advanceDue() - ($advance - $paid)))->toBeLessThan(0.001);

    $order->updateQuietly(['created_at' => '2026-10-01', 'agent_order_reference' => null]);
    expect($advanceDue())->toBe(0.0);
    $order->updateQuietly(['created_at' => now()]);
    expect(abs($advanceDue() - ($advance - $paid)))->toBeLessThan(0.001);

    actingAs(agentOrganisationAdmin($agentOrganisation, 'agt-mr-'.Str::lower(Str::random(6))));

    $this->post(route('grp.models.agent.money_request.store', $agent->id), ['organisation_id' => $client->id, 'lines' => [['kind' => 'advance', 'purchase_order_id' => $order->id, 'amount' => $advance]]])
        ->assertSessionHasErrors('lines');
    $this->post(route('grp.models.agent.money_request.store', $agent->id), ['organisation_id' => $client->id, 'lines' => [['kind' => 'advance', 'purchase_order_id' => $order->id, 'amount' => $advance - $paid]]])
        ->assertSessionHasNoErrors();
    $depositRequest = \App\Models\SupplyChain\DepositRequest::where('agent_id', $agent->id)->latest('id')->firstOrFail();

    $this->patch(route('grp.models.deposit_request_item.mark_paid', $depositRequest->items()->firstOrFail()->id))->assertNotFound();
    $this->patch(route('grp.models.org_agent.agent_fee.update', $orgAgent->id), ['agent_fee_percentage' => 5])->assertSessionHasNoErrors();
    expect((float) $orgAgent->refresh()->agent_fee_percentage)->toBe(5.0);

    $agentUser = auth()->user();
    $clientUser = agentOrganisationAdmin($client, 'cli-mr-'.Str::lower(Str::random(6)));
    actingAs($clientUser);

    $this->patch(route('grp.models.org_agent.agent_fee.update', $orgAgent->id), ['agent_fee_percentage' => 0])->assertForbidden();
    $this->post(route('grp.models.deposit_request.pay', $depositRequest->id), ['date' => date('Y-m-d'), 'amount' => $advance - $paid + 1])->assertSessionHasErrors('amount');
    $this->post(route('grp.models.deposit_request.pay', $depositRequest->id), ['date' => date('Y-m-d'), 'amount' => $advance - $paid])->assertSessionHasNoErrors();

    $transfer = \App\Models\SupplyChain\AgentPayment::where('deposit_request_id', $depositRequest->id)->firstOrFail();
    expect($depositRequest->refresh()->state)->toBe(\App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum::SETTLED)
        ->and($transfer->isRecordedByClient())->toBeTrue();

    actingAs($agentUser);
    $this->patch(route('grp.models.agent_payment.update', $transfer->id), ['date' => date('Y-m-d'), 'type' => 'transfer_received'])->assertForbidden();
    $this->delete(route('grp.models.agent_payment.delete', $transfer->id))->assertForbidden();

    actingAs($clientUser);
    $this->delete(route('grp.models.agent_payment.delete', $transfer->id))->assertSessionHasNoErrors();
    expect($depositRequest->refresh()->state)->toBe(\App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum::REQUESTED)
        ->and($depositRequest->settled_at)->toBeNull()
        ->and($depositRequest->items()->whereNotNull('paid_at')->exists())->toBeFalse();

    actingAs($agentUser);
    $this->patch(route('grp.models.deposit_request.cancel', $depositRequest->id))->assertSessionHasNoErrors();
    expect($depositRequest->refresh()->state)->toBe(\App\Enums\SupplyChain\AspoDeposit\DepositRequestStateEnum::CANCELLED)
        ->and(abs($advanceDue() - ($advance - $paid)))->toBeLessThan(0.001);
    actingAs($clientUser);

    $walletLine = \App\Actions\SupplyChain\AgentPayment\StoreAgentLedgerEntry::make()->action($agent, ['organisation_id' => $client->id, 'type' => 'transfer_received', 'date' => date('Y-m-d'), 'amount' => 1, 'receipt' => \Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')]);
    $receipt    = \App\Models\Helpers\Media::findOrFail($walletLine->media_id);

    $this->get(route('grp.media.download', $receipt->ulid))->assertOk();
    actingAs(agentOrganisationAdmin($this->orgAgent->agent->organisation, 'agt-rc-'.Str::lower(Str::random(6))));
    $this->get(route('grp.media.download', $receipt->ulid))->assertNotFound();

    actingAs($this->adminGuest->getUser());
});

test('an agent order the agent keeps a ledger for shows its goods, money requested and paid to the factory instead of paying the factory, and the agent accounting dashboard shows the ledger and money requests (HELP-4097)', function () {
    $orgAgent          = createFreshOrgAgent($this);
    $order             = UpdatePurchaseOrderStateToSubmitted::make()->action(collect(createAgentPurchaseOrders($this, $orgAgent, 1))->first()->refresh())->refresh();
    $agent             = $orgAgent->agent;
    $agentOrganisation = $agent->organisation;
    $client            = $order->organisation;
    $agentOrganisation->update(['currency_id' => $order->currency_id]);
    $order->update(['cost_extra' => 50]);
    \App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts::run($order);
    $order->refresh();

    $this->get(route('grp.org.procurement.purchase_orders.show', [$client->slug, $order->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('agent_money', null)->whereNot('payments', null));

    $deposit = \App\Actions\SupplyChain\AgentPayment\StoreAgentLedgerEntry::make()->action($agent, ['organisation_id' => $client->id, 'type' => 'supplier_deposit', 'date' => date('Y-m-d'), 'amount' => 30, 'purchase_order_id' => $order->id]);
    $deposit->update(['needs_review' => true]);

    $this->get(route('grp.org.procurement.purchase_orders.show', [$client->slug, $order->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('payments', null)
            ->where('agent_money.goods', fn ($amount) => abs((float) $amount - (float) $order->cost_items) < 0.001)
            ->where('agent_money.paid_to_suppliers', fn ($amount) => (float) $amount === 30.0)
            ->where('agent_money.to_pay', fn ($amount) => abs((float) $amount - ((float) $order->cost_items - 30)) < 0.001)
            ->where('agent_money.route.name', 'grp.org.procurement.org_agents.show')
            ->where('agent_money.route.parameters.tab', 'money'));

    $advance = collect(\App\Actions\SupplyChain\DepositRequest\GetAgentMoneyRequestLines::run($agent->refresh(), $client)['orders'])->firstWhere('purchase_order_id', $order->id)['advance_due'];

    actingAs(agentOrganisationAdmin($agentOrganisation, 'agt-dash-'.Str::lower(Str::random(6))));

    $this->get(route('grp.org.agent.purchase_orders.show', [$agentOrganisation->slug, $order->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('agent_money.requested', fn ($amount) => (float) $amount === 0.0)
            ->where('agent_money.route.name', 'grp.org.agent.accounting.deposit_requests.create')
            ->where('agent_money.route.parameters.purchase_order', $order->id));

    $this->get(route('grp.org.agent.accounting.deposit_requests.create', [$agentOrganisation->slug, 'client' => $client->slug, 'purchase_order' => $order->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('purchase_order', $order->reference)
            ->where('due.orders', fn ($orders) => collect($orders)->pluck('purchase_order_id')->all() === [$order->id]));

    $this->post(route('grp.models.agent.money_request.store', $agent->id), ['organisation_id' => $client->id, 'lines' => [['kind' => 'advance', 'purchase_order_id' => $order->id, 'amount' => $advance]]])
        ->assertSessionHasNoErrors();

    $this->get(route('grp.org.agent.accounting.dashboard', [$agentOrganisation->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Procurement/AgentAccountingDashboard')
            ->where('routes.ledger.name', 'grp.org.agent.accounting.ledger.index')
            ->where('data.summary.open_requests', 1)
            ->where('data.summary.needs_review', 1)
            ->where('data.summary.clients_owing', 1)
            ->where('data.open_requests.0.outstanding', fn ($amount) => abs((float) $amount - $advance) < 0.001)
            ->where('data.wallets', fn ($wallets) => ($wallet = collect($wallets)->firstWhere('organisation_id', $client->id)) && (float) $wallet['balance'] === -30.0)
            ->where('data.recently_paid.0.purchase_order', $order->reference)
            ->where('data.recently_paid.0.amount', fn ($amount) => (float) $amount === 30.0));

    $this->get(route('grp.org.agent.purchase_orders.show', [$agentOrganisation->slug, $order->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('agent_money.requested', fn ($amount) => abs((float) $amount - $advance) < 0.001));

    actingAs($this->adminGuest->getUser());
});

test('the agent sees the factories several clients order from at once and downloads one combined sheet with a block per client (HELP-4097)', function () {
    $orgAgent          = createFreshOrgAgent($this);
    $agentOrganisation = $orgAgent->agent->organisation;
    $otherClient       = Organisation::where('code', 'prc2')->first()
        ?? StoreOrganisation::make()->action($this->group, array_merge(Organisation::factory()->definition(), ['code' => 'prc2', 'type' => OrganisationTypeEnum::SHOP]));
    $otherOrgAgent     = StoreOrgAgent::make()->action($otherClient, $orgAgent->agent, []);

    [$orgSupplier, $orgSupplierProduct] = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $supplierProduct                    = $orgSupplierProduct->supplierProduct;
    $supplierProduct->update(['minimum_carton_order' => 50]);
    $otherOrgSupplier = OrgSupplier::where('supplier_id', $orgSupplier->supplier_id)->where('org_agent_id', $otherOrgAgent->id)->firstOrFail();

    $place = function (OrgSupplier $orgSupplier, $orgStock, int $units) use ($supplierProduct) {
        $order = StorePurchaseOrder::make()->action($orgSupplier, []);
        StorePurchaseOrderTransaction::make()->action($order, $supplierProduct->historicSupplierProduct, $orgStock, array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => $units]));

        return UpdatePurchaseOrderStateToSubmitted::make()->action($order->refresh())->refresh();
    };
    $order      = $place($orgSupplier, $this->orgStocks[0], 300);
    $otherOrder = $place($otherOrgSupplier, createOrgStocks($otherClient, [$this->stocks[0]])[0], 500);
    $agentOrganisation->update(['currency_id' => $order->currency_id]);

    actingAs(agentOrganisationAdmin($agentOrganisation, 'agt-ss-'.Str::lower(Str::random(6))));

    $row = collect($this->get(route('grp.org.agent.purchase_orders.shared_suppliers', [$agentOrganisation->slug]))
        ->assertOk()->viewData('page')['props']['data']['data'])->firstWhere('id', $orgSupplier->supplier_id);

    expect($row['client_'.$order->organisation_id])->toEqual(round((float) $order->cost_items, 2))
        ->and($row['client_'.$otherClient->id])->toEqual(round((float) $otherOrder->cost_items, 2))
        ->and($row['orders'])->toHaveCount(2)
        ->and($row['products_with_minimum'])->toBe(1)
        ->and($row['products_below_minimum'])->toBe(1);

    $file  = $this->get(route('grp.org.agent.purchase_orders.shared_suppliers.sheet', [$agentOrganisation->slug, $orgSupplier->supplier->slug]))->assertOk()->baseResponse->getFile()->getPathname();
    $cells = collect(\PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet()->toArray())->flatten()->filter()->values();

    expect($cells)->toContain($order->reference)
        ->and($cells)->toContain($otherOrder->reference)
        ->and($cells)->toContain($order->organisation->code.' · '.$order->reference)
        ->and($cells)->toContain('prc2 · '.$otherOrder->reference)
        ->and(collect($row['orders'])->pluck('route.name')->unique()->all())->toBe(['grp.org.agent.purchase_orders.show']);

    actingAs($this->adminGuest->getUser());
});

test('a legacy agent supplier purchase order link redirects to the purchase order it was split into', function () {
    [$orgSupplier] = createAgentOrgSupplierWithProduct($this);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    $newAspo = fn (string $reference, ?int $purchaseOrderId) => AgentSupplierPurchaseOrder::create([
        'group_id'          => $this->group->id,
        'reference'         => $reference,
        'purchase_order_id' => $purchaseOrderId,
        'supplier_id'       => $orgSupplier->supplier_id,
        'currency_id'       => $purchaseOrder->currency_id,
        'state'             => AgentSupplierPurchaseOrderStateEnum::CONFIRMED,
        'delivery_state'    => AgentSupplierPurchaseOrderDeliveryStateEnum::CONFIRMED,
        'date'              => now(),
    ]);

    $splitAspo = $newAspo('ASPOSPLIT'.Str::upper(Str::random(8)), null);
    $orphanAspo = $newAspo('ASPOORPHAN'.Str::upper(Str::random(8)), null);
    PurchaseOrder::whereKey($purchaseOrder->id)->update(['agent_supplier_purchase_order_id' => $splitAspo->id]);

    $this->get(route('grp.supply-chain.agent_supplier_purchase_orders.show', [$splitAspo->slug]))
        ->assertRedirect(route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]));

    $this->get(route('grp.supply-chain.agent_supplier_purchase_orders.show', [$orphanAspo->slug]))
        ->assertNotFound();

    $agentOrganisation = $purchaseOrder->agent->organisation;
    $agentStaff        = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    actingAsUserWithOnlyPermissions($agentStaff, ["procurement.$agentOrganisation->id.view"]);
    $this->get(route('grp.org.agent.agent_supplier_purchase_orders.show', [$agentOrganisation->slug, $splitAspo->slug]))
        ->assertRedirect(route('grp.org.procurement.purchase_orders.show', [$agentOrganisation->slug, $purchaseOrder->slug]));
    $this->followingRedirects()->get(route('grp.org.procurement.agent_supplier_purchase_orders.show', [$agentOrganisation->slug, $splitAspo->slug]))
        ->assertOk();
});

test('purchase order products are downloaded as excel and uploaded from a spreadsheet', function () {
    $supplier    = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $tradeUnit = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    $stock     = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE]));
    SyncStockTradeUnits::run($stock, [$tradeUnit->id => ['quantity' => 1]]);

    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'XLS-01',
        'name'             => 'Spreadsheet product',
        'cost'             => 10,
        'trade_units'      => [$tradeUnit->id],
        'units_per_pack'   => 1,
        'units_per_carton' => 10
    ]);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, Arr::except(PurchaseOrder::factory()->definition(), 'reference'), strict: false);

    $csv = tempnam(sys_get_temp_dir(), 'po-transactions').'.csv';
    file_put_contents($csv, "code,quantity\nxls-01,7\nNOT-SUPPLIED,3\n");
    $upload = ImportPurchaseOrderTransactions::make()->action($purchaseOrder, new \Illuminate\Http\UploadedFile($csv, 'po.csv', 'text/csv', null, true));

    $line = $purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $supplierProduct->id)->first();
    expect($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(1)
        ->and((float) $line->quantity_ordered)->toBe(7.0);

    file_put_contents($csv, "code,quantity\nXLS-01,12\n");
    ImportPurchaseOrderTransactions::make()->action($purchaseOrder, new \Illuminate\Http\UploadedFile($csv, 'po.csv', 'text/csv', null, true));

    expect($purchaseOrder->purchaseOrderTransactions()->count())->toBe(1)
        ->and((float) $line->fresh()->quantity_ordered)->toBe(12.0);

    $export = new \App\Exports\Procurement\PurchaseOrderTransactionsExport($purchaseOrder);
    $exportedRow = array_combine($export->headings(), $export->map($export->collection()->first()));
    expect($exportedRow['code'])->toBe('XLS-01')
        ->and($exportedRow['units_per_carton'])->toBe(10)
        ->and($exportedRow['tariff_code'])->toBe($tradeUnit->tariff_code);

    $this->get(route('grp.org.procurement.purchase_orders.transactions.export', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertOk()
        ->assertDownload();

    UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());

    expect(fn () => ImportPurchaseOrderTransactions::make()->action($purchaseOrder->refresh(), new \Illuminate\Http\UploadedFile($csv, 'po.csv', 'text/csv', null, true)))
        ->toThrow(ValidationException::class);
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

test('org supplier products json answers procurement staff of the organisation and refuses shop staff', function () {
    list(, , $shop) = createShop();
    $user = \App\Models\SysAdmin\User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $url  = route('grp.json.org_supplier_products.index', $this->organisation->slug);

    actingAsUserWithOnlyPermissions($user, ["products.$shop->id.view"]);
    $this->getJson($url)->assertForbidden();

    actingAsUserWithOnlyPermissions($user, ["procurement.{$this->organisation->id}.view"]);
    $this->getJson($url)->assertOk();
});

test('the agent asks the organisation about a new supplier price and confirms only once it is answered', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $orgAgent = createFreshOrgAgent($this);
    $admin    = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    $buyerPermissions = ["procurement.{$this->organisation->id}.view", "procurement.{$this->organisation->id}.edit", 'supply-chain.edit'];
    actingAsUserWithOnlyPermissions($admin, $buyerPermissions);
    [$order, $siblingOrder] = collect(createAgentPurchaseOrders($this, $orgAgent, 2))
        ->map(fn (PurchaseOrder $order) => UpdatePurchaseOrderStateToSubmitted::make()->action($order->refresh())->refresh())
        ->all();
    $order->updateQuietly(['buyer_id' => $admin->id]);
    $agentOrganisation = $orgAgent->agent->organisation;
    $agentUser         = agentOrganisationAdmin($agentOrganisation, 'agent-price-'.Str::lower(Str::random(6)));

    $line         = $order->purchaseOrderTransactions()->firstOrFail();
    $oldUnitCost  = (float) $line->unit_cost;
    $askRoute     = route('grp.models.purchase-order.transaction.price_change.ask', [$order->id, $line->id]);
    $answerRoute  = route('grp.models.purchase-order.transaction.price_change.answer', [$order->id, $line->id]);
    $confirmRoute = route('grp.models.purchase-order.confirm', $order->id);
    $itemLine     = fn (string $url) => collect($this->get($url)->assertOk()->viewData('page')['props']['items']['data'])->firstWhere('id', $line->id);

    actingAs($admin);
    $this->post($askRoute, ['unit_cost' => $oldUnitCost + 1])->assertForbidden();

    actingAs($agentUser);
    expect($itemLine(route('grp.org.agent.purchase_orders.show', [$agentOrganisation->slug, $order->slug, 'tab' => 'items'])))
        ->askPriceRoute->name->toBe('grp.models.purchase-order.transaction.price_change.ask')
        ->answerPriceRoute->toBeNull();

    $this->post($askRoute, ['unit_cost' => $oldUnitCost + 1, 'note' => 'Raw material went up'])->assertSessionHasNoErrors();
    $line->refresh();
    expect($line->price_query_state)->toBe(PurchaseOrderTransactionPriceQueryStateEnum::PENDING)
        ->and((float) $line->price_query_unit_cost)->toEqual($oldUnitCost + 1)
        ->and((float) $line->price_query_previous_unit_cost)->toEqual($oldUnitCost)
        ->and((float) $line->unit_cost)->toEqual($oldUnitCost)
        ->and($line->price_queried_by_id)->toBe($agentUser->id);
    \Illuminate\Support\Facades\Notification::assertSentTo(
        $admin,
        \App\Notifications\PurchaseOrderStageChangedByAgentNotification::class,
        fn ($notification) => $notification->purchaseOrder->is($order) && str_contains($notification->change, $line->supplierProduct->code)
    );

    $this->patch($confirmRoute)->assertSessionHasErrors('price_change');
    expect($order->refresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED)
        ->and($siblingOrder->refresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED);
    $this->patch($answerRoute, ['answer' => 'accepted'])->assertForbidden();

    actingAs($admin);
    $buyerLine = $itemLine(route('grp.org.procurement.purchase_orders.show', [$order->organisation->slug, $order->slug, 'tab' => 'items']));
    expect($buyerLine['askPriceRoute'])->toBeNull()
        ->and($buyerLine['answerPriceRoute']['name'])->toBe('grp.models.purchase-order.transaction.price_change.answer')
        ->and($buyerLine['price_query']['state'])->toBe('pending');

    $this->patch($answerRoute, ['answer' => 'adjusted'])->assertSessionHasErrors('unit_cost');
    $this->patch($answerRoute, ['answer' => 'accepted'])->assertSessionHasNoErrors();
    $line->refresh();
    expect($line->price_query_state)->toBe(PurchaseOrderTransactionPriceQueryStateEnum::ACCEPTED)
        ->and((float) $line->unit_cost)->toEqual($oldUnitCost + 1)
        ->and((float) $line->net_amount)->toEqual(round(($oldUnitCost + 1) * (float) $line->quantity_ordered, 2));
    \Illuminate\Support\Facades\Notification::assertSentTo(
        $agentUser,
        \App\Notifications\PurchaseOrderPriceChangeAnsweredNotification::class,
        fn ($notification) => $notification->purchaseOrderTransaction->is($line) && str_contains($notification->title(), $order->reference)
    );
    $this->patch($answerRoute, ['answer' => 'rejected'])->assertSessionHasErrors('answer');

    actingAs($agentUser);
    $this->post($askRoute, ['unit_cost' => $oldUnitCost + 3])->assertSessionHasNoErrors();
    actingAs($admin);
    $this->patch($answerRoute, ['answer' => 'rejected'])->assertSessionHasNoErrors();
    expect((float) $line->refresh()->unit_cost)->toEqual($oldUnitCost + 1)
        ->and($line->price_query_state)->toBe(PurchaseOrderTransactionPriceQueryStateEnum::REJECTED);

    actingAs($agentUser);
    $this->post($askRoute, ['unit_cost' => $oldUnitCost + 3])->assertSessionHasNoErrors();
    actingAs($admin);
    $this->patch($answerRoute, ['answer' => 'adjusted', 'unit_cost' => $oldUnitCost + 2, 'update_supplier_cost' => true])->assertSessionHasNoErrors();
    expect((float) $line->refresh()->unit_cost)->toEqual($oldUnitCost + 2)
        ->and($line->price_query_state)->toBe(PurchaseOrderTransactionPriceQueryStateEnum::ADJUSTED)
        ->and((float) $line->supplierProduct->refresh()->cost)->toEqual($oldUnitCost + 2);

    $siblingLine = $siblingOrder->purchaseOrderTransactions()->firstOrFail();
    actingAs($agentUser);
    $this->post(route('grp.models.purchase-order.transaction.price_change.ask', [$siblingOrder->id, $siblingLine->id]), ['unit_cost' => (float) $siblingLine->unit_cost + 1])->assertSessionHasNoErrors();
    CancelPurchaseOrderTransaction::make()->action($siblingLine->refresh());
    actingAs($admin);
    $this->patch(route('grp.models.purchase-order.transaction.price_change.answer', [$siblingOrder->id, $siblingLine->id]), ['answer' => 'accepted'])->assertSessionHasErrors('answer');
    expect((float) $siblingLine->refresh()->net_amount)->toEqual(0.0);

    actingAs($agentUser);
    $this->patch($confirmRoute)->assertSessionHasNoErrors();
    expect($order->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED);
    $this->post($askRoute, ['unit_cost' => $oldUnitCost + 5])->assertSessionHasErrors('unit_cost');
    actingAs($this->adminGuest->getUser());
});

test('the agent asks the organisation for a new quantity and confirms only once it is answered', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $orgAgent = createFreshOrgAgent($this);
    $admin    = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    actingAsUserWithOnlyPermissions($admin, ["procurement.{$this->organisation->id}.view", "procurement.{$this->organisation->id}.edit"]);
    [$order, $siblingOrder] = collect(createAgentPurchaseOrders($this, $orgAgent, 2))
        ->map(fn (PurchaseOrder $order) => UpdatePurchaseOrderStateToSubmitted::make()->action($order->refresh())->refresh())
        ->all();
    $order->updateQuietly(['buyer_id' => $admin->id]);
    $agentOrganisation = $orgAgent->agent->organisation;
    $agentUser         = agentOrganisationAdmin($agentOrganisation, 'agent-qty-'.Str::lower(Str::random(6)));

    $line         = $order->purchaseOrderTransactions()->firstOrFail();
    $oldQuantity  = (float) $line->quantity_ordered;
    $unitCost     = (float) $line->unit_cost;
    $askRoute     = route('grp.models.purchase-order.transaction.quantity_change.ask', [$order->id, $line->id]);
    $answerRoute  = route('grp.models.purchase-order.transaction.quantity_change.answer', [$order->id, $line->id]);
    $confirmRoute = route('grp.models.purchase-order.confirm', $order->id);
    $itemLine     = fn (string $url) => collect($this->get($url)->assertOk()->viewData('page')['props']['items']['data'])->firstWhere('id', $line->id);

    actingAs($admin);
    $this->post($askRoute, ['quantity_ordered' => $oldQuantity + 10])->assertForbidden();

    actingAs($agentUser);
    expect($itemLine(route('grp.org.agent.purchase_orders.show', [$agentOrganisation->slug, $order->slug, 'tab' => 'items'])))
        ->askQuantityRoute->name->toBe('grp.models.purchase-order.transaction.quantity_change.ask')
        ->answerQuantityRoute->toBeNull();

    $this->post($askRoute, ['quantity_ordered' => 0])->assertSessionHasErrors('quantity_ordered');
    $this->post($askRoute, ['quantity_ordered' => $oldQuantity + 10, 'note' => 'Supplier only sells full cartons'])->assertSessionHasNoErrors();
    $line->refresh();
    expect($line->quantity_query_state)->toBe(PurchaseOrderTransactionQuantityQueryStateEnum::PENDING)
        ->and((float) $line->quantity_query_quantity)->toEqual($oldQuantity + 10)
        ->and((float) $line->quantity_query_previous_quantity)->toEqual($oldQuantity)
        ->and((float) $line->quantity_ordered)->toEqual($oldQuantity)
        ->and($line->quantity_queried_by_id)->toBe($agentUser->id);
    \Illuminate\Support\Facades\Notification::assertSentTo(
        $admin,
        \App\Notifications\PurchaseOrderStageChangedByAgentNotification::class,
        fn ($notification) => $notification->purchaseOrder->is($order) && str_contains($notification->change, $line->supplierProduct->code)
    );

    $this->patch($confirmRoute)->assertSessionHasErrors('price_change');
    expect($order->refresh()->state)->toBe(PurchaseOrderStateEnum::SUBMITTED);
    $this->patch($answerRoute, ['answer' => 'accepted', 'quantity_ordered' => $oldQuantity + 10])->assertForbidden();

    actingAs($admin);
    $buyerLine = $itemLine(route('grp.org.procurement.purchase_orders.show', [$order->organisation->slug, $order->slug, 'tab' => 'items']));
    expect($buyerLine['askQuantityRoute'])->toBeNull()
        ->and($buyerLine['answerQuantityRoute']['name'])->toBe('grp.models.purchase-order.transaction.quantity_change.answer')
        ->and($buyerLine['quantity_query']['state'])->toBe('pending');
    $pendingRow = collect($this->get(route('grp.org.procurement.purchase_orders.index', $order->organisation->slug))->assertOk()->viewData('page')['props']['data']['data'])
        ->firstWhere('id', $order->id);
    expect($pendingRow['number_pending_line_answers'])->toBe(1);

    $this->patch($answerRoute, ['answer' => 'adjusted', 'quantity_ordered' => $oldQuantity + 10])->assertSessionHasErrors('answer');
    $this->patch($answerRoute, ['answer' => 'accepted', 'quantity_ordered' => $oldQuantity + 20])->assertSessionHasErrors('answer');
    $this->patch($answerRoute, ['answer' => 'accepted', 'quantity_ordered' => $oldQuantity + 10])->assertSessionHasNoErrors();
    $line->refresh();
    expect($line->quantity_query_state)->toBe(PurchaseOrderTransactionQuantityQueryStateEnum::ACCEPTED)
        ->and((float) $line->quantity_ordered)->toEqual($oldQuantity + 10)
        ->and((float) $line->net_amount)->toEqual(round($unitCost * ($oldQuantity + 10), 2));
    \Illuminate\Support\Facades\Notification::assertSentTo(
        $agentUser,
        \App\Notifications\PurchaseOrderQuantityChangeAnsweredNotification::class,
        fn ($notification) => $notification->purchaseOrderTransaction->is($line) && str_contains($notification->title(), $order->reference)
    );
    $this->patch($answerRoute, ['answer' => 'rejected', 'quantity_ordered' => $oldQuantity + 10])->assertSessionHasErrors('answer');

    actingAs($agentUser);
    $this->post($askRoute, ['quantity_ordered' => $oldQuantity + 50])->assertSessionHasNoErrors();
    actingAs($admin);
    $this->patch($answerRoute, ['answer' => 'rejected', 'quantity_ordered' => $oldQuantity + 50])->assertSessionHasNoErrors();
    expect((float) $line->refresh()->quantity_ordered)->toEqual($oldQuantity + 10)
        ->and($line->quantity_query_state)->toBe(PurchaseOrderTransactionQuantityQueryStateEnum::REJECTED);

    $siblingLine = $siblingOrder->purchaseOrderTransactions()->firstOrFail();
    actingAs($agentUser);
    $this->post(route('grp.models.purchase-order.transaction.quantity_change.ask', [$siblingOrder->id, $siblingLine->id]), ['quantity_ordered' => (float) $siblingLine->quantity_ordered + 1])->assertSessionHasNoErrors();
    CancelPurchaseOrderTransaction::make()->action($siblingLine->refresh());
    actingAs($admin);
    $this->patch(route('grp.models.purchase-order.transaction.quantity_change.answer', [$siblingOrder->id, $siblingLine->id]), ['answer' => 'accepted', 'quantity_ordered' => (float) $siblingLine->quantity_query_quantity])->assertSessionHasErrors('answer');

    actingAs($agentUser);
    $this->patch($confirmRoute)->assertSessionHasNoErrors();
    expect($order->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED);
    $this->post($askRoute, ['quantity_ordered' => $oldQuantity + 5])->assertSessionHasNoErrors();
    actingAs($this->adminGuest->getUser());
});

test('the agent cancels the line the supplier can not make, the buyer is told and the rest is confirmed', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $orgAgent = createFreshOrgAgent($this);
    $admin    = User::factory()->create(['group_id' => $this->group->id, 'status' => true]);
    actingAsUserWithOnlyPermissions($admin, ["procurement.{$this->organisation->id}.view", "procurement.{$this->organisation->id}.edit"]);
    [$order]  = createAgentPurchaseOrders($this, $orgAgent, 1);
    $keptLine = $order->purchaseOrderTransactions()->firstOrFail();
    $lostLine = StorePurchaseOrderTransaction::make()->action($order, $keptLine->historicSupplierProduct, $this->orgStocks[1], PurchaseOrderTransaction::factory()->definition());
    $order    = UpdatePurchaseOrderStateToSubmitted::make()->action($order->refresh())->refresh();
    $order->updateQuietly(['buyer_id' => $admin->id]);
    $agentUser = agentOrganisationAdmin($orgAgent->agent->organisation, 'agent-cancel-'.Str::lower(Str::random(6)));

    actingAs($agentUser);
    $this->patchJson(route('grp.models.purchase-order.transaction.cancel', [$order->id, $lostLine->id]))->assertOk();
    $this->patch(route('grp.models.purchase-order.stage', $order->id), [
        'field'  => 'production_started_at',
        'date'   => now($order->organisation->timezone?->name)->toDateString(),
        'reason' => 'No deposit with this factory',
    ])->assertSessionHasNoErrors();

    expect($lostLine->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::CANCELLED)
        ->and($keptLine->refresh()->state)->toBe(PurchaseOrderTransactionStateEnum::CONFIRMED)
        ->and($order->refresh()->state)->toBe(PurchaseOrderStateEnum::CONFIRMED);
    \Illuminate\Support\Facades\Notification::assertSentTo(
        $admin,
        \App\Notifications\PurchaseOrderStageChangedByAgentNotification::class,
        fn ($notification) => $notification->purchaseOrder->is($order) && str_contains($notification->change, 'cancelled '.$lostLine->supplierProduct->code)
    );
    actingAs($this->adminGuest->getUser());
});

test('a purchase order whose supplier was deleted still lists, without a supplier link', function (PurchaseOrder $purchaseOrder) {
    $supplier = $purchaseOrder->parent->supplier;
    $supplier->delete();

    $row = PurchaseOrdersResource::make($purchaseOrder->fresh()->load('parent.supplier'))->resolve();

    $supplier->restore();

    expect($row['supplier_slug'])->toBeNull()
        ->and($row['parent_slug'])->toBe($purchaseOrder->parent->slug);
})->depends('create purchase order independent supplier');

test('packing instructions of a draft order use the current units per carton', function () {
    $supplier    = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    $supplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'CARTON-PDF',
        'name'             => 'Pouch',
        'cost'             => 95,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 1,
        'units_per_carton' => 33,
    ]))->supplierProduct;

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, $this->orgStocks[0], array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 396]));

    UpdateSupplierProduct::make()->action($supplierProduct, ['units_per_carton' => 99], strict: false);

    $unitsPerCarton = [];
    \Mccarlosen\LaravelMpdf\Facades\LaravelMpdf::shouldReceive('loadView')->twice()->andReturnUsing(function ($view, $data) use (&$unitsPerCarton) {
        $unitsPerCarton[] = $data['products']->first()['units_per_carton'];

        return Mockery::mock(['output' => '%PDF']);
    });

    \App\Actions\Procurement\PurchaseOrder\PdfPackingInstructions::make()->handle($purchaseOrder->refresh());

    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    \App\Actions\Procurement\PurchaseOrder\PdfPackingInstructions::make()->handle($purchaseOrder->refresh());

    expect($unitsPerCarton)->toBe([99, 99])
        ->and($purchaseOrder->purchaseOrderTransactions()->first()->historic_supplier_product_id)->toBe($supplierProduct->refresh()->current_historic_supplier_product_id);
});

test('a purchase order whose sample is required cannot ship or be handed over until the sample is approved or the buyer records why it ships without', function () {
    $supplier           = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier        = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct    = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'PO-SAMPLE',
        'name'             => 'Sample required before shipping',
        'cost'             => 150,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    $orgSupplierProduct = StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
    $purchaseOrder      = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orgSupplierProduct->supplierProduct->historicSupplierProduct, $this->orgStocks[0], [...PurchaseOrderTransaction::factory()->definition(), 'quantity_ordered' => 5]);
    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    $purchaseOrder = UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());

    expect($purchaseOrder->sample_required)->toBeFalse();

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['sample_required' => true])->assertSessionHasNoErrors();
    $purchaseOrder->refresh();
    $newDelivery = fn () => collect(ShowPurchaseOrder::make()->getActions($purchaseOrder->refresh(), false))->firstWhere('key', 'new_stock_delivery');

    expect($purchaseOrder->sample_required)->toBeTrue()
        ->and($purchaseOrder->isWaitingForSample())->toBeTrue()
        ->and(fn () => StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh()))->toThrow(ValidationException::class)
        ->and($newDelivery()['disabled_reason'])->toContain($purchaseOrder->reference)
        ->and(GetPurchaseOrderStages::make()->skippedBy($purchaseOrder, 'handed_over_at', GetPurchaseOrderStages::run($purchaseOrder)))->toContain(PurchaseOrderStageEnum::fieldLabels()['sample_approved_at']);

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['sample_override_reason' => 'Repeat of an approved design, the customer needs it now'])->assertSessionHasNoErrors();
    $purchaseOrder->refresh();

    expect($purchaseOrder->isWaitingForSample())->toBeFalse()
        ->and($newDelivery()['disabled_reason'])->toBeNull()
        ->and(Audit::where('auditable_type', 'PurchaseOrder')->where('auditable_id', $purchaseOrder->id)->latest('id')->first()?->new_values['sample_override_reason'] ?? null)->toBe('Repeat of an approved design, the customer needs it now')
        ->and(StoreStockDeliveryFromPurchaseOrder::make()->action($purchaseOrder->refresh()))->toBeInstanceOf(StockDelivery::class);

    $purchaseOrder->updateQuietly(['sample_override_reason' => null, 'sample_approved_at' => now()]);
    expect($purchaseOrder->refresh()->isWaitingForSample())->toBeFalse();
});

test('a draft supplier purchase order already shows whether its sample must be approved before shipping', function () {
    $supplier      = StoreSupplier::make()->action(parent: $this->group, modelData: Supplier::factory()->definition());
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'PO-DRAFT-SAMPLE',
        'name'             => 'Draft sample check',
        'cost'             => 150,
        'stock_id'         => $this->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sampleRequirement.required', false)->where('stages', null));

    $this->patch(route('grp.models.purchase-order.update', $purchaseOrder->id), ['sample_required' => true])->assertSessionHasNoErrors();

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sampleRequirement.required', true)->where('sampleRequirement.approved', false));
});
