<?php

use App\Actions\CRM\TrafficSourceCampaign\UI\IndexGoogleAdsCampaigns;
use App\Actions\CRM\TrafficSourceCampaign\UI\ShowGoogleAdsCampaign;
use Illuminate\Support\Facades\Route;

Route::get('/', IndexGoogleAdsCampaigns::class)->name('index');
Route::get('/{trafficSourceCampaign:slug}', ShowGoogleAdsCampaign::class)->name('show')->withoutScopedBindings();
