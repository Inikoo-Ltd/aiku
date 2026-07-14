<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 07 Mar 2023 11:12:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

use App\Actions\Search\Search;
use App\Actions\SysAdmin\User\Search\GetUsersFromSearch;
use Illuminate\Support\Facades\Route;

Route::get('/', Search::class)->name('index');
Route::get('/get-users', GetUsersFromSearch::class)->name('get_users');
