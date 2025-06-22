<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Apr 2025 21:23:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */



/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\StoreMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\HydrateMasterShop;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Masters\MasterShop\UpdateMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetOrderingIntervals;
use App\Models\Masters\MasterAssetOrderingStats;
use App\Models\Masters\MasterAssetSalesIntervals;
use App\Models\Masters\MasterAssetStats;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterProductCategoryOrderingIntervals;
use App\Models\Masters\MasterProductCategoryOrderingStats;
use App\Models\Masters\MasterProductCategorySalesIntervals;
use App\Models\Masters\MasterProductCategoryStats;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterShopOrderingIntervals;
use App\Models\Masters\MasterShopOrderingStats;
use App\Models\Masters\MasterShopSalesIntervals;
use App\Models\Masters\MasterShopStats;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});


beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();
    Config::set("inertia.testing.page_paths", [resource_path("js/Pages/Grp")]);
    actingAs($this->adminGuest->getUser());
});



test("UI Index Master Shops", function () {
    $response = get(
        route("grp.masters.master_shops.index")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterShops")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has("data");
    });
});


test('create master shop', function () {
    $masterShop = StoreMasterShop::make()->action(
        $this->group,
        [
            'code' => "SHOP1",
            'name' => "shop1",
            'type' => ShopTypeEnum::DROPSHIPPING
        ]
    );

    $masterShop->refresh();
    $group = $masterShop->group;

    expect($masterShop)->toBeInstanceOf(MasterShop::class)
        ->and($masterShop->stats)->toBeInstanceOf(MasterShopStats::class)
        ->and($masterShop->orderingStats)->toBeInstanceOf(MasterShopOrderingStats::class)
        ->and($masterShop->orderingIntervals)->toBeInstanceOf(MasterShopOrderingIntervals::class)
        ->and($masterShop->salesIntervals)->toBeInstanceOf(MasterShopSalesIntervals::class)
        ->and($masterShop->timeSeries()->count())->toBe(5)
        ->and($masterShop)->not->toBeNull()
        ->and($masterShop->code)->toBe('SHOP1')
        ->and($masterShop->name)->toBe('shop1')
        ->and($masterShop->group_id)->toBe($this->group->id)
        ->and($masterShop->type)->toBe(ShopTypeEnum::DROPSHIPPING)
        ->and($masterShop->status)->toBeTrue()
        ->and($group->goodsStats->number_master_shops)->toBe(1)
        ->and($group->goodsStats->number_current_master_shops)->toBe(1);

    return $masterShop;
});

test("UI Show master shop", function (MasterShop $masterShop) {
    $this->withoutExceptionHandling();
    $response  = get(
        route("grp.masters.master_shops.show", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($masterShop) {
        $page
            ->component("Masters/MasterShop")
            ->has("title")
            ->has("breadcrumbs", 4)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                $page->where("title", $masterShop->name)
                        ->has('subNavigation')
                        ->etc()
            )
            ->has("tabs");
    });
})->depends('create master shop');

test('update master shop', function (MasterShop $masterShop) {
    $updatedMasterShop = UpdateMasterShop::make()->action(
        $masterShop,
        [
            'name' => "shop2",
            'type' => ShopTypeEnum::FULFILMENT,
        ]
    );

    $updatedMasterShop->refresh();

    expect($updatedMasterShop)->toBeInstanceOf(MasterShop::class)
        ->and($updatedMasterShop)->not->toBeNull()
        ->and($updatedMasterShop->name)->toBe('shop2')
        ->and($updatedMasterShop->type)->toBe(ShopTypeEnum::FULFILMENT);

    UpdateMasterShop::make()->action(
        $masterShop,
        [
            'status' => false
        ]
    );
    $group = $masterShop->group;
    expect($group->goodsStats->number_master_shops)->toBe(1)
        ->and($group->goodsStats->number_current_master_shops)->toBe(0);
})->depends('create master shop');


test('create master shop from command', function () {
    $this->artisan('master_shop:create', [
        'group' => $this->group->slug,
        'type'  => ShopTypeEnum::DROPSHIPPING,
        'code'  => 'ds',
        'name'  => 'Dropshipping class'
    ])->assertExitCode(0);


    $group = $this->group->refresh();

    expect($group->goodsStats->number_master_shops)->toBe(2)
        ->and($group->goodsStats->number_current_master_shops)->toBe(1);
});

test('assign master shop to shop', function () {
    $masterShop = MasterShop::first();
    UpdateShop::make()->action(
        $this->shop,
        [
            'master_shop_id' => $masterShop->id
        ]
    );
    $masterShop->refresh();

    expect($masterShop->stats->number_shops)->toBe(1)
        ->and($masterShop->stats->number_current_shops)->toBe(0);
});

