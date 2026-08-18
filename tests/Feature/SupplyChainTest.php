<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 09:03:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\SupplyChain\SupplierProduct\UI\GetSupplierProductShowcase;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgAgent\UpdateOrgAgent;
use App\Actions\Procurement\OrgSupplier\UpdateOrgSupplier;
use App\Actions\SupplyChain\Agent\DeleteAgent;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Agent\UpdateAgent;
use App\Actions\SupplyChain\Supplier\DeleteSupplier;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Imports\SupplyChain\SupplierProductImport;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgAgentStats;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierStats;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    $this->stocks       = createStocks($this->group);

    $this->adminGuest = createAdminGuest($this->organisation->group);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->getUser());
});

test('create agent', function () {
    $modelData = Agent::factory()->definition();
    $agent     = StoreAgent::make()->action(
        group: $this->group,
        modelData: $modelData
    );

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($this->group->supplyChainStats->number_agents)->toBe(1)
        ->and($this->group->supplyChainStats->number_archived_agents)->toBe(0);


    return $agent;
});

test('update agent', function (Agent $agent) {
    $modelData    = [
        'name'          => 'UpdatedName',
        'delivery_type' => 'parcel',
        'delivery_time' => 45,
        'payment_terms' => '50% upfront',
        'image'         => \Illuminate\Http\UploadedFile::fake()->image('agent.jpg', 200, 200),
    ];
    $updatedAgent = UpdateAgent::make()->action(
        agent: $agent,
        modelData: $modelData
    );

    expect($updatedAgent)->toBeInstanceOf(Agent::class)
        ->and($updatedAgent->name)->toBe('UpdatedName')
        ->and(Arr::get($updatedAgent->data, 'delivery_type'))->toBe('parcel')
        ->and(Arr::get($updatedAgent->data, 'delivery_time'))->toBe(45)
        ->and(Arr::get($updatedAgent->settings, 'payment_terms'))->toBe('50% upfront')
        ->and($updatedAgent->image_id)->not->toBeNull();

    return $updatedAgent;
})->depends('create agent');


test('create another agent', function () {
    $modelData = Agent::factory()->definition();
    $agent     = StoreAgent::make()->action(
        group: $this->group,
        modelData: $modelData
    );

    expect($agent)->toBeInstanceOf(Agent::class)
        ->and($this->group->supplyChainStats->number_agents)->toBe(2)
        ->and($this->group->supplyChainStats->number_archived_agents)->toBe(0);

    return $agent;
});


test('create independent supplier', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($supplier->agent_id)->toBeNull()
        ->and($this->group->supplyChainStats->number_suppliers)->toBe(1);


    return $supplier;
});

test('update supplier', function (Supplier $supplier) {
    $modelData       = [
        'contact_name'  => 'UpdatedName',
        'delivery_type' => 'parcel',
        'delivery_time' => 45,
        'payment_terms' => '50% upfront',
        'image'         => \Illuminate\Http\UploadedFile::fake()->image('supplier.jpg', 200, 200),
    ];
    $updatedSupplier = UpdateSupplier::make()->action(
        supplier: $supplier,
        modelData: $modelData
    );

    expect($updatedSupplier)->toBeInstanceOf(Supplier::class)
        ->and($updatedSupplier->contact_name)->toBe('UpdatedName')
        ->and(Arr::get($updatedSupplier->data, 'delivery_type'))->toBe('parcel')
        ->and(Arr::get($updatedSupplier->data, 'delivery_time'))->toBe(45)
        ->and(Arr::get($updatedSupplier->settings, 'payment_terms'))->toBe('50% upfront')
        ->and($updatedSupplier->image_id)->not->toBeNull();

    return $updatedSupplier;
})->depends('create independent supplier');

test('create independent supplier 2', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($supplier->agent_id)->toBeNull()
        ->and($this->group->supplyChainStats->number_suppliers)->toBe(2);


    return $supplier;
});

test('number independent supplier should be two', function () {
    $this->assertEquals(2, $this->group->supplyChainStats->number_suppliers);
});

