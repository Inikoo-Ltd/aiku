<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Dec 2024 20:13:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Goods\Ingredient\StoreIngredient;
use App\Actions\Goods\Ingredient\UpdateIngredient;
use App\Actions\Goods\Stock\HydrateStock;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\Goods\StockFamily\DeleteStockFamily;
use App\Actions\Goods\StockFamily\HydrateStockFamily;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\StockFamily\UpdateStockFamily;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Masters\MasterShop\HydrateMasterShop;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Masters\MasterShop\UpdateMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Goods\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Models\Goods\Ingredient;
use App\Models\Goods\MasterAsset;
use App\Models\Goods\MasterAssetOrderingIntervals;
use App\Models\Goods\MasterAssetOrderingStats;
use App\Models\Goods\MasterAssetSalesIntervals;
use App\Models\Goods\MasterAssetStats;
use App\Models\Goods\MasterProductCategory;
use App\Models\Goods\MasterProductCategoryOrderingIntervals;
use App\Models\Goods\MasterProductCategoryOrderingStats;
use App\Models\Goods\MasterProductCategorySalesIntervals;
use App\Models\Goods\MasterProductCategoryStats;
use App\Models\Goods\MasterShop;
use App\Models\Goods\MasterShopOrderingIntervals;
use App\Models\Goods\MasterShopOrderingStats;
use App\Models\Goods\MasterShopSalesIntervals;
use App\Models\Goods\MasterShopStats;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
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


test('create stock family', function () {
    $stockFamily = StoreStockFamily::make()->action(
        $this->group,
        StockFamily::factory()->definition()
    );

    expect($stockFamily)->toBeInstanceOf($stockFamily::class)
        ->and($this->group->goodsStats->number_stock_families)->toBe(1)
        ->and($this->group->goodsStats->number_current_stock_families)->toBe(0);

    return $stockFamily;
});

test('update stock family', function (StockFamily $stockFamily) {
    $stockFamily = UpdateStockFamily::make()->action(
        $stockFamily,
        [
            'code' => 'A0001',
            'name' => 'Updated Stock Family Name'
        ]
    );

    expect($stockFamily->code)->toBe('A0001')
        ->and($stockFamily->name)->toBe('Updated Stock Family Name');

    return $stockFamily;
})->depends('create stock family');


test('create stock as draft', function () {
    $stockData = Stock::factory()->definition();
    $stock     = StoreStock::make()->action($this->group, $stockData);

    $tradeUnit = $this->group->tradeUnits->first();

    expect($stock)->toBeInstanceOf(Stock::class)
        ->and($this->group->goodsStats->number_trade_units)->toBe(1)
        ->and($this->group->goodsStats->number_stocks)->toBe(1)
        ->and($this->group->goodsStats->number_stocks_state_in_process)->toBe(1)
        ->and($this->group->goodsStats->number_current_stocks)->toBe(0)
        ->and($tradeUnit)->toBeInstanceOf(TradeUnit::class)
        ->and($tradeUnit->code)->toBe($stockData['code'])
        ->and($tradeUnit->name)->toBe($stockData['name']);

    return $stock->fresh();
});

test('create stock in stock family', function (StockFamily $stockFamily) {
    $stockData = Stock::factory()->definition();
    data_set($stockData, 'state', StockStateEnum::ACTIVE);
    $stock = StoreStock::make()->action($stockFamily, $stockData);

    expect($stock)->toBeInstanceOf(Stock::class)
        ->and($stockFamily->state)->toBe(StockFamilyStateEnum::IN_PROCESS)
        ->and($this->group->goodsStats->number_stocks)->toBe(2)
        ->and($this->group->goodsStats->number_stocks_state_in_process)->toBe(1)
        ->and($this->group->goodsStats->number_current_stocks)->toBe(1)
        ->and($this->group->goodsStats->number_stock_families)->toBe(1)
        ->and($this->group->goodsStats->number_current_stock_families)->toBe(1);


    return $stock->fresh();
})->depends('create stock family');

test('activate draft stock', function (Stock $stock) {
    UpdateStock::make()->action(
        $stock,
        [
            'state' => StockStateEnum::ACTIVE
            ]
    );

    expect($stock->state)->toBe(StockStateEnum::ACTIVE)
    ->and($this->group->goodsStats->number_stocks_state_in_process)->toBe(0)
        ->and($this->group->goodsStats->number_current_stocks)->toBe(2);

    return $stock;
})->depends('create stock as draft');

test('delete stock family', function ($stockFamily) {
    $deletedStockFamily = DeleteStockFamily::make()->action($stockFamily);

    expect(StockFamily::find($deletedStockFamily->id))->toBeNull();

    return $deletedStockFamily;
})->depends('create stock family');

test('store ingredient', function () {
    $ingredient = StoreIngredient::make()->action($this->group, [
        'name' => 'test'
    ]);

    expect($ingredient)->toBeInstanceOf(Ingredient::class)
        ->and($ingredient->name)->toBe('test');

    return $ingredient;
});

test('update ingredient', function (Ingredient $ingredient) {
    $ingredient = UpdateIngredient::make()->action($ingredient, [
        'name' => 'update'
    ]);

    expect($ingredient)->toBeInstanceOf(Ingredient::class)
        ->and($ingredient->name)->toBe('update');
})->depends('store ingredient');

test("UI Show Goods Dashboard", function () {
    $response    = get(
        route("grp.goods.dashboard")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/GoodsDashboard")
            ->has("breadcrumbs", 2)
            ->has("title")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                $page->where("title", 'goods strategy')
                    ->etc()
            )
            ->has("flatTreeMaps");
    });
});

