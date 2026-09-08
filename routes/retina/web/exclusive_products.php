<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Retina\Ecom\ExclusiveProducts\UI\IndexRetinaExclusiveProducts;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', IndexRetinaExclusiveProducts::class)->name('dashboard');
