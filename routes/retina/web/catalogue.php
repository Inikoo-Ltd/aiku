<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaDepartments;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowRetinaCatalogue::class)->name('dashboard');

Route::prefix('departments')->as('departments.')->group(function () {
    Route::get('/', IndexRetinaDepartments::class)->name('index');
});