test("UI Index Master Departments", function (MasterShop $masterShop) {
    $response = get(
        route("grp.masters.master_shops.show.master_departments.index", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterDepartments")
            ->has("title")
            ->has("breadcrumbs", 5)
            ->has("data")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                    $page->has('subNavigation')->etc()
            );
    });
})->depends('create master shop');

test("UI Master Dashboard", function () {
    $response = get(
        route("grp.masters.dashboard")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MastersDashboard")
            ->has("title")
            ->has("breadcrumbs", 2)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                    $page->has('title')->etc()
            );
    });
});

test("UI Index Master Families", function (MasterShop $masterShop) {
    $response = get(
        route("grp.masters.master_shops.show.master_families.index", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterFamilies")
            ->has("title")
            ->has("breadcrumbs", 5)
            ->has("data")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                    $page->has('subNavigation')->etc()
            );
    });
})->depends('create master shop');

test("UI Index Master SubDepartments", function (MasterShop $masterShop) {
    $response = get(
        route("grp.masters.master_shops.show.sub-departments.index", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterSubDepartments")
            ->has("title")
            ->has("breadcrumbs", 2)
            ->has("data")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                    $page->has('subNavigation')->etc()
            );
    });
})->depends('create master shop')->todo();

test('create master product category', function (MasterShop $masterShop) {
    $masterProductCategory = StoreMasterProductCategory::make()->action(
        $masterShop,
        [
            'code' => 'PRODUCT_CATEGORY1',
            'name' => 'product category 1',
            'type' => MasterProductCategoryTypeEnum::DEPARTMENT
        ]
    );

    $masterProductCategory->refresh();
    $masterShop->refresh();

    expect($masterProductCategory)->toBeInstanceOf(MasterProductCategory::class)
        ->and($masterProductCategory->stats)->toBeInstanceOf(MasterProductCategoryStats::class)
        ->and($masterProductCategory->orderingStats)->toBeInstanceOf(MasterProductCategoryOrderingStats::class)
        ->and($masterProductCategory->orderingIntervals)->toBeInstanceOf(MasterProductCategoryOrderingIntervals::class)
        ->and($masterProductCategory->salesIntervals)->toBeInstanceOf(MasterProductCategorySalesIntervals::class)
        ->and($masterProductCategory->timeSeries()->count())->toBe(5)
        ->and($masterProductCategory)->not->toBeNull()
        ->and($masterProductCategory->code)->toBe('PRODUCT_CATEGORY1')
        ->and($masterProductCategory->name)->toBe('product category 1')
        ->and($masterProductCategory->master_shop_id)->toBe($masterShop->id)
        ->and($masterProductCategory->group_id)->toBe($this->group->id)
        ->and($masterProductCategory->type)->toBe(MasterProductCategoryTypeEnum::DEPARTMENT)
        ->and($masterShop->stats->number_master_product_categories_type_department)->toBe(1)
        ->and($masterShop->stats->number_current_master_product_categories_type_department)->toBe(1);

    return $masterProductCategory;
})->depends("create master shop");

test('update master product category', function (MasterProductCategory $masterProductCategory) {
    $updatedMasterProductCategory = UpdateMasterProductCategory::make()->action(
        $masterProductCategory,
        [
            'code' => 'PRODUCT_CATEGORY2',
            'name' => 'product category 2',
            'status' => false
        ]
    );

    $updatedMasterProductCategory->refresh();
    $masterShop = $updatedMasterProductCategory->masterShop;
    expect($updatedMasterProductCategory)->toBeInstanceOf(MasterProductCategory::class)
        ->and($updatedMasterProductCategory)->not->toBeNull()
        ->and($updatedMasterProductCategory->code)->toBe('PRODUCT_CATEGORY2')
        ->and($updatedMasterProductCategory->name)->toBe('product category 2')
        ->and($masterShop->stats->number_master_product_categories_type_department)->toBe(1)
        ->and($masterShop->stats->number_current_master_product_categories_type_department)->toBe(0);
})->depends("create master product category");

