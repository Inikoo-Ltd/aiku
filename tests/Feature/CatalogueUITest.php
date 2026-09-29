<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:17:45 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\ProductCategory\GetDepartmentTimeSeriesStats;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\SalesAnalysis\GetSalesAnalysis;
use App\Actions\Catalogue\SalesAnalysis\SalesAnalysisScope;
use App\Actions\Catalogue\ProductCategory\GetSubDepartmentTimeSeriesStats;
use App\Actions\Catalogue\Shop\SalesTarget\GetShopMonthSalesTarget;
use App\Actions\Catalogue\Shop\SalesTarget\GetShopYearSalesTarget;
use App\Actions\CRM\Customer\GetShopCustomersDashboard;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomersDashboard;
use App\Actions\Catalogue\Shop\SalesTarget\UpdateShopSalesTarget;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dashboards\ShopDashboardSalesTableTabsEnum;
use App\Enums\Dashboards\ShopDashboardSectionsEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Enums\UI\Catalogue\FamilyTabsEnum;
use App\Enums\UI\Catalogue\ProductTabsEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Billables\Charge;
use App\Models\Billables\Service;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\Catalogue\ShopTimeSeries;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Support\Carbon;
use App\Models\SysAdmin\Guest;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

uses()->group('ui');

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->user         = createAdminGuest($this->group)->getUser();


    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;

    $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = createCustomer($this->shop);

    list(
        $this->tradeUnit,
        $this->product
    ) = createProduct($this->shop);

    $this->department = $this->product->department;
    $this->family     = $this->product->family;


    $subDepartment = $this->shop->productCategories()->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->first();
    if (!$subDepartment) {
        $subDepartmentData = ProductCategory::factory()->definition();
        data_set($subDepartmentData, 'type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value);
        $subDepartment = StoreProductCategory::make()->action(
            $this->department,
            $subDepartmentData
        );
    }
    $this->subDepartment = $subDepartment;

    /** @var Collection $collection */
    $collection = Collection::first();
    if (!$collection) {
        data_set($storeData, 'code', 'Test');
        data_set($storeData, 'name', 'Test Name');

        $collection = StoreCollection::make()->action(
            $this->shop,
            $storeData
        );
    }
    $this->collectionModel = $collection;

    $charge = Charge::first();
    if (!$charge) {
        $charge = StoreCharge::make()->action(
            $this->shop,
            [
                'code'        => 'MyFColl',
                'name'        => 'My first charge',
                'type'        => ChargeTypeEnum::HANGING,
                'trigger'     => ChargeTriggerEnum::ORDER,
                'description' => 'Charge description',
                'price'       => fake()->numberBetween(100, 2000),
                'unit'        => 'charge',
            ]
        );
        $this->shop->refresh();
    }
    $this->charge = $charge;

    $service = Service::first();
    if (!$service) {
        $service = StoreService::make()->action(
            $this->shop,
            [
                'code'  => 'MySvc',
                'name'  => 'My first service',
                'price' => fake()->numberBetween(100, 2000),
                'unit'  => 'service',
                'state' => ServiceStateEnum::ACTIVE,
            ]
        );
        $this->shop->refresh();
    }
    $this->service = $service;
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    $this->user->refresh();
    actingAs($this->user);
});



test('UI Index catalogue departments', function () {
    $this->withoutExceptionHandling();

    $response = get(route('grp.org.shops.show.catalogue.departments.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Departments')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI show department', function () {
    $this->withoutExceptionHandling();


    $response = get(route('grp.org.shops.show.catalogue.departments.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug
    ]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Department')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->product->department->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show department sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        'tab'         => DepartmentTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Department')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProductCategory($this->department));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI create department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.create', [$this->organisation->slug, $this->shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI edit department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.product_category.update')
                    ->where('parameters', [
                        'productCategory' => $this->department->id
                    ])
            )
            ->has('breadcrumbs', 3);
    });
});

test('UI Index catalogue family inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.index', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Families')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI Create catalogue family inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.create', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI show family in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Family')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->family->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show family sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        $this->family->slug,
        'tab'         => FamilyTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Family')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProductCategory($this->family));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI edit family in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.product_category.update')
                    ->where('parameters', [
                        'productCategory' => $this->family->id
                    ])
            )
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue product inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.products.index', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});


