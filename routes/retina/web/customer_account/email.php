<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jan 2025 03:58:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Retina\UI\SysAdmin\ShowRetinaEmailManagement;
use Illuminate\Support\Facades\Route;

Route::get('/settings', ShowRetinaEmailManagement::class)->name('settings.edit');