test('create supplier in agent', function ($agent) {
    expect($agent->stats->number_suppliers)->toBe(0);

    $supplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition()
    );
    $agent->refresh();
    expect($supplier)->toBeInstanceOf(Supplier::class)
        ->and($agent->stats->number_suppliers)->toBe(1);

    return $supplier;
})->depends('create agent');

test('create supplier product independent supplier', function ($supplier) {
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[0]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $this->assertModelExists($supplierProduct);

    return $supplierProduct;
})->depends('create independent supplier');

test('create supplier product independent supplier 2', function ($supplier) {
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[1]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $this->assertModelExists($supplierProduct);

    return $supplierProduct;
})->depends('create independent supplier');

test('create supplier product in agent supplier', function ($supplier) {
    $supplierProductData = SupplierProduct::factory()->definition();
    data_set($supplierProductData, 'stock_id', $this->stocks[2]->id);
    $supplierProduct = StoreSupplierProduct::make()->action($supplier, $supplierProductData);
    $this->group->refresh();
    expect($supplierProduct)->toBeInstanceOf(SupplierProduct::class)
        ->and($this->group->supplyChainStats->number_supplier_products)->toBe(3)
        ->and($this->group->supplyChainStats->number_independent_supplier_products)->toBe(2)
        ->and($this->group->supplyChainStats->number_supplier_products_in_agents)->toBe(1);
})->depends('create supplier in agent');

test('import supplier product row creates trade unit and stock family', function ($supplier) {
    $upload = Upload::create([
        'group_id'          => $this->group->id,
        'organisation_id'   => $this->organisation->id,
        'model'             => 'SupplierProduct',
        'parent_type'       => $supplier->getMorphClass(),
        'parent_id'         => $supplier->id,
        'original_filename' => 'supplier_products.xlsx',
        'filename'          => 'supplier_products.xlsx',
        'filesize'          => 0,
    ]);

    $import = new SupplierProductImport($supplier, $upload);

    $row = collect([
        'id_supplier_part_key'                => 'new',
        'suppliers_product_code'               => 'IMP-SUP-001',
        'suppliers_unit_description'           => 'Imported unit',
        'family'                               => 'IMP-FAM',
        'part_reference'                       => 'IMP-TU-001',
        'unit_label'                           => 'Imported trade unit',
        'units_per_sko'                        => 12,
        'skos_per_carton'                      => 4,
        'minimum_order_cartons'                => 1,
        'average_delivery_time_days'           => 21,
        'carton_cbm'                           => 0.08,
        'unit_cost'                            => 1.25,
        'unit_extra_costs'                     => 0,
        'unit_recommended_description_website' => 'Imported product description',
        'unit_barcode_ean_13_for_website'      => '5000000000001',
        'unit_weight_kg'                       => 0.5,
        'unit_dimensions_l_x_w_x_h_in_cm'      => '10 x 5 x 3',
        'country_of_origin'                    => 'GBR',
    ]);

    $uploadRecord = $upload->records()->create(['values' => $row->all(), 'row_number' => 2]);
    $import->storeModel($row, $uploadRecord);

    $tradeUnit = TradeUnit::where('group_id', $this->group->id)->where('code', 'IMP-TU-001')->first();
    expect($tradeUnit)->not->toBeNull()
        ->and($tradeUnit->name)->toBe('Imported trade unit')
        ->and($tradeUnit->description)->toBe('Imported product description');

    $stockFamily = StockFamily::where('group_id', $this->group->id)->where('code', 'IMP-FAM')->first();
    expect($stockFamily)->not->toBeNull();

    $supplierProduct = SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'IMP-SUP-001')->first();
    expect($supplierProduct)->not->toBeNull()
        ->and((int)$supplierProduct->tradeUnits()->first()->pivot->quantity)->toBe(12);

    $uploadRecord->refresh();
    expect($uploadRecord->status)->toBe(UploadRecordStatusEnum::COMPLETE->value);

    $secondRow          = clone $row;
    $secondUploadRecord = $upload->records()->create(['values' => $secondRow->all(), 'row_number' => 3]);
    $import->storeModel($secondRow, $secondUploadRecord);

    expect(TradeUnit::where('group_id', $this->group->id)->where('code', 'IMP-TU-001')->count())->toBe(1)
        ->and(StockFamily::where('group_id', $this->group->id)->where('code', 'IMP-FAM')->count())->toBe(1)
        ->and(SupplierProduct::where('supplier_id', $supplier->id)->where('code', 'IMP-SUP-001')->count())->toBe(1);
})->depends('create supplier in agent');