test('UI Index catalogue family in (tab index)', function () {
    $response = get(route('grp.org.shops.show.catalogue.families.index', [
        $this->organisation->slug,
        $this->shop->slug,
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Families')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue family in (tab sales)', function () {
    $response = get(route('grp.org.shops.show.catalogue.families.index', [
        $this->organisation->slug,
        $this->shop->slug,
        'tab' => 'sales'
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Families')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('sales')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue product in current', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.current_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});

test('UI show product navigation follows the list sort', function () {
    $this->withoutExceptionHandling();

    $makeProduct = function (string $code) {
        $productData = \App\Models\Catalogue\Product::factory()->definition();
        data_set($productData, 'code', $code);
        data_set($productData, 'trade_units', [['id' => $this->product->tradeUnits()->first()->id, 'quantity' => 1]]);
        data_set($productData, 'price', 100);

        return \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->family, $productData);
    };

    $first  = $makeProduct('NAVA01');
    $middle = $makeProduct('NAVB02');
    $last   = $makeProduct('NAVC03');

    $showRoute = fn ($product) => route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $product->slug
    ]);

    get($showRoute($middle).'?bucket_sort=code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->name)
            ->where('navigation.next.label', $last->name)
            ->etc()
    );

    get($showRoute($middle).'?bucket_sort=-code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $last->name)
            ->where('navigation.next.label', $first->name)
            ->etc()
    );
});

test('UI show product navigation skips non-main variants', function () {
    $this->withoutExceptionHandling();

    $makeProduct = function (string $code) {
        $productData = \App\Models\Catalogue\Product::factory()->definition();
        data_set($productData, 'code', $code);
        data_set($productData, 'trade_units', [['id' => $this->product->tradeUnits()->first()->id, 'quantity' => 1]]);
        data_set($productData, 'price', 100);

        return \App\Actions\Catalogue\Product\StoreProduct::make()->action($this->family, $productData);
    };

    $first = $makeProduct('VARA01');
    $last  = $makeProduct('VARC03');

    \App\Actions\Catalogue\Product\StoreProductVariant::run($first, [
        'code'    => 'VARB02',
        'ratio'   => 2,
        'price'   => 200,
        'name'    => $first->name.' 1000u',
        'is_main' => false
    ]);

    $showRoute = fn ($product) => route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $product->slug
    ]);

    get($showRoute($first).'?bucket_sort=code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.next.label', $last->name)
            ->etc()
    );

    get($showRoute($last).'?bucket_sort=code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->name)
            ->etc()
    );
});

test('UI Index catalogue product all', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.all_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue product in process', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.in_process_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});


test('UI Index catalogue product in discontinued', function () {
    $response = get(route('grp.org.shops.show.catalogue.products.discontinued_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});


test('UI Index catalogue products not online', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.products.not_online_products.index', [
        $this->organisation->slug,
        $this->shop->slug
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Products')
            ->has('title')
            ->has('pageHead')
            ->has('data')
            ->has('tabs')
            ->has('index')
            ->has('breadcrumbs', 4);
    });
});


test('UI show product in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.products.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Product')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->product->code)
                    ->etc()
            )
            ->has('tabs');
    });
});


test('UI show product sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        $this->product->slug,
        'tab'         => ProductTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Product')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProduct($this->product));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI Index catalogue sub department inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.index', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/SubDepartments')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI Create catalogue sub department inside department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.create', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI show sub department in department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->subDepartment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/SubDepartment')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->subDepartment->name)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show sub department sales analysis tab', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->department->slug,
        $this->subDepartment->slug,
        'tab'         => DepartmentTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/SubDepartment')
            ->missing('sales_analysis')
            ->loadDeferredProps(
                'sales_analysis',
                fn (AssertableInertia $reload) => $reload
                ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
                ->where('sales_analysis.frequency', 'daily')
                ->has('sales_analysis.breakdown')
                ->has('sales_analysis.stock_outs')
                ->has('sales_analysis.events')
            );
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forProductCategory($this->subDepartment));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI edit sub department in department', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->subDepartment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue collection', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.collections.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Collections')
            ->has('title')
            ->has('breadcrumbs', 4);
    });
});

