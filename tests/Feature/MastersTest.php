<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Apr 2025 21:23:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Masters\MasterAsset\HydrateMasterAssets;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterAsset\DeleteMasterAsset;
use App\Actions\Masters\MasterAsset\CheckMasterAssetTradeUnitOrgStockExistence;
use App\Actions\Masters\MasterAsset\UpdateBulkMasterProduct;
use App\Actions\Masters\MasterAsset\UpdateMultipleMasterProductsFamily;
use App\Actions\Masters\MasterCollection\AttachMasterCollectionToModel;
use App\Actions\Masters\MasterCollection\AttachModelsToMasterCollection;
use App\Actions\Masters\MasterCollection\AttachModelToMasterCollection;
use App\Actions\Masters\MasterCollection\AttachMultipleParentsToAMasterCollection;
use App\Actions\Masters\MasterCollection\DeleteMasterCollection;
use App\Actions\Masters\MasterCollection\DetachMasterCollectionFromModel;
use App\Actions\Masters\MasterCollection\DetachMasterModelFromMasterCollection;
use App\Actions\Masters\MasterCollection\HydrateMasterCollection as HydrateMasterCollectionAction;
use App\Actions\Masters\MasterCollection\StoreMasterCollection;
use App\Actions\Masters\MasterCollection\UI\GetMasterCollectionShowcase;
use App\Actions\Masters\MasterCollection\UpdateMasterCollection;
use App\Actions\Masters\MasterProductCategory\AttachMasterFamiliesToMasterDepartment;
use App\Actions\Masters\MasterProductCategory\AttachMasterFamiliesToMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\DeleteMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\DetachFamilyToMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\StoreMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UpdateMasterFamilyMasterDepartment;
use App\Actions\Masters\MasterProductCategory\UpdateMasterFamilyMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\UpdateMasterSubDepartmentMasterDepartment;
use App\Actions\Masters\MasterProductCategory\UpdateMasterSubDepartmentsMasterDepartment;
use App\Actions\Masters\MasterShop\GetMasterShopTimeSeriesStats;
use App\Actions\Masters\MasterShop\HydrateMasterShop;
use App\Actions\Masters\MasterShop\HydrateMasterShopSales;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Masters\MasterShop\UpdateMasterShop;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetOrderingIntervals;
use App\Models\Masters\MasterAssetStats;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterCollectionOrderingStats;
use App\Models\Masters\MasterCollectionStats;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterProductCategoryStats;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterShopOrderingStats;
use App\Models\Masters\MasterShopStats;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

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

function createFreshMasterShop(): MasterShop
{
    return StoreMasterShop::make()->action(group(), [
        'type' => ShopTypeEnum::B2B,
        'code' => 'MSH-'.uniqid(),
        'name' => 'Test Master Shop',
    ]);
}

function createMasterCollectionPermissionFixtures(): array
{
    $suffix           = uniqid();
    $masterShop       = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'MCPD-'.$suffix,
        'name' => 'Master Collection Permission Department',
    ]);
    $masterFamily     = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MCPF-'.$suffix,
        'name' => 'Master Collection Permission Family',
    ]);
    $masterCollection = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MCPC-'.$suffix,
            'name' => 'Master Collection Permission Collection',
        ],
        createChildren: false
    );
    $masterAsset      = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'MCPA-'.$suffix,
        'name'    => 'Master Collection Permission Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 10,
        'stocks'  => [],
    ]);

    return [$masterCollection, $masterAsset];
}

function ensureMasterProductCategory(): \App\Models\Masters\MasterProductCategory
{
    $group = group();

    $masterShop = \App\Models\Masters\MasterShop::query()->first();
    if (!$masterShop) {
        $masterShop = StoreMasterShop::make()->action($group, [
            'type' => ShopTypeEnum::B2B,
            'code' => 'MSH-'.uniqid(),
            'name' => 'Test Master Shop',
        ]);
    }

    return StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'DEPT-'.uniqid(),
        'name' => 'Test Department',
    ]);
}

function createMastersRoleGuest(Group $group, RolesEnum $role): Guest
{
    setPermissionsTeamId($group->id);

    $guest = StoreGuest::make()->action(
        $group,
        array_merge(
            Guest::factory()->definition(),
            [
                'positions' => [],
            ]
        )
    );

    $guest->getUser()->assignRole($role->value);

    return $guest;
}


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

test('JSON Get All Trade Units in Master Product Category', function () {
    $masterProductCategory = ensureMasterProductCategory();

    $response = getJson(route('grp.json.master_product_category.all_trade_units', [
        'masterProductCategory' => $masterProductCategory->id,
    ]));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data',
        'links',
        'meta',
    ]);
});

test('JSON Get Recommended Trade Units in Master Product Category', function () {
    $masterProductCategory = ensureMasterProductCategory();

    $response = getJson(route('grp.json.master-product-category.recommended-trade-units', [
        'masterProductCategory' => $masterProductCategory->id,
    ]));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data',
        'links',
        'meta',
    ]);
});

test('JSON Get Taken Trade Units in Master Product Category', function () {
    $masterProductCategory = ensureMasterProductCategory();

    $response = getJson(route('grp.json.master-product-category.taken-trade-units', [
        'masterProductCategory' => $masterProductCategory->id,
    ]));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data',
        'links',
        'meta',
    ]);
});

test('JSON Get Pick Fractional', function () {
    $response = getJson(route('grp.json.product.get-pick-fractional', [
        'numerator'   => 6,
        'denominator' => 4,
    ]));

    $response->assertSuccessful();
    $response->assertJson(fn ($json) => $json->etc());
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

test('UI Edit Master Shop', function (MasterShop $masterShop) {
    $response = get(
        route('grp.masters.master_shops.edit', [$masterShop->slug])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterShop) {
        $page
            ->component('EditModel')
            ->has('breadcrumbs')
            ->where('title', fn ($title) => is_string($title) && $title !== '')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head
                    ->where('title', fn ($title) => is_string($title) && $title !== '')
                    ->has('actions', 1)
                    ->where('actions.0.type', 'button')
                    ->where('actions.0.style', 'cancel')
                    ->where('actions.0.route.name', 'grp.masters.master_shops.show')
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint', 3)
                    ->has('blueprint.0.fields.code')
                    ->where('blueprint.0.fields.code.type', 'input')
                    ->where('blueprint.0.fields.code.value', $masterShop->code)
                    ->has('blueprint.0.fields.name')
                    ->where('blueprint.0.fields.name.type', 'input')
                    ->where('blueprint.0.fields.name.value', $masterShop->name)
                    ->has('blueprint.1.fields.cost_price_ratio')
                    ->where('blueprint.1.fields.cost_price_ratio.type', 'input_number')
                    ->has('blueprint.1.fields.price_rrp_ratio')
                    ->where('blueprint.1.fields.price_rrp_ratio.type', 'input_number')
                    ->has('args.updateRoute')
                    ->where('args.updateRoute.name', 'grp.models.master_shops.update')
                    ->where('args.updateRoute.parameters.masterShop', $masterShop->id)
            );
    });
})->depends('create master shop');

