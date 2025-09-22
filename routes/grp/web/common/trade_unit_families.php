<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 16 Aug 2025 10:34:18 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Goods\TradeUnitFamily\UI\CreateTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\EditTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\IndexTradeUnitFamilies;
use App\Actions\Goods\TradeUnitFamily\UI\ShowTradeUnitFamily;
use Illuminate\Support\Facades\Route;

/**
 * Common trade units routes used in multiple route files
 *
 * @param string $prefix Optional prefix for the routes (default: 'trade-units')
 * @param string $as Optional name prefix for the routes (default: 'trade-units.')
 * @return void
 */
function tradeUnitFamiliesRoutes(string $prefix = 'trade-unit-families', string $as = 'trade-unit-families.'): void
{
    Route::prefix($prefix)->as($as)->group(function () {
        Route::get('/', IndexTradeUnitFamilies::class)->name('index');
        Route::get('create', CreateTradeUnitFamily::class)->name('create');
        Route::prefix('{tradeUnitFamily:slug}')->group(function () {
            Route::get('', ShowTradeUnitFamily::class)->name('show');
            Route::get('', EditTradeUnitFamily::class)->name('edit');
        });
    });
}
