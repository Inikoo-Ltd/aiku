<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 22:13:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Fulfilment\Fulfilment\UI\CreateFulfilment;
use App\Actions\Fulfilment\Fulfilment\UI\IndexFulfilments;
use Illuminate\Support\Facades\Route;

Route::get('', IndexFulfilments::class)->name('index');
Route::get('create', CreateFulfilment::class)->name('create');

Route::prefix('{fulfilment}')->name('show.')
    ->group(function () {

        Route::name('operations.')
            ->group(__DIR__.'/operations.php');

        Route::name('catalogue.')
            ->group(__DIR__.'/fulfillment_catalogue.php');

        Route::name('crm.')
            ->group(__DIR__.'/crm.php');

        Route::prefix('web')
            ->name('web.')
            ->group(__DIR__.'/websites.php');

        Route::prefix('settings')
            ->name('settings')
            ->group(__DIR__.'/settings.php');

    });
