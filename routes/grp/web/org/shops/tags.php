<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 04 Nov 2025 09:43:56 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use App\Actions\Helpers\Tag\DeleteTag;
use App\Actions\Helpers\Tag\StoreTag;
use App\Actions\Helpers\Tag\UI\CreateTag;
use App\Actions\Helpers\Tag\UI\EditTag;
use App\Actions\Helpers\Tag\UI\IndexTags;
use App\Actions\Helpers\Tag\UpdateTag;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexTags::class, 'inSelfFilledTag'])->name('index');
Route::get('/create', [CreateTag::class, 'inSelfFilledTag'])->name('create');
Route::get('/{tag}/edit', [EditTag::class, 'inSelfFilledTag'])->name('edit');
Route::post('/store', [StoreTag::class, 'inSelfFilledTag'])->name('store');
Route::patch('/update/{tag:id}', [UpdateTag::class, 'inSelfFilledTag'])->name('update')->withoutScopedBindings();
Route::delete('/delete/{tag:id}', [DeleteTag::class, 'inSelfFilledTag'])->name('delete')->withoutScopedBindings();
