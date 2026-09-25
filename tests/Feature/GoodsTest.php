<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Dec 2024 20:13:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\SalesAnalysis\GetSalesAnalysis;
use App\Actions\Catalogue\SalesAnalysis\SalesAnalysisScope;
use App\Actions\Goods\Ingredient\Json\ParseIngredientsList;
use App\Actions\Goods\Ingredient\StoreIngredient;
use App\Actions\Goods\Ingredient\UpdateIngredient;
use App\Actions\Goods\Stock\HydrateStocks;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\UI\ShowGoodsAnalysis;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Actions\Goods\Stock\SyncStockTradeUnits;
use App\Actions\Goods\StockFamily\DeleteStockFamily;
use App\Actions\Goods\StockFamily\HydrateStockFamily;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\StockFamily\UpdateStockFamily;
use App\Actions\Goods\TradeUnit\HydrateTradeUnits;
use App\Actions\Goods\TradeUnitFamily\Hydrators\TradeUnitFamilyHydrateTradeUnits;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Models\Goods\Ingredient;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use Inertia\Testing\AssertableInertia;
use Illuminate\Validation\ValidationException;
use App\Models\Goods\TradeUnitFamily as TradeUnitFamilyModel;
use App\Actions\Goods\TradeUnitFamily\StoreTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UpdateTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\IndexTradeUnitFamilies;

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
        ->and($this->group->goodsStats->number_stocks_state_in_process)->toBe(2)
        ->and($this->group->goodsStats->number_current_stocks)->toBe(0)
        ->and($this->group->goodsStats->number_stock_families)->toBe(1)
        ->and($this->group->goodsStats->number_current_stock_families)->toBe(0);


    return $stock->fresh();
})->depends('create stock family');


test('delete stock family', function ($stockFamily) {
    $deletedStockFamily = DeleteStockFamily::make()->action($stockFamily);

    expect(StockFamily::find($deletedStockFamily->id))->toBeNull();

    return $deletedStockFamily;
})->depends('create stock family');

test('store ingredient', function () {
    $ingredientData = Ingredient::factory()->definition();
    $ingredient     = StoreIngredient::make()->action($this->group, $ingredientData);

    expect($ingredient)->toBeInstanceOf(Ingredient::class)
        ->and($ingredient->name)->toBe($ingredientData['name']);

    return $ingredient;
});

test('update ingredient', function (Ingredient $ingredient) {
    $ingredient = UpdateIngredient::make()->action($ingredient, [
        'name' => 'update'
    ]);

    expect($ingredient)->toBeInstanceOf(Ingredient::class)
        ->and($ingredient->name)->toBe('update');

    return $ingredient;
})->depends('store ingredient');

test('parse pasted ingredients list', function () {
    StoreIngredient::make()->action($this->group, ['name' => 'Aqua']);

    $parsed = ParseIngredientsList::make()->handle($this->group, "aqua, Linalool*\nGlycerin, , Glycerin", false);

    expect($parsed)->toHaveCount(3)
        ->and($parsed[0])->toBe(['name' => 'Aqua', 'slug' => 'aqua', 'is_new' => false])
        ->and($parsed[1]['name'])->toBe('Linalool')
        ->and($parsed[1]['is_new'])->toBeTrue()
        ->and($parsed[1]['slug'])->toBeNull()
        ->and(Ingredient::where('name', 'Linalool')->exists())->toBeFalse();

    $committed = ParseIngredientsList::make()->handle($this->group, 'Linalool*', true);

    expect($committed[0]['slug'])->not->toBeNull()
        ->and(Ingredient::where('name', 'Linalool')->exists())->toBeTrue();
});

test('index ingredients', function () {
    $count = Ingredient::count();
    Ingredient::factory()->count(3)->create(['group_id' => $this->group->id]);

    get(route('grp.goods.ingredients.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Goods/Ingredients')
                ->has('data.data', $count + 3)
        );
});

