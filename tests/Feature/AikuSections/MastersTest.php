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
use App\Actions\Masters\MasterCollection\HydrateMasterCollection as HydrateMasterCollectionAction;
use App\Actions\Masters\MasterCollection\UpdateMasterCollection;
use App\Actions\Masters\MasterCollection\DeleteMasterCollection;
use App\Actions\Masters\MasterCollection\StoreMasterCollection;
use App\Actions\Masters\MasterCollection\AttachMasterCollectionToModel;
use App\Actions\Masters\MasterCollection\AttachModelsToMasterCollection;
use App\Actions\Masters\MasterCollection\AttachModelToMasterCollection;
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
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterCollectionStats;
use App\Models\Masters\MasterCollectionOrderingStats;
use App\Models\Masters\MasterCollectionSalesIntervals;
use App\Models\Masters\MasterShopOrderingIntervals;
use App\Models\Masters\MasterShopOrderingStats;
use App\Models\Masters\MasterShopSalesIntervals;
use App\Models\Masters\MasterShopStats;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\Bus;

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
                fn (AssertableInertia $head) =>
                $head
                    ->where('title', fn ($title) => is_string($title) && $title !== '')
                    ->has('actions', 1)
                    ->where('actions.0.type', 'button')
                    ->where('actions.0.style', 'cancel')
                    ->where('actions.0.route.name', 'grp.masters.master_shops.show')
            )
            ->has(
                'formData',
                fn (AssertableInertia $form) =>
                $form
                    ->has('blueprint', 2)
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
            ->has("breadcrumbs", 4)
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
            ->has("breadcrumbs", 4)
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
                fn (AssertableInertia $page) =>
                $page->has('subNavigation')->etc()
            );
    });
})->depends('create master shop')->todo();

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

test('update master department', function (MasterProductCategory $masterProductCategory) {
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

    return $updatedMasterProductCategory;
})->depends("create master department");


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

test('create master asset', function (MasterProductCategory $masterFamily) {
    $masterAsset = StoreMasterAsset::make()->action(
        $masterFamily,
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


    expect($masterAsset)->toBeInstanceOf(MasterAsset::class)
        ->and($masterAsset->stats)->toBeInstanceOf(MasterAssetStats::class)
        ->and($masterAsset->orderingStats)->toBeInstanceOf(MasterAssetOrderingStats::class)
        ->and($masterAsset->orderingIntervals)->toBeInstanceOf(MasterAssetOrderingIntervals::class)
        ->and($masterAsset->salesIntervals)->toBeInstanceOf(MasterAssetSalesIntervals::class)
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
})->depends('create master department');

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
})->depends('create master department');

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

test('create master collection', function (MasterProductCategory $masterFamily) {
    // Create a master collection under the previously created master family
    $masterCollection = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC1',
            'name' => 'master collection 1',
        ],
        hydratorsDelay: 0,
        strict: true,
        audit: true,
        createChildren: false
    );

    $masterCollection->refresh();

    expect($masterCollection)->toBeInstanceOf(MasterCollection::class)
        ->and($masterCollection->stats)->toBeInstanceOf(MasterCollectionStats::class)
        ->and($masterCollection->orderingStats)->toBeInstanceOf(MasterCollectionOrderingStats::class)
        ->and($masterCollection->salesIntervals)->toBeInstanceOf(MasterCollectionSalesIntervals::class)
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

test('update master collection', function (MasterCollection $masterCollection) {
    // Pre-assert existing values
    expect($masterCollection->code)->toBe('MC1')
        ->and($masterCollection->name)->toBe('master collection 1');

    // Perform update via action
    UpdateMasterCollection::make()->action(
        $masterCollection,
        [
            'code' => 'MC1-UPDATED',
            'name' => 'Master Collection Updated',
            'description' => 'Updated description',
        ],
        hydratorsDelay: 0,
        strict: true,
        audit: true
    );

    // Refresh model and assert changes persisted
    $masterCollection->refresh();

    expect($masterCollection->code)->toBe('MC1-UPDATED')
        ->and($masterCollection->name)->toBe('Master Collection Updated')
        ->and($masterCollection->description)->toBe('Updated description');
})->depends('create master collection');

test('soft delete master collection', function (MasterProductCategory $masterFamily) {
    // Create a throwaway master collection to delete
    $mc = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC-DEL-SOFT',
            'name' => 'to be soft deleted',
        ],
        createChildren: false
    );

    $mc->refresh();

    DeleteMasterCollection::make()->action($mc, false);

    $mc->refresh();
    expect($mc->trashed())->toBeTrue();

})->depends('create master family');