test('UI Create Master Department', function (MasterShop $masterShop) {
    $response = get(
        route('grp.masters.master_shops.show.master_departments.create', [$masterShop->slug])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterShop) {
        $page
            ->component('CreateModel')
            ->has('breadcrumbs')
            ->where('title', fn ($title) => is_string($title) && $title !== '')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head
                    ->where('title', fn ($t) => is_string($t) && $t !== '')
                    ->has('actions', 1)
                    ->where('actions.0.type', 'button')
                    ->where('actions.0.style', 'cancel')
                    ->where('actions.0.route.name', 'grp.masters.master_shops.show.master_departments.index')
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint')
                    ->has('route')
                    ->where('route.name', 'grp.models.master_shops.master_department.store')
                    ->where('route.parameters.masterShop', $masterShop->id)
            );
    });
})->depends('create master shop');

test('create master department', function (MasterShop $masterShop) {
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
        ->and($masterProductCategory->timeSeries()->count())->toBe(5)
        ->and($masterProductCategory)->not->toBeNull()
        ->and($masterProductCategory->code)->toBe('PRODUCT_CATEGORY1')
        ->and($masterProductCategory->name)->toBe('product category 1')
        ->and($masterProductCategory->master_shop_id)->toBe($masterShop->id)
        ->and($masterProductCategory->group_id)->toBe($this->group->id)
        ->and($masterProductCategory->type)->toBe(MasterProductCategoryTypeEnum::DEPARTMENT)
        ->and($masterShop->stats->number_master_product_categories_type_department)->toBe(4)
        ->and($masterShop->stats->number_current_master_product_categories_type_department)->toBe(4);

    return $masterProductCategory;
})->depends("create master shop");

test('update master department', function (MasterProductCategory $masterProductCategory) {
    $updatedMasterProductCategory = UpdateMasterProductCategory::make()->action(
        $masterProductCategory,
        [
            'code'   => 'PRODUCT_CATEGORY2',
            'name'   => 'product category 2',
            'status' => false
        ]
    );

    $updatedMasterProductCategory->refresh();
    $masterShop = $updatedMasterProductCategory->masterShop;
    expect($updatedMasterProductCategory)->toBeInstanceOf(MasterProductCategory::class)
        ->and($updatedMasterProductCategory)->not->toBeNull()
        ->and($updatedMasterProductCategory->code)->toBe('PRODUCT_CATEGORY2')
        ->and($updatedMasterProductCategory->name)->toBe('product category 2')
        ->and($masterShop->stats->number_master_product_categories_type_department)->toBe(4)
        ->and($masterShop->stats->number_current_master_product_categories_type_department)->toBe(3);

    return $updatedMasterProductCategory;
})->depends("create master department");

test('UI Create Master SubDepartment in Department', function (MasterProductCategory $masterDepartment) {
    $response = get(
        route('grp.masters.master_departments.show.master_sub_departments.create', [$masterDepartment->slug])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterDepartment) {
        $page
            ->component('CreateModel')
            ->has('breadcrumbs')
            ->where('title', fn ($title) => is_string($title) && $title !== '')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head
                    ->where('title', fn ($t) => is_string($t) && $t !== '')
                    ->has('actions', 1)
                    ->where('actions.0.type', 'button')
                    ->where('actions.0.style', 'cancel')
                    ->where('actions.0.route.name', 'grp.masters.master_departments.show.master_sub_departments.index')
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint')
                    ->has('route')
                    ->where('route.name', 'grp.models.master_sub_department.store')
                    ->where('route.parameters.masterDepartment', $masterDepartment->id)
            );
    });
})->depends('create master department');

test('UI Edit Master Department', function (MasterProductCategory $masterDepartment) {
    $masterShop = $masterDepartment->masterShop;

    $response = get(
        route('grp.masters.master_shops.show.master_departments.edit', [$masterShop->slug, $masterDepartment->slug])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterDepartment) {
        $page
            ->component('EditModel')
            ->has('breadcrumbs')
            ->where('title', fn ($title) => is_string($title) && $title !== '')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head
                    ->where('title', fn ($t) => is_string($t) && $t !== '')
                    ->has('actions', 1)
                    ->where('actions.0.type', 'button')
                    ->where('actions.0.style', 'cancel')
                    ->where('actions.0.route.name', 'grp.masters.master_shops.show.master_departments.show')
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint')
                    ->has('blueprint.0.fields.code')
                    ->where('blueprint.0.fields.code.type', 'input')
                    ->where('blueprint.0.fields.code.value', $masterDepartment->code)
                    ->where('blueprint.1.fields.name.type', 'input')
                    ->etc()
            );
    });
})->depends('create master department');

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
})->depends('create master department');

test('UI Edit Master SubDepartment', function (MasterProductCategory $masterSubDepartment) {
    $masterDepartment = $masterSubDepartment->parent;

    $response = get(
        route('grp.masters.master_departments.show.master_sub_departments.edit', [
            'masterDepartment'    => $masterDepartment->slug,
            'masterSubDepartment' => $masterSubDepartment->slug,
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterSubDepartment) {
        $page
            ->component('EditModel')
            ->has('breadcrumbs')
            ->where('title', fn ($title) => is_string($title) && $title !== '')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head
                    ->where('title', fn ($t) => is_string($t) && $t !== '')
                    ->has('actions', 1)
                    ->where('actions.0.type', 'button')
                    ->where('actions.0.style', 'cancel')
                    ->where('actions.0.route.name', 'grp.masters.master_departments.show.master_sub_departments.show')
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint')
                    ->has('blueprint.0.fields.code')
                    ->where('blueprint.0.fields.code.type', 'input')
                    ->where('blueprint.0.fields.code.value', $masterSubDepartment->code)
                    ->where('blueprint.1.fields.name.type', 'input')
                    ->etc()
            );
    });
})->depends('create master sub department');