test('show ingredient', function (Ingredient $ingredient) {
    get(route('grp.goods.ingredients.show', ['ingredient' => $ingredient->slug]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Goods/Ingredient')
                ->has('pageHead.title')
        );
})->depends('update ingredient');

test("UI Show Goods Dashboard", function () {
    $response = get(route("grp.goods.dashboard", ["condition" => "oos", "sort" => "code"]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/ProductCommandControl")
            ->has("breadcrumbs", 2)
            ->has("title")
            ->where("pageHead.title", "Product Command & Control")
            ->has("kpis")
            ->has("rows")
            ->has("organisations")
            ->where("filters.condition", "oos")
            ->where("filters.sort", "code");
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

test("UI Show Stock Family sales analysis tab", function () {
    $stockFamily = StockFamily::first();

    $response = get(route('grp.goods.stock-families.show', [
        'stockFamily' => $stockFamily->slug,
        'tab'         => \App\Enums\UI\SupplyChain\StockFamilyTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/StockFamily')
            ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
            ->where('sales_analysis.frequency', 'daily')
            ->has('sales_analysis.breakdown')
            ->has('sales_analysis.stock_outs')
            ->has('sales_analysis.events')
            ->has('sales_analysis.filters.organisations');
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forStockFamily($stockFamily));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test("UI Index Stocks", function () {
    $this->withoutExceptionHandling();
    $response = get(
        route("grp.goods.stocks.index")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/Stocks")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has("pageHead");
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
                fn (AssertableInertia $page) => $page->where("title", $stock->code)->etc()
            )
            ->has("tabs");
    });
});

test("UI Show Stocks sales analysis tab", function () {
    $stock = Stock::first();

    $response = get(route('grp.goods.stocks.show', [
        'stock'       => $stock->slug,
        'tab'         => \App\Enums\UI\SupplyChain\StockTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/Stock')
            ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
            ->where('sales_analysis.frequency', 'daily')
            ->has('sales_analysis.breakdown')
            ->has('sales_analysis.stock_outs')
            ->has('sales_analysis.events')
            ->has('sales_analysis.filters.organisations');
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forStock($stock));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test("UI show stock navigation follows the bucket and sort", function () {
    $this->withoutExceptionHandling();

    $makeStock = function (string $code, StockStateEnum $state) {
        $stock = StoreStock::make()->action(
            $this->group,
            array_merge(Stock::factory()->definition(), ['code' => $code])
        );
        $stock->update(['state' => $state]);

        return $stock->refresh();
    };

    $first        = $makeStock('NAVSKOA', StockStateEnum::ACTIVE);
    $discontinued = $makeStock('NAVSKOB', StockStateEnum::DISCONTINUED);
    $middle       = $makeStock('NAVSKOC', StockStateEnum::ACTIVE);
    $last         = $makeStock('NAVSKOD', StockStateEnum::ACTIVE);

    get(route("grp.goods.stocks.active_stocks.show", [$middle->slug]))->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->name)
            ->where('navigation.next.label', $last->name)
            ->etc()
    );

    get(route("grp.goods.stocks.active_stocks.show", [$middle->slug]).'?bucket_sort=-code')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $last->name)
            ->where('navigation.next.label', $first->name)
            ->etc()
    );

    expect($discontinued->state)->toBe(StockStateEnum::DISCONTINUED);
});

test("UI Index Trade Units", function () {
    $response = get(
        route("grp.trade_units.units.index")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/TradeUnits")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has("pageHead");

    });
});

test("UI Index Orphan Trade Units", function () {
    $response = get(
        route("grp.trade_units.units.orphan")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/TradeUnits")
            ->has("title")
            ->has("breadcrumbs", 3)
            ->has("pageHead");

    });
});

test("UI Show TradeUnit", function () {
    $this->withoutExceptionHandling();
    $tradeUnit = TradeUnit::first();
    if (!$tradeUnit) {
        $tradeUnit = TradeUnit::factory()->create([
            'group_id' => $this->group->id,
            'code'     => 'TU-'.uniqid(),
            'name'     => 'Sample TU',
        ]);
    }
    if (!$tradeUnit->stats) {
        $tradeUnit->stats()->create();
    }
    $response = get(
        route("grp.trade_units.units.show", [$tradeUnit->slug])
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

test("UI Show TradeUnit sales analysis tab", function () {
    $tradeUnit = TradeUnit::first();
    if (!$tradeUnit) {
        $tradeUnit = TradeUnit::factory()->create([
            'group_id' => $this->group->id,
            'code'     => 'TU-'.uniqid(),
            'name'     => 'Sample TU',
        ]);
    }
    if (!$tradeUnit->stats) {
        $tradeUnit->stats()->create();
    }

    $response = get(route('grp.trade_units.units.show', [
        'tradeUnit'   => $tradeUnit->slug,
        'tab'         => \App\Enums\UI\SupplyChain\TradeUnitTabsEnum::SALES_ANALYSIS->value,
        'from'        => '2026-01-01',
        'to'          => '2026-03-31',
        'compareFrom' => '2025-01-01',
        'compareTo'   => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/TradeUnit')
            ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
            ->where('sales_analysis.frequency', 'daily')
            ->has('sales_analysis.breakdown')
            ->has('sales_analysis.stock_outs')
            ->has('sales_analysis.events')
            ->has('sales_analysis.filters.organisations');
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forTradeUnit($tradeUnit));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test("UI show trade unit navigation stays in the bucket and the group", function () {
    $this->withoutExceptionHandling();

    $makeTradeUnit = function (string $code, TradeUnitStatusEnum $status, $group) {
        $tradeUnit = TradeUnit::factory()->create([
            'group_id' => $group->id,
            'code'     => $code,
            'name'     => $code.' name',
            'status'   => $status,
        ]);
        $tradeUnit->stats()->create();

        return $tradeUnit;
    };

    $first        = $makeTradeUnit('NAVTUA', TradeUnitStatusEnum::ACTIVE, $this->group);
    $discontinued = $makeTradeUnit('NAVTUB', TradeUnitStatusEnum::DISCONTINUED, $this->group);
    $middle       = $makeTradeUnit('NAVTUC', TradeUnitStatusEnum::ACTIVE, $this->group);
    $last         = $makeTradeUnit('NAVTUD', TradeUnitStatusEnum::ACTIVE, $this->group);

    $otherGroup = \App\Actions\SysAdmin\Group\StoreGroup::make()->action(\App\Models\SysAdmin\Group::factory()->definition());
    $makeTradeUnit('NAVTUB2', TradeUnitStatusEnum::ACTIVE, $otherGroup);

    get(route("grp.trade_units.units.show", [$middle->slug]).'?bucket=active')->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.label', $first->name)
            ->where('navigation.next.label', $last->name)
            ->etc()
    );

    expect($discontinued->status)->toBe(TradeUnitStatusEnum::DISCONTINUED);
});

test("UI Edit Trade Unit", function () {
    $tradeUnit = TradeUnit::first();
    if (!$tradeUnit) {
        $tradeUnit = TradeUnit::factory()->create([
            'group_id' => $this->group->id,
            'code'     => 'TU-'.uniqid(),
            'name'     => 'Sample TU',
        ]);
    }
    $response = get(
        route("grp.trade_units.units.edit", [$tradeUnit->slug])
    );

    $response->assertInertia(function (AssertableInertia $page) use ($tradeUnit) {
        $page
            ->component("EditModel")
            ->has("breadcrumbs", 3)
            ->has("title")
            ->has("navigation")
            ->has(
                "formData",
                fn ($page) => $page->where("args", [
                    'updateRoute' => [
                        'name'       => 'grp.models.trade-unit.update',
                        'parameters' => $tradeUnit->id,
                    ],
                ])->etc()
            )
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", $tradeUnit->name)->etc()
            )
            ->where("formData.blueprint", function ($blueprint) {
                $languagesField = collect($blueprint)->pluck('fields.languages')->filter()->first();

                expect($languagesField['labelProp'])->toBe('label')
                    ->and($languagesField['options'])->not->toBeEmpty();

                foreach ($languagesField['options'] as $language) {
                    expect($language['label'])->toBe('('.strtoupper($language['code']).') '.$language['name']);
                }

                return true;
            });
    });
});

test("UI Show Trade Units Dashboard", function () {
    $response = get(
        route("grp.trade_units.dashboard")
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("Goods/GoodsDashboard")
            ->has("breadcrumbs", 2)
            ->has("title")
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'Trade Units Dashboard')->etc()
            );
    });
});

test("UI Create Stock in Group", function () {
    $response = get(
        route("grp.goods.stocks.create")
    );
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component("CreateModel")
            ->where("title", "New stock")
            ->has("breadcrumbs", 4)
            ->has('icon')
            ->has('formData', fn (AssertableInertia $page) => $page->where("route", [
                'name'       => 'grp.models.stock.store',
                'parameters' => []
            ])->etc())
            ->has(
                "pageHead",
                fn (AssertableInertia $page) => $page->where("title", 'New SKO')->etc()
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
            ->where('title', 'Ingredient')
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
            ->has('formData.blueprint.1.fields.composition.route')
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
            ->where("title", "New stock")
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
                fn (AssertableInertia $page) => $page->where("title", 'New SKO')->etc()
            );
    });
});


test('Hydrate stocks', function () {
    HydrateStocks::run(Stock::first());
    $this->artisan('hydrate:stocks')->assertSuccessful();
});

test('Hydrate stock families', function () {
    HydrateStockFamily::run(StockFamily::first());
    $this->artisan('hydrate:stock_families')->assertSuccessful();
});

test('Hydrate trade units', function () {
    HydrateTradeUnits::run(TradeUnit::first());
    $this->artisan('hydrate:trade_units')->assertSuccessful();
});


test('goods hydrator', function () {
    $this->artisan('hydrate -s goods')->assertExitCode(0);
});


test('store trade unit family action creates model and stats', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code' => 'TUF-'.uniqid(),
        'name' => 'Test Trade Unit Family',
    ]);

    expect($family)->toBeInstanceOf(TradeUnitFamilyModel::class)
        ->and($family->stats()->exists())->toBeTrue();
});

test('store trade unit family validation rejects reserved codes', function () {
    $group = createGroup();

    StoreTradeUnitFamily::make()->action($group, [
        'code' => 'ok-'.uniqid(),
        'name' => 'OK',
    ]);

    StoreTradeUnitFamily::make()->action($group, [
        'code' => 'upload-'.uniqid(),
        'name' => 'OK2',
    ]);

    StoreTradeUnitFamily::make()->action($group, [
        'code' => 'export-'.uniqid(),
        'name' => 'OK3',
    ]);

    StoreTradeUnitFamily::make()->action($group, [
        'code' => 'CREATE-'.uniqid(),
        'name' => 'OK4',
    ]);

    StoreTradeUnitFamily::make()->action($group, [
        'code' => 'create',
        'name' => 'Should fail',
    ]);
})->throws(ValidationException::class);

test('store trade unit family enforces unique code per group', function () {
    $groupA = createGroup();
    $groupB = createGroup();

    $code = 'TUF-'.uniqid();

    StoreTradeUnitFamily::make()->action($groupA, [
        'code' => $code,
        'name' => 'Fam A1',
    ]);

    StoreTradeUnitFamily::make()->action($groupB, [
        'code' => $code,
        'name' => 'Fam B1',
    ]);

    StoreTradeUnitFamily::make()->action($groupA, [
        'code' => $code,
        'name' => 'Fam A2 should fail',
    ]);
})->throws(ValidationException::class);

test('UI Show Trade Unit Family page loads', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code' => 'TUF-'.uniqid(),
        'name' => 'Shown Family',
    ]);

    $response = get(route('grp.trade_units.families.show', [$family->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($family) {
        $page
            ->component('Goods/TradeUnitFamily')
            ->where('title', __('Trade Unit Family').' '.$family->code)
            ->has('breadcrumbs')
            ->has('pageHead', function (AssertableInertia $head) use ($family) {
                $head->where('title', $family->code)
                    ->where('model', __('Trade Unit Family'))
                    ->has('actions', 1)
                    ->where('actions.0.style', 'edit')
                    ->etc();
            })
            ->has('tabs.current');
    });
});

test('UI Show Trade Unit Family sales analysis tab', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code' => 'TUF-'.uniqid(),
        'name' => 'Sales Analysis Family',
    ]);

    $response = get(route('grp.trade_units.families.show', [
        'tradeUnitFamily' => $family->slug,
        'tab'             => \App\Enums\UI\SupplyChain\TradeUnitFamilyTabsEnum::SALES_ANALYSIS->value,
        'from'            => '2026-01-01',
        'to'              => '2026-03-31',
        'compareFrom'     => '2025-01-01',
        'compareTo'       => '2025-03-31',
    ]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/TradeUnitFamily')
            ->where('sales_analysis.period', ['from' => '2026-01-01', 'to' => '2026-03-31'])
            ->where('sales_analysis.frequency', 'daily')
            ->has('sales_analysis.breakdown')
            ->has('sales_analysis.stock_outs')
            ->has('sales_analysis.events')
            ->has('sales_analysis.filters.organisations');
    });

    $teaser = GetSalesAnalysis::make()->teaser(SalesAnalysisScope::forTradeUnitFamily($family));

    expect($teaser)->toHaveKeys(['period', 'compare_period', 'sales', 'compare_sales', 'totals', 'shops', 'breakdown']);
});

