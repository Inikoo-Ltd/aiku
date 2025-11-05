<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 04 Nov 2025 09:43:56 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use App\Actions\Helpers\Tag\StoreTag;
use App\Actions\Helpers\Tag\UI\CreateTag;
use App\Actions\Helpers\Tag\UI\ShowTags;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowTags::class)->name('show');
Route::get('/create', CreateTag::class)->name('create');
Route::post('/store', StoreTag::class)->name('store');
