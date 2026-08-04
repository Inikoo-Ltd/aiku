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
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Actions\Masters\MasterShop\GetMasterShopTimeSeriesStats;
use App\Actions\Masters\MasterShop\HydrateMasterShop;
use App\Actions\Masters\MasterShop\HydrateMasterShopSales;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterDepartments;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Masters\MasterAssetTimeSeries\ProcessMasterAssetTimeSeriesRecords;
use App\Actions\Masters\MasterShop\UpdateMasterShop;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Masters\MasterAsset\UI\GetMasterProductShowcase;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetStats;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterCollectionOrderingStats;
use App\Models\Masters\MasterCollectionStats;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterProductCategoryStats;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterShopOrderingStats;
use App\Models\Masters\MasterShopStats;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

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

test("UI Show master shop showcase has price exchanges", function (MasterShop $masterShop) {
    $masterShop->update([
        'price_exchanges' => [
            'GBP' => ['is_major' => true],
            'EUR' => ['is_major' => true],
            'SEK' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 11],
        ]
    ]);

    $response = get(
        route("grp.masters.master_shops.show", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Masters/MasterShop")
            ->has("showcase.price_exchanges", 3)
            ->where("showcase.price_exchanges.0.is_major", true)
            ->where("showcase.price_exchanges.2.code", "SEK")
            ->where("showcase.price_exchanges.2.major", "EUR")
            ->where("showcase.price_exchanges.2.exchange", 11)
            ->etc();
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

    $expectsWarning = $masterAsset->stats->number_assets > 0;

    $response->assertInertia(function (AssertableInertia $page) use ($masterAsset, $expectsWarning) {
        $page
            ->component('EditModel')
            ->where('warning', fn ($warning) => $expectsWarning === ($warning !== null))
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
                    ->has('blueprint.6.fields.composition.route')
                    ->etc()
            );
    });
})->depends('create master asset');

test('UI Edit Master Product Composition', function (MasterAsset $masterAsset) {
    $response = get(
        route('grp.masters.master_shops.show.master_products.composition', [
            'masterShop'    => $masterAsset->masterShop->slug,
            'masterProduct' => $masterAsset->slug,
        ])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($masterAsset) {
        $page
            ->component('Goods/ProductComposition')
            ->has('breadcrumbs')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head
                    ->where('title', $masterAsset->code)
                    ->etc()
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) => $form
                    ->has('blueprint.0.fields.trade_units.priceContext')
                    ->where('blueprint.0.fields.trade_units.type', 'list-selector-trade-unit')
                    ->has('blueprint.1.fields.master_prices')
                    ->has('blueprint.1.fields.master_rrps')
                    ->etc()
            );
    });
})->depends('create master asset');

test('UI Index Master Products bulk edit tab lists products with their tax preset', function () {
    $masterShop = createFreshMasterShop();

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'BETAB-DEP-'.uniqid(),
        'name' => 'Bulk Edit Tab Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'BETAB-FAM-'.uniqid(),
        'name' => 'Bulk Edit Tab Family',
    ]);
    StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'BETAB-AST-'.uniqid(),
        'name'    => 'Bulk Edit Tab Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 12.5,
        'stocks'  => [],
    ]);

    $response = get(route('grp.masters.master_shops.show.master_families.master_products.index', [
        $masterFamily->masterShop->slug,
        $masterFamily->slug,
        'tab' => 'bulk_edit',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('Masters/MasterProducts')
            ->has('tabs.navigation.bulk_edit')
            ->has('taxPresetOptions')
            ->has(
                'bulk_edit.data.0',
                fn (AssertableInertia $row) => $row
                    ->has('code')
                    ->has('tax_preset')
                    ->etc()
            )
            ->etc()
    );
});

test('UI Index Master Products in family has pricing tab', function () {
    $masterShop = createFreshMasterShop();

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'PRTAB-DEP-'.uniqid(),
        'name' => 'Pricing Tab Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'PRTAB-FAM-'.uniqid(),
        'name' => 'Pricing Tab Family',
    ]);
    StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'PRTAB-AST-'.uniqid(),
        'name'    => 'Pricing Tab Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 12.5,
        'rrp'     => 30,
        'stocks'  => [],
    ]);

    $response = get(route('grp.masters.master_shops.show.master_families.master_products.index', [
        $masterFamily->masterShop->slug,
        $masterFamily->slug,
        'tab' => 'pricing',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('Masters/MasterProducts')
            ->has('tabs.navigation.pricing')
            ->has('pricing.data')
            ->has(
                'pricing.data.0',
                fn (AssertableInertia $row) => $row
                    ->has('code')
                    ->has('name')
                    ->has('price')
                    ->has('rrp')
                    ->has('currency_code')
                    ->etc()
            )
            ->etc()
    );
});