test('UI show suppliers product in supplier', function (SupplierProduct $supplierProduct) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.supplier_products.show', [
        $supplierProduct->supplier->slug,
        $supplierProduct->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProduct')
            ->has('title')
            ->has('pageHead')
            ->has('tabs')
            ->has('breadcrumbs', 4);
    });
})->depends('create supplier product independent supplier');

test('UI show supplier product in supply chain', function (SupplierProduct $supplierProduct) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.show', [
        $supplierProduct->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('SupplyChain/SupplierProduct');
    });

    $showcase = GetSupplierProductShowcase::run($supplierProduct);
    expect($showcase['composition'])->toBeArray();
})->depends('create supplier product independent supplier');


test('create trade unit', function () {
    $tradeUnit = StoreTradeUnit::make()->action(
        $this->group,
        TradeUnit::factory()->definition()
    );
    $this->assertModelExists($tradeUnit);

    return $tradeUnit;
});

test('create org-agent', function ($agent) {
    $orgAgent = StoreOrgAgent::make()->action(
        $this->organisation,
        $agent,
        []
    );

    expect($orgAgent)->toBeInstanceOf(OrgAgent::class)
        ->and($orgAgent->stats)->toBeInstanceOf(OrgAgentStats::class);

    return $orgAgent;
})->depends('create agent');

test('update org-agent', function ($orgAgent) {
    $updatedOrgAgent = UpdateOrgAgent::make()->action(
        $orgAgent,
        [
            'status' => false
        ]
    );

    expect($updatedOrgAgent)->toBeInstanceOf(OrgAgent::class)
        ->and($updatedOrgAgent->status)->toBeFalse();

    return $updatedOrgAgent;
})->depends('create org-agent');

test('the independent supplier is propagated as an org-supplier', function ($supplier) {
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->first();

    expect($orgSupplier)->toBeInstanceOf(OrgSupplier::class)
        ->and($orgSupplier->stats)->toBeInstanceOf(OrgSupplierStats::class);

    return $orgSupplier;
})->depends('create independent supplier');

test('update org-supplier', function ($orgSupplier) {
    $updatedOrgSupplier = UpdateOrgSupplier::make()->action(
        $orgSupplier,
        [
            'status' => false
        ]
    );

    expect($updatedOrgSupplier)->toBeInstanceOf(OrgSupplier::class)
        ->and($updatedOrgSupplier->status)->toBeFalse();

    return $updatedOrgSupplier;
})->depends('the independent supplier is propagated as an org-supplier');

test('delete agent', function () {
    /** @var Agent $agent */
    $agent = Agent::first();

    $deletedAgent = DeleteAgent::make()->action($agent);

    expect(Agent::find($agent->id))->toBeNull();

    return $deletedAgent;
});

test('delete supplier', function () {
    /** @var Supplier $supplier */
    $supplier = Supplier::first();

    $deletedSupplier = DeleteSupplier::make()->action($supplier);

    expect(Supplier::find($supplier->id))->toBeNull();

    return $deletedSupplier;
});

test('UI Index suppliers', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.index'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/Suppliers')
            ->has('title')
            ->has('pageHead')
            ->has('pageHead.actions', 1)
            ->where('pageHead.actions.0.route.name', 'grp.supply-chain.suppliers.create')
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI Index suppliers filtered by type', function (string $element) {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.index', ['elements[type]' => $element]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/Suppliers')
            ->has('data')
            ->has('breadcrumbs', 3);
    });
})->with(['free', 'through_agent', 'archived']);

