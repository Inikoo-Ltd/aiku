<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 16:26:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\UI\Dashboards\ShowGroupDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowGroupDashboard::class)->name('show');
