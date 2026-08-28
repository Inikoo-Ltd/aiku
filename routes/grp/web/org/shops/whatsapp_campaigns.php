<?php

use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappCampaigns;
use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappSubscribers;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexWhatsappCampaigns::class)->name('index');
Route::get('/subscribers', IndexWhatsappSubscribers::class)->name('subscribers.index');