test('UI Create collection', function () {
    $response = get(route('grp.org.shops.show.catalogue.collections.create', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI show collection', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.collections.show', [$this->organisation->slug, $this->shop->slug, $this->collectionModel->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Collection')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->collectionModel->code)
                    ->etc()
            )
            ->has('tabs');
    });
});

test('UI show collection navigation follows the bucket it was opened from', function () {
    $this->withoutExceptionHandling();

    $makeCollection = function (string $code, CollectionStateEnum $state) {
        $collection = StoreCollection::make()->action($this->shop, [
            'code'        => $code,
            'name'        => $code.' name',
            'description' => $code.' description',
        ]);
        $collection->update(['state' => $state]);

        return $collection->refresh();
    };

    $first    = $makeCollection('NAVCOLA', CollectionStateEnum::ACTIVE);
    $inactive = $makeCollection('NAVCOLB', CollectionStateEnum::INACTIVE);
    $middle   = $makeCollection('NAVCOLC', CollectionStateEnum::ACTIVE);
    $last     = $makeCollection('NAVCOLD', CollectionStateEnum::ACTIVE);

    $showRoute = fn ($collection) => route('grp.org.shops.show.catalogue.collections.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $collection->slug
    ]);

    get($showRoute($middle).'?bucket=active')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->code.' - '.$first->name)
            ->where('navigation.next.label', $last->code.' - '.$last->name)
            ->etc()
    );

    expect($inactive->state)->toBe(CollectionStateEnum::INACTIVE);
});

test('UI edit collection', function () {
    $response = get(route('grp.org.shops.show.catalogue.collections.edit', [$this->organisation->slug, $this->shop->slug, $this->collectionModel->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI edit product', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.families.show.products.edit', [$this->organisation->slug, $this->shop->slug, $this->family->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI edit product composition', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.catalogue.products.all_products.composition', [$this->organisation->slug, $this->shop->slug, $this->product->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/ProductComposition')
            ->has('title')
            ->has('pageHead')
            ->has('formData.blueprint.0.fields.trade_units')
            ->has('breadcrumbs');
    });
});

test('UI create product', function () {
    $response = get(route('grp.org.shops.show.catalogue.families.show.products.create', [$this->organisation->slug, $this->shop->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 5);
    });
});

test('UI Index Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Charges')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('data');
    });
});

test('UI Index Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Billables/Services')
            ->has('title')
            ->has('tabs')
            ->has('breadcrumbs', 3);
    });
});

test('UI create Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.create', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI show Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.show', [$this->organisation->slug, $this->shop->slug, $this->charge->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Catalogue/Charge')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->charge->name)
                    ->etc()
            );
    });
});