test('bulk update master assets prices applies per-unit rrp and skips independents', function () {
    $masterShop = createFreshMasterShop();

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'BLKPR-DEP-'.uniqid(),
        'name' => 'Bulk Prices Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'BLKPR-FAM-'.uniqid(),
        'name' => 'Bulk Prices Family',
    ]);

    $assetA = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'BLKPR-A-'.uniqid(),
        'name'    => 'Bulk A',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);
    $assetB = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'BLKPR-B-'.uniqid(),
        'name'    => 'Bulk B',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $assetA->updateQuietly([
        'units'       => 16,
        'master_rrps' => [
            'EUR' => ['value' => 10, 'independent' => false],
            'PLN' => ['value' => 300, 'independent' => true],
        ],
    ]);
    $assetB->updateQuietly(['units' => 2, 'master_rrps' => ['EUR' => ['value' => 5, 'independent' => false]]]);

    $foreignAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'BLKPR-X-'.uniqid(),
        'name'    => 'Bulk Foreign',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);
    $foreignGroup = \App\Actions\SysAdmin\Group\StoreGroup::make()->action(
        \App\Models\SysAdmin\Group::factory()->definition()
    );
    $foreignAsset->updateQuietly([
        'group_id'    => $foreignGroup->id,
        'master_rrps' => ['EUR' => ['value' => 5, 'independent' => false]],
    ]);

    Queue::fake();

    \App\Actions\Masters\MasterAsset\UpdateBulkMasterAssetsPrices::make()->action([
        'ids'          => [$assetA->id, $assetB->id, $foreignAsset->id],
        'rrp_per_unit' => true,
        'master_rrps'  => [
            'EUR' => ['value' => 2, 'independent' => false],
            'PLN' => ['value' => 99, 'independent' => false],
            'HUF' => ['value' => null, 'independent' => false],
        ],
    ]);

    $assetA->refresh();
    $assetB->refresh();

    expect(data_get($assetA->master_rrps, 'EUR.value'))->toEqual(32)
        ->and(data_get($assetA->master_rrps, 'PLN.value'))->toBe(300)
        ->and(data_get($assetA->master_rrps, 'PLN.independent'))->toBeTrue()
        ->and(data_get($assetA->master_rrps, 'HUF'))->toBeNull()
        ->and(data_get($assetB->master_rrps, 'EUR.value'))->toEqual(4)
        ->and(data_get($assetB->master_rrps, 'PLN.value'))->toEqual(198)
        ->and(data_get($foreignAsset->refresh()->master_rrps, 'EUR.value'))->toBe(5);

    Queue::assertPushed(
        \Lorisleiva\Actions\Decorators\JobDecorator::class,
        2,
    );
});

test('hydrate effective cost weights org costs by available stock', function () {
    $masterShop = createFreshMasterShop();

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'EFC-DEP-'.uniqid(),
        'name' => 'Effective Cost Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'EFC-FAM-'.uniqid(),
        'name' => 'Effective Cost Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'EFC-AST-'.uniqid(),
        'name'    => 'Effective Cost Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    [, $product] = createProduct($this->shop);
    $tradeUnit   = $product->tradeUnits()->first();
    $masterAsset->tradeUnits()->sync([$tradeUnit->id => ['quantity' => 2]]);

    $orgStock = $this->organisation->orgStocks()->first();
    $tradeUnit->orgStocks()->syncWithoutDetaching([$orgStock->id => ['quantity' => 1]]);
    $orgStock->updateQuietly([
        'current_supplier_sku_cost' => 6,
        'packed_in'                 => 2,
        'quantity_available'        => 50,
    ]);

    \App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateEffectiveCost::run($masterAsset->refresh());

    $exchange = \App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange::run(
        $this->organisation->currency,
        $this->organisation->group->currency
    );

    expect((float) $masterAsset->refresh()->effective_cost)
        ->toBe(round(6 / 2 * 2 * $exchange, 4));
});