test('create master asset', function (MasterShop $masterShop) {
    $masterAsset = StoreMasterAsset::make()->action(
        $masterShop,
        [
            'code' => 'MASTER_ASSET1',
            'name' => 'master asset 1',
            'is_main' => true,
            'type' => MasterAssetTypeEnum::RENTAL,
            'price' => 10,
            'stocks' => [],
        ]
    );

    $masterAsset->refresh();
    $masterShop->refresh();

    expect($masterAsset)->toBeInstanceOf(MasterAsset::class)
        ->and($masterAsset->stats)->toBeInstanceOf(MasterAssetStats::class)
        ->and($masterAsset->orderingStats)->toBeInstanceOf(MasterAssetOrderingStats::class)
        ->and($masterAsset->orderingIntervals)->toBeInstanceOf(MasterAssetOrderingIntervals::class)
        ->and($masterAsset->salesIntervals)->toBeInstanceOf(MasterAssetSalesIntervals::class)
        ->and($masterAsset->timeSeries()->count())->toBe(5)
        ->and($masterAsset)->not->toBeNull()
        ->and($masterAsset->code)->toBe('MASTER_ASSET1')
        ->and($masterAsset->name)->toBe('master asset 1')
        ->and($masterAsset->master_shop_id)->toBe($masterShop->id)
        ->and($masterAsset->group_id)->toBe($this->group->id)
        ->and($masterAsset->type)->toBe(MasterAssetTypeEnum::RENTAL);

    return $masterAsset;
})->depends("create master shop");

test('update master asset', function (MasterAsset $masterAsset) {
    $masterAsset = UpdateMasterAsset::make()->action(
        $masterAsset,
        [
            'name' => 'master asset 100',
            'price' => 100,
        ]
    );

    $masterAsset->refresh();

    expect($masterAsset)->toBeInstanceOf(MasterAsset::class)
        ->and($masterAsset->stats)->toBeInstanceOf(MasterAssetStats::class)
        ->and($masterAsset->orderingStats)->toBeInstanceOf(MasterAssetOrderingStats::class)
        ->and($masterAsset->orderingIntervals)->toBeInstanceOf(MasterAssetOrderingIntervals::class)
        ->and($masterAsset->salesIntervals)->toBeInstanceOf(MasterAssetSalesIntervals::class)
        ->and($masterAsset->timeSeries()->count())->toBe(5)
        ->and($masterAsset)->not->toBeNull()
        ->and($masterAsset->code)->toBe('MASTER_ASSET1')
        ->and($masterAsset->name)->toBe('master asset 100')
        ->and((int) $masterAsset->price)->toBe(100)
        ->and($masterAsset->type)->toBe(MasterAssetTypeEnum::RENTAL);

    return $masterAsset;
})->depends("create master asset");

test('Hydrate master_shops', function () {
    HydrateMasterShop::run(MasterShop::first());
    $this->artisan('hydrate:master_shops')->assertSuccessful();
});


test('create master sub department', function (MasterProductCategory $masterDepartment) {
    $masterSubDepartment = StoreMasterSubDepartment::make()->action(
        $masterDepartment,
        [
            'code' => 'SUB_DEPT1',
            'name' => 'sub department 1',
        ]
    );

    $masterSubDepartment->refresh();
    $masterDepartment->refresh();

    expect($masterSubDepartment)->toBeInstanceOf(MasterProductCategory::class)
        ->and($masterSubDepartment)->not->toBeNull()
        ->and($masterSubDepartment->code)->toBe('SUB_DEPT1')
        ->and($masterSubDepartment->name)->toBe('sub department 1')
        ->and($masterSubDepartment->type)->toBe(MasterProductCategoryTypeEnum::SUB_DEPARTMENT);

    return $masterSubDepartment;
})->depends('create master product category');

test("UI Index Master SubDepartments in Department", function (MasterProductCategory $masterDepartment) {
    $response = get(
        route("grp.masters.master_departments.show.master_sub_departments.index", [$masterDepartment->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterSubDepartments")
            ->has("title")
            ->has("data")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                    $page->has('subNavigation')->etc()
            );
    });
})->depends('create master product category');

test("UI Show Master SubDepartment", function (MasterProductCategory $masterSubDepartment) {
    $this->withoutExceptionHandling();

    $response = get(
        route("grp.masters.master_departments.show.master_sub_departments.show", [
            'masterDepartment' => $masterSubDepartment->parent->slug,
            'masterSubDepartment' => $masterSubDepartment->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterSubDepartment")
            ->has("title")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                    $page->has('subNavigation')->etc()
            )
            ->has("tabs");
    });
})->depends('create master sub department');

test('Hydrate master departments', function (MasterShop $masterShop) {
    MasterShopHydrateMasterDepartments::run($masterShop);

    $masterShop->refresh();

    expect($masterShop->stats->number_master_product_categories_type_department)->toBe(1)
        ->and($masterShop->stats->number_current_master_product_categories_type_department)->toBe(0);
})->depends('create master shop');

test('master hydrator', function () {
    $this->artisan('hydrate -s masters')->assertExitCode(0);
});
