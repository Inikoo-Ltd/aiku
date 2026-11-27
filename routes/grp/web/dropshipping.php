<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Dropshipping\UI\ShowDropshippingCommandCentre;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowDropshippingCommandCentre::class)->name('dashboard');
