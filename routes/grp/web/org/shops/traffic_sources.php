<?php

use App\Actions\CRM\TrafficSource\UI\IndexTrafficSources;
use App\Actions\CRM\TrafficSource\UI\ShowTrafficSource;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexTrafficSources::class)->name('index');
Route::get('/{trafficSource:slug}', ShowTrafficSource::class)->name('show');