test('UI Edit Trade Unit Family page loads', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code' => 'TUF-'.uniqid(),
        'name' => 'Editable Family',
    ]);

    $response = get(route('grp.trade_units.families.edit', [$family->slug]));

    $response->assertInertia(function (AssertableInertia $page) use ($family) {
        $page
            ->component('EditModel')
            ->has('breadcrumbs')
            ->has('pageHead', function (AssertableInertia $head) {
                $head->where('title', __('Edit trade unit family'))
                    ->has('actions', 1)
                    ->where('actions.0.style', 'cancel')
                    ->etc();
            })
            ->has('formData', function (AssertableInertia $form) use ($family) {
                $form->has('blueprint')
                    ->where('args.updateRoute.name', 'grp.models.trade_unit_family.update')
                    ->where('args.updateRoute.parameters.tradeUnitFamily', $family->id)
                    ->etc();
            });
    });
});

test('UI Create Trade Unit Family page loads', function () {
    $response = get(route('grp.trade_units.families.create'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->where('title', __('New Trade Unit Family'))
            ->has('breadcrumbs')
            ->has('pageHead', function (AssertableInertia $head) {
                $head->where('title', __('New trade family'))
                    ->has('actions', 1)
                    ->where('actions.0.style', 'cancel')
                    ->etc();
            })
            ->has('formData', function (AssertableInertia $form) {
                $form->where('route.name', 'grp.models.trade_unit_family.store')
                    ->has('blueprint')
                    ->etc();
            });
    });
});

