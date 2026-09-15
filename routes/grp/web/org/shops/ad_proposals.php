<?php

use App\Actions\CRM\TrafficSource\AdProposals\UI\IndexAdProposals;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexAdProposals::class)->name('index');
