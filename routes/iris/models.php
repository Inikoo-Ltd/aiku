<?php

/*
 * author Arya Permana - Kirin
 * created on 05-06-2025-14h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

use App\Actions\Iris\Portfolio\DeleteIrisPortfolioFavourites;
use App\Actions\Iris\Portfolio\DeleteIrisPortfolioFromMultiChannels;
use App\Actions\Iris\Portfolio\StoreIrisPortfolioFavourites;
use App\Actions\Iris\Portfolio\StoreIrisPortfolioToAllChannels;
use App\Actions\Iris\Portfolio\StoreIrisPortfolioToMultiChannels;
use Illuminate\Support\Facades\Route;

Route::post('portfolio-all-channels', StoreIrisPortfolioToAllChannels::class)->name('all_channels.portfolio.store');
Route::post('portfolio-multi-channels', StoreIrisPortfolioToMultiChannels::class)->name('multi_channels.portfolio.store');

Route::post('delete-portfolio-multi-channels', DeleteIrisPortfolioFromMultiChannels::class)->name('multi_channels.portfolio.store');

Route::post('favourites/{product:id}', StoreIrisPortfolioFavourites::class)->name('favourites.store');
Route::delete('un-favourites/{product:id}', DeleteIrisPortfolioFavourites::class)->name('favourites.delete');