test('effective cost hydrator is scheduled nightly and queued low-priority', function () {
    expect(\App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateEffectiveCost::make()->jobQueue)
        ->toBe('low-priority');

    config(['app.master' => true]);
    $schedule = new \Illuminate\Console\Scheduling\Schedule();
    $kernel   = app(\App\Console\Kernel::class);
    (function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $this->schedule($schedule);
    })->call($kernel, $schedule);

    expect(
        collect($schedule->events())->contains(
            fn ($event) => str_contains($event->command ?? '', 'master_assets:hydrate_effective_cost')
                && $event->expression === '30 2 * * *'
        )
    )->toBeTrue();
});

test('UI Edit Master Product with a trade unit not linked to a stock', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'NOSTK-DEPT-'.uniqid(),
        'name' => 'No Stock Department',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'NOSTK-FAM-'.uniqid(),
        'name' => 'No Stock Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'NOSTK-AST-'.uniqid(),
        'name'    => 'No Stock Master Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::RENTAL,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $tradeUnit = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition());
    $masterAsset->tradeUnits()->attach($tradeUnit->id, ['quantity' => 3]);

    expect(
        DB::table('model_has_trade_units')
            ->where('model_type', 'Stock')
            ->where('trade_unit_id', $tradeUnit->id)
            ->exists()
    )->toBeFalse();

    $response = get(
        route('grp.masters.master_shops.show.master_products.composition', [
            'masterShop'    => $masterShop->slug,
            'masterProduct' => $masterAsset->slug,
        ])
    );

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page->where(
            'formData.blueprint.0.fields.trade_units.value',
            fn ($tradeUnits) => collect($tradeUnits)->count() === 1
                && collect($tradeUnits)->every(fn ($tradeUnit) => $tradeUnit['packed_in'] == 1)
        )->etc()
    );

    $masterAsset->load('tradeUnits');
    $showcase = GetMasterProductShowcase::run($masterAsset);

    expect($showcase['trade_units'])->toHaveCount(1)
        ->and($showcase['trade_units'][0])->toHaveKey('pick_fractional');
});


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

test('master shop price exchanges default and seeder', function () {
    $masterShop = StoreMasterShop::make()->action($this->group, [
        'code' => 'AW',
        'name' => 'Ancient Wisdom Master',
        'type' => ShopTypeEnum::B2B,
    ]);

    expect($masterShop->price_exchanges)->toBe([]);

    (new \Database\Seeders\MasterShopPriceExchangesSeeder())->run();
    $masterShop->refresh();

    expect($masterShop->price_exchanges)->toHaveKeys(['GBP', 'EUR', 'PLN', 'CZK', 'HUF', 'RON', 'SEK', 'UAH'])
        ->and($masterShop->price_exchanges['EUR']['is_major'])->toBeTrue()
        ->and($masterShop->price_exchanges['SEK'])->toEqualCanonicalizing(['is_major' => false, 'major' => 'EUR', 'exchange' => 11, 'fraction_digits' => 0])
        ->and($masterShop->price_exchanges['PLN']['exchange'])->toBe(4.3);
});

