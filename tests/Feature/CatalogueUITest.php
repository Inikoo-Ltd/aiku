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
use App\Actions\Catalogue\Shop\SalesTarget\ForecastShopSales;
use App\Actions\Catalogue\Shop\SalesTarget\GetShopMonthSalesTarget;
use App\Actions\Catalogue\Shop\SalesTarget\GetShopYearSalesTarget;
use App\Actions\CRM\Customer\GetShopCustomersDashboard;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomersDashboard;
use App\Actions\Catalogue\Shop\SalesTarget\UpdateShopSalesTarget;
use App\Actions\Catalogue\Shop\Seeders\SeedShopPermissions;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Catalogue\Shop\SalesTarget\GenerateSalesTargetTips;
use App\Actions\Helpers\AI\AskToAi;
use App\Models\Catalogue\SalesTargetTip;
use App\Actions\Accounting\InvoiceCategory\StoreInvoiceCategory;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Models\Accounting\Invoice;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\SysAdmin\GetSectionRoute;
use App\Actions\UI\Dashboards\GetGroupWarehouseDashboardData;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\Catalogue\Shop\UI\GetCatalogueShowcase;
use App\Actions\UI\Grp\Layout\GetShopNavigation;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\SysAdmin\Guest;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
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


test('UI show family attachments tab lists trade unit documents read only', function () {
    get(route('grp.org.shops.show.catalogue.departments.show.families.show', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->family->slug, 'tab' => 'attachments']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/Family')
            ->has('attachments.documents'));
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

    [$code, $images, $firstShort, $secondShort, $thirdShort] = $row;

    expect($code)->toBe($product->code)
        ->and($images)->toBe("$firstShort, $secondShort")
        ->and($thirdShort)->toBeNull()
        ->and($firstShort)->toMatch('#^https?://[^/]+/i/[0-9A-Za-z]{1,11}\.jpg$#')
        ->and(strlen($firstShort))->toBeLessThan(strlen($firstJpg))
        ->and(\App\Actions\Helpers\Images\RedirectImageShortUrl::run(basename($firstShort)))->toBe($firstJpg)
        ->and(\App\Actions\Helpers\Images\RedirectImageShortUrl::run(basename($secondShort)))->toBe($secondJpg)
        ->and($export->mapRow($export->dataQuery()->where('products.id', $product->id)->first()))->toBe($row);

    $this->get($firstShort)->assertRedirect($firstJpg);

    expect(\App\Actions\Helpers\Images\ShortenImgProxyUrls::code($firstJpg, 0))->not->toBe(\App\Actions\Helpers\Images\ShortenImgProxyUrls::code($firstJpg, 1));

    $originalExport = new \App\Exports\Catalogue\ProductsExport($this->shop, 'all', ['image_1']);
    expect($originalExport->mapRow($originalExport->dataQuery()->where('products.id', $product->id)->first()))
        ->toBe(['https://media.test/signature/'.$encodeSource('local://media/first.jpeg')]);
});

