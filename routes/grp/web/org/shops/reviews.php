<?php

use App\Actions\Reviews\UI\ShowReview;
use Illuminate\Support\Facades\Route;

Route::get('', ShowReview::class)->name('dashboard');