test('attach family to master collection', function (MasterProductCategory $masterFamily, MasterCollection $masterCollection) {
    expect($masterCollection->masterFamilies->pluck('id'))
        ->not->toContain($masterFamily->id);

    AttachModelToMasterCollection::make()->action($masterCollection, $masterFamily);
    $masterCollection->refresh();

    expect($masterCollection->masterFamilies->pluck('id'))
        ->toContain($masterFamily->id);

    $count = $masterCollection->masterFamilies()
        ->where('master_product_categories.id', $masterFamily->id)
        ->count();

    AttachModelToMasterCollection::make()->action($masterCollection, $masterFamily);
    $masterCollection->refresh();

    expect($masterCollection->masterFamilies()
        ->where('master_product_categories.id', $masterFamily->id)
        ->count())->toBe($count);
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

    expect($masterCollection->masterCollections()
        ->where('master_collections.id', $extra->id)
        ->count())->toBe($count);
})->depends('create master family', 'create master collection');

test('force delete master collection', function (MasterProductCategory $masterFamily) {
    // Create a throwaway master collection to force delete
    $mc = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC-DEL-FORCE',
            'name' => 'to be force deleted',
        ],
        hydratorsDelay: 0,
        strict: true,
        audit: true,
        createChildren: false
    );

    $mc->refresh();

    $id = $mc->id;

    // Perform force delete
    DeleteMasterCollection::make()->action($mc, true);

    // Assert the record is fully removed (even with trashed)
    $found = MasterCollection::withTrashed()->find($id);
    expect($found)->toBeNull();

    // Note: Hydrator dispatch is implementation detail and may run synchronously; we only assert deletion here.
})->depends('create master family');

test('attach models to master collection', function (MasterProductCategory $masterFamily, MasterCollection $masterCollection) {
    // Create an additional master collection to be attached as a child collection
    $anotherCollection = StoreMasterCollection::make()->action(
        $masterFamily,
        [
            'code' => 'MC-ATTACH-CHILD',
            'name' => 'child collection to attach',
        ],
        hydratorsDelay: 0,
        strict: true,
        audit: true,
        createChildren: false
    );

    $anotherCollection->refresh();

    // Perform attachment: family and collection
    AttachModelsToMasterCollection::make()->action(
        $masterCollection,
        [
            'families' => [$masterFamily->id],
            'collections' => [$anotherCollection->id],
        ]
    );

    // Refresh and assert relations now include the attachments
    $masterCollection->refresh();

    expect($masterCollection->masterFamilies->pluck('id')->all())
        ->toContain($masterFamily->id)
        ->and($masterCollection->masterCollections->pluck('id')->all())
        ->toContain($anotherCollection->id);

    // Idempotency: re-run with duplicate IDs should not create duplicates
    AttachModelsToMasterCollection::make()->action(
        $masterCollection,
        [
            'families' => [$masterFamily->id, $masterFamily->id],
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
    // Pre-assert: not attached yet
    expect(
        $masterDepartment->masterCollections()
            ->where('master_collections.id', $masterCollection->id)
            ->exists()
    )->toBeFalse();

    // Attach
    AttachMasterCollectionToModel::make()->action($masterDepartment, $masterCollection);

    // Refresh and assert pivot with correct type
    $masterDepartment->refresh();
    $attached = $masterDepartment->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->wherePivot('type', 'master_department')
        ->exists();

    expect($attached)->toBeTrue();
})->depends('create master department', 'create master collection');

test('attach master collection to sub department', function (MasterProductCategory $masterSubDepartment, MasterCollection $masterCollection) {
    Bus::fake();
    // Ensure the provided category is a sub-department
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
    // First attach
    AttachMasterCollectionToModel::make()->action($masterDepartment, $masterCollection);
    // Second attach should not duplicate
    AttachMasterCollectionToModel::make()->action($masterDepartment, $masterCollection);

    $count = $masterDepartment->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->count();

    expect($count)->toBe(1);
})->depends('create master department', 'create master collection');

test('attach master collection without children', function (MasterProductCategory $masterDepartment, MasterCollection $masterCollection) {
    Bus::fake();
    // Explicitly call handle with attachChildren = false
    AttachMasterCollectionToModel::make()->handle($masterDepartment, $masterCollection, false);

    $exists = $masterDepartment->masterCollections()
        ->where('master_collections.id', $masterCollection->id)
        ->exists();

    expect($exists)->toBeTrue();
})->depends('create master department', 'create master collection');