test('UI edit Charges', function () {
    $response = get(route('grp.org.shops.show.billables.charges.edit', [$this->organisation->slug, $this->shop->slug, $this->charge->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI create Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.create', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI show Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.show', [$this->organisation->slug, $this->shop->slug, $this->service->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Billables/Service')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('navigation')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $this->service->name)
                    ->etc()
            );
    });
});

test('UI edit Services', function () {
    $response = get(route('grp.org.shops.show.billables.services.edit', [$this->organisation->slug, $this->shop->slug, $this->service->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has('formData');
    });
});

test('UI edit shop with related products description link', function () {
    $masterShop = StoreMasterShop::make()->action($this->group, [
        'code' => 'MS-'.uniqid(),
        'name' => 'Master Shop',
        'type' => ShopTypeEnum::DROPSHIPPING,
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'MD-'.uniqid(),
        'name' => 'Master Department',
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MF-'.uniqid(),
        'name' => 'Master Family',
    ]);

    $this->shop->update([
        'master_shop_id' => $masterShop->id,
    ]);

    $response = get(route('grp.org.shops.show.settings.edit', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($masterShop, $masterFamily) {
        $page
            ->component('EditModel')
            ->has(
                'formData.blueprint.3.fields.related_product_follow_master',
                fn (AssertableInertia $field) => $field
                    ->where('type', 'toggle')
                    ->where('descriptionLinks.manage_related_products.label', 'related products tab')
                    ->where('descriptionLinks.manage_related_products.route.name', 'grp.masters.master_shops.show.master_families.show')
                    ->where('descriptionLinks.manage_related_products.route.parameters.masterShop', $masterShop->slug)
                    ->where('descriptionLinks.manage_related_products.route.parameters.masterFamily', $masterFamily->slug)
                    ->where('descriptionLinks.manage_related_products.route.parameters.tab', 'related_products')
                    ->etc()
            );
    });
});


test('UI get section route catalogue dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.catalogue.dashboard', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_CATALOGUE->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('UI get section route billables charges index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.billables.charges.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_BILLABLES->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('UI get section route shop edit', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.settings.edit', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_SETTINGS->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('UI get section route shop dashboard', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.dashboard.show', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);

    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope)->not->toBeNull()
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_DASHBOARD->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('product index queries use time series aggregation', function () {
    request()->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'test', []));
    expect(\App\Actions\Catalogue\Product\UI\IndexProductsInGroup::make()->handle($this->group)->total())->toBeGreaterThanOrEqual(1)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsInOrganisation::make()->handle($this->organisation)->total())->toBeGreaterThanOrEqual(1)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsInTradeUnit::make()->handle(\App\Models\Goods\TradeUnit::first())->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexOutOfStockProducts::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsWithNoFamily::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexRRPViolationProducts::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\UI\IndexProductsInCollection::make()->handle($this->collectionModel)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\Json\GetProductsInCollection::make()->handle($this->collectionModel)->total())->toBeGreaterThanOrEqual(0)
        ->and(\App\Actions\Catalogue\Product\Json\GetProductsWithNoWebpage::make()->handle($this->shop)->total())->toBeGreaterThanOrEqual(0);
});

test('products export links every image as a jpg', function () {
    config([
        'img-proxy.base_url' => 'https://media.test',
        'img-proxy.key'      => str_repeat('ab', 32),
        'img-proxy.salt'     => str_repeat('cd', 32),
    ]);
    $encodeSource = fn (string $source) => rtrim(strtr(base64_encode($source), '+/', '-_'), '=');

    $product = \App\Models\Catalogue\Product::where('shop_id', $this->shop->id)->where('is_main', true)->whereNull('exclusive_for_customer_id')->first();
    $product->update([
        'web_images' => [
            'all' => [
                ['original' => ['original' => 'https://media.test/signature/'.$encodeSource('local://media/first.jpeg'), 'webp' => 'https://media.test/other/first.webp']],
                ['original' => ['original' => 'https://media.test/signature/'.$encodeSource('local://media/second.png')]],
            ],
        ],
    ]);

    $export = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all', ['code', 'images', 'image_1', 'image_2', 'image_3'], imagesAsJpg: true);
    $row    = $export->mapRow($export->dataQuery()->where('products.id', $product->id)->first());

    $firstJpg  = \App\Actions\Helpers\Images\GetImgProxyUrl::run(new \App\Helpers\ImgProxy\Image()->make('local://media/first.jpeg')->extension('jpg'));
    $secondJpg = \App\Actions\Helpers\Images\GetImgProxyUrl::run(new \App\Helpers\ImgProxy\Image()->make('local://media/second.png')->extension('jpg'));

    expect($firstJpg)->toEndWith('.jpg')
        ->and($secondJpg)->toEndWith('.jpg')
        ->and($row)->toBe([$product->code, "$firstJpg, $secondJpg", $firstJpg, $secondJpg, null]);

    $originalExport = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all', ['image_1']);
    expect($originalExport->mapRow($originalExport->dataQuery()->where('products.id', $product->id)->first()))
        ->toBe(['https://media.test/signature/'.$encodeSource('local://media/first.jpeg')]);
});

test('products export ends with the weight unit columns', function () {
    $product = \App\Models\Catalogue\Product::where('shop_id', $this->shop->id)->where('is_main', true)->whereNull('exclusive_for_customer_id')->first();
    $product->update(['marketing_weight' => 250, 'gross_weight' => null]);

    $export = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all');
    $row    = $export->mapRow($export->dataQuery()->where('products.id', $product->id)->first());

    expect(array_slice($export->headings(), -2))->toBe(['Unit weight (marketing) unit', 'Gross weight unit'])
        ->and(array_slice($row, -2))->toBe(['g', null]);
});

test('UI show product sends the available stock of each part', function () {
    $this->withoutExceptionHandling();
    $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->first();
    $orgStock->update(['quantity_available' => 7]);
    $this->product->orgStocks()->sync([$orgStock->id => ['quantity' => 1]]);

    get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        $this->organisation->slug,
        $this->shop->slug,
        $this->product->slug
    ]))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('showcase.org_stocks.0.id', $orgStock->id)
            ->where('showcase.org_stocks.0.quantity_available', '7')
            ->where('showcase.org_stocks.0.quantity', fn ($quantity) => (float) $quantity === 1.0)
            ->etc()
    );
});