test('update master shop price exchange recalculates minor prices', function () {
    $masterShop = createFreshMasterShop();

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'PXDEP-'.uniqid(),
        'name' => 'Price Exchange Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'PXFAM-'.uniqid(),
        'name' => 'Price Exchange Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'PXAST-'.uniqid(),
        'name'    => 'Price Exchange Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'master_prices' => [
            'EUR' => ['value' => 10, 'independent' => false],
            'SEK' => ['value' => 100, 'independent' => false],
        ]
    ]);

    $masterShop->update(['price_exchanges' => ['EUR' => ['is_major' => true]]]);

    $masterShop = \App\Actions\Masters\MasterShop\UpdateMasterShopPriceExchange::make()->action($masterShop, [
        'currency' => 'SEK',
        'is_major' => false,
        'major'    => 'EUR',
        'exchange' => 11,
    ]);

    $masterAsset->refresh();

    expect($masterShop->price_exchanges['SEK'])->toEqualCanonicalizing(['is_major' => false, 'major' => 'EUR', 'exchange' => 11, 'fraction_digits' => 2])
        ->and(data_get($masterAsset->master_prices, 'SEK.value'))->toBe('110');

    $masterAsset->updateQuietly([
        'master_prices' => [
            'EUR' => ['value' => 10, 'independent' => false],
            'SEK' => ['value' => 200, 'independent' => true],
        ]
    ]);

    \App\Actions\Masters\MasterShop\RecalculateMasterShopMinorCurrencyPrices::run($masterShop, 'SEK');
    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, 'SEK.value'))->toBe(200);

    expect(fn () => \App\Actions\Masters\MasterShop\UpdateMasterShopPriceExchange::make()->action($masterShop, [
        'currency' => 'EUR',
        'is_major' => false,
        'major'    => 'SEK',
        'exchange' => 0.09,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('updating master prices cascades to children, updates baskets and breaks webpage cache', function () {
    $masterShop = createFreshMasterShop();

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'CASCDEP-'.uniqid(),
        'name' => 'Cascade Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'CASCFAM-'.uniqid(),
        'name' => 'Cascade Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'CASCAST-'.uniqid(),
        'name'    => 'Cascade Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    [, $product] = createProduct($this->shop);
    $currencyCode = $this->shop->currency->code;

    $website = createWebsite($this->shop);
    $webpage = \App\Actions\Web\Webpage\StoreWebpage::make()->action(
        $website->storefront,
        \App\Models\Web\Webpage::factory()->definition()
    );

    $this->shop->updateQuietly(['master_shop_id' => $masterShop->id]);
    $product->updateQuietly([
        'master_product_id' => $masterAsset->id,
        'units'             => 1,
    ]);
    $webpage->updateQuietly([
        'model_type' => 'Product',
        'model_id'   => $product->id,
    ]);

    $cacheKeyIn  = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_in_'.$webpage->id;
    $cacheKeyOut = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_out_'.$webpage->id;
    Cache::put($cacheKeyIn, 'cached-page', 600);
    Cache::put($cacheKeyOut, 'cached-page', 600);

    Queue::fake();
    config(['iris.cache.varnish' => true, 'iris.cache.varnish_hosts' => ['http://varnish.test']]);
    \Illuminate\Support\Facades\Http::fake();

    \App\Actions\Masters\MasterAsset\UpdateMasterAssetPrices::make()->action($masterAsset, [
        'master_prices' => [$currencyCode => ['value' => 123.45, 'independent' => false]],
        'master_rrps'   => [$currencyCode => ['value' => 199.99, 'independent' => false]],
    ]);

    $product->refresh();
    expect((float) $product->price)->toBe(123.45)
        ->and((float) $product->rrp)->toBe(199.99)
        ->and(Cache::has($cacheKeyIn))->toBeFalse()
        ->and(Cache::has($cacheKeyOut))->toBeFalse();

    \Illuminate\Support\Facades\Http::assertSentCount(1);
    \Illuminate\Support\Facades\Http::assertSent(
        fn ($request) => $request->hasHeader('x-ban-webpage', (string) $webpage->id)
    );

    Queue::assertPushed(
        \Lorisleiva\Actions\Decorators\UniqueJobDecorator::class,
        fn ($job) => $job->displayName() === \App\Actions\Catalogue\Product\UpdateOrdersInBasketsAfterProductUpdated::class
    );
    Queue::assertNotPushed(
        \Lorisleiva\Actions\Decorators\JobDecorator::class,
        fn ($job) => $job->displayName() === \App\Actions\Catalogue\Product\BreakProductInWebpagesCache::class
    );
});

test('GetMasterShopCurrenciesRate reads major/minor and exchange rates from master shop price_exchanges', function () {
    $masterShop = createFreshMasterShop();

    $gbp = Currency::where('code', 'GBP')->firstOrFail();
    $eur = Currency::where('code', 'EUR')->firstOrFail();

    // Reflects a shop where GBP (not EUR) is the configured major currency.
    $masterShop->update(['price_exchanges' => [
        'GBP' => ['is_major' => true],
        'EUR' => ['is_major' => false, 'major' => 'GBP', 'exchange' => 1.18, 'increment' => 0.05],
    ]]);

    $this->shop->updateQuietly(['master_shop_id' => $masterShop->id, 'currency_id' => $gbp->id]);

    $eurShop = \App\Actions\Catalogue\Shop\StoreShop::run(
        $this->organisation,
        array_merge(Shop::factory()->definition(), ['code' => 'EURSHOP'])
    );
    $eurShop->updateQuietly(['master_shop_id' => $masterShop->id, 'currency_id' => $eur->id]);

    $rates = GetMasterShopCurrenciesRate::run($masterShop);

    expect($rates['GBP']['is_major'])->toBeTrue()
        ->and($rates['GBP']['ratio_eur'])->toBe(1.0)
        ->and($rates['GBP']['major'])->toBeNull()
        ->and($rates['GBP']['increment'])->toBeNull()
        ->and($rates['EUR']['is_major'])->toBeFalse()
        ->and($rates['EUR']['ratio_eur'])->toBe(1.18)
        ->and($rates['EUR']['major'])->toBe('GBP')
        ->and($rates['EUR']['increment'])->toBe(0.05);
});

test('updating master prices merges per currency, skips nulls and syncs legacy columns from the base major', function () {
    $masterShop = createFreshMasterShop();
    $masterShop->update(['price_exchanges' => [
        'GBP' => ['is_major' => true],
        'EUR' => ['is_major' => false, 'major' => 'GBP', 'exchange' => 1.18],
    ]]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'MRGDEP-'.uniqid(),
        'name' => 'Merge Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'MRGFAM-'.uniqid(),
        'name' => 'Merge Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'MRGAST-'.uniqid(),
        'name'    => 'Merge Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'master_prices' => [
            'GBP' => ['value' => 10, 'independent' => false],
            'EUR' => ['value' => 11.8, 'independent' => false],
            'PLN' => ['value' => 50, 'independent' => true],
        ],
    ]);

    expect(\App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate::baseCurrencyCode($masterShop->price_exchanges))->toBe('GBP');

    \App\Actions\Masters\MasterAsset\UpdateMasterAssetPrices::make()->action($masterAsset, [
        'master_prices' => [
            'GBP' => ['value' => 20, 'independent' => false],
            'EUR' => ['value' => null, 'independent' => false],
        ],
    ]);

    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, 'GBP.value'))->toBe(20)
        ->and(data_get($masterAsset->master_prices, 'EUR.value'))->toBe(11.8)
        ->and(data_get($masterAsset->master_prices, 'PLN.value'))->toBe(50)
        ->and(data_get($masterAsset->master_prices, 'PLN.independent'))->toBeTrue()
        ->and((float) $masterAsset->price)->toBe(20.0);
});