test('UI Show Master Department', function (MasterProductCategory $masterDepartment) {
    $response = get(
        route('grp.masters.master_departments.show', [$masterDepartment->slug])
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Masters/MasterDepartment')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead', fn (AssertableInertia $head) => $head->has('subNavigation')->etc())
            ->has('tabs')
            ->has('delete_route')
            ->where('delete_route.name', 'grp.masters.master_departments.delete');
    });
})->depends('create master department');

test('create master family', function (MasterProductCategory $masterDepartment) {
    $masterFamily = StoreMasterProductCategory::make()->action(
        $masterDepartment,
        [
            'code' => 'master_fam1',
            'name' => 'master family 1',
            'type' => MasterProductCategoryTypeEnum::FAMILY
        ]
    );


    expect($masterFamily)->toBeInstanceOf(MasterProductCategory::class)
        ->and($masterFamily->stats)->toBeInstanceOf(MasterProductCategoryStats::class);

    return $masterFamily;
})->depends("update master department");

test('UI Show Master Family in Department', function (MasterProductCategory $masterFamily) {
    // masterFamily created earlier is of type FAMILY and belongs to a department
    $response = get(
        route('grp.masters.master_departments.show.master_families.show', [
            'masterDepartment' => $masterFamily->masterDepartment->slug,
            'masterFamily'     => $masterFamily->slug,
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Masters/MasterFamily')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead', fn (AssertableInertia $head) => $head->has('subNavigation')->etc())
            ->has('tabs');
    });
})->depends('create master family');

test("UI Show master shop", function (MasterShop $masterShop) {
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.masters.master_shops.show", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($masterShop) {
        $page
            ->component("Masters/MasterShop")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $masterShop->name)
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
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->has('subNavigation')->etc()
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
                fn (AssertableInertia $page) => $page->has('title')->etc()
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
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->has('subNavigation')->etc()
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
            ->has("breadcrumbs", 4)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->has('subNavigation')->etc()
            );
    });
})->depends('create master shop');

test('hydrate master departments', function (MasterShop $masterShop) {
    MasterShopHydrateMasterDepartments::run($masterShop);

    $masterShop->refresh();

    expect($masterShop->stats->number_master_product_categories_type_department)->toBe(4)
        ->and($masterShop->stats->number_current_master_product_categories_type_department)->toBe(3);
})->depends('create master shop');

test('store master department', function () {
    $masterShop       = MasterShop::first();
    $masterDepartment = StoreMasterDepartment::make()->action(
        $masterShop,
        [
            'code' => 'SMF_DEPT1',
            'name' => 'smf department 1',
        ]
    );

    $masterDepartment->refresh();

    expect($masterDepartment)->toBeInstanceOf(MasterProductCategory::class)
        ->and($masterDepartment)->not->toBeNull()
        ->and($masterDepartment->code)->toBe('SMF_DEPT1')
        ->and($masterDepartment->name)->toBe('smf department 1')
        ->and($masterDepartment->master_shop_id)->toBe($masterShop->id)
        ->and($masterDepartment->group_id)->toBe($this->group->id)
        ->and($masterDepartment->type)->toBe(MasterProductCategoryTypeEnum::DEPARTMENT);

    return $masterDepartment;
})->depends('hydrate master departments');

test('store master family', function (MasterProductCategory $masterDepartment) {
    $masterFamily = StoreMasterFamily::make()->action(
        $masterDepartment,
        [
            'code' => 'SMF_FAM1',
            'name' => 'smf family 1',
        ]
    );

    $masterFamily->refresh();

    expect($masterFamily)->toBeInstanceOf(MasterProductCategory::class)
        ->and($masterFamily)->not->toBeNull()
        ->and($masterFamily->code)->toBe('SMF_FAM1')
        ->and($masterFamily->name)->toBe('smf family 1')
        ->and($masterFamily->master_shop_id)->toBe($masterDepartment->master_shop_id)
        ->and($masterFamily->group_id)->toBe($this->group->id)
        ->and($masterFamily->type)->toBe(MasterProductCategoryTypeEnum::FAMILY)
        ->and($masterFamily->stats)->toBeInstanceOf(MasterProductCategoryStats::class);
})->depends('store master department');

test('detach family from master sub department', function (MasterProductCategory $masterDepartment) {
    $masterSubDepartment = StoreMasterSubDepartment::make()->action(
        $masterDepartment,
        [
            'code' => 'SMF_SUBDEPT1',
            'name' => 'smf sub department 1',
        ]
    );

    $masterFamily = StoreMasterFamily::make()->action(
        $masterDepartment,
        [
            'code' => 'SMF_FAM_DETACH',
            'name' => 'smf family to detach',
        ]
    );

    AttachMasterFamiliesToMasterSubDepartment::make()->action(
        $masterSubDepartment,
        ['master_families' => [$masterFamily->id]]
    );

    $masterFamily->refresh();
    expect($masterFamily->master_sub_department_id)->toBe($masterSubDepartment->id);

    DetachFamilyToMasterSubDepartment::make()->handle($masterFamily);

    $masterFamily->refresh();

    expect($masterFamily->master_sub_department_id)->toBeNull()
        ->and($masterFamily->master_department_id)->toBe($masterDepartment->id)
        ->and($masterFamily->master_parent_id)->toBe($masterDepartment->id);
})->depends('store master department');


test('create master asset', function (MasterProductCategory $masterFamily) {
    $masterAsset = StoreMasterAsset::make()->action(
        $masterFamily,
        [
            'code'    => 'MASTER_ASSET1',
            'name'    => 'master asset 1',
            'is_main' => true,
            'type'    => MasterAssetTypeEnum::RENTAL,
            'price'   => 10,
            'stocks'  => [],
        ]
    );

    $masterAsset->refresh();


    expect($masterAsset)->toBeInstanceOf(MasterAsset::class)
        ->and($masterAsset->stats)->toBeInstanceOf(MasterAssetStats::class)
        ->and($masterAsset->timeSeries()->count())->toBe(5)
        ->and($masterAsset)->not->toBeNull()
        ->and($masterAsset->code)->toBe('MASTER_ASSET1')
        ->and($masterAsset->name)->toBe('master asset 1')
        ->and($masterAsset->group_id)->toBe($this->group->id)
        ->and($masterAsset->type)->toBe(MasterAssetTypeEnum::RENTAL);

    return $masterAsset;
})->depends("create master family");

