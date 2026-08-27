<?php

use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappCampaigns;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexWhatsappCampaigns::class)->name('index');