test('reprocessing a master asset time series with a mid period window keeps the whole period total', function () {
    $masterShop       = createFreshMasterShop();
    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'TS-DEP-'.uniqid(),
        'name' => 'Time Series Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'TS-FAM-'.uniqid(),
        'name' => 'Time Series Family',
    ]);
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'TS-AST-'.uniqid(),
        'name'    => 'Time Series Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $customer      = createCustomer($this->shop);
    $taxCategoryId = DB::table('tax_categories')->value('id');
    $monthStart    = now()->subMonth()->startOfMonth();

    foreach ([[2, 100], [20, 250]] as [$dayOffset, $amount]) {
        DB::table('invoice_transactions')->insert([
            'group_id'        => $this->shop->group_id,
            'organisation_id' => $this->shop->organisation_id,
            'shop_id'         => $this->shop->id,
            'customer_id'     => $customer->id,
            'tax_category_id' => $taxCategoryId,
            'master_asset_id' => $masterAsset->id,
            'date'            => $monthStart->copy()->addDays($dayOffset),
            'quantity'        => 1,
            'net_amount'      => $amount,
            'grp_net_amount'  => $amount,
            'data'            => '{}',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    ProcessMasterAssetTimeSeriesRecords::run(
        $masterAsset->id,
        TimeSeriesFrequencyEnum::MONTHLY,
        $monthStart->toDateString(),
        $monthStart->copy()->addDays(2)->toDateString()
    );

    $record = DB::table('master_asset_time_series as ts')
        ->join('master_asset_time_series_records as r', 'r.master_asset_time_series_id', '=', 'ts.id')
        ->where('ts.master_asset_id', $masterAsset->id)
        ->where('ts.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
        ->where('r.period', $monthStart->format('Y-m'))
        ->first();

    expect((float) $record->sales_grp_currency_external)->toBe(350.0)
        ->and((int) $record->sold)->toBe(2);
});

test('master product creation seeds minor prices from the official exchange, not live FX', function () {
    $masterShop = createFreshMasterShop();
    $masterShop->update(['price_exchanges' => [
        'GBP' => ['is_major' => true],
        'EUR' => ['is_major' => false, 'major' => 'GBP', 'exchange' => 1.18],
    ]]);

    $gbp = Currency::where('code', 'GBP')->firstOrFail();
    $eur = Currency::where('code', 'EUR')->firstOrFail();

    $this->shop->updateQuietly([
        'master_shop_id' => $masterShop->id,
        'currency_id'    => $gbp->id,
        'state'          => ShopStateEnum::OPEN,
    ]);

    $eurShop = \App\Actions\Catalogue\Shop\StoreShop::run(
        $this->organisation,
        array_merge(Shop::factory()->definition(), ['code' => 'EFX'.substr(uniqid(), -4)])
    );
    $eurShop->updateQuietly([
        'master_shop_id' => $masterShop->id,
        'currency_id'    => $eur->id,
        'state'          => ShopStateEnum::OPEN,
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'FXDEP-'.uniqid(),
        'name' => 'FX Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'FXFAM-'.uniqid(),
        'name' => 'FX Family',
    ]);

    $tradeUnit = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition());

    $data = \App\Actions\Masters\MasterAsset\Json\GetTradeUnitDataForMasterProductCreation::make()->handle(
        $masterFamily,
        ['trade_units' => [['id' => $tradeUnit->id, 'quantity' => 1]]]
    );

    // The minor must follow the master shop's agreed rate, never a live market rate.
    expect(data_get($data, 'currencies.EUR.ratio_eur'))->toBe(1.18)
        ->and(data_get($data, 'currencies.EUR.is_major'))->toBeFalse()
        ->and(data_get($data, 'currencies.EUR.major'))->toBe('GBP')
        ->and(data_get($data, 'currencies.GBP.ratio_eur'))->toBe(1.0)
        ->and(data_get($data, 'currencies.GBP.is_major'))->toBeTrue();
});

test('minor currency recalculation includes variant master assets', function () {
    $masterShop = createFreshMasterShop();
    $masterShop->update(['price_exchanges' => [
        'EUR' => ['is_major' => true],
        'SEK' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 11],
    ]]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'VARDEP-'.uniqid(),
        'name' => 'Variant Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'VARFAM-'.uniqid(),
        'name' => 'Variant Family',
    ]);

    $variant = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'VARAST-'.uniqid(),
        'name'    => 'Variant Asset',
        'is_main' => false,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 10,
        'stocks'  => [],
    ]);

    $variant->updateQuietly([
        'status'        => true,
        'master_prices' => [
            'EUR' => ['value' => 10, 'independent' => false],
            'SEK' => ['value' => 999, 'independent' => false],
        ],
    ]);

    \App\Actions\Masters\MasterShop\RecalculateMasterShopMinorCurrencyPrices::run($masterShop, 'SEK');

    $variant->refresh();

    expect(data_get($variant->master_prices, 'SEK.value'))->toBe('110');
});

