<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Actions\Catalogue\Shop\UI\ShowCatalogue;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShowCatalogue::class, 'inGroup'])->name('show');