test('UI index trade unit families page loads', function () {
    $response = get(route('grp.trade_units.families.index'));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/TradeUnitsFamilies')
            ->where('title', __('Trade Unit Families'))
            ->has('breadcrumbs');

    });
});

test('index trade unit families tableStructure returns closure', function () {
    $closure = IndexTradeUnitFamilies::make()->tableStructure($this->group);
    expect($closure)->toBeInstanceOf(Closure::class);
});

test('update trade unit family updates name and description', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code'        => 'TUF-'.uniqid(),
        'name'        => 'Original Name',
        'description' => 'Original Description',
    ]);

    $updated = UpdateTradeUnitFamily::make()->handle($family, [
        'name'        => 'Updated Name',
        'description' => 'Updated Description',
    ]);

    expect($updated->fresh()->name)->toBe('Updated Name')
        ->and($updated->fresh()->description)->toBe('Updated Description');
});

test('update trade unit family allows null description', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code'        => 'TUF-'.uniqid(),
        'name'        => 'With Desc',
        'description' => 'Some text',
    ]);

    $updated = UpdateTradeUnitFamily::make()->handle($family, [
        'description' => null,
    ]);

    expect($updated->fresh()->description)->toBeNull();
});

test('hydrate trade unit family trade units stats', function () {

    $family = StoreTradeUnitFamily::make()->action($this->group, [
        'code' => 'TUF001',
        'name' => 'Test Family',
    ]);

    TradeUnit::factory()->create([
        'group_id'             => $this->group->id,
        'trade_unit_family_id' => $family->id,
        'status'               => TradeUnitStatusEnum::IN_PROCESS,
    ]);

    TradeUnit::factory()->create([
        'group_id'             => $this->group->id,
        'trade_unit_family_id' => $family->id,
        'status'               => TradeUnitStatusEnum::IN_PROCESS,
    ]);

    TradeUnit::factory()->create([
        'group_id'             => $this->group->id,
        'trade_unit_family_id' => $family->id,
        'status'               => TradeUnitStatusEnum::ACTIVE,
    ]);

    TradeUnit::factory()->create([
        'group_id'             => $this->group->id,
        'trade_unit_family_id' => $family->id,
        'status'               => TradeUnitStatusEnum::DISCONTINUED,
    ]);

    TradeUnit::factory()->create([
        'group_id'             => $this->group->id,
        'trade_unit_family_id' => $family->id,
        'status'               => TradeUnitStatusEnum::ANOMALITY,
    ]);

    TradeUnitFamilyHydrateTradeUnits::run($family);

    $family->refresh();
    $stats = $family->stats;

    expect($stats)->not->toBeNull()
        ->and($stats->number_trade_units)->toBe(5)
        ->and($stats->number_trade_units_status_in_process)->toBe(2)
        ->and($stats->number_trade_units_status_active)->toBe(1)
        ->and($stats->number_trade_units_status_discontinued)->toBe(1)
        ->and($stats->number_trade_units_status_discontinuing)->toBe(0)
        ->and($stats->number_trade_units_status_anomality)->toBe(1);
});

