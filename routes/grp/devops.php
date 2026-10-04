<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 11:11:38 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


use App\Actions\DevOps\CiRun\StoreDeployProgress;
use App\Actions\DevOps\Server\GetServerInfo;
use App\Actions\DevOps\Server\StoreServerLiveMetric;
use App\Actions\DevOps\Server\StoreServerMetric;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Http\Middleware\Sample;

Route::get('/server/{server}', GetServerInfo::class)->name('devops.host.info');
Route::post('/metrics/{serverSlug}', StoreServerMetric::class)->name('devops.host.metrics.store');
Route::post('/metrics/{serverSlug}/live', StoreServerLiveMetric::class)->name('devops.host.metrics.live.store')->middleware(Sample::rate(0.05));
Route::post('/deploy-progress', StoreDeployProgress::class)->name('devops.deploy-progress.store');
