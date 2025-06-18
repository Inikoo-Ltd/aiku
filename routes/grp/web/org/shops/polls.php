<?php

/*
 * author Arya Permana - Kirin
 * created on 23-12-2024-15h-33m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

use App\Actions\CRM\Poll\UI\EditPoll;
use App\Actions\CRM\Poll\UI\IndexPolls;
use App\Actions\CRM\Poll\UI\ShowPoll;

Route::get('/', IndexPolls::class)->name('index');
Route::get('/{poll:slug}', ShowPoll::class)->name('show');
Route::get('/{poll:slug}/edit', EditPoll::class)->name('edit');