test('update master asset', function (MasterAsset $masterAsset) {
    $masterAsset = UpdateMasterAsset::make()->action(
        $masterAsset,
        [
            'name'  => 'master asset 100',
            'price' => 100,
        ]
    );

    $masterAsset->refresh();

    expect($masterAsset)->toBeInstanceOf(MasterAsset::class)
        ->and($masterAsset->stats)->toBeInstanceOf(MasterAssetStats::class)
        ->and($masterAsset->orderingIntervals)->toBeInstanceOf(MasterAssetOrderingIntervals::class)
        ->and($masterAsset->timeSeries()->count())->toBe(5)
        ->and($masterAsset)->not->toBeNull()
        ->and($masterAsset->code)->toBe('MASTER_ASSET1')
        ->and($masterAsset->name)->toBe('master asset 100')
        ->and((int)$masterAsset->price)->toBe(100)
        ->and($masterAsset->type)->toBe(MasterAssetTypeEnum::RENTAL);

    return $masterAsset;
})->depends("create master asset");

test('Hydrate master_shops', function () {
    HydrateMasterShop::run(MasterShop::first());
    $this->artisan('hydrate:master_shops')->assertSuccessful();
});


test("UI Index Master SubDepartments in Department", function (MasterProductCategory $masterDepartment) {
    $response = get(
        route("grp.masters.master_departments.show.master_sub_departments.index", [$masterDepartment->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterSubDepartments")
            ->has("title")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->has('subNavigation')->etc()
            );
    });
})->depends('create master department');

test("UI Show Master SubDepartment", function (MasterProductCategory $masterSubDepartment) {
    $this->withoutExceptionHandling();

    $response = get(
        route("grp.masters.master_departments.show.master_sub_departments.show", [
            'masterDepartment'    => $masterSubDepartment->parent->slug,
            'masterSubDepartment' => $masterSubDepartment->slug
        ])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterSubDepartment")
            ->has("title")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->has('subNavigation')->etc()
            )
            ->has("tabs");
    });
})->depends('create master sub department');

test('master hydrator', function () {
    $this->artisan('hydrate -s masters')->assertExitCode(0);
});

test('create master collection', function (MasterProductCategory $masterFamily) {
    // Create a master collection under the previously created master family
    $masterCollection = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC1',
            'name' => 'master collection 1',
        ],
        createChildren: false
    );

    $masterCollection->refresh();

    expect($masterCollection)->toBeInstanceOf(MasterCollection::class)
        ->and($masterCollection->stats)->toBeInstanceOf(MasterCollectionStats::class)
        ->and($masterCollection->orderingStats)->toBeInstanceOf(MasterCollectionOrderingStats::class)
        ->and($masterCollection)->not->toBeNull()
        ->and($masterCollection->code)->toBe('MC1')
        ->and($masterCollection->name)->toBe('master collection 1')
        ->and($masterCollection->group_id)->toBe($this->group->id);

    return $masterCollection;
})->depends('create master family');

test('Hydrate master collections', function (MasterCollection $masterCollection) {
    // Run the action directly
    HydrateMasterCollectionAction::run($masterCollection);

    // And ensure the artisan command runs successfully
    $this->artisan('hydrate:master_collections')->assertSuccessful();
})->depends('create master collection');