test('minor currency with zero fraction digits rounds converted prices up to whole numbers', function () {
    $masterShop = createFreshMasterShop();
    $masterShop->update(['price_exchanges' => [
        'EUR' => ['is_major' => true],
        'CZK' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 25.5, 'fraction_digits' => 0],
    ]]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'CZKDEP-'.uniqid(),
        'name' => 'CZK Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'CZKFAM-'.uniqid(),
        'name' => 'CZK Family',
    ]);

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'CZKAST-'.uniqid(),
        'name'    => 'CZK Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 9.76,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'status'        => true,
        'master_prices' => [
            'EUR' => ['value' => 9.76, 'independent' => false],
            'CZK' => ['value' => 248.88, 'independent' => false],
        ],
        'master_rrps'   => [
            'EUR' => ['value' => 19.98, 'independent' => false],
        ],
    ]);

    \App\Actions\Masters\MasterShop\RecalculateMasterShopMinorCurrencyPrices::run($masterShop, 'CZK');

    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, 'CZK.value'))->toBe('249')
        ->and(data_get($masterAsset->master_rrps, 'CZK.value'))->toBe('509.49');

    $this->artisan('master_shop:price_exchange', [
        'master_shop'       => $masterShop->slug,
        'currency'          => 'CZK',
        '--fraction-digits' => '2',
        '--force'           => true,
    ])->assertExitCode(0);

    $masterShop->refresh();
    expect($masterShop->price_exchanges['CZK'])
        ->toEqualCanonicalizing(['is_major' => false, 'major' => 'EUR', 'exchange' => 25.5, 'fraction_digits' => 2]);

    expect(formatPrice(9.76, 25.5))->toBe('248.88')
        ->and(formatPrice(9.76, 25.5, 0))->toBe('249')
        ->and(formatPrice(10, 25.5, 0))->toBe('255')
        ->and(formatPrice(1, 3, 0))->toBe('3')
        ->and(formatPrice(5.97, 4.3, 2, 0.05))->toBe('25.7')
        ->and(formatPrice(5, 4.3, 2, 0.05))->toBe('21.5')
        ->and(formatPrice(5.98, 4.3, 2, 0.05))->toBe('25.75');
});

