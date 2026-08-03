<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Actions\Dropshipping\UI\IndexPlatforms;
use App\Actions\Dropshipping\UI\ShowPlatform;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexPlatforms::class, 'inGroup'])->name('index');
Route::get('/{platform}', [ShowPlatform::class, 'inGroup'])->name('show');
