<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

use App\Actions\Helpers\AI\AskBot;
use Illuminate\Support\Facades\Route;

Route::get('/', AskBot::class)->name('index');
