<?php

/*
 * author Louis Perez
 * created on 17-04-2026-15h-59m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

use App\Actions\Fulfilment\PalletReturn\UI\IndexPalletReturnsBacklog;
use Illuminate\Support\Facades\Route;

Route::get('return-backlog/wholesale', IndexPalletReturnsBacklog::class)->name('pallet-returns-backlog.wholesale');
