<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 04:20:03 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Goods\Ingredient\UI\IndexIngredients;
use App\Actions\Goods\Ingredient\UI\ShowIngredient;
use App\Actions\Goods\MasterAsset\UI\IndexMasterAssets;
use App\Actions\Goods\MasterProductCategory\UI\IndexMasterDepartments;
use App\Actions\Goods\MasterProductCategory\UI\IndexMasterFamilies;
use App\Actions\Goods\MasterProductCategory\UI\IndexMasterSubDepartments;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterSubDepartment;
use App\Actions\Goods\MasterShop\UI\IndexMasterShops;
use App\Actions\Goods\MasterShop\UI\ShowMasterShop;
use App\Actions\Goods\Stock\ExportStocks;
use App\Actions\Goods\Stock\UI\CreateStock;
use App\Actions\Goods\Stock\UI\EditStock;
use App\Actions\Goods\Stock\UI\IndexStocks;
use App\Actions\Goods\Stock\UI\ShowStock;
use App\Actions\Goods\StockFamily\ExportStockFamilies;
use App\Actions\Goods\StockFamily\UI\CreateStockFamily;
use App\Actions\Goods\StockFamily\UI\EditStockFamily;
use App\Actions\Goods\StockFamily\UI\IndexStockFamilies;
use App\Actions\Goods\StockFamily\UI\ShowStockFamily;
use App\Actions\Goods\TradeUnit\UI\EditTradeUnit;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnits;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnit;
use App\Actions\Goods\UI\ShowGoodsDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowGoodsDashboard::class)->name('dashboard');


Route::prefix('stocks')->as('stocks.')->group(function () {
    Route::get('/', IndexStocks::class)->name('index');
    Route::get('/export', ExportStocks::class)->name('export');
    Route::get('/create', CreateStock::class)->name('create');


    Route::prefix('active')->as('active_stocks.')->group(function () {
        Route::get('/', [IndexStocks::class, 'active'])->name('index');
        Route::prefix('{stock}')->group(function () {
            Route::get('', ShowStock::class)->name('show');
            Route::get('edit', EditStock::class)->name('edit');
        });
    });


    Route::prefix('in-process')->as('in_process_stocks.')->group(function () {
        Route::get('/', [IndexStocks::class, 'inProcess'])->name('index');
        Route::prefix('{stock}')->group(function () {
            Route::get('', ShowStock::class)->name('show');
            Route::get('edit', EditStock::class)->name('edit');
        });
    });

    Route::prefix('discontinuing')->as('discontinuing_stocks.')->group(function () {
        Route::get('/', [IndexStocks::class, 'discontinuing'])->name('index');
        Route::prefix('{stock}')->group(function () {
            Route::get('', ShowStock::class)->name('show');
            Route::get('edit', EditStock::class)->name('edit');
        });
    });

    Route::prefix('discontinued')->as('discontinued_stocks.')->group(function () {
        Route::get('/', [IndexStocks::class, 'discontinued'])->name('index');
        Route::prefix('{stock}')->group(function () {
            Route::get('', ShowStock::class)->name('show');
            Route::get('edit', EditStock::class)->name('edit');
        });
    });


    Route::prefix('{stock}')->group(function () {
        Route::get('', ShowStock::class)->name('show');
        Route::get('edit', EditStock::class)->name('edit');
    });
});


Route::prefix('families')->as('stock-families.')->group(function () {
    Route::get('', IndexStockFamilies::class)->name('index');
    Route::get('/active', [IndexStockFamilies::class, 'active'])->name('active.index');
    Route::get('/in-process', [IndexStockFamilies::class, 'inProcess'])->name('in-process.index');
    Route::get('/discontinuing', [IndexStockFamilies::class, 'discontinuing'])->name('discontinuing.index');
    Route::get('/discontinued', [IndexStockFamilies::class, 'discontinued'])->name('discontinued.index');
    Route::get('/export', ExportStockFamilies::class)->name('export');
    Route::get('/create', CreateStockFamily::class)->name('create');

    Route::prefix('{stockFamily}')->group(function () {
        Route::get('', ShowStockFamily::class)->name('show');
        Route::get('/edit', EditStockFamily::class)->name('edit');


        Route::prefix('stocks')->as('show.stocks.')->group(function () {
            Route::get('/', [IndexStocks::class, 'inStockFamily'])->name('index');
            Route::get('/export', [ExportStocks::class, 'inStockFamily'])->name('export');
            Route::get('/create', [CreateStock::class, 'inStockFamily'])->name('create');

            Route::prefix('{stock}')->group(function () {
                Route::get('', [ShowStock::class, 'inStockFamily'])->name('show');
                Route::get('edit', [EditStock::class, 'inStockFamily'])->name('edit');
            });
        });
    });
});

Route::prefix('trade-units')->as('trade-units.')->group(function () {
    Route::get('/', IndexTradeUnits::class)->name('index');
    Route::prefix('{tradeUnit:slug}')->group(function () {
        Route::get('', ShowTradeUnit::class)->name('show');
        Route::get('edit', EditTradeUnit::class)->name('edit');
    });
});

Route::prefix('catalogue')->as('catalogue.')->group(function () {
    Route::get('/shops', IndexMasterShops::class)->name('shops.index');
    Route::get('/products', IndexMasterAssets::class)->name('products.index');
    Route::get('/families', [IndexMasterFamilies::class, 'inGroup'])->name('families.index');
    Route::get('/families/{masterFamily}', [ShowMasterFamily::class,'inGroup'])->name('families.show');

    Route::prefix('{masterShop}')->as('shops.show')->group(function () {
        Route::get('', ShowMasterShop::class)->name('');
        Route::prefix('departments')->as('.departments.')->group(function () {
            Route::get('index', IndexMasterDepartments::class)->name('index');
            Route::get('{masterDepartment}', ShowMasterDepartment::class)->name('show');
        });
        Route::prefix('families')->as('.families.')->group(function () {
            Route::get('index', IndexMasterFamilies::class)->name('index');
            Route::get('{masterFamily}', ShowMasterFamily::class)->name('show');
        });
        Route::prefix('sub-departments')->as('.sub-departments.')->group(function () {
            Route::get('index', IndexMasterSubDepartments::class)->name('index');
            Route::get('{masterSubDepartment}', ShowMasterSubDepartment::class)->name('show');
        });
        Route::prefix('products')->as('.products.')->group(function () {
            Route::get('index', [IndexMasterAssets::class, 'inMasterShop'])->name('index');
        });
    });
});

Route::prefix('ingredients')->as('ingredients.')->group(function () {
    Route::get('/', IndexIngredients::class)->name('index');
    Route::prefix('{ingredient:slug}')->group(function () {
        Route::get('', ShowIngredient::class)->name('show');
    });
});
