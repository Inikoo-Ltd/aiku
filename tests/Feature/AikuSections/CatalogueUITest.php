<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 21:17:45 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Analytics\GetSectionRoute;
use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Enums\Analytics\AikuSection\AikuSectionEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Analytics\AikuScopedSection;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

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
    $this->artisan('group:seed_aiku_scoped_sections')->assertExitCode(0);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    $this->user->refresh();
    actingAs($this->user);
});

// Department

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

test('UI create department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.create', [$this->organisation->slug, $this->shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI edit department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.org.catalogue.departments.update')
                    ->where('parameters', [
                        'organisation'    => $this->department->organisation_id,
                        'shop'            => $this->department->shop_id,
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

test('UI edit family in department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.families.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 2)
            ->has('pageHead')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                    ->where('name', 'grp.models.org.catalogue.families.update')
                    ->where('parameters', [
                        'organisation'    => $this->family->organisation_id,
                        'shop'            => $this->family->shop_id,
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

test('UI edit sub department in department', function () {
    $response = get(route('grp.org.shops.show.catalogue.departments.show.sub_departments.edit', [$this->organisation->slug, $this->shop->slug, $this->department->slug, $this->subDepartment->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 2)
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI Index catalogue collection', function () {
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

test('UI edit collection', function () {
    $response = get(route('grp.org.shops.show.catalogue.collections.edit', [$this->organisation->slug, $this->shop->slug, $this->collectionModel->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 2)
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
            ->has('formData.blueprint.0.fields', 7)
            ->has('pageHead')
            ->has('formData')
            ->has('breadcrumbs', 4);
    });
});

test('UI create product', function () {
    $response = get(route('grp.org.shops.show.catalogue.families.show.products.create', [$this->organisation->slug, $this->shop->slug, $this->family->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')
            ->has('formData.blueprint.0.fields', 6)
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
