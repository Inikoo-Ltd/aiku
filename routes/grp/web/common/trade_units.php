<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 16 Aug 2025 10:34:18 Central European Summer Time, Torremolinos, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Goods\TradeUnit\UI\EditTradeUnit;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnits;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnit;
use Illuminate\Support\Facades\Route;

/**
 * Common trade units routes used in multiple route files
 *
 * @param  string  $prefix  Optional prefix for the routes (default: 'trade-units')
 * @param  string  $as  Optional name prefix for the routes (default: 'trade-units.')
 */
function tradeUnitsRoutes(string $prefix = 'trade-units', string $as = 'trade-units.'): void
{
    Route::prefix($prefix)->as($as)->group(function () {
        Route::get('/all', IndexTradeUnits::class)->name('index');
        Route::get('/active', [IndexTradeUnits::class, 'active'])->name('active');
        Route::get('/in-process', [IndexTradeUnits::class, 'inProcess'])->name('in_process');
        Route::get('/discontinued', [IndexTradeUnits::class, 'discontinued'])->name('discontinued');
        Route::get('/anomality', [IndexTradeUnits::class, 'anomality'])->name('anomality');
        Route::prefix('{tradeUnit:slug}')->group(function () {
            Route::get('', ShowTradeUnit::class)->name('show');
            Route::get('edit', EditTradeUnit::class)->name('edit');
        });
    });
}