test('website pages swap imgproxy urls for short signed links that serve the same image', function () {
    config([
        'img-proxy.base_url' => 'https://media.test',
        'img-proxy.key'      => str_repeat('ab', 32),
        'img-proxy.salt'     => str_repeat('cd', 32),
    ]);

    $media = \App\Models\Helpers\Media::create([
        'group_id'              => $this->organisation->group_id,
        'ulid'                  => (string) Str::ulid(),
        'uuid'                  => (string) Str::uuid(),
        'name'                  => 'shot',
        'file_name'             => 'a1b2c3d4.jpeg',
        'mime_type'             => 'image/jpeg',
        'disk'                  => 'local',
        'collection_name'       => 'images',
        'size'                  => 4,
        'manipulations'         => [],
        'custom_properties'     => [],
        'generated_conversions' => [],
        'responsive_images'     => [],
    ]);

    $image    = fn () => new \App\Helpers\ImgProxy\Image()->make($media->getImgProxyFilename());
    $original = \App\Actions\Helpers\Images\GetImgProxyUrl::run($image());
    $thumb    = \App\Actions\Helpers\Images\GetImgProxyUrl::run($image()->resize(0, 600)->extension('avif'));
    $page     = ['blocks' => [['web_images' => ['original' => $original, 'avif' => $thumb]], ['srcset' => "$thumb 1x, $original 2x"]], 'other' => 'https://media.test/x/y'];

    $website           = new \App\Models\Web\Website(['settings' => []]);
    $shorten           = fn () => \App\Actions\Helpers\Images\ShortenWebsiteImageUrls::run($page, $website, 'https://www.shop.test/some/page');
    expect($shorten())->toBe($page);

    $website->settings = ['short_image_urls' => true];
    $short             = $shorten();
    $shortThumb        = $short['blocks'][0]['web_images']['avif'];
    $shortOriginal     = $short['blocks'][0]['web_images']['original'];

    expect($shortThumb)->toMatch('#^https://www\.shop\.test/i/[0-9a-z]+/[A-Za-z0-9_-]{8}/0x600\.avif$#')
        ->and($shortOriginal)->toMatch('#^https://www\.shop\.test/i/[0-9a-z]+/[A-Za-z0-9_-]{8}\.jpeg$#')
        ->and(strlen($shortThumb))->toBeLessThan(strlen($thumb) - 40)
        ->and($short['blocks'][1]['srcset'])->toBe("$shortThumb 1x, $shortOriginal 2x")
        ->and($short['other'])->toBe('https://media.test/x/y');

    $serve = fn (string $short) => \App\Actions\Helpers\Images\ServeWebsiteShortImage::make()->handle(...array_pad(explode('/', Str::after($short, '/i/'), 3), 3, ''));
    expect($serve($shortThumb))->toBe($thumb)
        ->and($serve($shortOriginal))->toBe($original)
        ->and($serve(str_replace('0x600', 'rs::0:600::', $shortThumb)))->toBe($thumb)
        ->and($serve(str_replace('0x600', '0x1200', $shortThumb)))->toBeNull()
        ->and($serve(str_replace('.avif', '.png', $shortThumb)))->toBeNull();

    \Illuminate\Support\Facades\Http::fake(['media.test/*' => \Illuminate\Support\Facades\Http::response('avif-bytes', 200, ['Content-Type' => 'image/avif'])]);
    $this->get(Str::after($shortThumb, 'https://www.shop.test'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/avif')
        ->assertSee('avif-bytes');
    $this->get(Str::after($shortOriginal, 'https://www.shop.test'))->assertOk();
    \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->url() === $thumb);
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

test('shop links on the dashboards open the shop dashboard on the target tab, whatever tab the user looked at last', function () {
    $originalSettings = $this->user->settings;
    $this->user->update(['settings' => array_merge($originalSettings ?? [], ['shop_dashboard_section' => ShopDashboardSectionsEnum::SALES->value])]);

    $targetUrl = route('grp.org.shops.show.dashboard.show', [$this->organisation->slug, $this->shop->slug, 'section' => ShopDashboardSectionsEnum::TARGET->value]);

    get(route('grp.majordomo.redirect_shops_from_dashboard', $this->shop->id))->assertRedirect($targetUrl);

    get($targetUrl)->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.super_blocks.0.sections.current', 'target'));

    $this->user->update(['settings' => $originalSettings]);
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

test('catalogue top of the month links to the department, family and product with their counts', function () {
    $this->shop->stats->update([
        'top_1m_department_id' => $this->department->id,
        'top_1m_family_id'     => $this->family->id,
        'top_1m_product_id'    => $this->product->id,
    ]);

    $topSelling = GetCatalogueShowcase::run($this->shop->fresh())['top_selling'];
    $shopParameters = ['organisation' => $this->organisation->slug, 'shop' => $this->shop->slug];

    expect($topSelling['department']['route'])->toBe([
        'name'       => 'grp.org.shops.show.catalogue.departments.show',
        'parameters' => [...$shopParameters, 'department' => $this->department->slug],
    ])
        ->and($topSelling['department']['counts'])->toBe([
            'families' => $this->department->stats->number_current_families,
            'products' => $this->department->stats->number_current_products,
        ])
        ->and($topSelling['family']['route']['parameters']['family'])->toBe($this->family->slug)
        ->and($topSelling['family']['counts'])->toBe(['products' => $this->family->stats->number_current_products])
        ->and($topSelling['product']['route']['name'])->toBe('grp.org.shops.show.catalogue.products.all_products.show')
        ->and($topSelling['product']['route']['parameters']['product'])->toBe($this->product->slug);

    get(route($topSelling['department']['route']['name'], $topSelling['department']['route']['parameters']))->assertOk();
    get(route($topSelling['family']['route']['name'], $topSelling['family']['route']['parameters']))->assertOk();
    get(route($topSelling['product']['route']['name'], $topSelling['product']['route']['parameters']))->assertOk();
});

test('shop top menu links to the target section of the shop dashboard', function () {
    $shopNavigation = GetShopNavigation::run($this->shop, $this->user)['dashboard'];
    $target         = collect($shopNavigation['topMenu']['subSections'])->filter()->first();

    expect($shopNavigation['route']['parameters']['section'])->toBe(ShopDashboardSectionsEnum::TARGET->value);

    expect($target['root'])->toBe('grp.org.shops.show.dashboard.show')
        ->and($target['route']['name'])->toBe('grp.org.shops.show.dashboard.show')
        ->and($target['route']['parameters']['section'])->toBe(ShopDashboardSectionsEnum::TARGET->value);

    get(route($target['route']['name'], $target['route']['parameters']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('dashboard.super_blocks.0.sections.current', ShopDashboardSectionsEnum::TARGET->value));
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
        ->and($block['chart']['weekly_versus_last_year'])->toHaveCount(10)
        ->and(last($block['chart']['weekly_versus_last_year']))->toBe(['x' => 2.323, 'y' => 20.0])
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

test('organisation target adds up its shops, leaving closed shops out of the target', function () {
    $secondShop = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $closedShop = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $closedShop->update(['state' => ShopStateEnum::CLOSED]);
    $today = Carbon::parse('2034-06-10', 'UTC');

    $seed = function (Shop $shop, TimeSeriesFrequencyEnum $frequency, array $salesByPeriod) {
        $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => $frequency]);
        foreach ($salesByPeriod as $period => $sales) {
            $timeSeries->records()->updateOrCreate(
                ['period' => $period, 'frequency' => $frequency->singleLetter()],
                ['sales_org_currency_external' => $sales]
            );
        }
    };

    $seed($this->shop, TimeSeriesFrequencyEnum::MONTHLY, ['2033-06' => 1000]);
    $seed($this->shop, TimeSeriesFrequencyEnum::DAILY, ['2033-06-05' => 400, '2034-06-03' => 300]);
    $seed($secondShop, TimeSeriesFrequencyEnum::MONTHLY, ['2033-06' => 2000]);
    $seed($secondShop, TimeSeriesFrequencyEnum::DAILY, ['2033-06-05' => 600, '2034-06-03' => 700]);
    $seed($closedShop, TimeSeriesFrequencyEnum::MONTHLY, ['2033-06' => 5000]);
    $seed($closedShop, TimeSeriesFrequencyEnum::DAILY, ['2033-06-05' => 100]);

    $growth = (float) config('marketing.default_sales_target_growth');
    $month  = GetShopMonthSalesTarget::run($this->organisation, $this->user, $today);

    $shopChildren = collect($month['children'])->keyBy('key');

    expect($month['sales_so_far'])->toBe(1000.0)
        ->and($month['last_year_so_far'])->toBe(1100.0)
        ->and($month['target']['amount'])->toEqualWithDelta(1000 * (1 + $growth), 0.05)
        ->and($month['target']['is_sum_of_shops'])->toBeTrue()
        ->and($month['selection_setting'])->toBe('organisation_target_shop_'.$this->organisation->id)
        ->and($shopChildren->has((string) $closedShop->id))->toBeFalse()
        ->and($shopChildren[(string) $secondShop->id])->toMatchArray(['name' => $secondShop->name, 'sales_so_far' => 700.0, 'last_year_total' => 600.0])
        ->and($shopChildren[(string) $secondShop->id]['target']['amount'])->toEqualWithDelta(600 * (1 + $growth), 0.05)
        ->and($shopChildren[(string) $secondShop->id]['link']['parameters'])->toMatchArray(['shop' => $secondShop->slug, 'section' => 'target'])
        ->and($month['can_edit'])->toBeFalse()
        ->and($month['update_route'])->toBeNull()
        ->and(GetShopYearSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta(3000 * (1 + $growth), 0.05);

    UpdateShopSalesTarget::make()->action($secondShop, ['target_org_currency' => 5000, 'month' => '2034-06']);

    expect(GetShopMonthSalesTarget::run($this->organisation, null, $today)['target'])->toMatchArray(['is_default' => false])
        ->and(GetShopMonthSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta(400 * (1 + $growth) + 5000, 0.05)
        ->and(GetShopYearSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta(1000 * (1 + $growth) + 5000, 0.05);

    get(route('grp.org.dashboard.show', $this->organisation->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('dashboard.super_blocks.0.month_target.target')->has('dashboard.super_blocks.0.year_target.target'));

    $secondShop->update(['state' => ShopStateEnum::CLOSED]);
});

test('expected month and year end add the TimesFM forecast of the days left, drawn with its likely range, and fall back to last year without one', function () {
    $shop  = $this->shop;
    $today = Carbon::parse('2036-05-10', 'UTC');

    $daily = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY]);
    for ($day = Carbon::parse('2036-04-01'); $day->lt($today); $day->addDay()) {
        $daily->records()->updateOrCreate(
            ['period' => $day->toDateString(), 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()],
            ['sales_org_currency_external' => $day->day === 3 ? 300 : 50, 'sales_grp_currency_external' => 40]
        );
    }

    config(['services.timesfm.url' => 'http://timesfm.test', 'services.timesfm.token' => 'secret']);
    Http::fake(['timesfm.test/forecast' => fn ($request) => Http::response([
        'version' => '3',
        'deciles' => array_fill(0, count($request['series']), array_fill(0, $request['horizon'], [-20, 40, 60, 80, 100, 120, 140, 160, 220])),
    ])]);

    expect(ForecastShopSales::run($today))->toBeGreaterThanOrEqual(1);

    Http::assertSent(fn ($request) => $request['horizon'] === 22 && $request->hasHeader('Authorization', 'Bearer secret'));
    Http::assertSent(fn ($request) => $request['horizon'] === 34);

    $variance = round((220 / 2.563) ** 2, 2);
    $forecast = $shop->stats->fresh()->sales_forecast;
    expect($forecast['version'])->toBe('3')
        ->and($forecast['from'])->toBe('2036-05-10')
        ->and($forecast['org'])->toHaveCount(22 + 214)
        ->and($forecast['org']['2036-05-10'])->toEqual([102.22, $variance])
        ->and($forecast['org']['2036-06-01'])->toEqual([14.6, round($variance / 7, 2)])
        ->and(array_key_last($forecast['org']))->toBe('2036-12-31');

    $salesSoFar = 300 + 8 * 50;
    $block      = GetShopMonthSalesTarget::run($shop, null, $today);
    $line       = $block['chart']['forecast'];
    expect($block['sales_so_far'])->toEqual($salesSoFar)
        ->and($block['expected'])->toEqualWithDelta($salesSoFar + 21 * 102.22, 0.01)
        ->and($line['expected'][8])->toBeNull()
        ->and($line['expected'][9])->toEqual($salesSoFar)
        ->and($line['expected'][30])->toEqualWithDelta($block['expected'], 0.01)
        ->and($line['low'][30])->toBeGreaterThan($salesSoFar)->toBeLessThan($line['expected'][30])
        ->and($line['high'][30])->toEqualWithDelta($line['expected'][30] + 1.2816 * 1.5 * sqrt(21 * $variance), 0.05);

    $year = GetShopYearSalesTarget::run($shop, null, $today);
    expect($year['expected'])->toEqualWithDelta($year['sales_so_far'] + 21 * 102.22 + 214 * 14.6, 0.05)
        ->and($year['chart']['forecast']['expected'][3])->toEqual(round($year['sales_so_far'] - $salesSoFar, 2))
        ->and($year['chart']['forecast']['expected'][11])->toEqualWithDelta($year['expected'], 0.05)
        ->and($year['chart']['forecast']['high'][11])->toBeGreaterThan($year['expected']);

    $shopChild = collect(GetShopMonthSalesTarget::run($this->organisation, null, $today)['children'])->firstWhere('key', (string) $shop->id);
    $nextMonth = GetShopMonthSalesTarget::run($shop, null, Carbon::parse('2036-06-02', 'UTC'));
    expect($shopChild['expected'])->toEqualWithDelta($salesSoFar + 21 * 102.22, 0.01)
        ->and($nextMonth['expected'])->toEqual(0)
        ->and($nextMonth['chart']['forecast'])->toBeNull();

    config(['services.timesfm.url' => null]);
    expect(ForecastShopSales::run($today))->toBe(0);

    $shop->stats->update(['sales_forecast' => null, 'sales_forecast_hydrated_at' => null]);
});

test('group target adds up every organisation in the group currency', function () {
    $today = Carbon::parse('2036-03-10', 'UTC');

    $seed = function (Shop $shop, TimeSeriesFrequencyEnum $frequency, array $salesByPeriod) {
        $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => $frequency]);
        foreach ($salesByPeriod as $period => $sales) {
            $timeSeries->records()->updateOrCreate(
                ['period' => $period, 'frequency' => $frequency->singleLetter()],
                ['sales_org_currency_external' => $sales * 10, 'sales_grp_currency_external' => $sales]
            );
        }
    };

    $seed($this->shop, TimeSeriesFrequencyEnum::MONTHLY, ['2035-03' => 2000]);
    $seed($this->shop, TimeSeriesFrequencyEnum::DAILY, ['2035-03-05' => 800, '2036-03-03' => 900]);

    $growth = (float) config('marketing.default_sales_target_growth');
    $month  = GetShopMonthSalesTarget::run($this->group, $this->user, $today);

    expect($month['sales_so_far'])->toBe(900.0)
        ->and($month['last_year_so_far'])->toBe(800.0)
        ->and($month['currency_code'])->toBe($this->group->currency->code)
        ->and($month['selection_setting'])->toBe('group_target_organisation')
        ->and(collect($month['children'])->sum('target.amount'))->toEqualWithDelta($month['target']['amount'], 0.05)
        ->and(collect($month['children'])->firstWhere('key', (string) $this->organisation->id)['currency_code'])->toBe($this->group->currency->code)
        ->and($month['target']['amount'])->toEqualWithDelta(800 * (1 + $growth), 0.05)
        ->and($month['target']['is_sum_of_shops'])->toBeTrue()
        ->and($month['can_edit'])->toBeFalse()
        ->and(GetShopYearSalesTarget::run($this->group, null, $today)['target']['amount'])->toEqualWithDelta(2000 * (1 + $growth), 0.05);

    get(route('grp.dashboard.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('dashboard.super_blocks.0.month_target.target')->has('dashboard.super_blocks.0.year_target.target'));
});

test('a shop selling under several invoice categories targets their sum, partners included, each category taking its share of the shop target until set', function () {
    $shop     = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $customer = createCustomer($shop);
    $today    = Carbon::parse('2038-05-10', 'UTC');
    $growth   = (float) config('marketing.default_sales_target_growth');

    $category = fn (string $name) => StoreInvoiceCategory::make()->action($this->organisation, [
        'name'        => $name,
        'type'        => InvoiceCategoryTypeEnum::VIP->value,
        'currency_id' => $this->organisation->currency_id,
    ]);
    $retail   = $category('Retail '.uniqid());
    $partners = $category('Partners '.uniqid());

    $invoice = function (string $date, int $invoiceCategoryId, float $amount) use ($customer) {
        $invoice = StoreInvoice::make()->action($customer, [...Invoice::factory()->definition(), 'date' => $date, 'in_process' => false]);
        DB::table('invoices')->where('id', $invoice->id)->update(['invoice_category_id' => $invoiceCategoryId, 'org_net_amount' => $amount, 'in_process' => false]);
    };
    $invoice('2037-05-12', $retail->id, 750);
    $invoice('2037-05-20', $partners->id, 250);
    $invoice('2038-05-03', $retail->id, 300);
    $invoice('2038-05-04', $partners->id, 200);

    $timeSeries = ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY]);
    $timeSeries->records()->updateOrCreate(['period' => '2037-05-12', 'frequency' => 'D'], ['sales_org_currency_external' => 750, 'sales_org_currency_internal' => 0]);
    $timeSeries->records()->updateOrCreate(['period' => '2037-05-20', 'frequency' => 'D'], ['sales_org_currency_external' => 0, 'sales_org_currency_internal' => 250]);
    $timeSeries->records()->updateOrCreate(['period' => '2038-05-03', 'frequency' => 'D'], ['sales_org_currency_external' => 300, 'sales_org_currency_internal' => 0]);
    $timeSeries->records()->updateOrCreate(['period' => '2038-05-04', 'frequency' => 'D'], ['sales_org_currency_external' => 0, 'sales_org_currency_internal' => 200]);

    $block = GetShopMonthSalesTarget::run($shop, $this->user, $today);
    $byCategory = collect($block['children'])->keyBy('invoice_category_id');

    expect($block['sales_so_far'])->toBe(500.0)
        ->and($block['last_year_total'])->toBe(1000.0)
        ->and($block['target']['amount'])->toEqualWithDelta(1000 * (1 + $growth), 0.05)
        ->and($block['target']['is_sum_of_categories'])->toBeTrue()
        ->and($block['selected_child'])->toBe('all')
        ->and($block['selection_setting'])->toBe('shop_target_category_'.$shop->id)
        ->and($byCategory[$retail->id])->toMatchArray(['key' => (string) $retail->id, 'name' => $retail->name, 'sales_so_far' => 300.0, 'last_year_total' => 750.0, 'can_edit' => true])
        ->and($byCategory[$retail->id]['target']['is_share'])->toBeTrue()
        ->and($byCategory[$retail->id]['chart']['this_year'])->toHaveCount(10)
        ->and($byCategory[$retail->id]['target']['amount'])->toEqualWithDelta(750 * (1 + $growth), 0.05)
        ->and($byCategory[$partners->id]['target']['amount'])->toEqualWithDelta(250 * (1 + $growth), 0.05);

    $organisationTarget = GetShopMonthSalesTarget::run($this->organisation, null, $today)['target']['amount'];

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 500, 'month' => '2038-05', 'invoice_category_id' => $partners->id]);

    $block = GetShopMonthSalesTarget::run($shop, $this->user, $today);

    expect($block['target']['amount'])->toEqualWithDelta(750 * (1 + $growth) + 500, 0.05)
        ->and(collect($block['children'])->firstWhere('invoice_category_id', $partners->id)['target'])->toMatchArray(['amount' => 500.0, 'is_share' => false])
        ->and(GetShopMonthSalesTarget::run($this->organisation, null, $today)['target']['amount'])->toEqualWithDelta($organisationTarget + 500 - 250 * (1 + $growth), 0.05);

    UpdateShopSalesTarget::make()->action($shop, ['target_org_currency' => 2000, 'month' => '2038-05']);

    expect(collect(GetShopMonthSalesTarget::run($shop, null, $today)['children'])->firstWhere('invoice_category_id', $retail->id)['target']['amount'])->toEqualWithDelta(1500, 0.05)
        ->and(GetShopYearSalesTarget::run($shop, null, $today)['target']['months_set'])->toBe(1);

    actingAs($this->user)->patchJson(route('grp.models.profile.update'), ['settings' => ['shop_target_category_'.$shop->id => (string) $partners->id]])->assertSuccessful();

    expect(GetShopMonthSalesTarget::run($shop, $this->user->fresh(), $today)['selected_child'])->toBe((string) $partners->id);

    $movedToOwnShop = $category('Faire '.uniqid());
    DB::table('invoice_categories')->where('id', $movedToOwnShop->id)->update(['settings' => json_encode(['shop_ids' => [$shop->id + 1000]])]);
    $invoice('2037-05-15', $movedToOwnShop->id, 400);
    Cache::tags(["dashboard-shop-$shop->id"])->flush();

    expect(collect(GetShopMonthSalesTarget::run($shop, null, $today)['children'])->pluck('invoice_category_id'))->not->toContain($movedToOwnShop->id);

    $shop->stats->update(['sales_forecast' => ['version' => '3', 'from' => '2038-05-10', 'org' => collect(range(10, 31))->mapWithKeys(fn (int $day) => [sprintf('2038-05-%02d', $day) => [10.0, 4.0]])->all(), 'grp' => null]]);
    $block      = GetShopMonthSalesTarget::run($shop, null, $today);
    $byCategory = collect($block['children'])->keyBy('invoice_category_id');

    expect($block['expected'])->toEqual(500 + 21 * 10)
        ->and(array_sum(array_column($block['children'], 'expected')))->toEqualWithDelta(500 + 21 * 10, 0.01)
        ->and($byCategory[$retail->id]['expected'])->toEqualWithDelta(300 + 210 * 630 / 1050, 0.01);

    $shop->update(['state' => ShopStateEnum::CLOSED]);
});

test('each morning a tip on reaching the target is written for the shop and shown on its target block', function () {
    $shop  = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $today = Carbon::parse('2039-05-06', 'UTC');

    ShopTimeSeries::firstOrCreate(['shop_id' => $shop->id, 'frequency' => TimeSeriesFrequencyEnum::DAILY])
        ->records()->updateOrCreate(['period' => '2038-05-12', 'frequency' => 'D'], ['sales_org_currency_external' => 1000]);

    AskToAi::shouldRun()->once()->andReturn('Call the customers due to reorder today.');

    expect(GenerateSalesTargetTips::run($shop, $today))->toBe(1);

    $growth = (float) config('marketing.default_sales_target_growth');
    $tip    = SalesTargetTip::where('shop_id', $shop->id)->sole();
    $block  = GetShopMonthSalesTarget::run($shop, null, $today);

    expect($tip->invoice_category_id)->toBeNull()
        ->and($tip->facts['target'])->toEqualWithDelta(1000 * (1 + $growth), 0.05)
        ->and($tip->facts)->toHaveKeys(['customers_due_to_reorder_within_a_week', 'open_baskets_amount', 'customers_who_bought_same_month_last_year_not_yet_this_month'])
        ->and($block['tip'])->toBe('Call the customers due to reorder today.')
        ->and($block['needed_per_day'])->toEqualWithDelta(1000 * (1 + $growth) / 25, 0.05)
        ->and($block['needed_this_week'])->toEqualWithDelta(1000 * (1 + $growth) * 3 / 26, 0.05)
        ->and(GetShopMonthSalesTarget::run($shop, null, $today->copy()->addDay())['tip'])->toBeNull();

    $shop->update(['state' => ShopStateEnum::CLOSED]);
});

test('group warehouse overview derives its numbers from the hydrated stats', function () {
    $orderingStats = [
        'number_delivery_notes_state_unassigned'       => 2,
        'number_delivery_notes_state_queued'            => 3,
        'number_delivery_notes_state_handling'         => 4,
        'number_delivery_notes_state_handling_blocked' => 1,
        'number_delivery_notes_state_picked'           => 5,
        'number_delivery_notes_state_packing'          => 6,
        'number_delivery_notes_state_packed'           => 7,
        'number_delivery_notes_state_finalised'        => 8,
    ];
    $procurementStats = [
        'number_stock_deliveries_state_confirmed'      => 1,
        'number_stock_deliveries_state_ready_to_ship'  => 2,
        'number_stock_deliveries_state_dispatched'     => 3,
        'number_stock_deliveries_state_received'       => 4,
        'number_stock_deliveries_state_checked'        => 5,
        'number_stock_deliveries_state_booking_in'     => 6,
        'number_open_purchase_orders'                  => 7,
    ];
    foreach ([$this->group, $this->organisation] as $owner) {
        $owner->orderHandlingStats()->update($orderingStats);
        $owner->procurementStats()->update($procurementStats);
    }

    Cache::forget("group-warehouse-stock-health:{$this->group->id}");
    $currentOrgStocks = DB::table('org_stocks')
        ->where('group_id', $this->group->id)
        ->whereIn('state', [OrgStockStateEnum::ACTIVE->value, OrgStockStateEnum::DISCONTINUING->value])
        ->where('quantity_status', OrgStockQuantityStatusEnum::OUT_OF_STOCK->value)
        ->count();

    $overview = GetGroupWarehouseDashboardData::run($this->group->refresh());

    expect($overview['totals']['work'])->toBe([
        'waiting'           => 5,
        'picking'           => 4,
        'blocked'           => 1,
        'packing'           => 11,
        'ready_to_ship'     => 15,
    ])
        ->and($overview['totals']['goods_in'])->toBe([
            'confirmed'            => 1,
            'on_the_way'           => 5,
            'to_book_in'           => 9,
            'booking_in'           => 6,
            'open_purchase_orders' => 7,
        ])
        ->and($overview['totals']['stock_health'])->toHaveKeys(['out_of_stock', 'critical', 'low', 'ideal', 'excess', 'error'])
        ->and($overview['totals']['stock_health']['out_of_stock'])->toBe($currentOrgStocks);

    $organisationRow = collect($overview['organisations'])->firstWhere('slug', $this->organisation->slug);
    expect($organisationRow['work']['waiting'])->toBe(5)
        ->and($organisationRow['routes']['goods_in']['name'])->toBe('grp.org.procurement.stock_deliveries.index');

    get(route('grp.dashboard.show'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('warehouseOverview.totals.work')->has('warehouseOverview.organisations'));
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

test('a compliance editor sees supply chain, goods and products, and edits only compliance information', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $newUser = fn () => StoreGuest::make()->action(
        $this->group,
        array_merge(Guest::factory()->definition(), ['positions' => []])
    )->getUser();

    $tradeUnit  = $this->product->tradeUnits()->first();
    $compliance = $newUser();
    $compliance->givePermissionTo(['compliance.view', 'compliance.edit']);
    actingAs($compliance);

    get(route('grp.supply-chain.supplier_products.index'))->assertOk();
    get(route('grp.goods.trade-units.show', [$tradeUnit->slug]))->assertOk();
    get(route('grp.goods.trade-units.edit', [$tradeUnit->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('formData.blueprint', fn ($blueprint) => collect($blueprint)->pluck('fields')->collapse()->keys()->doesntContain('name')
                && collect($blueprint)->pluck('fields')->collapse()->has('gpsr_warnings')));

    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_warnings' => 'Keep away from children'])->assertSessionHasNoErrors();
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['name' => 'Renamed by compliance'])->assertSessionHasErrors('name');
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_manual' => 'Read first', 'name' => 'Renamed by compliance'])->assertSessionHasErrors('name');
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['label_info_approved' => true])->assertSessionHasErrors('label_info_approved');
    patch(route('grp.models.product.update', $this->product->id), ['marketing_weight' => 321])->assertSessionHasNoErrors();
    patch(route('grp.models.product.update', $this->product->id), ['price' => 1])->assertSessionHasErrors('price');

    expect($tradeUnit->refresh()->gpsr_warnings)->toBe('Keep away from children')
        ->and($tradeUnit->name)->not->toBe('Renamed by compliance')
        ->and((int) $this->product->refresh()->marketing_weight)->toBe(321);

    expect($tradeUnit->refresh()->gpsr_manual)->not->toBe('Read first');

    $compliance->givePermissionTo('compliance.publish');
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['label_info_approved' => true])->assertSessionHasNoErrors();

    $viewer = $newUser();
    $viewer->givePermissionTo('compliance.view');
    actingAs($viewer);
    get(route('grp.goods.trade-units.show', [$tradeUnit->slug]))->assertOk();
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_warnings' => 'x'])->assertForbidden();

    $unrelated = $newUser();
    $unrelated->givePermissionTo('human-resources.'.$this->organisation->id.'.view');
    actingAs($unrelated);

    patch(route('grp.models.product.update', $this->product->id), ['price' => 1])->assertForbidden();
    patch(route('grp.models.trade-unit.update', $tradeUnit->id), ['gpsr_warnings' => 'x'])->assertForbidden();
});

test('only goods, masters or media editors change trade unit media, and attachments follow the edit permission of their model', function () {
    setPermissionsTeamId($this->group->id);
    SeedShopPermissions::run($this->shop);
    $newUser = fn (array $permissions) => tap(
        StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), ['positions' => []]))->getUser()
    )->givePermissionTo($permissions);

    $tradeUnit = $this->product->tradeUnits()->first();
    $pdf       = fn () => ['attachments' => [\Illuminate\Http\UploadedFile::fake()->create('terms.pdf', 10, 'application/pdf')], 'scope' => 'Other'];

    actingAs($newUser(["crm.{$this->shop->id}.edit"]));
    $this->post(route('grp.models.customer.attachment.attach', ['customer' => $this->customer->id]), $pdf())->assertSessionHasNoErrors();
    $attachment = $this->customer->attachments()->first();
    expect($attachment)->not->toBeNull();

    $mediaRoutes = [
        ['post', route('grp.models.trade-unit.upload_images', $tradeUnit->id)],
        ['post', route('grp.models.trade-unit.attach_images', $tradeUnit->id)],
        ['post', route('grp.models.trade-unit.upload_audio', $tradeUnit->id)],
        ['patch', route('grp.models.trade-unit.update_images', $tradeUnit->id)],
        ['patch', route('grp.models.trade-unit.update_image_alt', [$tradeUnit->id, $attachment->id])],
        ['delete', route('grp.models.trade-unit.detach_image', [$tradeUnit->id, $attachment->id])],
    ];

    actingAs($newUser(['goods.view', 'masters.view', "crm.{$this->shop->id}.view"]));
    foreach ($mediaRoutes as [$method, $url]) {
        $this->{$method}($url)->assertForbidden();
    }
    $this->post(route('grp.models.customer.attachment.attach', ['customer' => $this->customer->id]), $pdf())->assertForbidden();
    $this->delete(route('grp.models.customer.attachment.detach', [$this->customer->id, $attachment->id]))->assertForbidden();
    expect($this->customer->attachments()->count())->toBe(1);

    foreach (['goods.edit', 'masters.edit', 'group-webmaster.media-edit'] as $permission) {
        actingAs($newUser([$permission]));
        $this->post(route('grp.models.trade-unit.upload_images', $tradeUnit->id))->assertSessionHasErrors('images');
        $this->post(route('grp.models.trade-unit.upload_audio', $tradeUnit->id))->assertSessionHasErrors('audio');
    }

    actingAs($newUser(["crm.{$this->shop->id}.edit"]));
    $this->delete(route('grp.models.customer.attachment.detach', [$this->customer->id, $attachment->id]))->assertSuccessful();
    expect($this->customer->attachments()->count())->toBe(0);
});

test('accounts can edit billables, staff without product or accounting edit cannot', function () {
    setPermissionsTeamId($this->group->id);
    $newUser = function (array $permissions) {
        $user = StoreGuest::make()->action(
            $this->group,
            array_merge(Guest::factory()->definition(), ['positions' => []])
        )->getUser();
        $user->givePermissionTo($permissions);

        return $user->refresh();
    };

    actingAs($newUser(["accounting.{$this->organisation->id}.view"]));
    get(route('grp.org.shops.show.billables.services.show', [$this->organisation->slug, $this->shop->slug, $this->service->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pageHead.actions.0', false));
    patch(route('grp.models.shop.services.update', $this->service->id), ['name' => 'Viewer rename'])->assertForbidden();
    patch(route('grp.models.charge.update', $this->charge->id), ['name' => 'Viewer rename'])->assertForbidden();

    actingAs($newUser(["accounting.{$this->organisation->id}.view", "accounting.{$this->organisation->id}.edit"]));
    get(route('grp.org.shops.show.billables.services.show', [$this->organisation->slug, $this->shop->slug, $this->service->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pageHead.actions.0.style', 'edit'));
    get(route('grp.org.shops.show.billables.services.edit', [$this->organisation->slug, $this->shop->slug, $this->service->slug]))->assertOk();
    patch(route('grp.models.shop.services.update', $this->service->id), ['name' => 'Accounts rename'])->assertSessionHasNoErrors();
    patch(route('grp.models.charge.update', $this->charge->id), ['name' => 'Accounts rename'])->assertSessionHasNoErrors();

    expect($this->service->refresh()->name)->toBe('Accounts rename')
        ->and($this->charge->refresh()->name)->toBe('Accounts rename');
});

test('catalogue top listed and top sold tabs read the hourly rankings with the same totals as the live query', function () {
    $platform = $this->group->platforms()->where('type', \App\Enums\Ordering\Platform\PlatformTypeEnum::MANUAL)->firstOrFail();
    $customers = [$this->customer, createCustomer($this->shop)];
    foreach ($customers as $index => $customer) {
        $channel = \App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel::make()->action($customer, $platform, ['reference' => 'rankings-'.$index]);
        \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action($channel, $this->product, []);
    }

    \App\Actions\Catalogue\RebuildCatalogueRankings::run();
    actingAs($this->user);

    $rows = function (string $url, string $tab, array $columns) {
        return collect(get($url)->assertOk()->viewData('page')['props'][$tab]['data'])
            ->map(fn (array $row) => array_map(fn ($value) => is_numeric($value) ? (float) $value : $value, \Illuminate\Support\Arr::only($row, $columns)))
            ->sortBy('id')->values()->all();
    };

    $everything = '20000101-20991231';
    foreach (
        [
            route('grp.catalogue.show'),
            route('grp.org.shops.show.catalogue.dashboard', [$this->organisation->slug, $this->shop->slug]),
        ] as $url
    ) {
        foreach (
            [
                'top_listed_families' => [['id', 'total_listed', 'total_customers'], 'created_at'],
                'top_listed_products' => [['id', 'total_listed', 'total_customers'], 'created_at'],
                'top_sold_products'   => [['id', 'total_sold', 'total_amount'], 'date'],
            ] as $tab => [$columns, $dateColumn]
        ) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $ranked    = $rows($url.$separator.'tab='.$tab, $tab, $columns);
            $live      = $rows($url.$separator.'tab='.$tab.'&between['.$dateColumn.']='.$everything, $tab, $columns);

            expect($ranked)->toBe($live);
        }
    }

    $listedFamilies = $rows(route('grp.catalogue.show').'?tab=top_listed_families', 'top_listed_families', ['id', 'total_listed', 'total_customers']);
    expect(collect($listedFamilies)->firstWhere('id', (float) $this->family->id))->toMatchArray(['total_listed' => 2.0, 'total_customers' => (float) collect($customers)->unique('id')->count()]);
});

test('platform and country top listed and top sold tabs count portfolios and invoice lines against the product they belong to', function () {
    $platform = $this->group->platforms()->where('type', \App\Enums\Ordering\Platform\PlatformTypeEnum::MANUAL)->firstOrFail();
    actingAs($this->user);

    $platformUrl = fn (string $tab) => route('grp.org.shops.show.crm.platforms.show', [$this->organisation->slug, $this->shop->slug, $platform->slug, 'tab' => $tab]);
    $rowFor      = fn (string $url, string $tab, int $id) => collect(get($url)->assertOk()->viewData('page')['props'][$tab]['data'])->firstWhere('id', $id) ?? [];

    $before = [
        'families' => $rowFor($platformUrl('top_listed_families'), 'top_listed_families', $this->family->id)['total_listed'] ?? 0,
        'products' => $rowFor($platformUrl('top_listed_products'), 'top_listed_products', $this->product->asset_id)['total_listed'] ?? 0,
        'sold'     => $rowFor($platformUrl('top_sold_products'), 'top_sold_products', $this->product->asset_id)['total_sold'] ?? 0,
    ];

    $customer = \App\Actions\CRM\Customer\StoreCustomer::make()->action($this->shop, \App\Models\CRM\Customer::factory()->definition());
    DB::table('customers')->where('id', $customer->id)->update(['location' => json_encode(['AQ', 'Antarctica', ''])]);

    foreach ([0, 1] as $index) {
        $channel = \App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel::make()->action($customer, $platform, ['reference' => 'platform-top-'.$index]);
        \App\Actions\Dropshipping\Portfolio\StorePortfolio::make()->action($channel, $this->product, []);

        $invoice = StoreInvoice::make()->action($customer, [...Invoice::factory()->definition(), 'in_process' => false]);
        \App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction::make()->action($invoice, $this->product->historicAsset, [
            'date'            => now(),
            'tax_category_id' => $invoice->tax_category_id,
            'quantity'        => 3,
            'gross_amount'    => 10,
            'net_amount'      => 10,
        ]);
    }

    expect($rowFor($platformUrl('top_listed_families'), 'top_listed_families', $this->family->id)['total_listed'])->toEqual($before['families'] + 2)
        ->and($rowFor($platformUrl('top_listed_products'), 'top_listed_products', $this->product->asset_id))->toMatchArray(['code' => $this->product->code, 'total_listed' => $before['products'] + 2])
        ->and($rowFor($platformUrl('top_sold_products'), 'top_sold_products', $this->product->asset_id))->toMatchArray(['code' => $this->product->code, 'total_sold' => (float) ($before['sold'] + 6)]);

    $countryUrl = route('grp.org.shops.show.crm.countries.show', [$this->organisation->slug, $this->shop->slug, 'AQ', 'tab' => 'top_products']);
    expect(get($countryUrl)->assertOk()->viewData('page')['props']['top_products']['data'])->toHaveCount(1)
        ->and($rowFor($countryUrl, 'top_products', $this->product->asset_id))->toMatchArray(['code' => $this->product->code, 'total_sold' => 6, 'total_amount' => 20]);
});