test('UI Index Stock Families', function () {
    $response = get(
        route('grp.goods.stock-families.index')
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/StockFamilies')
            ->has('breadcrumbs')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $p) => $p
                    ->has('title')
                    ->has('icon')
                    ->has('subNavigation')
                    ->has('actions')
                    ->where('title', 'Master SKO Families')
                    ->etc()
            );
    });
});

test('UI Create Stock Family', function () {
    $response = get(
        route('grp.goods.stock-families.create')
    );

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('breadcrumbs')
            ->has('title')
            ->has(
                'pageHead',
                fn (AssertableInertia $p) => $p
                    ->where('title', 'New SKO family')
                    ->has('actions')
                    ->etc()
            )
            ->has(
                'formData',
                fn ($p) => $p
                    ->has('blueprint')
                    ->has('route')
                    ->where('route', [
                        'name' => 'grp.models.stock-family.store',
                    ])
                    ->etc()
            );
    });
});

test('stock and stock family indexes use time series aggregation', function () {
    request()->setRouteResolver(fn () => new \Illuminate\Routing\Route('GET', 'test', []));
    createStocks($this->group);

    $indexStocks = \App\Actions\Goods\Stock\UI\IndexStocks::make();
    $group       = $this->group;
    (function () use ($group) {
        $this->group = $group;
    })->call($indexStocks);

    expect($indexStocks->handle($this->group, bucket: 'all')->total())->toBeGreaterThanOrEqual(1)
        ->and(\App\Actions\Goods\StockFamily\UI\IndexStockFamilies::make()->handle($this->group, bucket: 'all')->total())->toBeGreaterThanOrEqual(0);
});

test('changing stock trade units recomputes packed_in on the stock and its org stocks', function () {
    $stocks   = createStocks($this->group);
    $stock    = $stocks[0];
    createOrgStocks($this->organisation, [$stock]);
    $tradeUnit = $stock->tradeUnits()->first();

    SyncStockTradeUnits::run($stock, [
        $tradeUnit->id => ['quantity' => 6]
    ]);

    $stock->refresh();
    $orgStock = $this->organisation->orgStocks()->where('stock_id', $stock->id)->first();

    expect($stock->packed_in)->toBe(6)
        ->and($orgStock->packed_in)->toBe(6)
        ->and((float) $orgStock->tradeUnits()->first()->pivot->quantity)->toBe(6.0);
});

test('warehouse packing can be edited per org stock from the master editor', function () {
    [, $product] = createProduct($this->shop);
    $stocks = createStocks($this->group);
    $stock  = $stocks[0];
    [$orgStock] = createOrgStocks($this->organisation, [$stock]);
    $tradeUnit = $stock->tradeUnits()->first();

    \Illuminate\Support\Facades\DB::table('product_has_org_stocks')->updateOrInsert(
        ['product_id' => $product->id, 'org_stock_id' => $orgStock->id],
        ['quantity' => 1]
    );
    expect($orgStock->products()->count())->toBeGreaterThanOrEqual(1);

    $response = \Pest\Laravel\patchJson(route('grp.models.org_stock.trade_units.update', [$orgStock->id]), [
        'trade_units' => [
            ['id' => $tradeUnit->id, 'quantity' => 12],
        ],
    ]);
    $response->assertOk();

    $orgStock->refresh();
    expect($orgStock->packed_in)->toBe(12)
        ->and((float) $orgStock->tradeUnits()->first()->pivot->quantity)->toBe(12.0);
});

test('UI Edit Stock Composition', function () {
    $stock    = Stock::first();
    $response = get(
        route('grp.goods.stocks.composition', [$stock->slug])
    );
    $response->assertInertia(function (AssertableInertia $page) use ($stock) {
        $page
            ->component('Goods/ProductComposition')
            ->has('breadcrumbs')
            ->has('formData.blueprint.0.fields.trade_units.productsContext')
            ->where('formData.args.updateRoute.name', 'grp.models.stock.update')
            ->has(
                'pageHead',
                fn (AssertableInertia $head) => $head->where('title', $stock->code)->etc()
            );
    });
});

test('UI Show Trade Unit composition tab', function () {
    $this->withoutExceptionHandling();
    createStocks($this->group);
    $tradeUnit = $this->group->tradeUnits()->first();

    $response = get(route('grp.goods.trade-units.show', [$tradeUnit->slug]).'?tab=composition');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/TradeUnit')
            ->where('tabs.current', 'composition')
            ->has('composition.stocks')
            ->has('composition.org_stocks')
            ->has('composition.master_products')
            ->has('composition.products');
    });
});

