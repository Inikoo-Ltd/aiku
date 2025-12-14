<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Dec 2024 20:13:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\Ingredient\StoreIngredient;
use App\Actions\Goods\Ingredient\UpdateIngredient;
use App\Actions\Goods\Stock\HydrateStocks;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\StockFamily\DeleteStockFamily;
use App\Actions\Goods\StockFamily\HydrateStockFamily;
use App\Actions\Goods\StockFamily\StoreStockFamily;
use App\Actions\Goods\StockFamily\UpdateStockFamily;
use App\Actions\Goods\TradeUnit\HydrateTradeUnits;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Models\Goods\Ingredient;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use Inertia\Testing\AssertableInertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use App\Models\Goods\TradeUnitFamily as TradeUnitFamilyModel;
use App\Actions\Goods\TradeUnitFamily\StoreTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UpdateTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\IndexTradeUnitFamilies;
use App\Actions\Goods\TradeUnitFamily\UI\CreateTradeUnitFamily as CreateTradeUnitFamilyUI;
use App\Actions\Goods\TradeUnitFamily\UI\EditTradeUnitFamily as EditTradeUnitFamilyUI;
use App\Actions\Goods\TradeUnitFamily\UI\ShowTradeUnitFamily as ShowTradeUnitFamilyUI;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTradeUnitFamilies;

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
                $page->where("title", 'Goods strategy')
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
    $this->withoutExceptionHandling();
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
                fn (AssertableInertia $page) => $page->where("title", $stock->name)->etc()
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
    $this->withoutExceptionHandling();
    $tradeUnit = TradeUnit::first();
    $response  = get(
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
                fn (AssertableInertia $page) => $page->where("title", 'New SKU')->etc()
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
                fn (AssertableInertia $page) => $page->where("title", 'New SKU')->etc()
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
            ->where('title', __('Edit Trade Unit Family'))
            ->has('breadcrumbs')
            ->has('pageHead', function (AssertableInertia $head) {
                $head->where('title', __('Edit trade family'))
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
            ->has('breadcrumbs')
            ->has('data');
    });
});

test('index trade unit families tableStructure returns closure', function () {
    $closure = IndexTradeUnitFamilies::make()->tableStructure($this->group);
    expect($closure)->toBeInstanceOf(Closure::class);
});

test('update trade unit family updates name and description', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code' => 'TUF-'.uniqid(),
        'name' => 'Original Name',
        'description' => 'Original Description',
    ]);

    $updated = UpdateTradeUnitFamily::make()->handle($family, [
        'name' => 'Updated Name',
        'description' => 'Updated Description',
    ]);

    expect($updated->fresh()->name)->toBe('Updated Name')
        ->and($updated->fresh()->description)->toBe('Updated Description');
});

test('update trade unit family allows null description', function () {
    $group = createGroup();

    $family = StoreTradeUnitFamily::make()->action($group, [
        'code' => 'TUF-'.uniqid(),
        'name' => 'With Desc',
        'description' => 'Some text',
    ]);

    $updated = UpdateTradeUnitFamily::make()->handle($family, [
        'description' => null,
    ]);

    expect($updated->fresh()->description)->toBeNull();
});

