<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 16:26:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */


use App\Actions\Dashboard\ShowOrganisationDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowOrganisationDashboard::class)->name('show');
