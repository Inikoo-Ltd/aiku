<?php

/*
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Thu, 13 Jun 2024 13:27:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\StockDelivery\StoreStockDelivery;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Goods\Stock;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\StockDelivery;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->adminGuest   = createAdminGuest($this->organisation->group);

    $otherOrg = Organisation::skip(1)->take(1)->first();
    if (!$otherOrg) {
        $orgData = Organisation::factory()->definition();
        data_set($orgData, 'code', 'acme2');
        data_set($orgData, 'type', OrganisationTypeEnum::SHOP);
        $otherOrg = StoreOrganisation::make()->action($this->organisation->group, $orgData);
    }

    $this->otherOrg = $otherOrg;

    $agent = Agent::first();
    if (!$agent) {
        $storeData = Agent::factory()->definition();
        $agent     = StoreAgent::make()->action(
            $this->organisation->group,
            $storeData
        );
    }

    $this->agent = $agent;

    $orgAgent = OrgAgent::first();
    if (!$orgAgent) {
        $orgAgent     = StoreOrgAgent::make()->action(
            $this->organisation,
            $this->agent,
            []
        );
    }

    $this->orgAgent = $orgAgent;


    $supplier = Supplier::first();
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
        $stock  = StoreStock::make()->action(
            $this->organisation->group,
            $storeData
        );
    }

    $this->stock = $stock;

    $supplierProduct = SupplierProduct::first();
    if (!$supplierProduct) {
        $storeData = SupplierProduct::factory()->definition();
        data_set($storeData, 'stock_id', $this->stock->id);
        $supplierProduct  = StoreSupplierProduct::make()->action(
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
        $stockDelivery  = StoreStockDelivery::make()->action(
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
        $purchaseOrder  = StorePurchaseOrder::make()->action(
            $this->orgSupplier,
            PurchaseOrder::factory()->definition()
        );
    }

    $this->purchaseOrder = $purchaseOrder;

    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

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
            ->has('flatTreeMaps');

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
                        ->where('title', $this->purchaseOrder->reference)
                        ->etc()
            )
            ->has('tabs')
            ->has('currency')
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
            ->has('tabs');

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