test('customer portfolio showcase does not send the stock of each part', function () {
    $orgStock = \App\Models\Inventory\OrgStock::where('organisation_id', $this->organisation->id)->first();
    $orgStock->update(['quantity_available' => 7]);
    $this->product->orgStocks()->sync([$orgStock->id => ['quantity' => 1]]);
    request()->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', 'portfolio', []))->name('retina.portfolio'));

    $showcase = \App\Actions\Catalogue\Product\UI\GetProductShowcaseInPortfolio::run($this->product);

    expect($showcase['org_stocks'][0]['id'])->toBe($orgStock->id)
        ->and($showcase['org_stocks'][0])->not->toHaveKeys(['quantity', 'quantity_available'])
        ->and($showcase['parts'][0])->not->toHaveKeys(['quantity', 'quantity_available']);
});

test('sales are visible to webmasters but not to staff unrelated to sales', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $newUser = fn () => StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    $unrelated = $newUser();
    $unrelated->givePermissionTo('human-resources.'.$this->organisation->id.'.view');
    actingAs($unrelated);
    get(route('grp.org.shops.index', [$this->organisation->slug]))->assertForbidden();

    $webmaster = $newUser();
    $webmaster->givePermissionTo('web.'.$this->shop->id.'.view');
    actingAs($webmaster);

    get(route('grp.org.shops.index', [$this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Org/Catalogue/Shops'));

    get(route('grp.dashboard.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/GrpDashboard')->has('dashboard.super_blocks', 1));
});

test('shop dashboard sales table shows departments, with brands as an icon on the right', function () {
    $response = get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Org/Catalogue/Shop')
            ->where('dashboard.super_blocks.0.blocks.0.tabs.departments.title', 'Departments')
            ->where('dashboard.super_blocks.0.blocks.0.tabs.sub_departments.title', 'Sub-departments')
            ->has('dashboard.super_blocks.0.month_target.target')
            ->has('dashboard.super_blocks.0.year_target.target')
            ->where('dashboard.super_blocks.0.sections.current', 'target')
            ->where('dashboard.super_blocks.0.sections.navigation', fn ($navigation) => array_keys($navigation->all()) === array_map(
                fn (ShopDashboardSectionsEnum $section) => $section->value,
                ShopDashboardSectionsEnum::forShop($this->shop)
            ));
    });

    $departmentsTable = ShopDashboardSalesTableTabsEnum::DEPARTMENTS->table(
        $this->shop,
        ['departments' => GetDepartmentTimeSeriesStats::run($this->shop)]
    );

    expect($departmentsTable['header']['columns']['label']['formatted_value'])->toBe('Department')
        ->and($departmentsTable)->toHaveKeys(['body', 'totals'])
        ->and(ShopDashboardSalesTableTabsEnum::BRANDS->blueprint())->toMatchArray(['type' => 'icon', 'align' => 'right']);

    $subDepartmentsTable = ShopDashboardSalesTableTabsEnum::SUB_DEPARTMENTS->table(
        $this->shop,
        ['sub_departments' => GetSubDepartmentTimeSeriesStats::run($this->shop)]
    );

    expect($subDepartmentsTable['header']['columns']['label']['formatted_value'])->toBe('Sub-department')
        ->and($subDepartmentsTable)->toHaveKeys(['body', 'totals']);
});