// UI: Index master collections in a master shop
test('UI Index Master Collections in Master Shop', function (MasterShop $masterShop) {
    $response = get(
        route('grp.masters.master_shops.show.master_collections.index', [
            'masterShop' => $masterShop->slug,
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Masters/MasterCollections')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has('data');
    });
})->depends('create master shop');

// UI: Show master collection
test('UI Show Master Collection', function (MasterCollection $masterCollection) {
    $response = get(
        route('grp.masters.master_shops.show.master_collections.show', [
            'masterShop'       => $masterCollection->masterShop->slug,
            'masterCollection' => $masterCollection->slug,
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Masters/MasterCollection')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has('tabs')
            ->has('routes');
    });
})->depends('create master collection');

test('masters viewer cannot see master collection model controls', function () {
    [$masterCollection] = createMasterCollectionPermissionFixtures();
    $viewer = createMastersRoleGuest($this->group, RolesEnum::MASTERS_VIEWER);
    actingAs($viewer->getUser());

    $routes = [
        'grp.masters.master_shops.show.master_collections.linked_master_collections' => 'Masters/MasterCollections',
        'grp.masters.master_shops.show.master_collections.families'                  => 'Masters/MasterFamilies',
        'grp.masters.master_shops.show.master_collections.products'                  => 'Masters/MasterProducts',
    ];

    foreach ($routes as $routeName => $component) {
        get(route($routeName, [
            'masterShop'       => $masterCollection->masterShop->slug,
            'masterCollection' => $masterCollection->slug,
        ]))->assertInertia(fn (AssertableInertia $page) => $page
            ->component($component)
            ->where('routes', []));
    }
});

test('masters manager can see master collection model controls', function () {
    [$masterCollection] = createMasterCollectionPermissionFixtures();
    $manager = createMastersRoleGuest($this->group, RolesEnum::MASTERS_MANAGER);
    actingAs($manager->getUser());

    $routes = [
        'grp.masters.master_shops.show.master_collections.linked_master_collections',
        'grp.masters.master_shops.show.master_collections.families',
        'grp.masters.master_shops.show.master_collections.products',
    ];

    foreach ($routes as $routeName) {
        get(route($routeName, [
            'masterShop'       => $masterCollection->masterShop->slug,
            'masterCollection' => $masterCollection->slug,
        ]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('routes.submitAttach.name', 'grp.models.master_collection.attach-models')
            ->where('routes.detach.name', 'grp.models.master_collection.detach-models'));
    }
});

test('master collection model mutations require masters edit permission', function () {
    [$masterCollection, $masterAsset] = createMasterCollectionPermissionFixtures();
    AttachModelToMasterCollection::make()->action($masterCollection, $masterAsset);

    expect($masterCollection->masterProducts()->whereKey($masterAsset->id)->exists())->toBeTrue();

    $viewer = createMastersRoleGuest($this->group, RolesEnum::MASTERS_VIEWER);
    actingAs($viewer->getUser());

    post(route('grp.models.master_collection.attach-models', $masterCollection), [
        'products' => [$masterAsset->id],
    ])->assertForbidden();

    delete(route('grp.models.master_collection.detach-models', $masterCollection), [
        'product' => $masterAsset->id,
    ])->assertForbidden();

    expect($masterCollection->masterProducts()->whereKey($masterAsset->id)->exists())->toBeTrue();

    $manager = createMastersRoleGuest($this->group, RolesEnum::MASTERS_MANAGER);
    actingAs($manager->getUser());

    delete(route('grp.models.master_collection.detach-models', $masterCollection), [
        'product' => $masterAsset->id,
    ])->assertRedirect();

    expect($masterCollection->masterProducts()->whereKey($masterAsset->id)->exists())->toBeFalse();

    post(route('grp.models.master_collection.attach-models', $masterCollection), [
        'products' => [$masterAsset->id],
    ])->assertRedirect();

    expect($masterCollection->masterProducts()->whereKey($masterAsset->id)->exists())->toBeTrue();
});

// UI: Edit master collection
test('UI Edit Master Collection', function (MasterCollection $masterCollection) {
    $response = get(
        route('grp.masters.master_shops.show.master_collections.edit', [
            'masterShop'       => $masterCollection->masterShop->slug,
            'masterCollection' => $masterCollection->slug,
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('breadcrumbs')
            ->has('pageHead')
            ->has('formData');
    });
})->depends('create master collection');

test('JSON families not attached to master collection', function (MasterCollection $masterCollection) {
    $response = get(
        route('grp.json.master_shop.master_families_not_attached_to_master_collection', [
            'masterShop' => $masterCollection->masterShop->slug,
            'scope'      => $masterCollection->slug,
        ])
    );

    $response->assertSuccessful();
})->depends('create master collection');

test('JSON products not attached to master collection', function (MasterCollection $masterCollection) {
    $response = get(
        route('grp.json.master_shop.master_products_not_attached_to_master_collection', [
            'masterShop'       => $masterCollection->masterShop->slug,
            'masterCollection' => $masterCollection->slug,
        ])
    );

    $response->assertSuccessful();
})->depends('create master collection');

test('JSON departments for master collection scope', function (MasterCollection $masterCollection) {
    $response = get(
        route('grp.json.master_shop.master_departments', [
            'masterShop' => $masterCollection->masterShop->slug,
            'scope'      => $masterCollection->slug,
        ])
    );

    $response->assertSuccessful();
})->depends('create master collection');

test('GetMasterCollectionShowcase returns expected shape', function (MasterCollection $masterCollection) {
    $data = GetMasterCollectionShowcase::run($masterCollection);

    expect($data)
        ->toBeArray()
        ->and($data)
        ->toHaveKeys(['id', 'slug', 'code', 'name', 'routes']);
})->depends('create master collection');


test('update master collection', function (MasterCollection $masterCollection) {
    expect($masterCollection->code)->toBe('MC1')
        ->and($masterCollection->name)->toBe('master collection 1');

    UpdateMasterCollection::make()->action(
        $masterCollection,
        [
            'code'        => 'MC1-UPDATED',
            'name'        => 'Master Collection Updated',
            'description' => 'Updated description',
        ]
    );

    $masterCollection->refresh();

    expect($masterCollection->code)->toBe('MC1-UPDATED')
        ->and($masterCollection->name)->toBe('Master Collection Updated')
        ->and($masterCollection->description)->toBe('Updated description');
})->depends('create master collection');

test('soft delete master collection', function (MasterProductCategory $masterFamily) {
    $mc = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC-DEL-SOFT',
            'name' => 'to be soft deleted',
        ],
        createChildren: false
    );

    $mc->refresh();

    DeleteMasterCollection::make()->action($mc);

    $mc->refresh();
    expect($mc->trashed())->toBeTrue();
})->depends('create master family');

test('attach family to master collection', function (MasterProductCategory $masterFamily, MasterCollection $masterCollection) {
    $department = $masterFamily->parent; // parent department
    $newFamily  = StoreMasterFamily::make()->action($department, [
        'code' => 'FAM-NEW-ATTACH',
        'name' => 'Family New Attach',
    ]);
    $newFamily->refresh();

    expect($masterCollection->masterFamilies->pluck('id'))
        ->not->toContain($newFamily->id);

    AttachModelToMasterCollection::make()->action($masterCollection, $newFamily);
    $masterCollection->refresh();

    expect($masterCollection->masterFamilies->pluck('id'))
        ->toContain($newFamily->id);

    $count = $masterCollection->masterFamilies()
        ->where('master_product_categories.id', $newFamily->id)
        ->count();

    AttachModelToMasterCollection::make()->action($masterCollection, $newFamily);
    $masterCollection->refresh();

    expect(
        $masterCollection->masterFamilies()
            ->where('master_product_categories.id', $newFamily->id)
            ->count()
    )->toBe($count);
})->depends('create master family', 'create master collection');

test('attach collection to master collection', function (MasterProductCategory $masterFamily, MasterCollection $masterCollection) {
    $extra = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC-EXTRA-ATTACH',
            'name' => 'extra to attach',
        ],
        createChildren: false
    );

    $extra->refresh();


    expect($masterCollection->masterCollections->pluck('id'))
        ->not->toContain($extra->id);

    AttachModelToMasterCollection::make()->action($masterCollection, $extra);
    $masterCollection->refresh();

    expect($masterCollection->masterCollections->pluck('id'))
        ->toContain($extra->id);

    // Verify idempotency
    $count = $masterCollection->masterCollections()
        ->where('master_collections.id', $extra->id)
        ->count();

    AttachModelToMasterCollection::make()->action($masterCollection, $extra);
    $masterCollection->refresh();

    expect(
        $masterCollection->masterCollections()
            ->where('master_collections.id', $extra->id)
            ->count()
    )->toBe($count);
})->depends('create master family', 'create master collection');

test('force delete master collection', function (MasterProductCategory $masterFamily) {
    $mc = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC-DEL-FORCE',
            'name' => 'to be force deleted',
        ],
        createChildren: false
    );

    $mc->refresh();

    $id = $mc->id;

    DeleteMasterCollection::make()->action($mc, true);

    $found = MasterCollection::withTrashed()->find($id);
    expect($found)->toBeNull();
})->depends('create master family');

test('attach models to master collection', function (MasterProductCategory $masterFamily, MasterCollection $masterCollection) {
    // Create an additional master collection to be attached as a child collection
    $anotherCollection = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC-ATTACH-CHILD',
            'name' => 'child collection to attach',
        ],
        createChildren: false
    );

    $anotherCollection->refresh();

    AttachModelsToMasterCollection::make()->action(
        $masterCollection,
        [
            'families'    => [$masterFamily->id],
            'collections' => [$anotherCollection->id],
        ]
    );

    $masterCollection->refresh();

    expect($masterCollection->masterFamilies->pluck('id')->all())
        ->toContain($masterFamily->id)
        ->and($masterCollection->masterCollections->pluck('id')->all())
        ->toContain($anotherCollection->id);

    AttachModelsToMasterCollection::make()->action(
        $masterCollection,
        [
            'families'    => [$masterFamily->id, $masterFamily->id],
            'collections' => [$anotherCollection->id, $anotherCollection->id],
        ]
    );

    $masterCollection->refresh();

    expect($masterCollection->masterFamilies->where('id', $masterFamily->id)->count())
        ->toBe(1)
        ->and($masterCollection->masterCollections->where('id', $anotherCollection->id)->count())
        ->toBe(1);
})->depends('create master family', 'create master collection');

