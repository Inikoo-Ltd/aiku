<?php

use App\Actions\Helpers\Tag\UI\IndexTags;
use Illuminate\Support\Facades\Route;

Route::get('/', [IndexTags::class, 'inSystemTags'])->name('index');
