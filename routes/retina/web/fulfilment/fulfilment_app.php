<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 11:56:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\Fulfilment\Notification\IndexRetinaFulfilmentCustomerNotification;
use App\Actions\Retina\Fulfilment\UI\IndexRetinaPricing;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'storage/dashboard');

Route::prefix("storage")
    ->name("storage.")
    ->group(__DIR__."/storage.php");

Route::prefix("itemised-storage")
    ->name("itemised_storage.")
    ->group(__DIR__."/stored_items.php");

Route::prefix("billing")
    ->name("billing.")
    ->group(__DIR__."/billing.php");

Route::prefix("spaces")
    ->name("spaces.")
    ->group(__DIR__."/spaces.php");


// \Route::get('pricing', IndexRetinaPricing::class)->name('pricing');
Route::get('/notifications', IndexRetinaFulfilmentCustomerNotification::class)->name('notifications');

Route::prefix("pricing")
    ->name("pricing")
    ->group(__DIR__."/pricing.php");
