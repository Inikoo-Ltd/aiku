<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Actions\CRM\Platform\UI\IndexPlatforms;
use App\Actions\CRM\Platform\UI\ShowPlatform;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexPlatforms::class, 'inGroup'])->name('index');
Route::get('/{platform}', [ShowPlatform::class, 'inGroup'])->name('show');
