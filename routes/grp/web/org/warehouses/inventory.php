<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Aug 2024 15:32:58 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Fulfilment\Pallet\UI\EditPallet;
use App\Actions\Fulfilment\Pallet\UI\IndexDamagedPalletsInWarehouse;
use App\Actions\Fulfilment\Pallet\UI\IndexLostPalletsInWarehouse;
use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInWarehouse;
use App\Actions\Fulfilment\Pallet\UI\IndexReturnedPalletsInWarehouse;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Goods\Stock\UI\CreateStock;
use App\Actions\Goods\Stock\UI\EditStock;
use App\Actions\Goods\Stock\UI\ShowStock;
use App\Actions\Goods\StockFamily\ExportStockFamilies;
use App\Actions\Goods\StockFamily\UI\CreateStockFamily;
use App\Actions\Goods\StockFamily\UI\EditStockFamily;
use App\Actions\Goods\StockFamily\UI\RemoveStockFamily;
use App\Actions\Inventory\OrgStock\ExportOrgStocks;
use App\Actions\Inventory\OrgStock\UI\EditOrgStock;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Inventory\OrgStock\UI\ShowOrgStock;
use App\Actions\Inventory\OrgStockFamily\UI\IndexOrgStockFamilies;
use App\Actions\Inventory\OrgStockFamily\UI\ShowOrgStockFamily;
use App\Actions\Inventory\UI\ShowInventoryDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowInventoryDashboard::class)->name('dashboard');


Route::prefix('stocks')->as('org_stocks.')->group(function () {



    Route::prefix('all')->as('all_org_stocks.')->group(function () {
        Route::get('/', IndexOrgStocks::class)->name('index');
        Route::get('/export', ExportOrgStocks::class)->name('export');

        Route::prefix('{orgStock}')->group(function () {
            Route::get('', ShowOrgStock::class)->name('show');
            Route::get('edit', EditOrgStock::class)->name('edit');
        });
    });

    Route::prefix('current')->as('current_org_stocks.')->group(function () {
        Route::get('/', [IndexOrgStocks::class, 'current'])->name('index');
        Route::prefix('{orgStock}')->group(function () {
            Route::get('', [ShowOrgStock::class, 'current'])->name('show');
            Route::get('edit', [EditOrgStock::class, 'current'])->name('edit');
        });
    });

    Route::prefix('active')->as('active_org_stocks.')->group(function () {
        Route::get('/', [IndexOrgStocks::class, 'active'])->name('index');
        Route::prefix('{orgStock}')->group(function () {
            Route::get('', [ShowOrgStock::class, 'active'])->name('show');
            Route::get('edit', [EditOrgStock::class, 'active'])->name('edit');
        });
    });

    Route::prefix('in-process')->as('in_process_org_stocks.')->group(function () {
        Route::get('/', [IndexOrgStocks::class, 'inProcess'])->name('index');
        Route::prefix('{orgStock}')->group(function () {
            Route::get('', [ShowOrgStock::class, 'inProcess'])->name('show');
            Route::get('edit', [EditOrgStock::class, 'inProcess'])->name('edit');
        });
    });

    Route::prefix('discontinuing')->as('discontinuing_org_stocks.')->group(function () {
        Route::get('/', [IndexOrgStocks::class, 'discontinuing'])->name('index');
        Route::prefix('{orgStock}')->group(function () {
            Route::get('', [ShowOrgStock::class, 'discontinuing'])->name('show');
            Route::get('edit', [EditOrgStock::class, 'discontinuing'])->name('edit');
        });
    });

    Route::prefix('discontinued')->as('discontinued_org_stocks.')->group(function () {
        Route::get('/', [IndexOrgStocks::class, 'discontinued'])->name('index');
        Route::prefix('{orgStock}')->group(function () {
            Route::get('', [ShowOrgStock::class, 'discontinued'])->name('show');
            Route::get('edit', [EditOrgStock::class, 'discontinued'])->name('edit');
        });
    });



});

Route::prefix('families')->as('org_stock_families.')->group(function () {
    Route::get('', IndexOrgStockFamilies::class)->name('index');
    Route::get('/export', ExportStockFamilies::class)->name('export');
    Route::get('/create', CreateStockFamily::class)->name('create');

    Route::prefix('{orgStockFamily}')->group(function () {
        Route::get('', ShowOrgStockFamily::class)->name('show');
        Route::get('/edit', EditStockFamily::class)->name('edit');
        Route::get('/delete', RemoveStockFamily::class)->name('remove');

        Route::name('show.')->group(function () {
            Route::prefix('stocks')->as('org_stocks.')->group(function () {
                Route::get('/', [IndexOrgStocks::class, 'inStockFamily'])->name('index');
                Route::get('/export', [ExportOrgStocks::class, 'inStockFamily'])->name('export');
                Route::get('/create', [CreateStock::class, 'inStockFamily'])->name('create');

                Route::prefix('{orgStock}')->group(function () {
                    Route::get('', [ShowStock::class, 'inStockFamily'])->name('show');
                    Route::get('edit', [EditStock::class, 'inStockFamily'])->name('edit');
                });
            });
        });
    });
});


Route::prefix('pallets')->as('pallets.')->group(function () {

    Route::prefix('current')->as('current.')->group(function () {
        Route::get('', IndexPalletsInWarehouse::class)->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inWarehouse'])->name('show');
        Route::get('{pallet}/edit', [EditPallet::class, 'inWarehouse'])->name('edit');
    });

    Route::prefix('returned')->as('returned.')->group(function () {
        Route::get('', IndexReturnedPalletsInWarehouse::class)->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inWarehouse'])->name('show');
    });

    Route::prefix('damaged')->as('damaged.')->group(function () {
        Route::get('', IndexDamagedPalletsInWarehouse::class)->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inWarehouse'])->name('show');
    });

    Route::prefix('lost')->as('lost.')->group(function () {
        Route::get('', IndexLostPalletsInWarehouse::class)->name('index');
        Route::get('{pallet}', [ShowPallet::class, 'inWarehouse'])->name('show');
    });



});