test('Hydrate master assets', function (MasterAsset $masterAsset) {
    HydrateMasterAssets::run($masterAsset);
    $masterAsset->refresh();
    expect($masterAsset)->toBeInstanceOf(MasterAsset::class);
})->depends('update master asset');

test('attach master collection to department', function (MasterProductCategory $masterDepartment, MasterCollection $masterCollection) {
    Bus::fake();
    expect(
        $masterDepartment->masterCollections()
            ->where('master_collections.id', $masterCollection->id)
            ->exists()
    )->toBeFalse();

    AttachMasterCollectionToModel::make()->action($masterDepartment, $masterCollection);

    $masterDepartment->refresh();
    $attached = $masterDepartment->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->wherePivot('type', 'master_department')
        ->exists();

    expect($attached)->toBeTrue();
})->depends('create master department', 'create master collection');


test('create master product page loads for family in master shop', function () {
    $group = $this->group;

    $masterShop = StoreMasterShop::make()->action($group, [
        'code' => 'MSH-'.uniqid(),
        'name' => 'Master Shop',
        'type' => ShopTypeEnum::B2B,
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'DEPT-'.uniqid(),
        'name' => 'Dept',
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'FAM-'.uniqid(),
        'name' => 'Family',
    ]);

    $response = get(
        route(
            'grp.masters.master_shops.show.master_families.master_products.create',
            [
                $masterShop,
                'masterFamily' => $masterFamily,
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterFamily) {
        $page->component('CreateModel')
            ->where('title', __('New master product'))
            ->where('pageHead.title', __('New master product'))
            ->has('breadcrumbs')
            ->has('formData', function (AssertableInertia $form) use ($masterFamily) {
                $form->where('route.name', 'grp.models.master_family.store-assets')
                    ->where('route.parameters.masterFamily', $masterFamily->id)
                    ->has('blueprint', function (AssertableInertia $bp) use ($masterFamily) {
                        $bp->has(0, function (AssertableInertia $section) use ($masterFamily) {
                            $section->where('title', __('Create Master Product'))
                                ->has('fields.trade_units', function (AssertableInertia $field) use ($masterFamily) {
                                    $field->where('type', 'list-selector')
                                        ->where('label', __(key: 'Trade units'))
                                        ->where('routeFetch.name', 'grp.json.master-product-category.recommended-trade-units')
                                        ->where('routeFetch.parameters.masterProductCategory', $masterFamily->id)
                                        ->etc();
                                })
                                ->has('fields.code', function (AssertableInertia $field) {
                                    $field->where('type', 'input')
                                        ->where('label', __('Code'))
                                        ->where('required', true)
                                        ->etc();
                                })
                                ->has('fields.name', function (AssertableInertia $field) {
                                    $field->where('type', 'input')
                                        ->where('label', __('Name'))
                                        ->where('required', true)
                                        ->etc();
                                })
                                ->has('fields.price', function (AssertableInertia $field) {
                                    $field->where('type', 'input')
                                        ->where('label', __('Price'))
                                        ->where('required', true)
                                        ->etc();
                                });
                        });
                    })
                    ->etc();
            });
    });
});

test('attach master collection to sub department', function (MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection) {
    Bus::fake();
    expect($masterSubDepartment->type)->toBe(MasterProductCategoryTypeEnum::SUB_DEPARTMENT);

    AttachMasterCollectionToModel::make()->action($masterSubDepartment, $masterCollection);

    $masterSubDepartment->refresh();
    $attached = $masterSubDepartment->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->wherePivot('type', 'master_sub_department')
        ->exists();

    expect($attached)->toBeTrue();
})->depends('create master sub department', 'create master collection');

test('attach master collection to shop', function (MasterShop $masterShop, MasterCollection $masterCollection) {
    AttachMasterCollectionToModel::make()->action($masterShop, $masterCollection);

    $masterShop->refresh();
    $attached = $masterShop->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->wherePivot('type', 'master_shop')
        ->exists();

    expect($attached)->toBeTrue();
})->depends('create master shop', 'create master collection');

test('attach master collection is idempotent', function (MasterProductCategory $masterDepartment, MasterCollection $masterCollection) {
    Bus::fake();
    AttachMasterCollectionToModel::make()->action($masterDepartment, $masterCollection);
    AttachMasterCollectionToModel::make()->action($masterDepartment, $masterCollection);

    $count = $masterDepartment->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->count();

    expect($count)->toBe(1);
})->depends('create master department', 'create master collection');

test('attach master collection without children', function (MasterProductCategory $masterDepartment, MasterCollection $masterCollection) {
    Bus::fake();
    AttachMasterCollectionToModel::make()->handle($masterDepartment, $masterCollection, false);

    $exists = $masterDepartment->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->exists();

    expect($exists)->toBeTrue();
})->depends('create master department', 'create master collection');

test('UI Edit Master Product', function (MasterAsset $masterAsset) {
    $masterShop   = $masterAsset->masterShop;
    $masterFamily = $masterAsset->masterFamily;

    $response = get(
        route('grp.masters.master_shops.show.master_families.master_products.edit', [
            'masterShop'    => $masterShop->slug,
            'masterFamily'  => $masterFamily->slug,
            'masterProduct' => $masterAsset->slug,
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterAsset) {
        $page
            ->component('EditModel')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head
                    ->where('model', __('Editing master product'))
                    ->where('title', $masterAsset->code)
                    ->has('actions', 1)
                    ->where('actions.0.type', 'button')
                    ->where('actions.0.style', 'exitEdit')
                    ->etc()
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint')
                    ->has('blueprint.0.fields.code')
                    ->where('blueprint.0.fields.code.type', 'input')
                    ->where('blueprint.0.fields.code.value', $masterAsset->code)
                    ->has('blueprint.1.fields.name')
                    ->where('blueprint.1.fields.name.type', 'input')
                    ->where('blueprint.1.fields.name.value', $masterAsset->name)
                    ->has('args.updateRoute')
                    ->where('args.updateRoute.name', 'grp.models.master_asset.update')
                    ->where('args.updateRoute.parameters.masterAsset', $masterAsset->id)
                    ->etc()
            );
    });
})->depends('create master asset');


// ADDITIONAL MASTER ASSET ACTIONS

test('DeleteMasterAsset force deletes a master asset', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'DMA-DEPT-'.uniqid(),
        'name' => 'Delete Master Asset Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'DMA-FAM-'.uniqid(),
        'name' => 'Delete Master Asset Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'DMA-AST-'.uniqid(),
        'name'    => 'Delete Master Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $masterAssetId = $masterAsset->id;

    DeleteMasterAsset::make()->handle($masterAsset);

    expect(MasterAsset::find($masterAssetId))->toBeNull();
});

