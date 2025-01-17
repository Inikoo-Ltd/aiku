<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\UI\Retina\Dashboard\ShowRetinaDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowRetinaDashboard::class)->name('show');