test('majordomo redirect supplier link', function () {
    $freeSupplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $agent        = StoreAgent::make()->action(
        group: $this->group,
        modelData: Agent::factory()->definition()
    );
    $agentSupplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition()
    );

    $this->get(route('grp.majordomo.redirect_supplier', [$freeSupplier->id]))
        ->assertRedirect(route('grp.supply-chain.suppliers.show', [$freeSupplier->slug]));

    $this->get(route('grp.majordomo.redirect_supplier', [$agentSupplier->id]))
        ->assertRedirect(route('grp.supply-chain.agents.show.suppliers.show', [$agent->slug, $agentSupplier->slug]));
});

test('majordomo redirect supplier product link', function () {
    $freeSupplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition()
    );
    $freeProductData = SupplierProduct::factory()->definition();
    data_set($freeProductData, 'stock_id', $this->stocks[0]->id);
    $freeProduct = StoreSupplierProduct::make()->action($freeSupplier, $freeProductData);

    $agent = StoreAgent::make()->action(
        group: $this->group,
        modelData: Agent::factory()->definition()
    );
    $agentSupplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition()
    );
    $agentProductData = SupplierProduct::factory()->definition();
    data_set($agentProductData, 'stock_id', $this->stocks[1]->id);
    $agentProduct = StoreSupplierProduct::make()->action($agentSupplier, $agentProductData);

    $this->get(route('grp.majordomo.redirect_supplier_product', [$freeProduct->id]))
        ->assertRedirect(route('grp.supply-chain.supplier_products.show', [$freeProduct->slug]));

    $this->get(route('grp.majordomo.redirect_supplier_product', [$agentProduct->id]))
        ->assertRedirect(route('grp.supply-chain.agents.show.supplier_products.show', [$agent->slug, $agentProduct->slug]));
});

test('UI create supplier', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.create'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index suppliers product in supplier', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $response = $this->get(route('grp.supply-chain.suppliers.supplier_products.index', [
        $supplier->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('upload_spreadsheet')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index free supplier products', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.free'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->where('title', 'Free Supplier Products')
            ->has('pageHead.subNavigation', 3)
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI Index supplier products in agents', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.in_agents'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->where('title', 'Agents Supplier Products')
            ->has('pageHead.subNavigation', 3)
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI supply chain dashboard', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.dashboard'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplyChainDashboard')
            ->has('title')
            ->has('pageHead')
            ->has('flatTreeMaps')
            ->has('breadcrumbs', 2);
    });
});

test('UI supply chain control', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.control.dashboard'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplyChainControl')
            ->has('title')
            ->has('pageHead')
            ->has('breadcrumbs', 3)
            ->has('stalled_aspos')
            ->has('deposits_at_risk')
            ->has('pos_without_action')
            ->has('agent_scorecard');
    });
});

test('UI create suppliers product in supplier', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $response = $this->get(route('grp.supply-chain.suppliers.supplier_products.create', [
        $supplier->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 5);
    });
});

test('UI Index suppliers product in agent', function () {
    $this->withoutExceptionHandling();
    $supplier = Supplier::first();
    $supplier->update(['agent_id' => Agent::first()->id]);
    $response = $this->get(route('grp.supply-chain.agents.show.supplier_products.index', [
        $supplier->agent->slug,
        $supplier->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/SupplierProducts')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('upload_spreadsheet')
            ->has('breadcrumbs', 4);
    });
});

test('UI show free supplier has direct procurement navigation', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($supplier) {
        $page
            ->component('SupplyChain/Supplier')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $supplier->name)
                    ->where('subNavigation.2.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
                    ->where('subNavigation.3.route.name', 'grp.supply-chain.suppliers.stock_deliveries.index')
                    ->etc()
            )
            ->where('showcase.stats.1.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
            ->has('tabs');
    });
});