test('DeleteMasterAsset soft deletes when forceDelete is false', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'DMAS-DEPT-'.uniqid(),
        'name' => 'Soft Delete Master Asset Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'DMAS-FAM-'.uniqid(),
        'name' => 'Soft Delete Master Asset Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'DMAS-AST-'.uniqid(),
        'name'    => 'Soft Delete Master Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 10,
        'stocks'  => [],
    ]);

    DeleteMasterAsset::make()->handle($masterAsset, false);

    $this->assertSoftDeleted('master_assets', ['id' => $masterAsset->id]);
});

test('CheckMasterAssetTradeUnitOrgStockExistence returns true when no trade units are checked', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'CMA-DEPT-'.uniqid(),
        'name' => 'Check Master Asset Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'CMA-FAM-'.uniqid(),
        'name' => 'Check Master Asset Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'CMA-AST-'.uniqid(),
        'name'    => 'Check Master Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $isValid = CheckMasterAssetTradeUnitOrgStockExistence::make()->handle($masterAsset, ['trade_units' => []]);

    expect($isValid)->toBeTrue();
});

test('UpdateBulkMasterProduct updates rrp and price for multiple master products', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'UBP-DEPT-'.uniqid(),
        'name' => 'Update Bulk Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'UBP-FAM-'.uniqid(),
        'name' => 'Update Bulk Family',
    ]);
    $masterAssetOne = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'UBP-AST1-'.uniqid(),
        'name'    => 'Bulk Asset 1',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 10,
        'unit'    => 'each',
        'stocks'  => [],
    ]);
    $masterAssetTwo = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'UBP-AST2-'.uniqid(),
        'name'    => 'Bulk Asset 2',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 20,
        'unit'    => 'each',
        'stocks'  => [],
    ]);

    UpdateBulkMasterProduct::make()->handle([
        'products' => [
            ['id' => $masterAssetOne->id, 'rrp' => 15, 'price' => 12],
            ['id' => $masterAssetTwo->id, 'rrp' => 25, 'price' => 22],
        ],
    ]);

    expect((int)$masterAssetOne->refresh()->price)->toBe(12)
        ->and((int)$masterAssetOne->rrp)->toBe(15)
        ->and((int)$masterAssetTwo->refresh()->price)->toBe(22)
        ->and((int)$masterAssetTwo->rrp)->toBe(25);
});

test('UpdateMultipleMasterProductsFamily moves master assets to a new family', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'UMF-DEPT-'.uniqid(),
        'name' => 'Update Multiple Family Department',
    ]);
    $masterFamilyOld = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'UMF-FAM-OLD-'.uniqid(),
        'name' => 'Old Family',
    ]);
    $masterFamilyNew = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'UMF-FAM-NEW-'.uniqid(),
        'name' => 'New Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamilyOld, [
        'code'    => 'UMF-AST-'.uniqid(),
        'name'    => 'Asset To Move',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 10,
        'stocks'  => [],
    ]);

    UpdateMultipleMasterProductsFamily::make()->handle($masterFamilyNew, [
        'master_assets' => [$masterAsset->id],
    ]);

    expect($masterAsset->refresh()->master_family_id)->toBe($masterFamilyNew->id);
});


// ADDITIONAL MASTER COLLECTION ACTIONS

test('DetachMasterCollectionFromModel detaches a master collection from a department', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'DMC-DEPT-'.uniqid(),
        'name' => 'Detach Master Collection Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'DMC-FAM-'.uniqid(),
        'name' => 'Detach Master Collection Family',
    ]);
    $masterCollection = StoreMasterCollection::make()->action($masterFamily, [
        'code' => 'DMC-COL-'.uniqid(),
        'name' => 'Detach Master Collection',
    ]);

    AttachMasterCollectionToModel::make()->action($masterDepartment, $masterCollection);

    expect($masterDepartment->masterCollections()->where('master_collections.id', $masterCollection->id)->exists())->toBeTrue();

    DetachMasterCollectionFromModel::make()->handle($masterDepartment, $masterCollection, false);

    expect($masterDepartment->masterCollections()->where('master_collections.id', $masterCollection->id)->exists())->toBeFalse();
});

test('DetachMasterModelFromMasterCollection detaches a master family from a master collection', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'DMM-DEPT-'.uniqid(),
        'name' => 'Detach Model Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'DMM-FAM-'.uniqid(),
        'name' => 'Detach Model Family',
    ]);
    $masterCollection = StoreMasterCollection::make()->action($masterFamily, [
        'code' => 'DMM-COL-'.uniqid(),
        'name' => 'Detach Model Collection',
    ]);

    AttachModelToMasterCollection::make()->action($masterCollection, $masterFamily);

    expect($masterCollection->masterFamilies()->where('master_product_categories.id', $masterFamily->id)->exists())->toBeTrue();

    DetachMasterModelFromMasterCollection::make()->handle($masterCollection, $masterFamily, false);

    expect($masterCollection->masterFamilies()->where('master_product_categories.id', $masterFamily->id)->exists())->toBeFalse();
});

test('AttachMultipleParentsToAMasterCollection attaches departments and shops', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'AMP-DEPT-'.uniqid(),
        'name' => 'Attach Multiple Parents Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'AMP-FAM-'.uniqid(),
        'name' => 'Attach Multiple Parents Family',
    ]);
    $masterCollection = StoreMasterCollection::make()->action($masterFamily, [
        'code' => 'AMP-COL-'.uniqid(),
        'name' => 'Attach Multiple Parents Collection',
    ]);

    AttachMultipleParentsToAMasterCollection::make()->handle($masterCollection, [
        'departments' => [$masterDepartment->id],
    ]);

    expect($masterDepartment->masterCollections()->where('master_collections.id', $masterCollection->id)->exists())->toBeTrue();
});


// ADDITIONAL MASTER PRODUCT CATEGORY ACTIONS

test('DeleteMasterProductCategory force deletes a master sub department without children', function () {
    $masterShop      = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'DPC-DEPT-'.uniqid(),
        'name' => 'Delete Product Category Department',
    ]);
    $masterSubDepartment = StoreMasterSubDepartment::make()->action($masterDepartment, [
        'code' => 'DPC-SUB-'.uniqid(),
        'name' => 'Delete Product Category SubDepartment',
    ], false);

    $masterSubDepartmentId = $masterSubDepartment->id;

    DeleteMasterProductCategory::make()->handle($masterSubDepartment, true);

    expect(MasterProductCategory::find($masterSubDepartmentId))->toBeNull();
});

