<?php

use App\Actions\Reviews\UI\IndexFamilyReviews;
use App\Actions\Reviews\UI\IndexOverallReviews;
use App\Actions\Reviews\UI\IndexProductReviews;
use App\Actions\Reviews\UI\ShowReview;
use App\Actions\Reviews\UI\ShowReviewsBacklog;
use Illuminate\Support\Facades\Route;

Route::get('', ShowReview::class)->name('dashboard');
Route::get('backlog', ShowReviewsBacklog::class)->name('backlog');
Route::get('overall', IndexOverallReviews::class)->name('overall');
Route::get('families', IndexFamilyReviews::class)->name('families');
Route::get('products', IndexProductReviews::class)->name('products');
