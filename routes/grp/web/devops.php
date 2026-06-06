<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


use App\Actions\DevOps\UI\ShowDevopsDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowDevopsDashboard::class)->name('dashboard');
Route::get('/requests', IndexDevopsRequests::class)->name('requests.index');