test('shop month sales target defaults to last year plus growth until management sets it', function () {
    $shop  = $this->shop;
    $today = now('UTC')->startOfDay();

    $block = GetShopMonthSalesTarget::run($shop, null, $today);
    $lastYearTotal = $block['last_year_total'];

    expect($block['target']['is_default'])->toBeTrue()
        ->and($block['target']['amount'])->toBe($lastYearTotal > 0 ? round($lastYearTotal * (1 + config('marketing.default_sales_target_growth')), 2) : null)
        ->and($block['chart']['this_year'])->toHaveCount($today->day)
        ->and($block['can_edit'])->toBeFalse();

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 123456.78, 'month' => $today->format('Y-m')]);

    $block = GetShopMonthSalesTarget::run($shop, $this->user, $today);

    expect($block['target']['is_default'])->toBeFalse()
        ->and($block['target']['amount'])->toBe(123456.78)
        ->and($block['gap'])->toBe(round(max(0, 123456.78 - $block['sales_so_far'] - $block['pipeline']['amount']), 2));
});

test('shop year sales target compares the same days last year, January included, and sums monthly targets', function () {
    $shop  = $this->shop;
    $today = Carbon::parse('2031-03-10', 'UTC');

    $seed = function (TimeSeriesFrequencyEnum $frequency, array $salesByPeriod) use ($shop) {
        $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => $frequency]);
        foreach ($salesByPeriod as $period => $sales) {
            $timeSeries->records()->updateOrCreate(
                ['period' => $period, 'frequency' => $frequency->singleLetter()],
                ['sales_org_currency_external' => $sales]
            );
        }
    };

    $seed(TimeSeriesFrequencyEnum::MONTHLY, ['2030-01' => 1000, '2030-02' => 2000, '2030-03' => 3000, '2030-12' => 4000, '2031-01' => 1100, '2031-02' => 2100, '2031-03' => 9999]);
    $seed(TimeSeriesFrequencyEnum::DAILY, ['2030-03-05' => 500, '2030-03-20' => 900, '2031-03-02' => 600, '2031-03-15' => 700]);

    $growth = (float) config('marketing.default_sales_target_growth');
    $block  = GetShopYearSalesTarget::run($shop, null, $today);

    expect($block['sales_so_far'])->toBe(3800.0)
        ->and($block['last_year_so_far'])->toBe(3500.0)
        ->and($block['last_year_total'])->toBe(10000.0)
        ->and($block['remaining_days'])->toBe(296)
        ->and($block['chart']['this_year'])->toBe([1100.0, 3200.0, 3800.0])
        ->and($block['expected'])->toBe(round(3800 + 6500 * (3800 / 3500), 2))
        ->and($block['target']['amount'])->toEqualWithDelta(10000 * (1 + $growth), 0.05)
        ->and($block['target']['is_default'])->toBeTrue()
        ->and($block['can_edit'])->toBeFalse();

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 5000, 'month' => '2031-02']);

    $block = GetShopYearSalesTarget::run($shop, $this->user, $today);

    expect($block['target']['amount'])->toEqualWithDelta(8000 * (1 + $growth) + 5000, 0.05)
        ->and($block['target']['is_default'])->toBeFalse()
        ->and($block['target']['months_set'])->toBe(1);
});

test('shop dashboard tab data serves the sub-departments table', function () {
    getJson(route('grp.org.shops.show.dashboard.tab-data', [$this->organisation->slug, $this->shop->slug, 'tab' => 'sub_departments']))
        ->assertOk()
        ->assertJsonPath('tab', 'sub_departments')
        ->assertJsonPath('table.header.columns.label.formatted_value', 'Sub-department');
});

test('only organisation or group admins can change the shop sales target', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $routeParameters = ['organisation' => $this->shop->organisation_id, 'shop' => $this->shop->id];

    $webmaster = StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();
    $webmaster->givePermissionTo('web.'.$this->shop->id.'.view');
    actingAs($webmaster);
    patchJson(route('grp.models.org.shop.sales_target.update', $routeParameters), ['target_org_currency' => 1])->assertForbidden();

    $admin = $this->user;
    $admin->givePermissionTo('org-admin.'.$this->shop->organisation_id);
    actingAs($admin);
    patchJson(route('grp.models.org.shop.sales_target.update', $routeParameters), ['target_org_currency' => 50000])->assertSuccessful();

    expect(ShopSalesTarget::where('shop_id', $this->shop->id)->where('month', now('UTC')->startOfMonth()->toDateString())->first())
        ->target_org_currency->toBe('50000.00')
        ->set_by_user_id->toBe($admin->id);
});

