<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Actions\Ordering\SalesChannel\UpdateSalesChannel;
use App\Actions\Ordering\SalesChannel\UI\EditSalesChannel;
use App\Actions\Ordering\SalesChannel\UI\IndexSalesChannels;
use App\Actions\Ordering\SalesChannel\UI\ShowSalesChannel;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexSalesChannels::class)->name('index');
Route::get('/{salesChannel}', ShowSalesChannel::class)->name('show');
Route::get('/{salesChannel}/edit', EditSalesChannel::class)->name('edit');
Route::patch('/{salesChannel:id}/update', UpdateSalesChannel::class)->name('update');