test('organisation tariff code override keeps the shared HS heading and changes only the national digits', function () {
    [, $product] = createProduct($this->shop);
    $tradeUnit   = $product->tradeUnits->first();
    $otherOrg    = \App\Actions\SysAdmin\Organisation\StoreOrganisation::make()->action(
        $this->group,
        array_merge(\App\Models\SysAdmin\Organisation::factory()->definition(), ['code' => 'ovr', 'type' => \App\Enums\SysAdmin\Organisation\OrganisationTypeEnum::SHOP])
    );

    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($tradeUnit, ['tariff_code' => '1234 56 7890']);
    $product->refresh();
    expect($product->tariff_code)->toBe('1234 56 7890');

    $override = \App\Actions\Goods\TradeUnit\SetTradeUnitTariffCodeOverride::make()->action($tradeUnit, $this->organisation, $this->user, [
        'national_extension' => '0011',
        'reason'             => 'UK tariff classifies this as a set',
    ]);
    $product->refresh();

    expect($override->approved_by_user_id)->toBe($this->user->id)
        ->and($override->approved_at)->not->toBeNull()
        ->and($tradeUnit->fresh()->getTariffCodeForOrganisation($this->organisation->id))->toBe('1234560011')
        ->and($tradeUnit->fresh()->getTariffCodeForOrganisation($otherOrg->id))->toBe('1234 56 7890')
        ->and($product->tariff_code)->toBe('1234560011');

    \App\Actions\Goods\TradeUnit\DeleteTradeUnitTariffCodeOverride::make()->action($tradeUnit, $this->organisation);
    $product->refresh();
    expect($product->tariff_code)->toBe('1234 56 7890')
        ->and($tradeUnit->fresh()->tariffCodeOverrides()->count())->toBe(0);
});