test('minor currency with increment rounds converted prices and rrps up to the step', function () {
    $masterShop = createFreshMasterShop();
    $masterShop->update(['price_exchanges' => [
        'EUR' => ['is_major' => true],
        'PLN' => ['is_major' => false, 'major' => 'EUR', 'exchange' => 4.3, 'increment' => 0.05],
    ]]);

    $masterDepartment = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'PLNDEP-'.uniqid(),
        'name' => 'PLN Dept',
    ]);
    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'PLNFAM-'.uniqid(),
        'name' => 'PLN Family',
    ]);

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => 'PLNAST-'.uniqid(),
        'name'    => 'PLN Asset',
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => 5.97,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'status'        => true,
        'master_prices' => [
            'EUR' => ['value' => 5.97, 'independent' => false],
            'PLN' => ['value' => 25.67, 'independent' => false],
        ],
        'master_rrps'   => [
            'EUR' => ['value' => 19.98, 'independent' => false],
        ],
    ]);

    \App\Actions\Masters\MasterShop\RecalculateMasterShopMinorCurrencyPrices::run($masterShop, 'PLN');

    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, 'PLN.value'))->toBe('25.7')
        ->and(data_get($masterAsset->master_rrps, 'PLN.value'))->toBe('85.95');

    \App\Actions\Masters\MasterAsset\UpdateMasterAssetPrices::make()->action($masterAsset, [
        'master_prices' => [
            'EUR' => ['value' => 8.8, 'independent' => false],
            'PLN' => ['value' => 37.84, 'independent' => false],
        ],
    ]);

    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, 'PLN.value'))->toBe('37.85')
        ->and(data_get($masterAsset->master_prices, 'EUR.value'))->toBe(8.8);

    \App\Actions\Masters\MasterAsset\UpdateMasterAssetPrices::make()->action($masterAsset, [
        'master_prices' => [
            'PLN' => ['value' => 37.84, 'independent' => true],
        ],
    ]);

    $masterAsset->refresh();

    expect(data_get($masterAsset->master_prices, 'PLN.value'))->toBe(37.84);
});

test('master shop currencies rate can restrict to open shops only', function () {
    $masterShop = createFreshMasterShop();
    $masterShop->update(['price_exchanges' => [
        'GBP' => ['is_major' => true],
        'EUR' => ['is_major' => false, 'major' => 'GBP', 'exchange' => 1.18],
    ]]);

    $gbp = Currency::where('code', 'GBP')->firstOrFail();
    $eur = Currency::where('code', 'EUR')->firstOrFail();

    $this->shop->updateQuietly([
        'master_shop_id' => $masterShop->id,
        'currency_id'    => $gbp->id,
        'state'          => ShopStateEnum::OPEN,
    ]);

    $closedEurShop = \App\Actions\Catalogue\Shop\StoreShop::run(
        $this->organisation,
        array_merge(Shop::factory()->definition(), ['code' => 'CLS'.substr(uniqid(), -4)])
    );
    $closedEurShop->updateQuietly([
        'master_shop_id' => $masterShop->id,
        'currency_id'    => $eur->id,
        'state'          => ShopStateEnum::CLOSED,
    ]);

    $allShops  = GetMasterShopCurrenciesRate::run($masterShop);
    $openShops = GetMasterShopCurrenciesRate::run($masterShop, onlyOpenShops: true);

    // Edit / bulk edit / family pages keep a closed shop's currency editable.
    expect($allShops->keys()->all())->toContain('GBP', 'EUR')
        ->and($allShops['EUR']['ratio_eur'])->toBe(1.18)
        // Master product creation only seeds currencies of shops that will actually sell.
        ->and($openShops->keys()->all())->toContain('GBP')
        ->and($openShops->keys()->all())->not->toContain('EUR');
});

