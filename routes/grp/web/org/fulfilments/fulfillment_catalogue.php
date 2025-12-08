<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Apr 2024 15:07:52 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\UI\CreatePhysicalGoods;
use App\Actions\Catalogue\Product\UI\ShowProduct;
use App\Actions\Fulfilment\UI\Catalogue\PhysicalGoods\EditFulfilmentPhysicalGoods;
use App\Actions\Fulfilment\UI\Catalogue\PhysicalGoods\IndexFulfilmentPhysicalGoods;
use App\Actions\Fulfilment\UI\Catalogue\PhysicalGoods\ShowFulfilmentPhysicalGood;
use App\Actions\Fulfilment\UI\Catalogue\Rentals\CreateRental;
use App\Actions\Fulfilment\UI\Catalogue\Rentals\EditRental;
use App\Actions\Fulfilment\UI\Catalogue\Rentals\IndexFulfilmentRentals;
use App\Actions\Fulfilment\UI\Catalogue\Rentals\ShowRental;
use App\Actions\Fulfilment\UI\Catalogue\Services\CreateFulfilmentService;
use App\Actions\Fulfilment\UI\Catalogue\Services\EditFulfilmentService;
use App\Actions\Fulfilment\UI\Catalogue\Services\IndexFulfilmentServices;
use App\Actions\Fulfilment\UI\Catalogue\Services\ShowFulfilmentService;
use App\Actions\Fulfilment\UI\Catalogue\ShowFulfilmentCatalogueDashboard;

Route::get('billables', ShowFulfilmentCatalogueDashboard::class)->name('index');
Route::get('billables/{product}', [ShowProduct::class, 'inFulfilment'])->name('show');

Route::get('rentals', IndexFulfilmentRentals::class)->name('rentals.index');
Route::get('rentals/create', CreateRental::class)->name('rentals.create');
Route::get('rentals/{rental}', ShowRental::class)->name('rentals.show');
Route::get('rentals/{rental}/edit', EditRental::class)->name('rentals.edit');

Route::get('services', IndexFulfilmentServices::class)->name('services.index');
Route::get('services/create', CreateFulfilmentService::class)->name('services.create');
Route::get('services/{service}', ShowFulfilmentService::class)->name('services.show');
Route::get('services/{service}/edit', EditFulfilmentService::class)->name('services.edit');

Route::get('physical-goods', IndexFulfilmentPhysicalGoods::class)->name('physical_goods.index');
Route::get('physical-goods/create', CreatePhysicalGoods::class)->name('physical_goods.create');
Route::get('physical-goods/{product}', ShowFulfilmentPhysicalGood::class)->name('physical_goods.show');
Route::get('physical-goods/{product}/edit', EditFulfilmentPhysicalGoods::class)->name('physical_goods.edit');
