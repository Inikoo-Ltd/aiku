<?php

use App\Actions\Reviews\UI\IndexReviewsTabs;
use App\Actions\Reviews\UI\ShowReview;
use Illuminate\Support\Facades\Route;

Route::get('', ShowReview::class)->name('dashboard');
Route::get('index', IndexReviewsTabs::class)->name('index');
