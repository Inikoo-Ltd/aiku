<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

use App\Actions\Helpers\AI\AskBot;
use App\Actions\Helpers\AI\AskBotVision;
use Illuminate\Support\Facades\Route;

Route::post('/vision', AskBotVision::class)->name('vision.index');
Route::get('/', AskBot::class)->name('index');