test('dropshipping shops get sales channels and platforms, wholesale shops get customers', function () {
    $dropshipping = Shop::factory()->make(['type' => ShopTypeEnum::DROPSHIPPING]);
    $wholesale    = Shop::factory()->make(['type' => ShopTypeEnum::B2B]);

    expect(array_keys(ShopDashboardSectionsEnum::navigation($dropshipping)))->toBe(['target', 'sales', 'sales_analysis', 'sales_channels', 'platforms', 'marketing'])
        ->and(array_keys(ShopDashboardSectionsEnum::navigation($wholesale)))->toBe(['target', 'sales', 'sales_analysis', 'customers', 'marketing'])
        ->and(ShopDashboardSectionsEnum::current($wholesale, ['shop_dashboard_section' => 'platforms']))->toBe('target')
        ->and(ShopDashboardSectionsEnum::current($dropshipping, ['shop_dashboard_section' => 'platforms']))->toBe('platforms')
        ->and(ShopDashboardSectionsEnum::current($wholesale, ['shop_dashboard_section' => 'customers'], 'sales_analysis'))->toBe('sales_analysis');
});

test('sales analysis lives in the shop dashboard sales analysis tab', function () {
    get(route('grp.org.shops.show.dashboard.sales_analysis', [$this->organisation->slug, $this->shop->slug, 'from' => '2026-01-01']))
        ->assertRedirect(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => 'sales_analysis', 'from' => '2026-01-01']));

    get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => 'sales_analysis']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('dashboard.super_blocks.0.sections.current', 'sales_analysis')
            ->missing('sales_analysis')
            ->reloadOnly('sales_analysis', fn (AssertableInertia $reload) => $reload->has('sales_analysis')));
});

test('shop customers tab reads the hydrated snapshot and matches sister shop buyers by email', function () {
    $otherShop = Shop::where('id', '!=', $this->shop->id)->where('group_id', $this->shop->group_id)->first()
        ?? StoreShop::make()->action($this->organisation, Shop::factory()->definition());

    $this->customer->update(['email' => 'Twin.Buyer@example.com', 'trade_state' => CustomerTradeStateEnum::MANY]);
    $twin = createCustomer($otherShop);
    $twin->update(['email' => '  twin.buyer@EXAMPLE.com ', 'trade_state' => CustomerTradeStateEnum::ONE]);

    $this->shop->crmStats()->update(['customers_dashboard' => null]);
    expect(GetShopCustomersDashboard::run($this->shop->refresh()))->toBe(['pending' => true]);

    ShopHydrateCustomersDashboard::run($this->shop);
    $data = GetShopCustomersDashboard::run($this->shop->refresh());

    expect($data['base'])->toHaveKeys(['ordered', 'active', 'losing', 'lost', 'never_ordered'])
        ->and($data['this_month'])->toHaveKeys(['registrations', 'registrations_with_orders'])
        ->and($data['problems'])->toHaveKeys(['conversations', 'classified', 'problems', 'by_topic'])
        ->and($data['problems_month_to_date'])->toHaveKeys(['since', 'problems', 'by_topic'])
        ->and($data['sister_shops']['shared_buyers'])->toBeGreaterThanOrEqual(1)
        ->and(collect($data['sister_shops']['shops'])->pluck('code'))->toContain($otherShop->code);

    get(route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => 'customers']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('customers_dashboard')
            ->reloadOnly('customers_dashboard', fn (AssertableInertia $reload) => $reload->has('customers_dashboard.sister_shops')));
});

test('shop dashboard widgets compute only the widgets a tab asks for', function () {
    $response = getJson(route('grp.org.shops.show.dashboard.widgets', [$this->organisation->slug, $this->shop->slug, 'only' => 'top_products,top_families,department_movers,family_movers,out_of_stock,out_of_stock_month,problems_month,customer_actions']))
        ->assertOk();

    expect($response->json())->toHaveKeys(['top_products', 'top_families', 'department_movers.growing', 'department_movers.falling', 'family_movers.period', 'out_of_stock.products', 'out_of_stock.estimated_lost', 'out_of_stock.rows', 'out_of_stock_month.estimated_lost', 'customer_actions.at_risk', 'customer_actions.overdue', 'routes'])
        ->not->toHaveKeys(['marketing', 'email', 'channels']);
});
