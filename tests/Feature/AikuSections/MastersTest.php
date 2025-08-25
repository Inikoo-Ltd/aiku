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
use App\Actions\Masters\MasterAsset\HydrateMasterAssets;
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
use App\Models\Sysadmin\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\DB;

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
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn(AssertableInertia $page) =>
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
            ->has("breadcrumbs", 4)
            ->has("data")
            ->has(
                "pageHead",
                fn(AssertableInertia $page) =>
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
                fn(AssertableInertia $page) =>
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
            ->has("breadcrumbs", 4)
            ->has("data")
            ->has(
                "pageHead",
                fn(AssertableInertia $page) =>
                $page->has('subNavigation')->etc()
            );
    });
})->depends('create master shop');

test("UI Index Master SubDepartments", function (MasterShop $masterShop) {
    $response = get(
        route("grp.masters.master_shops.show.master_sub_departments.index", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterSubDepartments")
            ->has("title")
            ->has("breadcrumbs", 2)
            ->has("data")
            ->has(
                "pageHead",
                fn(AssertableInertia $page) =>
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
                fn(AssertableInertia $page) =>
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
                fn(AssertableInertia $page) =>
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

test('Hydrate master assets', function (MasterAsset $masterAsset) {
    HydrateMasterAssets::run($masterAsset);
    $masterAsset->refresh();
    expect($masterAsset)->toBeInstanceOf(MasterAsset::class);
})->depends('update master asset');

test('it throws a validation exception when creating a master shop without a code', function () {
    $this->expectException(ValidationException::class);

    StoreMasterShop::make()->action(
        $this->group,
        [
            'name' => "Incomplete Shop",
            'type' => ShopTypeEnum::DROPSHIPPING
        ]
    );
});

test('it throws a validation exception when updating a master shop with an empty name', function (MasterShop $masterShop) {
    $this->expectException(ValidationException::class);

    UpdateMasterShop::make()->action(
        $masterShop,
        [
            'name' => '',
        ]
    );
})->depends('create master shop');

test('it fails to assign a non-existent master shop to a shop', function () {
    $this->expectException(ValidationException::class);
    UpdateShop::make()->action(
        $this->shop,
        [
            'master_shop_id' => 99999
        ]
    );
});

test('it fails to create master product category without a code', function (MasterShop $masterShop) {
    $this->expectException(ValidationException::class);

    StoreMasterProductCategory::make()->action(
        $masterShop,
        [
            'name' => 'product category 1',
            'type' => MasterProductCategoryTypeEnum::DEPARTMENT
        ]
    );
})->depends("create master shop");

test('it fails to update master product category with a duplicate code', function (MasterProductCategory $categoryToUpdate) {
    StoreMasterProductCategory::make()->action($categoryToUpdate->masterShop, [
        'code' => 'DUPLICATE_CAT_CODE',
        'name' => 'Existing Category',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT
    ]);

    $this->expectException(ValidationException::class);

    UpdateMasterProductCategory::make()->action($categoryToUpdate, [
        'code' => 'DUPLICATE_CAT_CODE',
    ]);
})->depends('create master product category');

test('it throws a validation exception when updating a master product category with an empty name', function (MasterProductCategory $masterProductCategory) {
    $this->expectException(ValidationException::class);

    UpdateMasterProductCategory::make()->action(
        $masterProductCategory,
        [
            'name' => '',
        ]
    );
})->depends('create master product category');

test('it fails to create master asset with an invalid price', function (MasterShop $masterShop) {
    $this->expectException(ValidationException::class);

    StoreMasterAsset::make()->action(
        $masterShop,
        [
            'code' => 'MASTER_ASSET_INVALID',
            'name' => 'master asset invalid',
            'is_main' => true,
            'type' => MasterAssetTypeEnum::RENTAL,
            'price' => 'bukan-angka',
            'stocks' => [],
        ]
    );
})->depends("create master shop");

test('it fails to update master asset with an empty name', function (MasterAsset $masterAsset) {
    $this->expectException(ValidationException::class);

    UpdateMasterAsset::make()->action(
        $masterAsset,
        [
            'name' => '',
        ]
    );
})->depends('create master asset');

test('it fails to create master sub department without a name', function (MasterProductCategory $masterDepartment) {
    $this->expectException(ValidationException::class);

    StoreMasterSubDepartment::make()->action(
        $masterDepartment,
        [
            'code' => 'SUB_DEPT_INVALID',
        ]
    );
})->depends('create master product category');

test('it fails to update master shop with a duplicate code', function (MasterShop $shopToUpdate) {
    StoreMasterShop::make()->action($this->group, [
        'code' => "EXISTING_CODE",
        'name' => "Existing Shop",
        'type' => ShopTypeEnum::DROPSHIPPING
    ]);

    $this->expectException(ValidationException::class);

    UpdateMasterShop::make()->action($shopToUpdate, [
        'code' => 'EXISTING_CODE'
    ]);
})->depends('create master shop');

test('it fails to create master shop from command if required arguments are missing', function () {
    $this->artisan('master_shop:create', [
        'group' => $this->group->slug,
        'type'  => ShopTypeEnum::DROPSHIPPING,
    ])
        ->expectsOutputToContain('Not enough arguments (missing: "code, name")')
        ->assertFailed();
});

test('it fails to create master shop from command if required arguments are missing', function () {
    $this->artisan('master_shop:create', [
        'group' => $this->group->slug,
        'type'  => ShopTypeEnum::DROPSHIPPING,
    ])
        ->expectsOutputToContain('Not enough arguments (missing: "code, name")')
        ->assertFailed();
});

test('deactivating a master shop also deactivates its product categories', function (MasterProductCategory $masterProductCategory) {
    expect($masterProductCategory->status)->toBeTrue();

    UpdateMasterShop::make()->action($masterProductCategory->masterShop, [
        'status' => false
    ]);

    $masterProductCategory->refresh();

    expect($masterProductCategory->status)->toBeFalse();
})->depends('create master product category');

test('creating a sub-department updates parent category stats', function (MasterProductCategory $masterDepartment) {
    expect($masterDepartment->stats->number_sub_departments)->toBe(0);

    StoreMasterSubDepartment::make()->action($masterDepartment, [
        'code' => 'SUB1',
        'name' => 'Sub Department One',
    ]);

    $masterDepartment->refresh();

    expect($masterDepartment->stats->number_sub_departments)->toBe(1);
})->depends('create master product category');

test('it correctly updates master asset price to zero', function (MasterAsset $masterAsset) {
    UpdateMasterAsset::make()->action($masterAsset, [
        'price' => 0,
    ]);

    $masterAsset->refresh();

    expect((int) $masterAsset->price)->toBe(0);
})->depends('create master asset');

test('a non-admin user cannot create a master shop', function () {
    $regularUser = User::factory()->create();
    actingAs($regularUser);

    $this->expectException(AuthorizationException::class);

    StoreMasterShop::make()->action(
        $this->group,
        [
            'code' => "SHOP_FAIL",
            'name' => "Failed Shop",
            'type' => ShopTypeEnum::DROPSHIPPING
        ]
    );
});

test('it correctly updates master asset price to zero', function (MasterAsset $masterAsset) {
    UpdateMasterAsset::make()->action($masterAsset, [
        'price' => 0,
    ]);

    $masterAsset->refresh();

    expect((int) $masterAsset->price)->toBe(0);
})->depends('create master asset');

test('updating a sub-department name does not affect the parent category stats', function (MasterProductCategory $masterSubDepartment) {
    $masterDepartment = $masterSubDepartment->parent;
    $initialCount = $masterDepartment->stats->number_sub_departments;

    UpdateMasterProductCategory::make()->action($masterSubDepartment, [
        'name' => 'Updated Sub Department Name',
    ]);

    expect($masterDepartment->refresh()->stats->number_sub_departments)->toBe($initialCount);
})->depends('create master sub department');

test('it correctly handles updating master asset price to zero', function (MasterAsset $masterAsset) {
    UpdateMasterAsset::make()->action($masterAsset, ['price' => 0]);
    expect((int) $masterAsset->refresh()->price)->toBe(0);
})->depends('create master asset');

test('creating a product category with special characters in name is handled correctly', function (MasterShop $masterShop) {
    $specialName = 'Category /w "Special" Chars & Symbols!';
    $category = StoreMasterProductCategory::make()->action($masterShop, [
        'code' => 'SPECIAL_CHARS',
        'name' => $specialName,
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT
    ]);

    expect($category->name)->toBe($specialName)
        ->and($category->slug)->toBe('category-w-special-chars-symbols');
})->depends('create master shop');

test('a non-admin user cannot perform master actions', function ($action, $data) {
    $regularUser = User::factory()->create();
    actingAs($regularUser);

    $this->expectException(AuthorizationException::class);

    $action::make()->action(...$data);
})->with([
    'create master shop' => [
        fn() => StoreMasterShop::class,
        fn() => [$this->group, ['code' => 'FAIL', 'name' => 'Fail', 'type' => ShopTypeEnum::DROPSHIPPING]]
    ],
    'create master product category' => [
        fn() => StoreMasterProductCategory::class,
        fn() => [MasterShop::first(), ['code' => 'FAIL', 'name' => 'Fail', 'type' => MasterProductCategoryTypeEnum::DEPARTMENT]]
    ],
    'create master asset' => [
        fn() => StoreMasterAsset::class,
        fn() => [MasterShop::first(), ['code' => 'FAIL', 'name' => 'Fail', 'type' => MasterAssetTypeEnum::RENTAL, 'price' => 10]]
    ],
]);

test('it prevents creating duplicate master shop codes under race conditions', function () {
    $results = ParallelTesting::concurrently([
        fn() => StoreMasterShop::make()->action($this->group, [
            'code' => 'RACE_CODE',
            'name' => 'Shop A',
            'type' => ShopTypeEnum::DROPSHIPPING
        ]),
        fn() => StoreMasterShop::make()->action($this->group, [
            'code' => 'RACE_CODE',
            'name' => 'Shop B',
            'type' => ShopTypeEnum::DROPSHIPPING
        ]),
    ]);

    $this->assertCount(1, array_filter($results, fn($result) => $result instanceof \Illuminate\Validation\ValidationException));
    $this->assertDatabaseCount('master_shops', 1, ['code' => 'RACE_CODE']);
});

test('hydrating master departments is idempotent', function (MasterProductCategory $masterDepartment) {
    StoreMasterSubDepartment::make()->action($masterDepartment, ['code' => 'SUB1', 'name' => 'Sub']);
    $masterDepartment->refresh();
    expect($masterDepartment->stats->number_sub_departments)->toBe(1);

    MasterShopHydrateMasterDepartments::run($masterDepartment->masterShop);
    expect($masterDepartment->refresh()->stats->number_sub_departments)->toBe(1);

    MasterShopHydrateMasterDepartments::run($masterDepartment->masterShop);
    expect($masterDepartment->refresh()->stats->number_sub_departments)->toBe(1);
})->depends('create master product category');

test('it handles creating a sub-department with a very long name', function (MasterProductCategory $masterDepartment) {
    $longName = str_repeat('a', 255);

    $subDepartment = StoreMasterSubDepartment::make()->action($masterDepartment, [
        'code' => 'LONG_NAME',
        'name' => $longName,
    ]);

    $this->assertDatabaseHas('master_product_categories', [
        'id' => $subDepartment->id,
        'name' => $longName
    ]);

    expect($subDepartment->name)->toBe($longName);
})->depends('create master product category');

test('it prevents N+1 query problems when fetching master shops with categories', function () {
    MasterShop::factory(10)
        ->has(MasterProductCategory::factory()->count(3), 'productCategories')
        ->create(['group_id' => $this->group->id]);

    DB::enableQueryLog();

    get(route("grp.masters.master_shops.index"));

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($queries))->toBeLessThan(5, 'Terdeteksi adanya N+1 Query Problem!');
});

test('it prevents mass assignment vulnerability on master asset update', function (MasterAsset $masterAsset) {
    $originalPrice = $masterAsset->price;

    UpdateMasterAsset::make()->action($masterAsset, [
        'name' => 'Updated Name via Test',
        'is_approved' => true,
    ]);

    $masterAsset->refresh();

    expect($masterAsset->name)->toBe('Updated Name via Test');
    expect((int)$masterAsset->price)->toBe((int)$originalPrice);
    $this->assertArrayNotHasKey('is_approved', $masterAsset->getAttributes());
})->depends('create master asset');

test('it shows a success notification after creating a master shop', function () {
    $shopData = [
        'code' => "UI_TEST_SHOP",
        'name' => "UI Test Shop",
        'type' => ShopTypeEnum::DROPSHIPPING->value
    ];

    $response = $this->post(route('grp.masters.master_shops.store'), $shopData);

    $response->assertRedirect();

    $redirectResponse = $this->get($response->headers->get('Location'));

    $redirectResponse->assertInertia(
        fn($page) =>
        $page->has('flash.success')
            ->where('flash.success', 'Master Shop berhasil dibuat.')
    );
});

test('a master asset status can only be changed from active to inactive after being used', function (MasterAsset $masterAsset) {
    expect($masterAsset->status)->toBeTrue()
        ->and($masterAsset->stats->times_used)->toBe(0);

    $action = UpdateMasterAsset::make()->action($masterAsset, ['status' => false]);
    expect($action)->toBeFalse();
    expect($masterAsset->refresh()->status)->toBeTrue();

    $masterAsset->stats->increment('times_used');

    $action = UpdateMasterAsset::make()->action($masterAsset, ['status' => false]);
    expect($action)->toBeInstanceOf(MasterAsset::class);
    expect($masterAsset->refresh()->status)->toBeFalse();
})->depends('create master asset');

test('[REGRESSION] hydrator correctly counts categories with numeric names', function (MasterShop $masterShop) {
    StoreMasterProductCategory::make()->action($masterShop, ['code' => 'CAT1', 'name' => 'Category 1']);
    StoreMasterProductCategory::make()->action($masterShop, ['code' => 'CAT2', 'name' => 'Category 2']);

    HydrateMasterShop::run($masterShop);

    expect($masterShop->refresh()->stats->number_master_product_categories)->toBe(2);
})->depends('create master shop');

test('hydrator correctly syncs after a master product category is deleted', function (MasterProductCategory $category1, MasterProductCategory $category2) {
    $masterShop = $category1->masterShop;
    $category2->update(['master_shop_id' => $masterShop->id]);

    HydrateMasterShop::run($masterShop);
    expect($masterShop->refresh()->stats->number_master_product_categories)->toBe(2);

    $category1->delete();

    HydrateMasterShop::run($masterShop);

    expect($masterShop->refresh()->stats->number_master_product_categories)->toBe(1);
})->depends('create master product category', 'create master product category');


test('it prevents a category from being its own parent', function (MasterProductCategory $category) {
    $this->expectException(\Illuminate\Validation\ValidationException::class);

    UpdateMasterProductCategory::make()->action($category, [
        'parent_id' => $category->id,
    ]);
})->depends('create master product category');