test('UI show agent supplier has agent supplier purchase order navigation', function () {
    $agent = StoreAgent::make()->action(
        group: $this->group,
        modelData: Agent::factory()->definition(),
    );
    $supplier = StoreSupplier::make()->action(
        parent: $agent,
        modelData: Supplier::factory()->definition(),
    );

    $this->get(route('grp.supply-chain.suppliers.show', [$supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageHead.subNavigation.2.route.name', 'grp.supply-chain.suppliers.agent_supplier_purchase_orders.index')
            ->where('showcase.stats.1.route.name', 'grp.supply-chain.suppliers.agent_supplier_purchase_orders.index')
            ->etc());
});

test('UI index purchase orders in free supplier', function () {
    $supplier = StoreSupplier::make()->action(
        parent: $this->group,
        modelData: Supplier::factory()->definition(),
    );
    $this->withoutExceptionHandling();

    $this->get(route('grp.supply-chain.suppliers.purchase_orders.index', [$supplier->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrders')
            ->where('pageHead.subNavigation.2.route.name', 'grp.supply-chain.suppliers.purchase_orders.index')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data'));
});

test('UI index agent supplier purchase orders in supplier', function () {
    $supplier = Supplier::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.agent_supplier_purchase_orders.index', [$supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrders')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI index agent supplier purchase orders in agent', function () {
    $agent = Agent::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show.agent_supplier_purchase_orders.index', [$agent->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/AgentSupplierPurchaseOrders')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data')
            ->has('pageHead.subNavigation');
    });
});

test('UI index stock deliveries in agent', function () {
    $agent = Agent::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show.stock_deliveries.index', [$agent->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI index stock deliveries in supplier', function () {
    $supplier = Supplier::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.suppliers.stock_deliveries.index', [$supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Procurement/StockDeliveries')
            ->has('title')
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('UI show supplier navigation follows the free bucket', function () {
    $this->withoutExceptionHandling();

    $makeSupplier = function (string $code, ?Agent $agent = null) {
        $data = array_merge(Supplier::factory()->definition(), ['code' => $code, 'name' => $code.' name']);

        return StoreSupplier::make()->action(parent: $agent ?? $this->group, modelData: $data);
    };

    $agent = Agent::first();

    $first    = $makeSupplier('NAVSUPA');
    $inAgent  = $agent ? $makeSupplier('NAVSUPB', $agent) : null;
    $middle   = $makeSupplier('NAVSUPC');
    $last     = $makeSupplier('NAVSUPD');

    $this->get(route('grp.supply-chain.suppliers.show', [$middle->slug]).'?bucket=free')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->code)
            ->where('navigation.next.label', $last->code)
            ->etc()
    );

    if ($inAgent) {
        expect($inAgent->agent_id)->not->toBeNull();
    }
});

test('UI edit supplier', function () {
    $agent    = Agent::first();
    $supplier = $agent->suppliers()->first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show.suppliers.edit', [$agent->slug, $supplier->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($supplier) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $supplier->code)
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI edit supplier product', function () {
    $supplierProduct = SupplierProduct::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.supplier_products.edit', [$supplierProduct->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($supplierProduct) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $supplierProduct->code)
                    ->etc()
            )
            ->has('formData.args.updateRoute')
            ->has('formData.blueprint.0.fields.code');
    });
});

test('UI Index agents', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.index'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('SupplyChain/Agents')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('breadcrumbs', 3);
    });
});

test('UI show agent', function () {
    $agent = Agent::first();
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.show', [$agent->slug]));
    $response->assertInertia(function (AssertableInertia $page) use ($agent) {
        $page
            ->component('SupplyChain/Agent')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $agent->organisation->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI create agent', function () {
    $this->withoutExceptionHandling();
    $response = $this->get(route('grp.supply-chain.agents.create'));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New agent')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI get section route group supply chain index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.supply-chain.suppliers.index', []);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::GROUP_SUPPLY_CHAIN->value);
});