test("UI Edit stock family", function () {
    $stockFamily = StoreStockFamily::make()->action(
        $this->group,
        StockFamily::factory()->definition()
    );
    $response    = get(
        route("grp.goods.stock-families.edit", [$stockFamily])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($stockFamily) {
        $page
            ->component("EditModel")
            ->has("breadcrumbs", 3)
            ->has("title")
            ->has("navigation")
            ->has("formData", fn ($page) => $page->where("args", [
                'updateRoute' => [
                    'name'       => 'grp.models.stock-family.update',
                    'parameters' => $stockFamily->id
                ],
            ])->etc())
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $stockFamily->name)->etc()
            )
            ->has("formData");
    });
});

test("UI Show Stock Family", function () {
    $stockFamily = StockFamily::first();

    $response = get(
        route("grp.goods.stock-families.show", [$stockFamily->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($stockFamily) {
        $page
            ->component("Goods/StockFamily")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has('navigation')
            ->has('tabs')
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $stockFamily->name)->etc()
            );
    });
});

test("UI Index Stocks", function () {
    $response = get(
        route("grp.goods.stocks.index")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/Stocks")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has("pageHead")
            ->has("data");
    });
});

test("UI Show Stocks", function () {
    $stock    = Stock::first();
    $response = get(
        route("grp.goods.stocks.show", [$stock->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($stock) {
        $page
            ->component("Goods/Stock")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $stock->slug)->etc()
            )
            ->has("tabs");
    });
});

test("UI Index Trade Units", function () {
    $response = get(
        route("grp.goods.trade-units.index")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/TradeUnits")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has("pageHead")
            ->has("data");
    });
});

test("UI Show TradeUnit", function () {
    $tradeUnit = TradeUnit::first();
    $response  = get(
        route("grp.goods.trade-units.show", [$tradeUnit->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($tradeUnit) {
        $page
            ->component("Goods/TradeUnit")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $tradeUnit->code)->etc()
            )
            ->has("tabs");
    });
});

test("UI Create Stock in Group", function () {
    $response = get(
        route("grp.goods.stocks.create")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("CreateModel")
            ->where("title", "new stock")
            ->has("breadcrumbs", 4)
            ->has('icon')
            ->has('formData', fn (AssertableInertia $page) => $page->where("route", [
                'name'       => 'grp.models.stock.store',
                'parameters' => []
            ])->etc())
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'new SKU')->etc()
            );
    });
});

test('UI index goods ingredients', function () {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.goods.ingredients.index'
        )
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/Ingredients')
            ->where('title', 'Ingredients')
            ->has('breadcrumbs', 3)
            ->has('data')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Ingredients')
                    ->etc()
            );
    });
});

test('UI show goods ingredients', function (Ingredient $ingredient) {
    $this->withoutExceptionHandling();
    $response = get(
        route(
            'grp.goods.ingredients.show',
            [
                $ingredient->slug
            ]
        )
    );

    $response->assertInertia(function (AssertableInertia $page) use ($ingredient) {
        $page
            ->component('Goods/Ingredient')
            ->where('title', 'ingredient')
            ->has('breadcrumbs', 3)
            ->has('tabs')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $ingredient->name)
                    ->etc()
            );
    });
})->depends('store ingredient');

test("UI Edit Stock in Group", function () {
    $stock    = Stock::first();
    $response = get(
        route("grp.goods.stocks.edit", [$stock->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($stock) {
        $page
            ->component("EditModel")
            ->where("title", "sku")
            ->has("breadcrumbs", 3)
            ->has('navigation')
            ->has('formData', fn (AssertableInertia $page) => $page->where("args", [
                'updateRoute' => [
                    'name'       => 'grp.models.stock.update',
                    'parameters' => $stock->id
                ],
            ])->etc())
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $stock->name)->etc()
            );
    });
});

test("UI Create Stock in Stock Family Group", function () {
    $stockFamily = StockFamily::first();
    $response    = get(
        route("grp.goods.stock-families.show.stocks.create", [$stockFamily->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($stockFamily) {
        $page
            ->component("CreateModel")
            ->where("title", "new stock")
            ->has("breadcrumbs", 5)
            ->has('icon')
            ->has('formData', fn (AssertableInertia $page) => $page->where("route", [
                'name'       => 'grp.models.stock-family.stock.store',
                'parameters' => [
                    'stockFamily' => $stockFamily->id
                ]
            ])->etc())
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'new SKU')->etc()
            );
    });
});

test("UI Index Master Shops", function () {
    $response = get(
        route("grp.masters.shops.index")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/MasterShops")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has("data")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) =>
                    $page->has('subNavigation')->etc()
            );
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
        route("grp.masters.shops.show", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($masterShop) {
        $page
            ->component("Goods/MasterShop")
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
        route("grp.masters.shops.show.departments.index", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/MasterDepartments")
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

test("UI Index Master Families", function (MasterShop $masterShop) {
    $response = get(
        route("grp.masters.shops.show.families.index", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/MasterFamilies")
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
        route("grp.masters.shops.show.sub-departments.index", [$masterShop->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/MasterSubDepartments")
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

test('Hydrate stocks', function () {
    HydrateStock::run(Stock::first());
    $this->artisan('hydrate:stocks')->assertSuccessful();
});

test('Hydrate stock families', function () {
    HydrateStockFamily::run(StockFamily::first());
    $this->artisan('hydrate:stock_families')->assertSuccessful();
});

test('goods hydrator', function () {
    $this->artisan('hydrate -s goods')->assertExitCode(0);
});
