<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 11:11:38 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


use App\Actions\DevOps\Server\GetServerInfo;
use App\Actions\DevOps\Server\StoreServerMetric;
use Illuminate\Support\Facades\Route;

Route::get('/server/{server}', GetServerInfo::class)->name('devops.host.info');
Route::post('/metrics/{serverSlug}', StoreServerMetric::class)->name('devops.host.metrics.store');
