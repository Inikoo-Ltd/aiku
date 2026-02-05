<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Actions\SalesChannels\UI\IndexSalesChannels;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexSalesChannels::class)->name('index');
