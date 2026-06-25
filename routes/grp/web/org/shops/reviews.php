<?php

use App\Actions\Reviews\UI\IndexOverallReviews;
use App\Actions\Reviews\UI\ShowFamilyReviews;
use App\Actions\Reviews\UI\ShowProductReviews;
use App\Actions\Reviews\UI\ShowReview;
use Illuminate\Support\Facades\Route;

Route::get('', ShowReview::class)->name('dashboard');
Route::get('all', IndexOverallReviews::class)->name('all');
Route::get('families', ShowFamilyReviews::class)->name('families');
Route::get('products', ShowProductReviews::class)->name('products');