test('AttachMasterFamiliesToMasterDepartment moves families under a department', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'AFD-DEPT-'.uniqid(),
        'name' => 'Attach Families Department',
    ]);
    $otherDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'AFD-DEPT2-'.uniqid(),
        'name' => 'Attach Families Other Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($otherDepartment, [
        'code' => 'AFD-FAM-'.uniqid(),
        'name' => 'Attach Families Family',
    ]);

    AttachMasterFamiliesToMasterDepartment::make()->handle($masterDepartment, [
        'master_families' => [$masterFamily->id],
    ]);

    expect($masterFamily->refresh()->master_department_id)->toBe($masterDepartment->id);
});

test('AttachMasterFamiliesToMasterSubDepartment moves families under a sub department', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'AFS-DEPT-'.uniqid(),
        'name' => 'Attach Families SubDepartment Department',
    ]);
    $masterSubDepartment = StoreMasterSubDepartment::make()->action($masterDepartment, [
        'code' => 'AFS-SUB-'.uniqid(),
        'name' => 'Attach Families SubDepartment',
    ], false);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'AFS-FAM-'.uniqid(),
        'name' => 'Attach Families SubDepartment Family',
    ]);

    AttachMasterFamiliesToMasterSubDepartment::make()->handle($masterSubDepartment, [
        'master_families' => [$masterFamily->id],
    ]);

    expect($masterFamily->refresh()->master_sub_department_id)->toBe($masterSubDepartment->id)
        ->and($masterFamily->master_department_id)->toBe($masterDepartment->id);
});

test('UpdateMasterFamilyMasterDepartment reassigns a family to another department', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartmentOld = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'UFD-DEPT-OLD-'.uniqid(),
        'name' => 'Update Family Department Old',
    ]);
    $masterDepartmentNew = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'UFD-DEPT-NEW-'.uniqid(),
        'name' => 'Update Family Department New',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartmentOld, [
        'code' => 'UFD-FAM-'.uniqid(),
        'name' => 'Update Family Department Family',
    ]);

    $updatedFamily = UpdateMasterFamilyMasterDepartment::make()->handle($masterFamily, [
        'master_department_id' => $masterDepartmentNew->id,
    ]);

    expect($updatedFamily->master_department_id)->toBe($masterDepartmentNew->id)
        ->and($updatedFamily->master_sub_department_id)->toBeNull();
});

test('UpdateMasterFamilyMasterSubDepartment reassigns a family to a sub department', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'UFS-DEPT-'.uniqid(),
        'name' => 'Update Family SubDepartment Department',
    ]);
    $masterSubDepartment = StoreMasterSubDepartment::make()->action($masterDepartment, [
        'code' => 'UFS-SUB-'.uniqid(),
        'name' => 'Update Family SubDepartment',
    ], false);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'UFS-FAM-'.uniqid(),
        'name' => 'Update Family SubDepartment Family',
    ]);

    $updatedFamily = UpdateMasterFamilyMasterSubDepartment::make()->handle($masterFamily, [
        'master_sub_department_id' => $masterSubDepartment->id,
    ]);

    expect($updatedFamily->master_sub_department_id)->toBe($masterSubDepartment->id)
        ->and($updatedFamily->master_department_id)->toBe($masterDepartment->id);
});

test('UpdateMasterSubDepartmentMasterDepartment reassigns a sub department to another department', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartmentOld = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'USD-DEPT-OLD-'.uniqid(),
        'name' => 'Update SubDepartment Department Old',
    ]);
    $masterDepartmentNew = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'USD-DEPT-NEW-'.uniqid(),
        'name' => 'Update SubDepartment Department New',
    ]);
    $masterSubDepartment = StoreMasterSubDepartment::make()->action($masterDepartmentOld, [
        'code' => 'USD-SUB-'.uniqid(),
        'name' => 'Update SubDepartment',
    ], false);

    $updatedSubDepartment = UpdateMasterSubDepartmentMasterDepartment::make()->handle($masterSubDepartment, [
        'master_department_id' => $masterDepartmentNew->id,
    ]);

    expect($updatedSubDepartment->master_department_id)->toBe($masterDepartmentNew->id);
});

test('UpdateMasterSubDepartmentsMasterDepartment reassigns multiple sub departments', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartmentOld = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'USDS-DEPT-OLD-'.uniqid(),
        'name' => 'Update SubDepartments Department Old',
    ]);
    $masterDepartmentNew = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'USDS-DEPT-NEW-'.uniqid(),
        'name' => 'Update SubDepartments Department New',
    ]);
    $masterSubDepartmentOne = StoreMasterSubDepartment::make()->action($masterDepartmentOld, [
        'code' => 'USDS-SUB1-'.uniqid(),
        'name' => 'Update SubDepartments One',
    ], false);
    $masterSubDepartmentTwo = StoreMasterSubDepartment::make()->action($masterDepartmentOld, [
        'code' => 'USDS-SUB2-'.uniqid(),
        'name' => 'Update SubDepartments Two',
    ], false);

    $result = UpdateMasterSubDepartmentsMasterDepartment::make()->handle($masterDepartmentNew, [
        'master_sub_department_ids' => [$masterSubDepartmentOne->id, $masterSubDepartmentTwo->id],
    ]);

    expect($result)->toBeTrue()
        ->and($masterSubDepartmentOne->refresh()->master_department_id)->toBe($masterDepartmentNew->id)
        ->and($masterSubDepartmentTwo->refresh()->master_department_id)->toBe($masterDepartmentNew->id);
});


// ADDITIONAL MASTER SHOP ACTIONS

test('GetMasterShopTimeSeriesStats returns stats data for the group master shops', function () {
    createFreshMasterShop();

    $stats = GetMasterShopTimeSeriesStats::make()->handle($this->group);

    expect($stats)->toBeArray()
        ->and(count($stats))->toBeGreaterThanOrEqual(1)
        ->and($stats[0])->toHaveKey('slug')
        ->and($stats[0])->toHaveKey('group_slug');
});

test('HydrateMasterShopSales hydrates orders stats for a master shop', function () {
    $masterShop = createFreshMasterShop();

    HydrateMasterShopSales::make()->handle($masterShop);

    expect($masterShop->refresh())->toBeInstanceOf(MasterShop::class);
});
