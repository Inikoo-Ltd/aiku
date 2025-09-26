<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 11:51:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\UI\AikuPublic\ShowHome;
use App\Actions\UI\AikuPublic\ShowUnsubscribe;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowHome::class)->name('home');
