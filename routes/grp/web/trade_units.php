<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:04:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Goods\TradeUnit\UI\EditTradeUnit;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnits;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnit;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\Goods\TradeUnitFamily\UI\CreateTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\EditTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\IndexTradeUnitFamilies;
use App\Actions\Goods\TradeUnitFamily\UI\ShowTradeUnitFamily;
use App\Actions\Helpers\Tag\UI\CreateTag;
use App\Actions\Helpers\Tag\UI\EditTag;
use App\Actions\Helpers\Tag\UI\IndexTags;
use Illuminate\Support\Facades\Route;


Route::get('/dashboard', ShowTradeUnitsDashboard::class)->name('dashboard');
Route::prefix('units')->as('units.')->group(function () {
    Route::get('/all', IndexTradeUnits::class)->name('index');
    Route::get('/active', [IndexTradeUnits::class, 'active'])->name('active');
    Route::get('/in-process', [IndexTradeUnits::class, 'inProcess'])->name('in_process');
    Route::get('/discontinued', [IndexTradeUnits::class, 'discontinued'])->name('discontinued');
    Route::get('/anomality', [IndexTradeUnits::class, 'anomality'])->name('anomality');
    Route::prefix('{tradeUnit:slug}')->group(function () {
        Route::get('', ShowTradeUnit::class)->name('show');
        Route::get('edit', EditTradeUnit::class)->name('edit');

        Route::name('tags.')->prefix('tags')->group(function () {
            Route::get('/', [IndexTags::class, 'inTradeUnit'])->name('index');
            Route::get('create', [CreateTag::class, 'inTradeUnit'])->name('create');
            Route::get('/{tag}/edit', [EditTag::class, 'inTradeUnit'])->name('edit');
        });
    });
});

Route::prefix('families')->as('families.')->group(function () {
    Route::get('all', IndexTradeUnitFamilies::class)->name('index');
    Route::get('create', CreateTradeUnitFamily::class)->name('create');
    Route::prefix('{tradeUnitFamily:slug}')->group(function () {
        Route::get('', ShowTradeUnitFamily::class)->name('show');
        Route::get('edit', EditTradeUnitFamily::class)->name('edit');
    });
});