<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:04:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Goods\TradeUnit\UI\EditTradeUnit;
use App\Actions\Goods\TradeUnit\UI\IndexMissingDimensionsTradeUnits;
use App\Actions\Goods\TradeUnit\UI\IndexMissingWeightTradeUnits;
use App\Actions\Goods\TradeUnit\UI\IndexOrphanTradeUnits;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnits;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnit;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\Goods\TradeUnitFamily\UI\CreateTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\EditTradeUnitFamily;
use App\Actions\Goods\TradeUnitFamily\UI\IndexTradeUnitFamilies;
use App\Actions\Goods\TradeUnitFamily\UI\ShowTradeUnitFamily;
use App\Actions\Goods\TradeUnit\UI\IndexTradeUnitsInBrand;
use App\Actions\Helpers\Brand\UI\CreateBrand;
use App\Actions\Helpers\Brand\UI\EditBrand;
use App\Actions\Helpers\Brand\UI\IndexBrands;
use App\Actions\Helpers\Brand\UI\ShowBrand;
use App\Actions\Helpers\Tag\UpdateTag;
use App\Actions\Helpers\Tag\UI\EditTag;
use App\Actions\Helpers\Tag\UI\IndexTagsProductProperty;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowTradeUnitsDashboard::class)->name('dashboard');
Route::prefix('units')->as('units.')->group(function () {
    Route::get('/all', IndexTradeUnits::class)->name('index');
    Route::get('/active', [IndexTradeUnits::class, 'active'])->name('active');
    Route::get('/in-process', [IndexTradeUnits::class, 'inProcess'])->name('in_process');
    Route::get('/discontinuing', [IndexTradeUnits::class, 'discontinuing'])->name('discontinuing');
    Route::get('/discontinued', [IndexTradeUnits::class, 'discontinued'])->name('discontinued');
    Route::get('/anomality', [IndexTradeUnits::class, 'anomality'])->name('anomality');
    Route::get('/orphan', IndexOrphanTradeUnits::class)->name('orphan');
    Route::get('/missing-weight', IndexMissingWeightTradeUnits::class)->name('missing_weight');
    Route::get('/missing-dimensions', IndexMissingDimensionsTradeUnits::class)->name('missing_dimensions');
    Route::prefix('{tradeUnit:slug}')->group(function () {
        Route::get('', ShowTradeUnit::class)->name('show');
        Route::get('edit', EditTradeUnit::class)->name('edit');
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

Route::prefix('brands')->as('brands.')->group(function () {
    Route::get('/', IndexBrands::class)->name('index');
    Route::get('create', CreateBrand::class)->name('create');
    Route::prefix('{brand:slug}')->group(function () {
        Route::get('/', ShowBrand::class)->name('show');
        Route::get('edit', EditBrand::class)->name('edit');
        Route::get('trade-units', IndexTradeUnitsInBrand::class)->name('trade_units.index');
    });
});

Route::prefix('tags')->as('tags.')->group(function () {
    Route::get('/', IndexTagsProductProperty::class)->name('index');
    Route::patch('update/{tag:id}', [UpdateTag::class, 'inProductProperty'])->name('update')->withoutScopedBindings();
    Route::prefix('{tag:slug}')->group(function () {
        Route::get('edit', [EditTag::class, 'inProductProperty'])->name('edit');
    });
});