test('master products in trade unit index uses time series aggregation', function () {
    request()->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'test', []));
    $tradeUnits = createTradeUnits($this->group);

    expect(\App\Actions\Masters\MasterAsset\UI\IndexMasterProductsInTradeUnit::make()->handle($tradeUnits[0])->total())->toBeGreaterThanOrEqual(0);
});

test('AddMissingFamiliesToMaster mirrors every shop family state onto the master', function (
    \App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum $state,
    bool $expectedStatus,
    bool $expectedMarkForDiscontinued
) {
    $masterShop = createFreshMasterShop();
    $this->shop->update(['master_shop_id' => $masterShop->id]);

    $department = \App\Actions\Catalogue\ProductCategory\StoreProductCategory::make()->action($this->shop, [
        'code' => 'DEP'.uniqid(),
        'name' => 'Test department',
        'type' => \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $family = \App\Actions\Catalogue\ProductCategory\StoreProductCategory::make()->action($department, [
        'code' => 'FAM'.uniqid(),
        'name' => 'Test family',
        'type' => \App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum::FAMILY,
    ]);
    $family->update(['state' => $state, 'master_product_category_id' => null]);

    \App\Actions\Maintenance\Masters\AddMissingFamiliesToMaster::make()->handle($this->shop, $masterShop);

    $family->refresh();
    $masterFamily = MasterProductCategory::find($family->master_product_category_id);

    expect($masterFamily)->not->toBeNull()
        ->and($masterFamily->master_shop_id)->toBe($masterShop->id)
        ->and($masterFamily->status)->toBe($expectedStatus)
        ->and($masterFamily->mark_for_discontinued)->toBe($expectedMarkForDiscontinued);
})->with([
    'active' => [\App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::ACTIVE, true, false],
    'discontinuing' => [\App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::DISCONTINUING, false, true],
    'discontinued' => [\App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::DISCONTINUED, false, false],
    'inactive' => [\App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::INACTIVE, false, false],
    'in process' => [\App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum::IN_PROCESS, false, false],
]);

test('MatchAssetsToMaster links an asset once and skips redundant writes', function () {
    $masterShop = createFreshMasterShop();
    $this->shop->update(['master_shop_id' => $masterShop->id]);

    createProduct($this->shop);
    $product = $this->shop->products()->orderBy('id')->first();

    $masterFamily = StoreMasterProductCategory::make()->action(
        parent: $masterShop,
        modelData: [
            'code' => 'MFAM'.uniqid(),
            'name' => 'Master family',
            'type' => MasterProductCategoryTypeEnum::FAMILY,
        ],
        createChildren: false
    );

    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'  => $product->code,
        'name'  => $product->name,
        'type'  => MasterAssetTypeEnum::PRODUCT,
        'price' => 100,
        'unit'  => 'piece',
    ]);

    \App\Actions\Masters\MasterAsset\MatchAssetsToMaster::run($product->asset);

    $product->refresh();
    $asset = $product->asset->refresh();

    expect($asset->master_asset_id)->toBe($masterAsset->id)
        ->and($product->master_product_id)->toBe($masterAsset->id);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    \App\Actions\Masters\MasterAsset\MatchAssetsToMaster::run($asset);

    expect($queries)->toBeLessThan(5)
        ->and($asset->refresh()->master_asset_id)->toBe($masterAsset->id)
        ->and($product->refresh()->master_product_id)->toBe($masterAsset->id);
});
