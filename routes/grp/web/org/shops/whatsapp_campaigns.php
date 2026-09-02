<?php

use App\Actions\Comms\WhatsappCampaign\CancelWhatsappCampaignSchedule;
use App\Actions\Comms\WhatsappCampaign\DeleteWhatsappCampaign;
use App\Actions\Comms\WhatsappCampaign\ScheduleWhatsappCampaign;
use App\Actions\Comms\WhatsappCampaign\SendWhatsappCampaign;
use App\Actions\Comms\WhatsappCampaign\StoreWhatsappCampaign;
use App\Actions\Comms\WhatsappCampaign\UpdateWhatsappCampaign;
use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappCampaignRecipients;
use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappCampaignSentRecipients;
use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappCampaigns;
use App\Actions\UI\Dropshipping\Marketing\IndexWhatsappSubscribers;
use App\Actions\UI\Dropshipping\Marketing\ShowWhatsappCampaign;
use App\Actions\UI\Dropshipping\Marketing\ShowWhatsappCampaignWorkshop;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexWhatsappCampaigns::class)->name('index');
Route::post('/', StoreWhatsappCampaign::class)->name('store');
Route::get('/subscribers', IndexWhatsappSubscribers::class)->name('subscribers.index');
Route::get('/{whatsappCampaign}/workshop', ShowWhatsappCampaignWorkshop::class)->name('workshop');
Route::get('/{whatsappCampaign}/recipients', IndexWhatsappCampaignRecipients::class)->name('recipients.index');
Route::get('/{whatsappCampaign}/sent-recipients', IndexWhatsappCampaignSentRecipients::class)->name('sent-recipients.index');
Route::post('/{whatsappCampaign}/send', SendWhatsappCampaign::class)->name('send');
Route::post('/{whatsappCampaign}/schedule', ScheduleWhatsappCampaign::class)->name('schedule');
Route::post('/{whatsappCampaign}/cancel-schedule', CancelWhatsappCampaignSchedule::class)->name('cancel-schedule');
Route::get('/{whatsappCampaign}', ShowWhatsappCampaign::class)->name('show');
Route::patch('/{whatsappCampaign}', UpdateWhatsappCampaign::class)->name('update');
Route::delete('/{whatsappCampaign}', DeleteWhatsappCampaign::class)->name('delete');
