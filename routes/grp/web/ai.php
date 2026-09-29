<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Helpers\AI\UI\ShowAiDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowAiDashboard::class)->name('dashboard');
