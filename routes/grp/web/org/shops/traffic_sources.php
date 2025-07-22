<?php

use App\Actions\CRM\TrafficSource\UI\IndexTrafficSources;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexTrafficSources::class)->name('index');
