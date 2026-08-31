<?php

use App\Actions\Comms\WhatsappCampaign\StoreWhatsappCampaign;
use App\Actions\Comms\WhatsappCampaign\UpdateWhatsappCampaign;
use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappCampaigns;
use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappSubscribers;
use App\Actions\UI\Dropshipping\Marketing\ShowWhatsappCampaign;
use App\Actions\UI\Dropshipping\Marketing\ShowWhatsappCampaignWorkshop;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexWhatsappCampaigns::class)->name('index');
Route::post('/', StoreWhatsappCampaign::class)->name('store');
Route::get('/subscribers', IndexWhatsappSubscribers::class)->name('subscribers.index');
Route::get('/{whatsappCampaign}/workshop', ShowWhatsappCampaignWorkshop::class)->name('workshop');
Route::get('/{whatsappCampaign}', ShowWhatsappCampaign::class)->name('show');
Route::patch('/{whatsappCampaign}', UpdateWhatsappCampaign::class)->name('update');
