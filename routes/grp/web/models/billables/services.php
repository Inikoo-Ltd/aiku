<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 19:10:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Billables\Service\UpdateFulfilmentService;
use App\Actions\Billables\Service\UpdateShopService;
use Illuminate\Support\Facades\Route;

Route::patch('fulfilment/service/{service:id}', UpdateFulfilmentService::class)->name('fulfilment.services.update');
Route::patch('shop/service/{service:id}', UpdateShopService::class)->name('shop.services.update');