test('organisation tariff code override needs a shared 6-digit heading and 2-4 national digits', function () {
    [, $product] = createProduct($this->shop);
    $tradeUnit   = $product->tradeUnits->first();
    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($tradeUnit, ['tariff_code' => null]);

    expect(fn () => \App\Actions\Goods\TradeUnit\SetTradeUnitTariffCodeOverride::make()->action($tradeUnit, $this->organisation, $this->user, [
        'national_extension' => '0011',
        'reason'             => 'x',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($tradeUnit, ['tariff_code' => '1234567890']);
    expect(fn () => \App\Actions\Goods\TradeUnit\SetTradeUnitTariffCodeOverride::make()->action($tradeUnit, $this->organisation, $this->user, [
        'national_extension' => 'ab',
        'reason'             => 'x',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('UI Show Trade Unit lists the tariff code per organisation', function () {
    $this->withoutExceptionHandling();
    [, $product] = createProduct($this->shop);
    $tradeUnit   = $product->tradeUnits->first();

    $response = get(route('grp.goods.trade-units.show', [$tradeUnit->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Goods/TradeUnit')
            ->has('showcase.properties.tariff_code_by_organisation.0', fn (AssertableInertia $row) => $row
                ->where('organisation_code', $this->organisation->code)
                ->has('update_route')
                ->etc());
    });
});

test('repair fills and overwrites trade unit tariff codes from the latest Aurora history audit', function () {
    [, $product] = createProduct($this->shop);
    $tradeUnit   = $product->tradeUnits->first();
    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($tradeUnit, ['tariff_code' => null]);
    $tradeUnit->update(['source_id' => $this->organisation->id.':1', 'status' => \App\Enums\Goods\TradeUnit\TradeUnitStatusEnum::ACTIVE]);

    $auditRow = fn (string $code, string $at) => [
        'tags'           => '["goods"]',
        'auditable_type' => 'TradeUnit',
        'auditable_id'   => $tradeUnit->id,
        'event'          => 'updated',
        'old_values'     => '{}',
        'new_values'     => json_encode(['tariff_code' => $code]),
        'created_at'     => $at,
        'updated_at'     => $at,
    ];
    \Illuminate\Support\Facades\DB::table('audits')->insert([$auditRow('1111110000', '2026-01-01'), $auditRow('2520100000', '2026-08-01')]);

    $repair = \App\Actions\Goods\TradeUnit\RepairTradeUnitTariffCodesFromAurora::make();

    $dryRun = $repair->handle();
    expect($dryRun)->toHaveCount(1)
        ->and($dryRun[0]['aurora'])->toBe('2520100000')
        ->and($dryRun[0]['action'])->toBe('fill')
        ->and($tradeUnit->fresh()->tariff_code)->toBeNull();

    $repair->handle(fix: true);
    expect($tradeUnit->fresh()->tariff_code)->toBe('2520100000');

    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($tradeUnit, ['tariff_code' => '2842908080']);
    expect($repair->handle())->toBeEmpty()
        ->and($repair->handle(overwrite: true)[0]['action'])->toBe('overwrite');

    $repair->handle(fix: true, overwrite: true);
    expect($tradeUnit->fresh()->tariff_code)->toBe('2520100000')
        ->and($product->fresh()->tariff_code)->toBe('2520100000');
});

test('tariff codes index lists rows and the export name is editable', function () {
    $tariffCode = \App\Models\Helpers\TariffCode::firstOrCreate(['hs_code' => '330741'], ['section' => 'VI', 'description' => 'Agarbatti and other odoriferous preparations which operate by burning', 'level' => 6]);

    get(route('grp.goods.tariff_codes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Goods/TariffCodes')->has('data.data'));

    \Pest\Laravel\patch(route('grp.models.tariff_code.update', $tariffCode->id), ['name' => 'Incense'])->assertStatus(302);

    \App\Models\Helpers\TariffCode::firstOrCreate(['hs_code' => '3307410010'], ['section' => 'VI', 'description' => 'Agarbatti', 'level' => 10, 'parent_id' => $tariffCode->id, 'name' => 'Incense Sticks']);

    $fetch = \App\Actions\Transfers\Aurora\FetchAuroraTariffCodeNames::make();

    expect($tariffCode->fresh()->name)->toBe('Incense')
        ->and(\App\Models\Helpers\TariffCode::exportNameFor('3307 41 0099'))->toBe('Incense Sticks')
        ->and(\App\Models\Helpers\TariffCode::exportNameFor('3307490000'))->toBeNull()
        ->and(\App\Models\Helpers\TariffCode::exportNameFor('330741'))->toBe('Incense')
        ->and(\App\Models\Helpers\TariffCode::exportNameFor('9999999999'))->toBeNull()
        ->and($fetch->normaliseCode('902300000'))->toBe('0902300000')
        ->and($fetch->normaliseCode('9021000'))->toBe('09021000')
        ->and($fetch->normaliseCode('3406000000'))->toBe('3406000000');
});

describe('Product Command & Control extras', function () {
    beforeEach(function () {
        Cache::forget(ShowGoodsDashboard::cacheKey($this->group->id));
    });

    test('dashboard page carries period, rates, editable_organisations and can_change_group', function () {
        $response = get(route('grp.goods.dashboard'));

        $response->assertOk()->assertInertia(function (AssertableInertia $page) {
            $page
                ->component('Goods/ProductCommandControl')
                ->where('period', '90d')
                ->has('periods')
                ->has('rates')
                ->has('editable_organisations')
                ->has('can_change_group');
        });
    });

    test('export streams a CSV with the expected header row', function () {
        $response = get(route('grp.goods.export'));

        $content   = $response->streamedContent();
        $firstLine = strtok($content, "\n");

        expect($response->headers->get('content-type'))->toContain('text/csv')
            ->and($firstLine)->toContain('Code')
            ->and($firstLine)->toContain('Group status');
    });

    test('product detail route returns the per organisation and status history structure', function () {
        [$stock] = createStocks($this->group);
        createOrgStocks($this->organisation, [$stock]);

        $response = get(route('grp.goods.products.show', ['stock' => $stock->slug]));

        $response->assertOk()->assertJsonStructure([
            'id', 'code', 'name', 'group_state', 'all_retired',
            'organisations' => [
                '*' => [
                    'organisation', 'name', 'on_hand', 'available', 'allocated', 'inbound',
                    'next_expected_at', 'inbound_lines', 'days_of_cover', 'state', 'differs_from_group', 'shops', 'monthly_sales',
                ],
            ],
            'status_history',
        ]);
    });

    test('ns, off, raw-material and retired conditions are classified correctly', function () {
        $family = StoreStockFamily::make()->action($this->group, array_merge(StockFamily::factory()->definition(), ['code' => 'GDCCFAM']));

        $stockNs  = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE, 'code' => 'GDCC-NS']));
        $stockOff = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE, 'code' => 'GDCC-OFF']));
        $stockRaw = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE, 'code' => 'GDCC-RAW']));
        $stockRet = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE, 'code' => 'GDCC-RET']));

        DB::table('stocks')->whereIn('id', [$stockNs->id, $stockOff->id, $stockRaw->id, $stockRet->id])->update(['stock_family_id' => $family->id]);

        [$orgStockNs, $orgStockOff, $orgStockRaw, $orgStockRet] = createOrgStocks($this->organisation, [$stockNs, $stockOff, $stockRaw, $stockRet]);

        $orgStockNs->update(['quantity_available' => 0, 'quantity_in_locations' => 0, 'is_on_demand' => false]);
        $orgStockOff->update(['quantity_available' => 10, 'quantity_in_locations' => 10, 'is_on_demand' => false]);
        $orgStockRaw->update(['quantity_available' => 10, 'quantity_in_locations' => 10, 'is_on_demand' => false]);
        $orgStockRet->update(['state' => OrgStockStateEnum::DISCONTINUED]);

        $productionId = DB::table('productions')->insertGetId([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'slug'            => 'gdcc-test-production',
            'code'            => 'GDCCPROD',
            'name'            => 'GDCC test production',
            'settings'        => '{}',
            'data'            => '{}',
            'sources'         => '{}',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('raw_materials')->insert([
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'slug'            => 'gdcc-test-raw-material',
            'type'            => 'ingredient',
            'production_id'   => $productionId,
            'org_stock_id'    => $orgStockRaw->id,
            'code'            => 'GDCCRAW',
            'description'     => 'GDCC test raw material',
            'unit_cost'       => 1,
            'data'            => '{}',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        Cache::forget(ShowGoodsDashboard::cacheKey($this->group->id));

        $familyResult = ShowGoodsDashboard::make()->forGroup($this->group)->handle(['family' => 'GDCCFAM']);
        $familyRows   = collect($familyResult['rows'])->keyBy('code');

        expect($familyRows->get('GDCC-NS')['organisations'][$this->organisation->code]['condition'])->toBe('ns')
            ->and($familyRows->get('GDCC-OFF')['organisations'][$this->organisation->code]['condition'])->toBe('off')
            ->and($familyRows->get('GDCC-RAW')['organisations'][$this->organisation->code]['condition'])->not->toBe('off')
            ->and($familyRows->has('GDCC-RET'))->toBeFalse();

        $retiredResult = ShowGoodsDashboard::make()->forGroup($this->group)->handle(['family' => 'GDCCFAM', 'state' => OrgStockStateEnum::DISCONTINUED->value]);
        $retiredRows   = collect($retiredResult['rows'])->keyBy('code');

        expect($retiredRows->get('GDCC-RET')['organisations'][$this->organisation->code]['condition'])->toBe('ret')
            ->and($retiredRows->get('GDCC-RET')['all_retired'])->toBeTrue();

        $searchResult = ShowGoodsDashboard::make()->forGroup($this->group)->handle(['family' => 'GDCCFAM', 'search' => 'GDCC-RET']);

        expect(collect($searchResult['rows'])->pluck('code'))->toContain('GDCC-RET');
    });
});

describe('Product analysis view', function () {
    beforeEach(function () {
        Cache::forget(ShowGoodsDashboard::cacheKey($this->group->id));
        foreach (['month', 'quarter', 'year'] as $granularity) {
            Cache::forget(ShowGoodsAnalysis::cacheKey($this->group->id, $granularity));
        }
    });

    test('page renders with the expected props for each granularity', function (string $granularity) {
        $response = get(route('grp.goods.analysis', ['granularity' => $granularity]));

        $response->assertOk()->assertInertia(function (AssertableInertia $page) use ($granularity) {
            $page
                ->component('Goods/ProductAnalysis')
                ->has('breadcrumbs')
                ->where('granularity', $granularity)
                ->has('summary')
                ->has('series')
                ->has('families_table')
                ->has('organisations_table')
                ->has('products_table')
                ->has('stock_trend')
                ->has('exceptions')
                ->has('urgent_actions')
                ->has('promotion_candidates')
                ->has('status_history')
                ->has('inbound');
        });
    })->with(['month', 'quarter', 'year']);

    test('filters are echoed back', function () {
        $family = StoreStockFamily::make()->action($this->group, array_merge(StockFamily::factory()->definition(), ['code' => 'GAFAM']));

        $response = get(route('grp.goods.analysis', [
            'organisation' => $this->organisation->code,
            'family'       => $family->code,
            'search'       => 'gizmo',
            'granularity'  => 'quarter',
        ]));

        $response->assertOk()->assertInertia(function (AssertableInertia $page) use ($family) {
            $page
                ->where('filters.organisation', $this->organisation->code)
                ->where('filters.family', $family->code)
                ->where('filters.search', 'gizmo')
                ->where('filters.granularity', 'quarter');
        });
    });

    test('comparisons compute current vs previous vs last year correctly on fixture time series records', function () {
        [$stock] = createStocks($this->group);
        [$orgStock] = createOrgStocks($this->organisation, [$stock]);

        $timeSeriesId = DB::table('org_stock_time_series')->insertGetId([
            'org_stock_id' => $orgStock->id,
            'frequency'    => 'monthly',
            'from'         => now()->subYears(2)->startOfYear(),
            'to'           => now(),
            'data'         => '{}',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $currentFrom  = now()->startOfMonth();
        $previousFrom = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $lastYearFrom = now()->copy()->subYearNoOverflow()->startOfMonth();

        foreach ([[$currentFrom, 100], [$previousFrom, 40], [$lastYearFrom, 25]] as [$from, $sales]) {
            DB::table('org_stock_time_series_records')->insert([
                'org_stock_time_series_id'    => $timeSeriesId,
                'frequency'                   => 'M',
                'sales_grp_currency_external' => $sales,
                'from'                        => $from,
                'to'                          => $from->copy()->endOfMonth(),
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ]);
        }

        $result = ShowGoodsAnalysis::make()->forGroup($this->group)->handle(['granularity' => 'month', 'search' => $stock->code]);

        expect($result['summary']['current'])->toBe(100.0)
            ->and($result['summary']['previous'])->toBe(40.0)
            ->and($result['summary']['last_year'])->toBe(25.0)
            ->and($result['summary']['change_vs_previous'])->toBe(150.0)
            ->and($result['summary']['change_vs_last_year'])->toBe(300.0)
            ->and($result['product'])->not->toBeNull()
            ->and($result['product']['code'])->toBe($stock->code);
    });

    test('promotion candidates and urgent actions come from the command view dataset', function () {
        $family = StoreStockFamily::make()->action($this->group, array_merge(StockFamily::factory()->definition(), ['code' => 'GAPROMO']));

        $stockSell = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE, 'code' => 'GA-SELL']));
        $stockOff  = StoreStock::make()->action($this->group, array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE, 'code' => 'GA-OFF']));

        DB::table('stocks')->whereIn('id', [$stockSell->id, $stockOff->id])->update(['stock_family_id' => $family->id]);

        [$orgStockSell, $orgStockOff] = createOrgStocks($this->organisation, [$stockSell, $stockOff]);

        $orgStockSell->update(['state' => OrgStockStateEnum::DISCONTINUING, 'quantity_available' => 5, 'quantity_in_locations' => 5]);
        $orgStockOff->update(['quantity_available' => 10, 'quantity_in_locations' => 10, 'is_on_demand' => false]);

        Cache::forget(ShowGoodsDashboard::cacheKey($this->group->id));
        Cache::forget(ShowGoodsAnalysis::cacheKey($this->group->id, 'month'));

        $result = ShowGoodsAnalysis::make()->forGroup($this->group)->handle(['family' => $family->code]);

        expect(collect($result['promotion_candidates'])->pluck('code'))->toContain('GA-SELL')
            ->and(collect($result['urgent_actions']['offline_with_stock'])->pluck('code'))->toContain('GA-OFF')
            ->and(collect($result['exceptions']['offline'])->pluck('code'))->toContain('GA-OFF');
    });
});
